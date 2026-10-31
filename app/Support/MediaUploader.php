<?php

namespace App\Support;

use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Store an uploaded file as a media library asset.
 */
class MediaUploader
{
    public function __construct(protected CurrentSite $currentSite) {}

    /**
     * Assets belong to the current site, or are shared when $shared is true
     * (or no site is selected, which only all-site users can do).
     */
    public function store(UploadedFile $file, User $uploader, bool $shared = false): MediaAsset
    {
        $asset = MediaAsset::query()->create([
            'site_id' => $shared ? null : $this->currentSite->id(),
            'title' => Str::of($file->getClientOriginalName())->beforeLast('.')->replace(['-', '_'], ' ')->title()->limit(200)->toString(),
            'uploaded_by' => $uploader->id,
        ]);

        $asset->addMedia($file)
            ->usingFileName(Str::uuid().'.'.strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin'))
            ->toMediaCollection('file');

        return $asset;
    }
}
