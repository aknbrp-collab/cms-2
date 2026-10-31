<?php

use App\Enums\SocialPostStatus;
use App\Enums\SocialProvider;
use App\Models\MediaAsset;
use App\Models\Site;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\SocialPostTarget;
use App\Social\SocialException;
use App\Social\SocialManager;
use App\Social\SocialPublisher;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Social post')] class extends Component {
    #[Locked]
    public ?string $postId = null;

    #[Locked]
    public ?string $siteId = null;

    public string $caption = '';

    public string $link = '';

    /** @var list<string> Media asset ids, in order. */
    public array $media = [];

    public ?string $pickedMediaId = null;

    /** @var list<string> */
    public array $accountIds = [];

    /**
     * Per-account settings keyed by account id.
     *
     * @var array<string, array{caption_override: string, hashtags: string, privacy_level: string, allow_comment: bool, allow_duet: bool, allow_stitch: bool, disclose: bool, brand_organic: bool, brand_content: bool}>
     */
    public array $targets = [];

    /** @var array<string, array<string, mixed>> TikTok creator info keyed by account id. */
    public array $creatorInfo = [];

    public string $scheduleAt = '';

    public function mount(?SocialPost $post = null): void
    {
        if ($post?->exists) {
            abort_unless(Auth::user()->canAccessSite($post->site), 404);
            $this->postId = $post->id;
            $this->siteId = $post->site_id;
            $this->caption = $post->caption;
            $this->link = (string) $post->link;
            $this->media = $post->media ?? [];
            $this->scheduleAt = (string) $post->scheduled_at?->format('Y-m-d\TH:i');

            foreach ($post->targets as $target) {
                $this->accountIds[] = $target->social_account_id;
                $options = $target->options ?? [];
                $this->targets[$target->social_account_id] = [
                    'caption_override' => (string) $target->caption_override,
                    'hashtags' => (string) $target->hashtags,
                    'privacy_level' => (string) ($options['privacy_level'] ?? ''),
                    'allow_comment' => ! ($options['disable_comment'] ?? false),
                    'allow_duet' => ! ($options['disable_duet'] ?? false),
                    'allow_stitch' => ! ($options['disable_stitch'] ?? false),
                    'disclose' => ($options['brand_organic'] ?? false) || ($options['brand_content'] ?? false),
                    'brand_organic' => (bool) ($options['brand_organic'] ?? false),
                    'brand_content' => (bool) ($options['brand_content'] ?? false),
                ];
            }

            $this->loadCreatorInfo();
        } else {
            $this->authorize('social_posts.create');
            $site = current_site();
            abort_if($site === null || ! Auth::user()->canAccessSite($site), 403, __('Choose a site in the sidebar first.'));
            $this->siteId = $site->id;
        }
    }

    #[Computed]
    public function post(): ?SocialPost
    {
        return $this->postId ? SocialPost::query()->withoutGlobalScopes()->with('targets.account')->find($this->postId) : null;
    }

    #[Computed]
    public function canEdit(): bool
    {
        $post = $this->post;

        return $post === null
            ? Auth::user()->can('social_posts.create')
            : $post->status->isEditable() && Auth::user()->can('social_posts.update');
    }

    /**
     * @return Collection<int, SocialAccount>
     */
    #[Computed]
    public function accounts(): Collection
    {
        return SocialAccount::query()->forSite((string) $this->siteId)->orderBy('provider')->orderBy('name')->get();
    }

    /**
     * @return Collection<int, MediaAsset>
     */
    #[Computed]
    public function mediaAssets(): Collection
    {
        return (new SocialPost(['media' => $this->media]))->mediaAssets();
    }

    public function updatedPickedMediaId(): void
    {
        if ($this->pickedMediaId && ! in_array($this->pickedMediaId, $this->media, true) && count($this->media) < 10) {
            $this->media[] = $this->pickedMediaId;
        }

        $this->pickedMediaId = null;
    }

    public function removeMedia(int $index): void
    {
        unset($this->media[$index]);
        $this->media = array_values($this->media);
    }

    public function moveMedia(int $index, int $direction): void
    {
        if (isset($this->media[$index], $this->media[$index + $direction])) {
            [$this->media[$index], $this->media[$index + $direction]] = [$this->media[$index + $direction], $this->media[$index]];
        }
    }

    public function updatedAccountIds(): void
    {
        foreach ($this->accountIds as $id) {
            $this->targets[$id] ??= [
                'caption_override' => '', 'hashtags' => '', 'privacy_level' => '',
                'allow_comment' => false, 'allow_duet' => false, 'allow_stitch' => false,
                'disclose' => false, 'brand_organic' => false, 'brand_content' => false,
            ];
        }

        $this->loadCreatorInfo();
    }

    /**
     * Characters of the final caption for an account (caption + hashtags).
     */
    public function captionLength(string $accountId): int
    {
        $settings = $this->targets[$accountId] ?? ['caption_override' => '', 'hashtags' => ''];
        $caption = trim($settings['caption_override'] ?: $this->caption);
        $hashtags = SocialPostTarget::normalizeHashtags($settings['hashtags']);

        return mb_strlen($hashtags === '' ? $caption : $caption."\n\n".$hashtags);
    }

    public function saveDraft(): void
    {
        $this->persist();
        Flux::toast(variant: 'success', text: __('Draft saved.'));
    }

    public function schedule(SocialPublisher $publisher): void
    {
        $this->validate(['scheduleAt' => ['required', 'date', 'after:now']], [], ['scheduleAt' => __('schedule time')]);
        $post = $this->persist();
        $publisher->schedule($post, Auth::user(), CarbonImmutable::parse($this->scheduleAt));

        Flux::toast(variant: 'success', text: __('Scheduled.'));
        $this->redirectRoute('admin.social.posts', navigate: true);
    }

    public function publishNow(SocialPublisher $publisher): void
    {
        $post = $this->persist();
        $publisher->schedule($post, Auth::user());

        Flux::modal('confirm-publish')->close();
        Flux::toast(variant: 'success', text: __('Publishing started. Each account is posted separately.'));
        $this->redirectRoute('admin.social.edit', $post, navigate: true);
    }

    public function unschedule(SocialPublisher $publisher): void
    {
        $publisher->unschedule($this->post, Auth::user());
        unset($this->post, $this->canEdit);
        Flux::toast(text: __('Back to draft.'));
    }

    public function retry(string $targetId, SocialPublisher $publisher): void
    {
        $target = $this->post?->targets->firstWhere('id', $targetId) ?? abort(404);
        $publisher->retry($target, Auth::user());

        unset($this->post);
        Flux::toast(text: __('Retrying.'));
    }

    protected function persist(): SocialPost
    {
        abort_unless($this->canEdit, 403);

        $accountIds = $this->accounts->modelKeys();

        $this->validate([
            'caption' => ['required', 'string', 'max:63206'],
            'link' => ['nullable', 'url', 'max:2048'],
            'media' => ['array', 'max:10'],
            'media.*' => ['exists:media_assets,id'],
            'accountIds' => ['array', 'min:1'],
            'accountIds.*' => ['in:'.implode(',', $accountIds)],
            'targets.*.caption_override' => ['nullable', 'string', 'max:63206'],
            'targets.*.hashtags' => ['nullable', 'string', 'max:1000'],
            'targets.*.privacy_level' => ['nullable', 'string', 'max:50'],
        ], [], ['accountIds' => __('accounts')]);

        $post = $this->post ?? new SocialPost(['site_id' => $this->siteId, 'author_id' => Auth::id()]);
        $post->fill(['caption' => $this->caption, 'link' => $this->link ?: null, 'media' => array_values($this->media)])->save();

        $post->targets()->whereNotIn('social_account_id', $this->accountIds)->delete();

        foreach ($this->accountIds as $accountId) {
            $settings = $this->targets[$accountId];
            $isTikTok = $this->accounts->firstWhere('id', $accountId)?->provider === SocialProvider::TikTok;

            $post->targets()->updateOrCreate(['social_account_id' => $accountId], [
                'caption_override' => $settings['caption_override'] ?: null,
                'hashtags' => $settings['hashtags'] ?: null,
                'options' => $isTikTok ? [
                    'privacy_level' => $settings['privacy_level'] ?: null,
                    'disable_comment' => ! $settings['allow_comment'],
                    'disable_duet' => ! $settings['allow_duet'],
                    'disable_stitch' => ! $settings['allow_stitch'],
                    'brand_organic' => $settings['disclose'] && $settings['brand_organic'],
                    'brand_content' => $settings['disclose'] && $settings['brand_content'],
                ] : null,
            ]);
        }

        if ($this->postId === null) {
            $this->postId = $post->id;
        }

        unset($this->post);

        return $post->refresh();
    }

    protected function loadCreatorInfo(): void
    {
        foreach ($this->accounts->whereIn('id', $this->accountIds) as $account) {
            if ($account->provider !== SocialProvider::TikTok || isset($this->creatorInfo[$account->id])) {
                continue;
            }

            try {
                $this->creatorInfo[$account->id] = app(SocialManager::class)->forProvider($account->provider)->creatorInfo($account);
            } catch (SocialException $e) {
                $this->creatorInfo[$account->id] = ['error' => $e->getMessage()];
            }
        }
    }
}; ?>

@php
    $post = $this->post;
    $hasVideo = $this->mediaAssets->contains(fn ($asset) => str_starts_with((string) $asset->file()?->mime_type, 'video/'));
    $privacyLabels = ['PUBLIC_TO_EVERYONE' => __('Everyone'), 'MUTUAL_FOLLOW_FRIENDS' => __('Friends'), 'FOLLOWER_OF_CREATOR' => __('Followers'), 'SELF_ONLY' => __('Only me')];
@endphp

<div class="flex w-full flex-1 flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:link :href="route('admin.social.posts')" wire:navigate class="text-sm">← {{ __('Social posts') }}</flux:link>
            <flux:heading size="xl" class="mt-1">{{ $post ? __('Social post') : __('New social post') }}</flux:heading>
            <flux:text class="mt-1">{{ App\Models\Site::query()->find($siteId)?->name }}</flux:text>
        </div>
        @if ($post)
            <flux:badge :color="$post->status->color()">{{ $post->status->label() }}</flux:badge>
        @endif
    </div>

    @error('targets')
        <flux:callout icon="exclamation-triangle" color="red" :heading="__('Fix these before publishing')">
            <flux:callout.text>
                <ul class="list-disc ps-5">@foreach ($errors->get('targets') as $message)<li>{{ $message }}</li>@endforeach</ul>
            </flux:callout.text>
        </flux:callout>
    @enderror
    <flux:error name="status" />

    @if (! $this->canEdit && $post)
        {{-- Read-only results view once publishing has started. --}}
        <div class="grid gap-6 lg:grid-cols-3">
            <flux:card class="space-y-3 lg:col-span-2">
                <flux:heading>{{ __('Caption') }}</flux:heading>
                <flux:text class="whitespace-pre-line">{{ $post->caption }}</flux:text>
                <div class="flex flex-wrap gap-2">
                    @foreach ($this->mediaAssets as $asset)
                        @if ($asset->isImage())
                            <img src="{{ $asset->url('thumb') }}" alt="" class="size-20 rounded-lg object-cover">
                        @else
                            <div class="flex size-20 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800"><flux:icon.film class="size-8 text-zinc-400" /></div>
                        @endif
                    @endforeach
                </div>
            </flux:card>
            <flux:card class="space-y-3">
                <flux:heading>{{ __('Accounts') }}</flux:heading>
                @foreach ($post->targets as $target)
                    <div class="space-y-1 border-b border-zinc-100 pb-3 last:border-0 dark:border-zinc-700" wire:key="target-{{ $target->id }}">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm font-medium">{{ $target->account->provider->label() }} · {{ $target->account->displayName() }}</span>
                            <flux:badge size="sm" :color="$target->status->color()">{{ $target->status->label() }}</flux:badge>
                        </div>
                        @if ($target->permalink)
                            <flux:link :href="$target->permalink" target="_blank" class="text-xs">{{ __('View post') }} ↗</flux:link>
                        @endif
                        @if ($target->last_error)
                            <flux:text size="sm" class="text-red-600">{{ $target->last_error }}</flux:text>
                        @endif
                        @if ($target->status === App\Enums\SocialTargetStatus::Failed)
                            @can('social_posts.publish')
                                <flux:button size="xs" icon="arrow-path" wire:click="retry('{{ $target->id }}')">{{ __('Retry') }}</flux:button>
                            @endcan
                        @endif
                    </div>
                @endforeach
                @if ($post->targets->contains(fn ($target) => $target->account->provider === App\Enums\SocialProvider::TikTok))
                    <flux:text size="sm">{{ __('TikTok may take a few minutes to process and show the post.') }}</flux:text>
                @endif
            </flux:card>
        </div>
    @else
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="min-w-0 space-y-6 lg:col-span-2">
                <flux:card class="space-y-5">
                    <flux:textarea wire:model.live.debounce.500ms="caption" :label="__('Caption')" rows="6" :description="__('Written once; tweak it per account on the right.')" />
                    <flux:input wire:model="link" type="url" :label="__('Link (Facebook only)')" placeholder="https://…" />

                    <div class="space-y-3">
                        <flux:label>{{ __('Media') }} <span class="font-normal text-zinc-500">({{ __('max 10; first item is the cover') }})</span></flux:label>
                        <div class="flex flex-wrap gap-3">
                            @foreach ($this->mediaAssets as $index => $asset)
                                <div class="relative" wire:key="media-{{ $asset->id }}">
                                    @if ($asset->isImage())
                                        <img src="{{ $asset->url('thumb') }}" alt="" class="size-24 rounded-lg object-cover">
                                    @else
                                        <div class="flex size-24 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800"><flux:icon.film class="size-8 text-zinc-400" /></div>
                                    @endif
                                    <div class="mt-1 flex justify-center">
                                        <flux:button size="xs" variant="ghost" icon="arrow-left" wire:click="moveMedia({{ $index }}, -1)" :disabled="$loop->first" />
                                        <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="removeMedia({{ $index }})" />
                                        <flux:button size="xs" variant="ghost" icon="arrow-right" wire:click="moveMedia({{ $index }}, 1)" :disabled="$loop->last" />
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <livewire:admin.media-picker wire:model.live="pickedMediaId" kind="visual" :key="'social-media-'.count($media)" />
                        <flux:text size="sm">{{ __('Instagram and TikTok need at least one photo or video. Media must be reachable on a public URL (S3 in production).') }}</flux:text>
                    </div>
                </flux:card>
            </div>

            <div class="space-y-6">
                <flux:card class="space-y-4">
                    <flux:heading>{{ __('Publish to') }}</flux:heading>
                    @forelse ($this->accounts as $account)
                        <flux:checkbox wire:model.live="accountIds" :value="$account->id" :label="$account->provider->label().' · '.$account->displayName()" :disabled="! $account->isUsable()" :description="$account->isUsable() ? null : __('Reconnect needed')" wire:key="acc-{{ $account->id }}" />
                    @empty
                        <flux:text>{{ __('No accounts for this site yet.') }} <flux:link :href="route('admin.social.accounts')" wire:navigate>{{ __('Connect one') }}</flux:link></flux:text>
                    @endforelse
                    <flux:error name="accountIds" />
                </flux:card>

                @foreach ($this->accounts->whereIn('id', $accountIds) as $account)
                    @php
                        $limit = $account->provider->captionLimit();
                        $length = $this->captionLength($account->id);
                        $info = $creatorInfo[$account->id] ?? [];
                    @endphp
                    <flux:card class="space-y-4" wire:key="settings-{{ $account->id }}">
                        <div class="flex items-center justify-between">
                            <flux:heading size="sm">{{ $account->provider->label() }} · {{ $account->displayName() }}</flux:heading>
                            <span @class(['text-xs font-mono', 'text-red-600 font-bold' => $length > $limit, 'text-zinc-500' => $length <= $limit])>{{ $length }}/{{ $limit }}</span>
                        </div>
                        <flux:textarea wire:model.live.debounce.500ms="targets.{{ $account->id }}.caption_override" :label="__('Own caption (optional)')" rows="2" />
                        <flux:input wire:model.live.debounce.500ms="targets.{{ $account->id }}.hashtags" :label="__('Hashtags')" placeholder="santri, pesantren" :description="$account->provider->hashtagLimit() ? __('Max :n hashtags.', ['n' => $account->provider->hashtagLimit()]) : null" />

                        @if ($account->provider === SocialProvider::TikTok)
                            {{-- TikTok Content Posting API UX requirements. --}}
                            @if (isset($info['error']))
                                <flux:callout icon="exclamation-triangle" color="red" :text="$info['error']" />
                            @else
                                <flux:text size="sm">{{ __('Posting as') }} <strong>{{ $info['creator_nickname'] ?? $account->name }}</strong></flux:text>
                                <flux:select wire:model.live="targets.{{ $account->id }}.privacy_level" :label="__('Who can view this post')">
                                    <option value="">{{ __('Choose…') }}</option>
                                    @foreach ($info['privacy_level_options'] ?? [] as $option)
                                        <option value="{{ $option }}" @disabled($option === 'SELF_ONLY' && ($targets[$account->id]['brand_content'] ?? false))>{{ $privacyLabels[$option] ?? $option }}</option>
                                    @endforeach
                                </flux:select>
                                <div class="space-y-2">
                                    <flux:label>{{ __('Allow users to') }}</flux:label>
                                    <flux:checkbox wire:model="targets.{{ $account->id }}.allow_comment" :label="__('Comment')" :disabled="(bool) ($info['comment_disabled'] ?? false)" />
                                    @if ($hasVideo)
                                        <flux:checkbox wire:model="targets.{{ $account->id }}.allow_duet" :label="__('Duet')" :disabled="(bool) ($info['duet_disabled'] ?? false)" />
                                        <flux:checkbox wire:model="targets.{{ $account->id }}.allow_stitch" :label="__('Stitch')" :disabled="(bool) ($info['stitch_disabled'] ?? false)" />
                                    @endif
                                </div>
                                <flux:switch wire:model.live="targets.{{ $account->id }}.disclose" :label="__('Disclose commercial content')" :description="__('Turn on if this promotes yourself, a brand, product or service.')" />
                                @if ($targets[$account->id]['disclose'] ?? false)
                                    <div class="space-y-2 ps-2">
                                        <flux:checkbox wire:model.live="targets.{{ $account->id }}.brand_organic" :label="__('Your brand')" :description="__('Labelled as Promotional content.')" />
                                        <flux:checkbox wire:model.live="targets.{{ $account->id }}.brand_content" :label="__('Branded content')" :description="__('Labelled as Paid partnership. Cannot be private.')" />
                                        @if (! ($targets[$account->id]['brand_organic'] ?? false) && ! ($targets[$account->id]['brand_content'] ?? false))
                                            <flux:text size="sm" class="text-amber-600">{{ __('Choose at least one option.') }}</flux:text>
                                        @endif
                                    </div>
                                @endif
                                @if (isset($info['max_video_post_duration_sec']) && $hasVideo)
                                    <flux:text size="sm">{{ __('Videos up to :s seconds.', ['s' => $info['max_video_post_duration_sec']]) }}</flux:text>
                                @endif
                                <flux:text size="sm" class="text-zinc-500">
                                    @if ($targets[$account->id]['brand_content'] ?? false)
                                        {!! __('By posting, you agree to TikTok\'s <a href="https://www.tiktok.com/legal/page/global/bc-policy/en" target="_blank" class="underline">Branded Content Policy</a> and <a href="https://www.tiktok.com/legal/page/global/music-usage-confirmation/en" target="_blank" class="underline">Music Usage Confirmation</a>.') !!}
                                    @else
                                        {!! __('By posting, you agree to TikTok\'s <a href="https://www.tiktok.com/legal/page/global/music-usage-confirmation/en" target="_blank" class="underline">Music Usage Confirmation</a>.') !!}
                                    @endif
                                </flux:text>
                            @endif
                        @endif
                    </flux:card>
                @endforeach

                <flux:card class="space-y-4">
                    <flux:button wire:click="saveDraft" class="w-full">{{ __('Save draft') }}</flux:button>
                    @can('social_posts.publish')
                        <flux:input type="datetime-local" wire:model="scheduleAt" :label="__('Schedule for')" />
                        <flux:button variant="primary" icon="calendar" wire:click="schedule" class="w-full">{{ __('Schedule') }}</flux:button>
                        <flux:modal.trigger name="confirm-publish">
                            <flux:button icon="paper-airplane" class="w-full">{{ __('Publish now') }}</flux:button>
                        </flux:modal.trigger>
                        @if ($post?->status === SocialPostStatus::Scheduled)
                            <flux:button variant="ghost" wire:click="unschedule" class="w-full">{{ __('Unschedule') }}</flux:button>
                        @endif
                    @endcan
                </flux:card>
            </div>
        </div>

        <flux:modal name="confirm-publish" class="md:w-[28rem]">
            <div class="space-y-4">
                <flux:heading size="lg">{{ __('Publish now?') }}</flux:heading>
                <flux:text>{{ __('The post is sent to these accounts right away:') }}</flux:text>
                <ul class="list-disc ps-5 text-sm">
                    @foreach ($this->accounts->whereIn('id', $accountIds) as $account)
                        <li>{{ $account->provider->label() }} · {{ $account->displayName() }}</li>
                    @endforeach
                </ul>
                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                    <flux:button variant="primary" wire:click="publishNow">{{ __('Publish') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
