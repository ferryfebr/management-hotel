<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Room;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
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

        $preset = $request->query('preset', 'month');

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $preset = 'custom';
            $revenueStart = \Illuminate\Support\Carbon::parse($request->query('start_date'))->startOfDay();
            $revenueEnd = \Illuminate\Support\Carbon::parse($request->query('end_date'))->endOfDay();
        } else {
            [$revenueStart, $revenueEnd] = match ($preset) {
                'today' => [now()->startOfDay(), now()->endOfDay()],
                'week' => [now()->startOfWeek(), now()->endOfWeek()],
                default => [now()->startOfMonth(), now()->endOfMonth()],
            };
        }

        $revenue = Payment::whereIn('type', ['dp', 'pelunasan'])
            ->whereBetween('paid_at', [$revenueStart, $revenueEnd])
            ->sum('amount');

        $recentTransactions = Transaction::with(['customer', 'room'])
            ->latest()
            ->limit(10)
            ->get();

        return view('dashboard.index', compact(
            'roomStats',
            'todayCheckIns',
            'todayCheckOuts',
            'revenue',
            'preset',
            'revenueStart',
            'revenueEnd',
            'recentTransactions'
        ));
    }
}