<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Human-readable, translated names for codes shown in the admin: permission
 * modules and actions, audit events and content types (morph aliases).
 * Written as literal __() calls so lang/id.json can be checked for them.
 */
class AdminLabels
{
    public static function module(string $module): string
    {
        return match ($module) {
            'pages' => __('Pages'),
            'posts' => __('Posts'),
            'events' => __('Agenda'),
            'galleries' => __('Galleries'),
            'faqs' => __('FAQ'),
            'admissions' => __('Admissions'),
            'leaders' => __('Leadership'),
            'facilities' => __('Facilities'),
            'achievements' => __('Achievements'),
            'extracurriculars' => __('Extracurriculars'),
            'lecturers' => __('Lecturers'),
            'programs' => __('Programs'),
            'curricula' => __('Curriculum'),
            'publications' => __('Publications'),
            'journals' => __('Journals'),
            'organizations' => __('Organizations'),
            'hero_slides' => __('Homepage slider'),
            'users' => __('Users'),
            'roles' => __('Roles'),
            'sites' => __('Site settings'),
            'menus' => __('Menus'),
            'categories' => __('Categories'),
            'media' => __('Media library'),
            'audit_logs' => __('Audit logs'),
            'integration_logs' => __('Integration log'),
            'siakad_articles' => __('SIAKAD articles'),
            'legal_questions' => __('Tanya Hukum'),
            'social_accounts' => __('Social accounts'),
            'social_posts' => __('Social posts'),
            default => Str::headline($module),
        };
    }

    public static function action(string $action): string
    {
        return match ($action) {
            'view' => __('view'),
            'create' => __('create'),
            'update' => __('edit'),
            'delete' => __('delete'),
            'submit' => __('submit for review'),
            'publish' => __('publish'),
            'assign' => __('assign'),
            'retry' => __('retry'),
            'review' => __('review'),
            'moderate' => __('moderate'),
            'forward' => __('forward'),
            'connect' => __('connect'),
            default => $action,
        };
    }

    /**
     * Verb for an audit event, e.g. "created" or "workflow.published".
     */
    public static function event(string $event): string
    {
        return match (Str::afterLast($event, '.')) {
            'created' => __('created'),
            'updated' => __('updated'),
            'deleted' => __('deleted'),
            'login' => __('logged in'),
            'logout' => __('logged out'),
            'failed' => __('failed'),
            'hidden' => __('hid'),
            'shown' => __('showed'),
            'submitted' => __('submitted'),
            'approved' => __('approved'),
            'rejected' => __('rejected'),
            'published' => __('published'),
            'unpublished' => __('unpublished'),
            'scheduled' => __('scheduled'),
            'rescheduled' => __('rescheduled'),
            'archived' => __('archived'),
            'restored' => __('restored'),
            'exported' => __('exported'),
            'connected' => __('connected'),
            'dispatched' => __('sent'),
            'document_uploaded' => __('uploaded a document for'),
            'document_downloaded' => __('downloaded a document of'),
            'forward_queued' => __('forwarded'),
            'forward_failed' => __('failed to forward'),
            'data_deleted' => __('deleted data of'),
            'correction_applied' => __('applied a correction to'),
            'correction_dismissed' => __('dismissed a correction for'),
            default => $event,
        };
    }

    /**
     * Name of a content type stored as a morph alias, e.g. "media_asset".
     */
    public static function type(string $type): string
    {
        return match ($type) {
            'user' => __('User'),
            'site' => __('Site'),
            'post' => __('Post'),
            'page' => __('Page'),
            'category' => __('Category'),
            'media_asset' => __('Media file'),
            'menu', 'menu_item' => __('Menu link'),
            'seo_meta' => __('SEO'),
            'event' => __('Event'),
            'gallery', 'gallery_item' => __('Gallery'),
            'faq' => __('Question'),
            'leader' => __('Leadership'),
            'admission_period' => __('Admission period'),
            'admission_registration' => __('Registration'),
            'admission_requirement', 'admission_document' => __('Document'),
            'facility' => __('Facility'),
            'extracurricular' => __('Extracurricular'),
            'achievement' => __('Achievement'),
            'lecturer' => __('Lecturer'),
            'program' => __('Program'),
            'course' => __('Course'),
            'journal' => __('Journal'),
            'organization' => __('Organization'),
            'publication' => __('Publication'),
            'integration_log' => __('Integration log'),
            'siakad_article' => __('SIAKAD article'),
            'legal_question' => __('Tanya Hukum question'),
            'social_account' => __('Account'),
            'social_post', 'social_post_target' => __('Social post'),
            'hero_slide' => __('Slide'),
            default => class_basename($type),
        };
    }
}
