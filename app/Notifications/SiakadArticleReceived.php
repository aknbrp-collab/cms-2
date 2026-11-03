<?php

namespace App\Notifications;

use App\Models\SiakadArticle;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Tell reviewers a new article arrived from SIAKAD, or that a rejected one was sent again.
 */
class SiakadArticleReceived extends Notification
{
    use Queueable;

    public function __construct(public SiakadArticle $article, public bool $resubmitted = false) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => $this->resubmitted
                ? __('SIAKAD sent a revised version of a rejected article: ":title"', ['title' => $this->article->title])
                : __('New article from SIAKAD: ":title"', ['title' => $this->article->title]),
            'url' => route('admin.siakad-articles.show', $this->article),
            'site_id' => $this->article->site_id,
        ];
    }
}
