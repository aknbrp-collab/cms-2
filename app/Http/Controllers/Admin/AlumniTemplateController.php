<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Empty CSV template for the alumni bulk add (Excel-friendly, UTF-8 with BOM).
 */
class AlumniTemplateController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Nama', 'Tahun Lulus', 'Kota'], ';', '"', '');
            fputcsv($out, ['Ahmad Fauzi', '2018', 'Kendal'], ';', '"', '');
            fclose($out);
        }, 'template-alumni.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
