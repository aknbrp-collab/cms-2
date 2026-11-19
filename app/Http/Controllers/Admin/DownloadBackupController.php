<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Backups;
use App\Support\Audit;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Download a backup archive from the admin Data page.
 */
class DownloadBackupController extends Controller
{
    public function __invoke(Request $request, Backups $backups): StreamedResponse
    {
        $backup = $backups->find($request->string('disk')->toString(), $request->string('path')->toString()) ?? abort(404);

        Audit::record('data.backup_downloaded', meta: ['disk' => $request->string('disk')->toString(), 'file' => basename($backup->path())]);

        return response()->streamDownload(function () use ($backup) {
            $stream = $backup->stream();
            fpassthru($stream);
            fclose($stream);
        }, basename($backup->path()), [
            'Content-Type' => 'application/zip',
            'Content-Length' => (string) (int) $backup->sizeInBytes(),
        ]);
    }
}
