<?php

namespace App\Console\Commands;

use App\Social\SocialPublisher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('social:dispatch-due')]
#[Description('Queue scheduled social posts whose time has come')]
class SocialDispatchDue extends Command
{
    public function handle(SocialPublisher $publisher): int
    {
        $this->info("Dispatched {$publisher->dispatchDue()} social post(s).");

        return self::SUCCESS;
    }
}
