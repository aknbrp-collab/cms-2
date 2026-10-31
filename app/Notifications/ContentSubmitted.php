<?php

namespace App\Notifications;

use App\Contracts\Reviewable;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;

/**
 * Tell reviewers that content is waiting for review.
 */
class ContentSubmitted extends Notification
{
    use Queueable;

    public function __construct(
        public Reviewable&Model $content,
        public User $submitter,
    ) {}

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
            'message' => __(':user submitted :type ":title" for review.', [
                'user' => $this->submitter->name,
                'type' => strtolower($this->content::contentLabel()),
                'title' => $this->content->getAttribute('title'),
            ]),
            'content_type' => $this->content->getMorphClass(),
            'content_id' => $this->content->getKey(),
            'site_id' => $this->content->getAttribute('site_id'),
        ];
    }
}
