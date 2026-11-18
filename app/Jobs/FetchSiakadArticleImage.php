<?php

namespace App\Jobs;

use App\Enums\SiakadArticleStatus;
use App\Models\IntegrationLog;
use App\Models\MediaAsset;
use App\Models\SiakadArticle;
use App\Siakad\IntegrationLogger;
use App\Siakad\SiakadException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Image\Image;
use Throwable;

/**
 * Download the picture of a SIAKAD article (contract 1.2, image_url) into the
 * media library. SIAKAD's link stops working once the article is withdrawn or
 * the picture changes, so the CMS keeps its own copy.
 *
 * Only hosts in siakad.media_hosts (default: the SIAKAD_BASE_URL host) are
 * contacted, and the file must be a real JPEG, PNG or WEBP within the size
 * limit. It is re-encoded, which drops metadata and anything hidden in it.
 */
class FetchSiakadArticleImage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300];

    private const TYPES = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];

    public function __construct(public SiakadArticle $article, public string $url) {}

    public function handle(): void
    {
        $article = $this->current();

        if ($article === null) {
            return;
        }

        $started = microtime(true);

        try {
            $asset = $this->download($article);
        } catch (SiakadException $e) {
            IntegrationLogger::log(
                IntegrationLog::OUTBOUND, 'articles.image_fetch', IntegrationLog::FAILED, $article,
                externalId: $article->external_id, request: ['image_url' => $this->url],
                httpStatus: $e->httpStatus, error: $e->getMessage(), durationMs: $this->elapsed($started),
            );

            // Retrying cannot fix a refused host, a wrong file or a 4xx answer.
            if ($e->isPermanent()) {
                $this->fail($e);

                return;
            }

            throw $e;
        }

        $article->forceFill(['image_asset_id' => $asset->id, 'image_source_url' => $this->url, 'image_error' => null])->save();

        // A picture that arrives after the same version was approved belongs on the website too.
        if ($article->status === SiakadArticleStatus::Approved && ! $article->updated_after_approval && $article->publication) {
            $article->publication->forceFill(['image_id' => $asset->id, 'image_caption' => $article->image_caption])->save();
        }

        IntegrationLogger::log(
            IntegrationLog::OUTBOUND, 'articles.image_fetch', IntegrationLog::SUCCESS, $article,
            externalId: $article->external_id, request: ['image_url' => $this->url], response: ['media_asset_id' => $asset->id],
            httpStatus: 200, durationMs: $this->elapsed($started),
        );
    }

    /**
     * Every attempt failed: show the reason in the article screen.
     */
    public function failed(?Throwable $exception): void
    {
        $this->current()?->forceFill(['image_error' => Str::limit((string) $exception?->getMessage(), 490)])->save();
    }

    /**
     * The article, unless SIAKAD has sent another picture (or none) since.
     */
    protected function current(): ?SiakadArticle
    {
        $article = SiakadArticle::query()->withoutGlobalScopes()->with('publication')->find($this->article->id);

        return $article?->image_url === $this->url ? $article : null;
    }

    /**
     * @throws SiakadException not retryable for problems a retry cannot fix
     */
    protected function download(SiakadArticle $article): MediaAsset
    {
        $this->assertAllowedHost();
        $maxBytes = (int) config('siakad.image_max_kb') * 1024;

        try {
            $response = Http::timeout((int) config('siakad.timeout'))->get($this->url);
        } catch (ConnectionException $e) {
            throw new SiakadException('Could not download the picture: '.$e->getMessage());
        }

        if ($response->failed()) {
            throw new SiakadException("The picture link answered HTTP {$response->status()}.", $response->status());
        }

        $body = $response->body();

        if (strlen($body) > $maxBytes) {
            throw new SiakadException('The picture is larger than '.round($maxBytes / 1048576, 1).' MB.', retryable: false);
        }

        $info = @getimagesizefromstring($body);
        $extension = is_array($info) ? (self::TYPES[$info[2]] ?? null) : null;

        if ($extension === null) {
            throw new SiakadException('The picture link did not return a JPEG, PNG or WEBP image.', retryable: false);
        }

        $temporary = (string) tempnam(sys_get_temp_dir(), 'sia');
        $path = $temporary.'.'.$extension;
        rename($temporary, $path);
        file_put_contents($path, $body);

        try {
            Image::load($path)->orientation()->save($path);
        } catch (Throwable) {
            @unlink($path);

            throw new SiakadException('The picture could not be read.', retryable: false);
        }

        $label = Str::limit($article->image_caption ?: $article->title, 200);
        $asset = MediaAsset::query()->create([
            'site_id' => $article->site_id,
            'title' => $label,
            'alt' => $label,
            'caption' => $article->image_caption,
        ]);

        $asset->addMedia($path)
            ->usingName($label)
            ->usingFileName(Str::uuid().'.'.$extension)
            ->toMediaCollection('file');

        return $asset;
    }

    /**
     * @throws SiakadException when the link points anywhere but SIAKAD's media hosts
     */
    protected function assertAllowedHost(): void
    {
        $hosts = config('siakad.media_hosts') ?: array_filter([parse_url((string) config('siakad.base_url'), PHP_URL_HOST)]);
        $scheme = parse_url($this->url, PHP_URL_SCHEME);
        $host = parse_url($this->url, PHP_URL_HOST);

        if (! in_array($scheme, ['http', 'https'], true) || ! is_string($host) || ! in_array(strtolower($host), array_map('strtolower', $hosts), true)) {
            throw new SiakadException("Pictures are only downloaded from SIAKAD_MEDIA_HOSTS ({$host} is not listed).", retryable: false);
        }
    }

    protected function elapsed(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }
}
