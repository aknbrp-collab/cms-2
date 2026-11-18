<?php

use App\Enums\Role;
use App\Enums\SiteKey;
use App\Jobs\FetchSiakadArticleImage;
use App\Models\IntegrationLog;
use App\Models\MediaAsset;
use App\Models\Publication;
use App\Models\SiakadArticle;
use App\Models\Site;
use App\Siakad\ArticleInbox;
use App\Siakad\SiakadException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * SIAKAD contract 1.2: articles may carry a picture (image_url, image_caption).
 * Uses siakadCall() and articlePayload() from tests/Pest.php.
 */

beforeEach(function () {
    seedCms();
    $this->withoutVite();
    Storage::fake('public');
    config([
        'siakad.webhook_secret' => 'test-secret',
        'siakad.site' => 'mahad',
        'siakad.base_url' => 'https://siakad.test/api/cms',
        'siakad.media_hosts' => [],
    ]);
    $this->mahad = Site::findByKey(SiteKey::Mahad);
});

function jpegBytes(int $width = 800, int $height = 450): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 20, 90, 60));
    ob_start();
    imagejpeg($image);

    return (string) ob_get_clean();
}

function pictureArticle(array $overrides = []): array
{
    return articlePayload([
        'image_url' => 'https://siakad.test/media/art-1-v1.jpg',
        'image_caption' => 'Halaqah kitab di serambi masjid',
        'body_html' => '<figure><img src="https://siakad.test/media/art-1-v1.jpg"><figcaption>Halaqah</figcaption></figure><p>Isi artikel.</p>',
        ...$overrides,
    ]);
}

test('the picture is downloaded into the media library and not repeated in the body', function () {
    Http::fake(['siakad.test/media/*' => Http::response(jpegBytes(), 200, ['Content-Type' => 'image/jpeg'])]);

    siakadCall('POST', route('webhooks.siakad.articles'), pictureArticle())->assertCreated();

    $article = SiakadArticle::query()->sole();
    $asset = $article->image;

    expect($asset)->toBeInstanceOf(MediaAsset::class)
        ->and($asset->site_id)->toBe($this->mahad->id)
        ->and($asset->caption)->toBe('Halaqah kitab di serambi masjid')
        ->and($asset->isImage())->toBeTrue()
        ->and($article->image_source_url)->toBe('https://siakad.test/media/art-1-v1.jpg')
        ->and($article->image_error)->toBeNull()
        ->and($article->body)->not->toContain('<figure')->toContain('Isi artikel.')
        ->and(IntegrationLog::query()->where('action', 'articles.image_fetch')->where('status', 'success')->exists())->toBeTrue();
});

test('approval puts the picture on the website page', function () {
    Http::fake(['siakad.test/media/*' => Http::response(jpegBytes())]);
    siakadCall('POST', route('webhooks.siakad.articles'), pictureArticle())->assertCreated();
    $article = SiakadArticle::query()->sole();

    $publication = app(ArticleInbox::class)->approve($article, cmsUser(Role::ManagingEditor));

    expect($publication->image_id)->toBe($article->image_asset_id)
        ->and($publication->image_caption)->toBe('Halaqah kitab di serambi masjid');

    $url = $article->image->url('large');
    $this->get("http://mahad.test/publikasi/{$publication->slug}")
        ->assertOk()
        ->assertSee('Halaqah kitab di serambi masjid')
        ->assertSee('<meta property="og:image" content="'.$url.'">', false);
});

test('a picture that arrives after approval is added to the publication', function () {
    Queue::fake();
    siakadCall('POST', route('webhooks.siakad.articles'), pictureArticle())->assertCreated();
    $article = SiakadArticle::query()->sole();
    $publication = app(ArticleInbox::class)->approve($article, cmsUser(Role::ManagingEditor));
    expect($publication->image_id)->toBeNull();

    Http::fake(['siakad.test/media/*' => Http::response(jpegBytes())]);
    (new FetchSiakadArticleImage($article, 'https://siakad.test/media/art-1-v1.jpg'))->handle();

    expect($publication->fresh()->image_id)->toBe($article->fresh()->image_asset_id)->not->toBeNull();
});

test('a changed picture waits for re-approval and a removed one is cleared', function () {
    Http::fake([
        'siakad.test/media/art-1-v1.jpg' => Http::response(jpegBytes()),
        'siakad.test/media/art-1-v2.jpg' => Http::response(jpegBytes(600, 400)),
    ]);
    siakadCall('POST', route('webhooks.siakad.articles'), pictureArticle())->assertCreated();
    $publication = app(ArticleInbox::class)->approve(SiakadArticle::query()->sole(), cmsUser(Role::ManagingEditor));
    $firstPicture = $publication->image_id;

    siakadCall('POST', route('webhooks.siakad.articles'), pictureArticle(['image_url' => 'https://siakad.test/media/art-1-v2.jpg']))->assertOk();

    $article = SiakadArticle::query()->sole();
    expect($article->image_asset_id)->not->toBe($firstPicture)
        ->and($article->image_source_url)->toBe('https://siakad.test/media/art-1-v2.jpg')
        ->and($publication->fresh()->image_id)->toBe($firstPicture);

    app(ArticleInbox::class)->approve($article, cmsUser(Role::ManagingEditor));
    expect($publication->fresh()->image_id)->toBe($article->image_asset_id);

    siakadCall('POST', route('webhooks.siakad.articles'), articlePayload())->assertOk();
    expect(SiakadArticle::query()->sole())->image_url->toBeNull()->image_asset_id->toBeNull();
});

test('pictures from other hosts are refused without a request', function () {
    Http::fake();

    siakadCall('POST', route('webhooks.siakad.articles'), pictureArticle(['image_url' => 'https://evil.example/x.jpg']))->assertCreated();

    Http::assertNothingSent();
    expect(SiakadArticle::query()->sole())
        ->image_asset_id->toBeNull()
        ->image_error->toContain('evil.example is not listed');
});

test('extra media hosts can be allowed', function () {
    config(['siakad.media_hosts' => ['cdn.siakad.test']]);
    Http::fake(['cdn.siakad.test/*' => Http::response(jpegBytes())]);

    siakadCall('POST', route('webhooks.siakad.articles'), pictureArticle(['image_url' => 'https://cdn.siakad.test/a.jpg']))->assertCreated();

    expect(SiakadArticle::query()->sole()->image_asset_id)->not->toBeNull();
});

test('files that are not pictures and missing links fail at once', function (string $body, int $status, string $error) {
    Http::fake(['siakad.test/media/*' => Http::response($body, $status)]);

    siakadCall('POST', route('webhooks.siakad.articles'), pictureArticle())->assertCreated();

    Http::assertSentCount(1);
    expect(SiakadArticle::query()->sole())->image_asset_id->toBeNull()->image_error->toContain($error);
})->with([
    'html page' => ['<html>not an image</html>', 200, 'did not return a JPEG, PNG or WEBP'],
    'gone' => ['', 404, 'HTTP 404'],
]);

test('server errors are retried', function () {
    expect((new SiakadException('x', 503))->isPermanent())->toBeFalse()
        ->and((new SiakadException('x', retryable: false))->isPermanent())->toBeTrue();
});

test('oversized pictures are refused', function () {
    config(['siakad.image_max_kb' => 1]);
    Http::fake(['siakad.test/media/*' => Http::response(jpegBytes(1600, 900))]);

    siakadCall('POST', route('webhooks.siakad.articles'), pictureArticle())->assertCreated();

    expect(SiakadArticle::query()->sole()->image_error)->toContain('larger than');
});

test('the picture fields are validated', function () {
    siakadCall('POST', route('webhooks.siakad.articles'), pictureArticle(['image_url' => 'ftp://siakad.test/a.jpg', 'image_caption' => str_repeat('a', 256)]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['image_url', 'image_caption']);
});

test('reviewers see a failed download and can retry it', function () {
    Http::fake(['siakad.test/media/*' => Http::sequence()->push('', 404)->push(jpegBytes())]);
    siakadCall('POST', route('webhooks.siakad.articles'), pictureArticle())->assertCreated();
    $article = SiakadArticle::query()->sole();
    onSite(SiteKey::Mahad);
    $this->actingAs(cmsUser(Role::MahadAdmin));

    Livewire::test('pages::admin.siakad-articles.show', ['article' => $article])
        ->assertSee('The picture could not be downloaded')
        ->call('retryImage');

    expect($article->fresh())->image_error->toBeNull()->image_asset_id->not->toBeNull();
});

test('editors set a picture on manual publications', function () {
    $publication = Publication::factory()->for($this->mahad)->create();
    $image = MediaAsset::factory()->create(['site_id' => $this->mahad->id]);
    onSite(SiteKey::Mahad);
    $this->actingAs(cmsUser(Role::MahadAdmin));

    Livewire::test('pages::admin.publications.edit', ['publication' => $publication])
        ->set('imageId', $image->id)
        ->set('imageCaption', 'Sampul buku')
        ->call('save')
        ->assertHasNoErrors();

    expect($publication->fresh())->image_id->toBe($image->id)->image_caption->toBe('Sampul buku');
});
