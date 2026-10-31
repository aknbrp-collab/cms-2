<?php

namespace App\Notifications;

use App\Contracts\Reviewable;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;

/**
 * Tell the author their content was approved or sent back.
 */
class ContentReviewed extends Notification
{
    use Queueable;

    public function __construct(
        public Reviewable&Model $content,
        public User $reviewer,
        public bool $approved,
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
        $replace = [
            'user' => $this->reviewer->name,
            'title' => $this->content->getAttribute('title'),
        ];

        return [
            'message' => $this->approved
                ? __(':user approved ":title".', $replace)
                : __(':user sent ":title" back for changes.', $replace),
            'note' => $this->content->getAttribute('review_note'),
            'approved' => $this->approved,
            'content_type' => $this->content->getMorphClass(),
            'content_id' => $this->content->getKey(),
            'site_id' => $this->content->getAttribute('site_id'),
        ];
    }
}
