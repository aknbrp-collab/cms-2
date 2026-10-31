<?php

namespace App\Console\Commands;

use App\Models\SocialAccount;
use App\Social\SocialException;
use App\Social\SocialManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('social:refresh-tokens')]
#[Description('Refresh expiring social access tokens and flag accounts that need reconnecting')]
class SocialRefreshTokens extends Command
{
    public function handle(SocialManager $social): int
    {
        $accounts = SocialAccount::query()->withoutGlobalScopes()->where('status', SocialAccount::ACTIVE)->get();

        foreach ($accounts as $account) {
            if ($account->refresh_expires_at?->isPast()) {
                $account->forceFill(['status' => SocialAccount::EXPIRED, 'last_error' => __('Authorization expired. Reconnect the account.')])->save();

                continue;
            }

            try {
                $social->forProvider($account->provider)->refresh($account);
            } catch (SocialException $e) {
                $account->forceFill([
                    'status' => $e->retryable ? SocialAccount::ACTIVE : SocialAccount::EXPIRED,
                    'last_error' => $e->getMessage(),
                ])->save();
                $this->warn("{$account->displayName()}: {$e->getMessage()}");
            }
        }

        $this->info("Checked {$accounts->count()} account(s).");

        return self::SUCCESS;
    }
}
