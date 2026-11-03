<?php

namespace App\Support;

/**
 * Registry of permission names, formatted as "{module}.{action}".
 */
class Permissions
{
    /**
     * Site content modules and the actions available on each.
     */
    public const CONTENT_MODULES = [
        'pages', 'posts', 'events', 'galleries', 'faqs', 'admissions', 'leaders',
        'facilities', 'achievements', 'extracurriculars',
        'lecturers', 'programs', 'curricula', 'publications', 'journals', 'organizations',
        'hero_slides',
    ];

    public const CONTENT_ACTIONS = ['view', 'create', 'update', 'delete', 'submit', 'publish'];

    /**
     * System modules and the actions available on each.
     */
    public const SYSTEM_MODULES = [
        'users' => ['view', 'create', 'update', 'delete'],
        'roles' => ['view', 'assign'],
        'sites' => ['view', 'update'],
        'menus' => ['view', 'update'],
        'categories' => ['view', 'create', 'update', 'delete'],
        'media' => ['view', 'create', 'update', 'delete'],
        'audit_logs' => ['view'],
        'integration_logs' => ['view', 'retry'],
        'siakad_articles' => ['view', 'review', 'publish'],
        'legal_questions' => ['view', 'moderate', 'forward', 'publish'],
        'social_accounts' => ['view', 'connect', 'delete'],
        'social_posts' => ['view', 'create', 'update', 'delete', 'publish'],
    ];

    /**
     * Every permission name in the system.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        $names = [];

        foreach (self::CONTENT_MODULES as $module) {
            foreach (self::CONTENT_ACTIONS as $action) {
                $names[] = "{$module}.{$action}";
            }
        }

        foreach (self::SYSTEM_MODULES as $module => $actions) {
            foreach ($actions as $action) {
                $names[] = "{$module}.{$action}";
            }
        }

        return $names;
    }

    /**
     * Permissions for the given content modules, optionally excluding actions.
     *
     * @param  list<string>  $modules
     * @param  list<string>  $except
     * @return list<string>
     */
    public static function content(array $modules, array $except = []): array
    {
        $actions = array_diff(self::CONTENT_ACTIONS, $except);
        $names = [];

        foreach (array_intersect($modules, self::CONTENT_MODULES) as $module) {
            foreach ($actions as $action) {
                $names[] = "{$module}.{$action}";
            }
        }

        return $names;
    }

    /**
     * All permissions of the given system module, or only the listed actions.
     *
     * @param  list<string>|null  $actions
     * @return list<string>
     */
    public static function system(string $module, ?array $actions = null): array
    {
        $actions = array_intersect($actions ?? self::SYSTEM_MODULES[$module], self::SYSTEM_MODULES[$module]);

        return array_values(array_map(fn (string $action) => "{$module}.{$action}", $actions));
    }
}
