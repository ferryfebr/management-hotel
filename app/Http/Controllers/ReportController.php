<?php

namespace App\Http\Controllers;

use App\Models\MonthlyRevenue;
use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $startDate = ($request->date('start_date') ?? now()->startOfMonth())->startOfDay();
        $endDate = ($request->date('end_date') ?? now()->endOfMonth())->endOfDay();

        $payments = Payment::with(['transaction.customer', 'transaction.room'])
            ->whereIn('type', ['dp', 'pelunasan'])
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->orderBy('paid_at')
            ->get();

        // Data yang sudah diarsipkan tetap masuk total lewat ringkasan bulanan.
        $archived = MonthlyRevenue::query()
            ->whereBetween('month', [$startDate->format('Y-m'), $endDate->format('Y-m')])
            ->get();

        $totalRevenue = (float) $payments->sum('amount') + (float) $archived->sum('revenue');

        $checkOuts = Transaction::where('status', Transaction::STATUS_CHECKED_OUT)
            ->whereBetween('check_out_date', [$startDate, $endDate])
            ->count() + (int) $archived->sum('check_outs');

        $totalRoomNights = (int) Transaction::where('status', Transaction::STATUS_CHECKED_OUT)
            ->whereBetween('check_out_date', [$startDate, $endDate])
            ->sum('total_days') + (int) $archived->sum('room_nights');

        return view('reports.index', compact(
            'payments',
            'totalRevenue',
            'checkOuts',
            'totalRoomNights',
            'startDate',
            'endDate',
            'archived'
        ));
    }
}
