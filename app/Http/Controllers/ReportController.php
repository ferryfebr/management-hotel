<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $startDate = $request->date('start_date') ?? now()->startOfMonth();
        $endDate = $request->date('end_date') ?? now()->endOfMonth();

        $payments = Payment::with(['transaction.customer', 'transaction.room'])
            ->whereIn('type', ['dp', 'pelunasan'])
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->orderBy('paid_at')
            ->get();

        $totalRevenue = $payments->sum('amount');

        $checkOuts = Transaction::where('status', Transaction::STATUS_CHECKED_OUT)
            ->whereBetween('check_out_date', [$startDate, $endDate])
            ->count();

        $totalRoomNights = Transaction::where('status', Transaction::STATUS_CHECKED_OUT)
            ->whereBetween('check_out_date', [$startDate, $endDate])
            ->sum('total_days');

        return view('reports.index', compact(
            'payments',
            'totalRevenue',
            'checkOuts',
            'totalRoomNights',
            'startDate',
            'endDate'
        ));
    }
}