<?php

namespace App\Enums;

/**
 * Tanya Hukum flow: submitted → forwarding → forwarded → answered → published
 * (or rejected by the moderator).
 */
enum LegalQuestionStatus: string
{
    case Submitted = 'submitted';
    case Rejected = 'rejected';
    case Forwarding = 'forwarding';
    case Forwarded = 'forwarded';
    case Answered = 'answered';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => __('New'),
            self::Rejected => __('Rejected'),
            self::Forwarding => __('Sending to SIAKAD'),
            self::Forwarded => __('Waiting for answer'),
            self::Answered => __('Answered'),
            self::Published => __('Published'),
        };
    }

    /**
     * Status shown to the person who asked (tracking page).
     */
    public function publicLabel(): string
    {
        return match ($this) {
            self::Submitted, self::Forwarding => 'Pertanyaan diterima dan sedang ditinjau',
            self::Rejected => 'Pertanyaan tidak dapat diproses',
            self::Forwarded => 'Sedang dikaji oleh dewan asatidz',
            self::Answered => 'Jawaban sedang disiapkan untuk diterbitkan',
            self::Published => 'Jawaban telah diterbitkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Submitted => 'sky',
            self::Rejected => 'red',
            self::Forwarding, self::Forwarded => 'amber',
            self::Answered => 'violet',
            self::Published => 'emerald',
        };
    }
}
