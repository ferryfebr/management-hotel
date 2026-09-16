<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Payment;
use App\Models\RoomLog;
use App\Models\RoomTransfer;
use App\Models\StayExtension;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    /**
     * Daftar akun resepsionis & room keeper (owner-only).
     */
    public function index(): View
    {
        $users = User::whereIn('role', [User::ROLE_RESEPSIONIS, User::ROLE_ROOM_KEEPER])
            ->orderBy('is_active', 'desc')
            ->orderBy('role')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('users.index', compact('users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'role' => ['required', 'in:resepsionis,room_keeper'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'email.unique' => 'Email sudah dipakai akun lain.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'password.min' => 'Kata sandi minimal 6 karakter.',
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'is_active' => true,
        ]);

        ActivityLog::record(
            'Tambah akun',
            ActivityLog::CATEGORY_AKUN,
            "{$data['name']} (" . User::roleLabel($data['role']) . ')'
        );

        return redirect()->route('users.index')
            ->with('success', 'Akun ' . User::roleLabel($data['role']) . ' berhasil ditambahkan.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        // Owner tidak boleh diubah lewat sini (hanya resepsionis/room_keeper).
        abort_unless(in_array($user->role, [User::ROLE_RESEPSIONIS, User::ROLE_ROOM_KEEPER]), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email,' . $user->id],
            'role' => ['required', 'in:resepsionis,room_keeper'],
            'is_active' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
        ], [
            'email.unique' => 'Email sudah dipakai akun lain.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'password.min' => 'Kata sandi minimal 6 karakter.',
        ]);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'is_active' => $request->boolean('is_active'),
        ]);

        if (! empty($data['password'])) {
            $user->update(['password' => Hash::make($data['password'])]);
        }

        ActivityLog::record(
            'Ubah akun',
            ActivityLog::CATEGORY_AKUN,
            "{$user->name} (" . User::roleLabel($user->role) . ')' . ($request->boolean('is_active') ? '' : ' — dinonaktifkan')
        );

        return redirect()->route('users.index')
            ->with('success', 'Akun berhasil diperbarui.');
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_unless(in_array($user->role, [User::ROLE_RESEPSIONIS, User::ROLE_ROOM_KEEPER]), 403);

        // Gunakan nonaktifkan (soft-deactivate), bukan hapus permanen,
        // agar histori aktivitas akun tetap terbaca untuk audit.
        $user->update(['is_active' => false]);

        ActivityLog::record(
            'Nonaktifkan akun',
            ActivityLog::CATEGORY_AKUN,
            "{$user->name} (" . User::roleLabel($user->role) . ')'
        );

        return redirect()->route('users.index')
            ->with('success', 'Akun dinonaktifkan. Histori aktivitasnya tetap tersimpan.');
    }

    /**
     * Ringkasan aktivitas semua pekerja (resepsionis & room keeper), owner-only.
     * Diambil dari data yang SUDAH ADA di tabel masing-masing (tanpa tabel log baru),
     * sehingga tidak ada duplikasi/penumpukan.
     */
    public function activities(Request $request): View
    {
        $userId = $request->integer('user');

        // Filter nama user untuk tampilan dropdown; null => semua.
        // Owner ikut ditampilkan agar aktivitasnya terlihat sinkron.
        $userPool = User::orderBy('name')->get(['id', 'name', 'role']);

        $rows = $this->collectActivities($userId);

        // Paginasi manual karena gabungan beberapa tabel.
        $perPage = 25;
        $page = max((int) $request->get('page', 1), 1);
        $slice = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        $activities = new LengthAwarePaginator(
            $slice,
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('users.activities', compact('activities', 'userPool', 'userId'));
    }

    /**
     * Bangun satu daftar aktivitas gabungan: aksi dari tabel domain
     * (transaksi, pembayaran, perpanjangan, transfer, log kamar) + audit log
     * manual (kamar/jenis kamar/akun), diurutkan terbaru. Semua role termasuk
     * owner disertakan agar histori tetap sinkron.
     */
    private function collectActivities(?int $userId): Collection
    {
        $items = collect();
        $limit = 200;

        // 1) Transaksi (reservasi / check-in / check-out / dsb)
        Transaction::query()
            ->with(['user:id,name,role', 'customer:id,name', 'room:id,room_number'])
            ->whereNotNull('user_id')
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->latest()
            ->limit($limit)
            ->get()
            ->each(function (Transaction $t) use (&$items) {
                $action = match ($t->status) {
                    Transaction::STATUS_RESERVED => 'Membuat reservasi',
                    Transaction::STATUS_CHECKED_IN => 'Check-in tamu',
                    Transaction::STATUS_CHECKED_OUT => 'Check-out tamu',
                    Transaction::STATUS_CANCELLED => 'Membatalkan reservasi',
                    Transaction::STATUS_NO_SHOW => 'Menandai tidak datang',
                    default => 'Memproses transaksi',
                };
                $items->push($this->row(
                    $t->user,
                    $action,
                    ($t->customer?->name ?? '-') . ($t->room?->room_number ? " — Kamar {$t->room->room_number}" : ''),
                    $t->created_at,
                    'transaksi'
                ));
            });

        // 2) Pembayaran
        Payment::query()
            ->with(['receiver:id,name,role', 'transaction:id,code,customer_id', 'transaction.customer:id,name'])
            ->whereNotNull('received_by')
            ->when($userId, fn ($q) => $q->where('received_by', $userId))
            ->latest()
            ->limit($limit)
            ->get()
            ->each(function (Payment $p) use (&$items) {
                $label = match ($p->type) {
                    'pelunasan' => 'Menerima pelunasan',
                    'refund' => 'Memproses refund',
                    'charge' => 'Menambah charge',
                    default => 'Mencatat pembayaran (DP)',
                };
                $items->push($this->row(
                    $p->receiver,
                    $label,
                    ($p->transaction?->customer?->name ?? '-') . ' — Rp ' . number_format((float) $p->amount, 0, ',', '.'),
                    $p->paid_at,
                    'pembayaran'
                ));
            });

        // 3) Perpanjangan menginap
        StayExtension::query()
            ->with(['extendedBy:id,name,role', 'transaction:id,code,customer_id', 'transaction.customer:id,name'])
            ->whereNotNull('extended_by')
            ->when($userId, fn ($q) => $q->where('extended_by', $userId))
            ->latest()
            ->limit($limit)
            ->get()
            ->each(function (StayExtension $e) use (&$items) {
                $items->push($this->row(
                    $e->extendedBy,
                    'Perpanjang masa inap',
                    ($e->transaction?->customer?->name ?? '-') . " (+{$e->additional_days} malam)",
                    $e->created_at,
                    'perpanjangan'
                ));
            });

        // 4) Transfer kamar
        RoomTransfer::query()
            ->with(['transferredBy:id,name,role', 'fromRoom:id,room_number', 'toRoom:id,room_number', 'transaction:id,code,customer_id', 'transaction.customer:id,name'])
            ->whereNotNull('transferred_by')
            ->when($userId, fn ($q) => $q->where('transferred_by', $userId))
            ->latest()
            ->limit($limit)
            ->get()
            ->each(function (RoomTransfer $r) use (&$items) {
                $items->push($this->row(
                    $r->transferredBy,
                    'Pindah kamar',
                    ($r->transaction?->customer?->name ?? '-') . ": {$r->fromRoom?->room_number} → {$r->toRoom?->room_number}",
                    $r->transferred_at,
                    'transfer'
                ));
            });

        // 5) Log status kamar (room keeper)
        RoomLog::query()
            ->with(['user:id,name,role', 'room:id,room_number'])
            ->whereNotNull('user_id')
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->latest()
            ->limit($limit)
            ->get()
            ->each(function (RoomLog $log) use (&$items) {
                $label = match ($log->status_reported) {
                    'clean' => 'Lapor kamar bersih',
                    'dirty' => 'Lapor kamar kotor',
                    'maintenance' => 'Lapor kamar rusak',
                    default => 'Update status kamar',
                };
                $items->push($this->row(
                    $log->user,
                    $label,
                    "Kamar {$log->room?->room_number}" . ($log->notes ? ' — ' . $log->notes : ''),
                    $log->created_at,
                    'kamar',
                    $log->proof_photo ? route('room-logs.photo', $log) : null
                ));
            });

        // 6) Audit log manual (tambah/ubah kamar, jenis kamar, akun, dsb)
        ActivityLog::query()
            ->with('user:id,name,role')
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->latest()
            ->limit($limit)
            ->get()
            ->each(function (ActivityLog $log) use (&$items) {
                $items->push($this->row(
                    $log->user,
                    $log->action,
                    $log->description ?? '',
                    $log->created_at,
                    $log->category
                ));
            });

        $items = $items->sortByDesc('time')->values();

        return $this->applyRoleVisibility($items);
    }

    /**
     * Resepsionis hanya boleh melihat aktivitas operasional. Aksi internal
     * owner (kelola jenis kamar, kelola akun, tambah/ubah/hapus kamar)
     * disembunyikan. Owner melihat semuanya.
     */
    private function applyRoleVisibility(Collection $items): Collection
    {
        if (auth()->user()?->isOwner()) {
            return $items;
        }

        $hiddenCategories = [
            ActivityLog::CATEGORY_JENIS_KAMAR,
            ActivityLog::CATEGORY_AKUN,
        ];

        $hiddenRoomActions = ['Tambah kamar', 'Ubah data kamar', 'Hapus kamar'];

        return $items->reject(function ($a) use ($hiddenCategories, $hiddenRoomActions) {
            if (in_array($a['type'], $hiddenCategories, true)) {
                return true;
            }

            return $a['type'] === ActivityLog::CATEGORY_KAMAR
                && in_array($a['action'], $hiddenRoomActions, true);
        })->values();
    }

    private function row(?User $user, string $action, string $detail, $time, string $type, ?string $photo = null): array
    {
        return [
            'user' => $user,
            'action' => $action,
            'detail' => $detail,
            'time' => $time,
            'type' => $type,
            'photo' => $photo,
        ];
    }
}