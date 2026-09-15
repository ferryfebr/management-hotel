<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Room;
use App\Models\Transaction;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $roomStats = [
            'total' => Room::count(),
            'available' => Room::where('status', Room::STATUS_AVAILABLE)->count(),
            'occupied' => Room::where('status', Room::STATUS_OCCUPIED)->count(),
            'dirty' => Room::where('status', Room::STATUS_DIRTY)->count(),
            'maintenance' => Room::where('status', Room::STATUS_MAINTENANCE)->count(),
        ];

        $todayCheckIns = Transaction::whereDate('check_in_date', today())
            ->whereIn('status', [Transaction::STATUS_RESERVED, Transaction::STATUS_CHECKED_IN])
            ->count();

        $todayCheckOuts = Transaction::whereDate('check_out_date', today())
            ->where('status', Transaction::STATUS_CHECKED_IN)
            ->count();

        $revenueThisMonth = Payment::whereIn('type', ['dp', 'pelunasan'])
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');

        $recentTransactions = Transaction::with(['customer', 'room'])
            ->latest()
            ->limit(10)
            ->get();

        return view('dashboard.index', compact(
            'roomStats',
            'todayCheckIns',
            'todayCheckOuts',
            'revenueThisMonth',
            'recentTransactions'
        ));
    }
}