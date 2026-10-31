<?php

use App\Enums\ContentStatus;
use App\Enums\Role;
use App\Enums\SiteKey;
use App\Models\AuditLog;
use App\Models\Page;
use App\Models\Post;
use App\Models\Site;
use App\Notifications\ContentReviewed;
use App\Notifications\ContentSubmitted;
use App\Services\ContentWorkflow;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    seedCms();
    Notification::fake();

    $this->workflow = app(ContentWorkflow::class);
    $this->pondok = Site::findByKey(SiteKey::Pondok);
    $this->author = cmsUser(Role::PondokAdmin);
    $this->editor = cmsUser(Role::ManagingEditor);
    $this->post = Post::factory()->for($this->pondok)->create(['author_id' => $this->author->id]);
});

test('a site admin submits a draft and editors are notified', function () {
    $this->workflow->submit($this->post, $this->author);

    expect($this->post->fresh()->status)->toBe(ContentStatus::UnderReview)
        ->and($this->post->fresh()->submitted_at)->not->toBeNull();

    Notification::assertSentTo($this->editor, ContentSubmitted::class);
    Notification::assertNotSentTo($this->author, ContentSubmitted::class);
    expect(AuditLog::query()->where('event', 'workflow.submitted')->where('auditable_id', $this->post->id)->exists())->toBeTrue();
});

test('site admins cannot approve or publish', function () {
    $this->workflow->submit($this->post, $this->author);

    expect(fn () => $this->workflow->approve($this->post, $this->author))->toThrow(AuthorizationException::class)
        ->and(fn () => $this->workflow->publish($this->post, $this->author))->toThrow(AuthorizationException::class);
});

test('an editor approves content and it is published now', function () {
    $this->workflow->submit($this->post, $this->author);
    $this->workflow->approve($this->post, $this->editor);

    $post = $this->post->fresh();

    expect($post->status)->toBe(ContentStatus::Published)
        ->and($post->published_at)->not->toBeNull()
        ->and($post->reviewed_by)->toBe($this->editor->id)
        ->and(Post::query()->published()->whereKey($post->id)->exists())->toBeTrue();

    Notification::assertSentTo($this->author, ContentReviewed::class, fn ($notification) => $notification->approved);
});

test('approval with a future date schedules the release', function () {
    $this->workflow->submit($this->post, $this->author);
    $this->workflow->approve($this->post, $this->editor, now()->addHour());

    $post = $this->post->fresh();
    expect($post->status)->toBe(ContentStatus::Approved)
        ->and($post->isScheduled())->toBeTrue()
        ->and(Post::query()->published()->whereKey($post->id)->exists())->toBeFalse();

    $this->artisan('cms:publish-scheduled')->assertSuccessful();
    expect($this->post->fresh()->status)->toBe(ContentStatus::Approved);

    $this->travel(61)->minutes();
    $this->artisan('cms:publish-scheduled')->assertSuccessful();

    expect($this->post->fresh()->status)->toBe(ContentStatus::Published)
        ->and(AuditLog::query()->where('event', 'workflow.scheduled_release')->exists())->toBeTrue();
});

test('rejecting returns the content to draft with a note', function () {
    $this->workflow->submit($this->post, $this->author);
    $this->workflow->reject($this->post, $this->editor, 'Please add a photo.');

    $post = $this->post->fresh();
    expect($post->status)->toBe(ContentStatus::Draft)
        ->and($post->review_note)->toBe('Please add a photo.');

    Notification::assertSentTo($this->author, ContentReviewed::class, fn ($notification) => ! $notification->approved);
});

test('transitions from the wrong status are rejected', function () {
    expect(fn () => $this->workflow->approve($this->post, $this->editor))->toThrow(ValidationException::class);

    $this->workflow->submit($this->post, $this->author);

    expect(fn () => $this->workflow->submit($this->post, $this->author))->toThrow(ValidationException::class);
});

test('site admins can only edit drafts', function () {
    expect($this->author->can('update', $this->post))->toBeTrue();

    $this->workflow->submit($this->post, $this->author);

    expect($this->author->can('update', $this->post->fresh()))->toBeFalse()
        ->and($this->editor->can('update', $this->post->fresh()))->toBeTrue();
});

test('admins of another site cannot touch the content', function () {
    $mahadAdmin = cmsUser(Role::MahadAdmin);

    expect($mahadAdmin->can('view', $this->post))->toBeFalse()
        ->and($mahadAdmin->can('update', $this->post))->toBeFalse()
        ->and(fn () => $this->workflow->submit($this->post, $mahadAdmin))->toThrow(AuthorizationException::class);
});

test('publishers can publish directly, unpublish, archive and restore', function () {
    $this->workflow->publish($this->post, $this->editor);
    expect($this->post->fresh()->status)->toBe(ContentStatus::Published);

    $this->workflow->unpublish($this->post, $this->editor);
    expect($this->post->fresh()->status)->toBe(ContentStatus::Draft)
        ->and($this->post->fresh()->published_at)->toBeNull();

    $this->workflow->archive($this->post, $this->editor);
    expect($this->post->fresh()->status)->toBe(ContentStatus::Archived);

    $this->workflow->restore($this->post, $this->editor);
    expect($this->post->fresh()->status)->toBe(ContentStatus::Draft);
});

test('pages use the same workflow', function () {
    $page = Page::factory()->for($this->pondok)->create(['author_id' => $this->author->id]);

    $this->workflow->submit($page, $this->author);
    $this->workflow->approve($page, $this->editor);

    expect($page->fresh()->status)->toBe(ContentStatus::Published);
});

test('the review queue lists submitted content for editors only', function () {
    $this->workflow->submit($this->post, $this->author);

    $this->actingAs($this->editor)->get(route('admin.review'))->assertOk()->assertSee($this->post->title);
    $this->actingAs($this->author)->get(route('admin.review'))->assertForbidden();
});
