<?php

namespace App\Enums;

/**
 * Kind of academic publication in the repository.
 */
enum PublicationType: string
{
    case Research = 'research';
    case JournalArticle = 'journal_article';
    case BahtsulMasail = 'bahtsul_masail';
    case Thesis = 'thesis';
    case Book = 'book';

    public function label(): string
    {
        return match ($this) {
            self::Research => __('Artikel & Riset'),
            self::JournalArticle => __('Artikel Jurnal'),
            self::BahtsulMasail => __('Bahtsul Masail'),
            self::Thesis => __('Risalah & Skripsi'),
            self::Book => __('Buku & Tahqiq'),
        };
    }
}
