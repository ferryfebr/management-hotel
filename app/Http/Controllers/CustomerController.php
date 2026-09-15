<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $customers = Customer::query()
            ->when($request->search, function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('phone', 'like', "%{$request->search}%")
                    ->orWhere('id_card_number', 'like', "%{$request->search}%");
            })
            ->orderByDesc('visit_count')
            ->paginate(15)
            ->withQueryString();

        return view('customers.index', compact('customers'));
    }

    public function show(Customer $customer): View
    {
        $customer->load([
            'transactions' => fn ($q) => $q->with('room')->latest('check_in_date'),
        ]);

        return view('customers.show', compact('customer'));
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'rating_status' => ['required', 'in:regular,warning,vip,blacklisted'],
            'notes' => ['nullable', 'string'],
        ]);

        $changes = [];

        if ($customer->rating_status !== $data['rating_status']) {
            $changes[] = "status: {$customer->rating_status} → {$data['rating_status']}";
        }

        if (($customer->notes ?? '') !== ($data['notes'] ?? '')) {
            $changes[] = 'catatan diubah';
        }

        $customer->update($data);

        ActivityLog::record(
            'Ubah data pelanggan',
            ActivityLog::CATEGORY_PELANGGAN,
            $customer->name . ($changes ? ' — ' . implode(', ', $changes) : '')
        );

        return back()->with('success', 'Data pelanggan berhasil diperbarui.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $detail = $customer->name . ($customer->phone ? " ({$customer->phone})" : '');

        $customer->delete(); // soft delete

        ActivityLog::record('Hapus pelanggan', ActivityLog::CATEGORY_PELANGGAN, $detail);

        return redirect()->route('customers.index')
            ->with('success', 'Data pelanggan dihapus (masih tersimpan untuk audit).');
    }

    /**
     * Streaming foto KTP lewat route terproteksi middleware auth,
     * bukan URL publik langsung, karena ini data PII sensitif.
     */
    public function idCardPhoto(Customer $customer): StreamedResponse
    {
        abort_unless($customer->id_card_photo, 404);

        return Storage::disk('private')->response($customer->id_card_photo);
    }

    /**
     * Cari pelanggan terdaftar berdasar nama (untuk dropdown autocomplete di form reservasi/check-in).
     */
    public function searchByName(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q'));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $results = Customer::where('name', 'like', "%{$q}%")
            ->orderByDesc('visit_count')
            ->limit(6)
            ->get(['id', 'name', 'phone', 'id_card_number', 'visit_count']);

        return response()->json($results->map(function ($c) {
            return [
                'name' => $c->name,
                'phone' => $c->phone,
                'id_card_number' => $c->id_card_number,
                'visit_count' => $c->visit_count,
            ];
        }));
    }

    /**
     * Cek keberadaan customer dari nomor KTP (untuk notif "pelanggan lama" real-time).
     * Data dinilai cukup: ditemukan/ditemukan + nama + status rating.
     *
     * POST (bukan GET) supaya NIK tidak muncul di URL/access log (security.md §2).
     */
    public function checkByCard(Request $request): JsonResponse
    {
        $idCard = preg_replace('/\D/', '', (string) $request->input('id_card_number'));

        if (strlen($idCard) !== 16) {
            return response()->json(['found' => false, 'valid' => false]);
        }

        $customer = Customer::where('id_card_number', $idCard)->first();

        if (! $customer) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'name' => $customer->name,
            'rating_status' => $customer->rating_status,
            'visit_count' => $customer->visit_count,
        ]);
    }
}