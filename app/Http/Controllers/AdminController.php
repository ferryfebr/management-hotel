<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ArchiveIndex;
use App\Models\Customer;
use App\Models\RoomLog;
use App\Models\Transaction;
use App\Support\ArchiveBuilder;
use App\Support\RetentionService;
use App\Support\UsageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AdminController extends Controller
{
    public function index(Request $request, UsageService $usage, RetentionService $retention): View
    {
        $snapshot = $usage->snapshot($request->boolean('refresh'));

        $counts = [
            'customers' => Customer::withTrashed()->count(),
            'transactions' => Transaction::count(),
            'room_logs' => RoomLog::count(),
            'activity_logs' => ActivityLog::count(),
        ];

        $limits = config('retention.limits');

        $archives = ArchiveIndex::query()
            ->latest()
            ->limit(10)
            ->get();

        $pendingCustomerExport = $retention->pendingCustomerExportCount();
        $emergency = (bool) Cache::get('retention:emergency', false);

        $archiveError = ArchiveBuilder::availabilityError(
            (string) config('retention.archive.disk', 'private'),
            (string) config('retention.archive.path', 'archives')
        );
        $backupError = ArchiveBuilder::availabilityError(
            (string) config('retention.backup.disk', 'private'),
            (string) config('retention.backup.path', 'backups')
        );

        return view('admin.index', compact(
            'snapshot',
            'counts',
            'limits',
            'archives',
            'pendingCustomerExport',
            'emergency',
            'archiveError',
            'backupError'
        ));
    }

    public function runRetention(RetentionService $retention): RedirectResponse
    {
        $summary = $retention->enforce();

        if (! empty($summary['error'])) {
            return back()->withErrors('Retensi gagal: '.$summary['error']);
        }

        $message = sprintf(
            'Retensi selesai. Transaksi: %d, room log: %d, activity log: %d, customer: %d. Database: %s%%.',
            $summary['transactions'] ?? 0,
            $summary['room_logs'] ?? 0,
            $summary['activity_logs'] ?? 0,
            $summary['customers'] ?? 0,
            number_format((float) ($summary['db_percent'] ?? 0), 2, ',', '.')
        );

        return back()->with('success', $message);
    }

    public function archives(RetentionService $retention): View
    {
        $archives = ArchiveIndex::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(30);

        $pendingCustomerExport = $retention->pendingCustomerExportCount();

        return view('admin.archives', compact('archives', 'pendingCustomerExport'));
    }

    public function download(ArchiveIndex $archive): StreamedResponse
    {
        abort_unless($archive->isReady(), 404);

        $disk = Storage::disk((string) config('retention.archive.disk', 'private'));
        abort_unless($disk->exists($archive->file_path), 404);

        return $disk->download($archive->file_path, $archive->file_name);
    }

    public function backup(RetentionService $retention): RedirectResponse
    {
        $availabilityError = ArchiveBuilder::availabilityError(
            (string) config('retention.backup.disk', 'private'),
            (string) config('retention.backup.path', 'backups')
        );

        if ($availabilityError) {
            return back()->withErrors('Backup database gagal: '.$availabilityError);
        }

        try {
            $index = $retention->createBackup();
        } catch (Throwable $e) {
            Log::error('Backup database gagal: '.$e->getMessage(), ['exception' => $e]);

            return back()->withErrors('Backup database gagal dibuat. Cek ruang penyimpanan lalu coba lagi.');
        }

        return redirect()
            ->route('admin.archives')
            ->with('success', 'Backup database berhasil dibuat: '.$index->file_name);
    }
}
