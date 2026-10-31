<?php

namespace App\Console\Commands;

use App\Services\ContentWorkflow;
use App\Support\ContentTypes;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cms:publish-scheduled')]
#[Description('Publish approved content whose publish date has arrived')]
class PublishScheduledContent extends Command
{
    public function handle(ContentWorkflow $workflow): int
    {
        $count = 0;

        foreach (ContentTypes::REVIEWABLE as $modelClass) {
            $count += $workflow->releaseScheduled($modelClass);
        }

        $this->info("Published {$count} scheduled item(s).");

        return self::SUCCESS;
    }
}
