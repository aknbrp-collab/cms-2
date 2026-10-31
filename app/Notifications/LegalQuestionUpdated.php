<?php

namespace App\Notifications;

use App\Models\LegalQuestion;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Tell Tanya Hukum admins about a new question or an answer from SIAKAD.
 */
class LegalQuestionUpdated extends Notification
{
    use Queueable;

    /**
     * @param  'submitted'|'answered'  $event
     */
    public function __construct(public LegalQuestion $question, public string $event) {}

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
        $excerpt = Str::limit($this->question->question, 80);

        return [
            'message' => $this->event === 'answered'
                ? __('SIAKAD answered Tanya Hukum :code: ":excerpt"', ['code' => $this->question->tracking_code, 'excerpt' => $excerpt])
                : __('New Tanya Hukum question :code: ":excerpt"', ['code' => $this->question->tracking_code, 'excerpt' => $excerpt]),
            'url' => route('admin.legal-questions.show', $this->question),
            'site_id' => $this->question->site_id,
        ];
    }
}
