<?php

use App\Concerns\BelongsToSite;
use App\Enums\SiteKey;
use App\Models\Site;
use App\Support\CurrentSite;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SiteScopedNote extends Model
{
    use BelongsToSite, HasUuids;

    protected $table = 'site_scoped_notes';

    protected $guarded = [];
}

beforeEach(function () {
    seedCms();

    Schema::create('site_scoped_notes', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->foreignUuid('site_id')->nullable();
        $table->string('title');
        $table->timestamps();
    });

    $this->pondok = Site::findByKey(SiteKey::Pondok);
    $this->mahad = Site::findByKey(SiteKey::Mahad);
    $this->currentSite = app(CurrentSite::class);
});

test('new records are assigned to the current site', function () {
    $note = $this->currentSite->using($this->pondok, fn () => SiteScopedNote::query()->create(['title' => 'A']));

    expect($note->site_id)->toBe($this->pondok->id);
});

test('queries are limited to the current site', function () {
    SiteScopedNote::query()->create(['title' => 'Pondok', 'site_id' => $this->pondok->id]);
    SiteScopedNote::query()->create(['title' => 'Mahad', 'site_id' => $this->mahad->id]);

    $titles = $this->currentSite->using($this->mahad, fn () => SiteScopedNote::query()->pluck('title')->all());

    expect($titles)->toBe(['Mahad']);
});

test('without a current site every site is visible', function () {
    SiteScopedNote::query()->create(['title' => 'Pondok', 'site_id' => $this->pondok->id]);
    SiteScopedNote::query()->create(['title' => 'Mahad', 'site_id' => $this->mahad->id]);

    expect(SiteScopedNote::query()->count())->toBe(2);
});

test('forSite and withoutSiteScope bypass the current site', function () {
    SiteScopedNote::query()->create(['title' => 'Pondok', 'site_id' => $this->pondok->id]);
    SiteScopedNote::query()->create(['title' => 'Mahad', 'site_id' => $this->mahad->id]);

    $this->currentSite->using($this->mahad, function () {
        expect(SiteScopedNote::query()->forSite($this->pondok)->pluck('title')->all())->toBe(['Pondok'])
            ->and(SiteScopedNote::withoutSiteScope()->count())->toBe(2);
    });
});
