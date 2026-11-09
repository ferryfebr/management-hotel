<?php

namespace App\Http\Controllers;

use App\Support\ArchiveBuilder;
use App\Support\RetentionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class CustomerArchiveController extends Controller
{
    public function store(RetentionService $retention): RedirectResponse|StreamedResponse
    {
        $availabilityError = ArchiveBuilder::availabilityError(
            (string) config('retention.archive.disk', 'private'),
            (string) config('retention.archive.path', 'archives')
        );

        if ($availabilityError) {
            return back()->withErrors('Export customer gagal: '.$availabilityError);
        }

        $lock = null;

        try {
            $lock = Cache::lock('customer-export:lock', 300);

            if (! $lock->get()) {
                return back()->withErrors('Export customer sedang berjalan. Coba lagi setelah selesai.');
            }

            $index = $retention->exportCustomerBatch();

            if (! $index) {
                return back()->withErrors('Tidak ada customer baru yang perlu diexport.');
            }

            $disk = Storage::disk((string) config('retention.archive.disk', 'private'));

            if (! $disk->exists($index->file_path)) {
                return back()->withErrors('File arsip customer gagal dibuat.');
            }

            return $disk->download($index->file_path, $index->file_name);
        } catch (Throwable $e) {
            Log::error('Export customer gagal: '.$e->getMessage(), ['exception' => $e]);

            return back()->withErrors('Export customer gagal dibuat. Cek ruang penyimpanan lalu coba lagi.');
        } finally {
            if ($lock) {
                try {
                    $lock->release();
                } catch (Throwable) {
                    // Abaikan.
                }
            }
        }
    }
}
