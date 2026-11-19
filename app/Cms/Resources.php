<?php

namespace App\Cms;

use App\Cms\Resources\Achievements;
use App\Cms\Resources\EducationUnits;
use App\Cms\Resources\Extracurriculars;
use App\Cms\Resources\Facilities;
use App\Cms\Resources\Journals;
use App\Cms\Resources\Lecturers;
use App\Cms\Resources\Organizations;
use App\Cms\Resources\Programs;

/**
 * Registry of the simple admin resources.
 */
class Resources
{
    public const CLASSES = [
        Facilities::class,
        Achievements::class,
        Extracurriculars::class,
        EducationUnits::class,
        Programs::class,
        Lecturers::class,
        Journals::class,
        Organizations::class,
    ];

    /**
     * @return array<string, AdminResource>
     */
    public static function all(): array
    {
        $resources = [];

        foreach (self::CLASSES as $class) {
            $resource = new $class;
            $resources[$resource->key()] = $resource;
        }

        return $resources;
    }

    public static function find(string $key): ?AdminResource
    {
        return self::all()[$key] ?? null;
    }
}
