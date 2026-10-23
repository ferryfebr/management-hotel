<?php

namespace App\Http\Controllers;

use App\Support\RetentionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerArchiveController extends Controller
{
    public function store(RetentionService $retention): RedirectResponse|StreamedResponse
    {
        $index = $retention->exportCustomerBatch();

        if (! $index) {
            return back()->withErrors('Tidak ada customer baru yang perlu diexport.');
        }

        $disk = Storage::disk((string) config('retention.archive.disk', 'private'));

        if (! $disk->exists($index->file_path)) {
            return back()->withErrors('File arsip customer gagal dibuat.');
        }

        return $disk->download($index->file_path, $index->file_name);
    }
}
