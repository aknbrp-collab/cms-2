<?php

use App\Enums\SocialProvider;
use App\Models\SocialAccount;
use App\Social\SocialManager;
use App\Support\Audit;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Social accounts')] class extends Component {
    /**
     * @return Collection<int, SocialAccount>
     */
    #[Computed]
    public function accounts(): Collection
    {
        return SocialAccount::query()
            ->with('site')
            ->whereIn('site_id', Auth::user()->accessibleSites()->modelKeys())
            ->orderBy('provider')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function social(): SocialManager
    {
        return app(SocialManager::class);
    }

    /**
     * Disconnect: wipe the tokens but keep the account for post history.
     */
    public function disconnect(string $id): void
    {
        $this->authorize('social_accounts.delete');

        $account = SocialAccount::query()->whereIn('site_id', Auth::user()->accessibleSites()->modelKeys())->findOrFail($id);
        $account->forceFill([
            'status' => SocialAccount::REVOKED,
            'access_token' => '',
            'refresh_token' => null,
            'last_error' => __('Disconnected by :name.', ['name' => Auth::user()->name]),
        ])->save();

        Audit::record('social.disconnected', $account);
        unset($this->accounts);
        Flux::toast(text: __('Account disconnected. Its tokens were deleted.'));
    }
}; ?>

<div class="flex w-full flex-1 flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Social accounts') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Facebook Pages, Instagram Business and TikTok accounts the CMS can publish to.') }}</flux:text>
    </div>

    @if (session('status'))
        <flux:callout icon="check-circle" color="emerald" :text="session('status')" />
    @endif
    @if (session('error'))
        <flux:callout icon="exclamation-triangle" color="red" :text="session('error')" />
    @endif
    @unless ($this->social->isLive())
        <flux:callout icon="beaker" color="amber" :heading="__('Demo mode')" :text="__('SOCIAL_DRIVER=fake: connecting creates demo accounts and publishing is simulated. Add the Meta/TikTok app keys and set SOCIAL_DRIVER=live to use the real platforms.')" />
    @endunless

    @can('social_accounts.connect')
        <flux:card class="space-y-3">
            <flux:heading>{{ __('Connect an account') }} @if (current_site()) <span class="font-normal text-zinc-500">→ {{ current_site()->name }}</span> @endif</flux:heading>
            @if (! current_site())
                <x-admin.needs-site />
            @else
                <div class="flex flex-wrap gap-2">
                    @foreach (SocialProvider::cases() as $provider)
                        @if ($this->social->canConnect($provider))
                            <flux:button :href="route('social.connect', $provider->value)" icon="plus">{{ $provider->label() }}</flux:button>
                        @else
                            <flux:tooltip :content="__('Add the app keys to .env first (see docs/social-review.md).')">
                                <flux:button disabled icon="plus">{{ $provider->label() }}</flux:button>
                            </flux:tooltip>
                        @endif
                    @endforeach
                </div>
                <flux:text size="sm">{{ __('Facebook and Instagram use one Meta login: every Page you grant (and its linked Instagram account) is connected.') }}</flux:text>
            @endif
        </flux:card>
    @endcan

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Account') }}</flux:table.column>
            <flux:table.column>{{ __('Platform') }}</flux:table.column>
            <flux:table.column>{{ __('Site') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->accounts as $account)
                <flux:table.row :key="$account->id">
                    <flux:table.cell>
                        <div class="flex items-center gap-3">
                            <flux:avatar size="sm" :src="$account->avatar_url" :name="$account->name" />
                            <div>
                                <div class="font-medium">{{ $account->displayName() }}</div>
                                @if ($account->last_error && $account->status !== 'active')
                                    <div class="text-xs text-red-600">{{ $account->last_error }}</div>
                                @endif
                            </div>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $account->provider->label() }}</flux:table.cell>
                    <flux:table.cell>{{ $account->site->name }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$account->isUsable() ? 'emerald' : 'red'">{{ $account->isUsable() ? __('Connected') : __('Reconnect needed') }}</flux:badge>
                        @if ($account->token_expires_at)
                            <div class="mt-1 text-xs text-zinc-500">{{ __('Token until :date', ['date' => $account->token_expires_at->translatedFormat('d M H:i')]) }}</div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        @if (! $account->isUsable() && current_site()?->is($account->site))
                            @can('social_accounts.connect')
                                <flux:button size="sm" :href="route('social.connect', $account->provider->value)">{{ __('Reconnect') }}</flux:button>
                            @endcan
                        @endif
                        @if ($account->status !== 'revoked')
                            @can('social_accounts.delete')
                                <flux:button size="sm" variant="ghost" icon="link-slash" wire:click="disconnect('{{ $account->id }}')" wire:confirm="{{ __('Disconnect this account? Scheduled posts to it will fail.') }}" />
                            @endcan
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5">{{ __('No accounts connected yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
