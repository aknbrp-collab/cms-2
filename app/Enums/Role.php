<?php

namespace App\Enums;

use App\Support\Permissions;

enum Role: string
{
    case Superadmin = 'superadmin';
    case ManagingEditor = 'managing-editor';
    case PondokAdmin = 'pondok-admin';
    case MahadAdmin = 'mahad-admin';
    case SocialMediaManager = 'social-media-manager';
    case TanyaHukumAdmin = 'tanya-hukum-admin';

    public function label(): string
    {
        return match ($this) {
            self::Superadmin => 'Superadmin',
            self::ManagingEditor => 'Managing Editor',
            self::PondokAdmin => 'Pondok Web Admin',
            self::MahadAdmin => "Ma'had Aly Web Admin",
            self::SocialMediaManager => 'Social Media Manager',
            self::TanyaHukumAdmin => 'Tanya Hukum Administrator',
        };
    }

    /**
     * The only site this role may act on, or null when it spans all sites.
     */
    public function site(): ?SiteKey
    {
        return match ($this) {
            self::PondokAdmin => SiteKey::Pondok,
            self::MahadAdmin, self::TanyaHukumAdmin => SiteKey::Mahad,
            default => null,
        };
    }

    /**
     * Permission names granted to this role.
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Superadmin => Permissions::all(),
            self::ManagingEditor => [
                ...Permissions::content(Permissions::CONTENT_MODULES),
                ...Permissions::system('media'),
                ...Permissions::system('menus'),
                ...Permissions::system('siakad_articles'),
                ...Permissions::system('audit_logs'),
            ],
            self::PondokAdmin => [
                ...Permissions::content(config('cms.sites.pondok.modules'), except: ['publish']),
                ...Permissions::system('media'),
                ...Permissions::system('menus', ['view']),
            ],
            self::MahadAdmin => [
                ...Permissions::content(config('cms.sites.mahad.modules'), except: ['publish']),
                ...Permissions::system('media'),
                ...Permissions::system('menus', ['view']),
                ...Permissions::system('siakad_articles', ['view', 'review']),
            ],
            self::SocialMediaManager => [
                ...Permissions::system('social_accounts'),
                ...Permissions::system('social_posts'),
                ...Permissions::system('media', ['view', 'create']),
            ],
            self::TanyaHukumAdmin => [
                ...Permissions::system('legal_questions'),
                ...Permissions::system('integration_logs'),
            ],
        };
    }
}
