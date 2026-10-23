<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Room;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

class DashboardReportTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-23 12:00:00');
        $this->owner = $this->makeUser(User::ROLE_OWNER);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_computes_room_and_transaction_statistics(): void
    {
        $available = $this->makeRoom(null, '101', Room::STATUS_AVAILABLE);
        $occupied = $this->makeRoom(null, '102', Room::STATUS_OCCUPIED);
        $dirty = $this->makeRoom(null, '103', Room::STATUS_DIRTY);
        $this->makeRoom(null, '104', Room::STATUS_MAINTENANCE);

        $this->makeTransaction([
            'room' => $available,
            'status' => Transaction::STATUS_RESERVED,
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDays(2)->toDateString(),
        ]);
        $this->makeTransaction([
            'room' => $occupied,
            'status' => Transaction::STATUS_CHECKED_IN,
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->toDateString(),
        ]);
        $this->makeTransaction([
            'room' => $dirty,
            'status' => Transaction::STATUS_CHECKED_OUT,
            'check_in_date' => now()->subDays(2)->toDateString(),
            'check_out_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->owner)
            ->get(route('dashboard.index'))
            ->assertOk();

        $this->assertSame([
            'total' => 4,
            'available' => 1,
            'occupied' => 1,
            'dirty' => 1,
            'maintenance' => 1,
        ], $response->viewData('roomStats'));
        $this->assertSame(2, $response->viewData('todayCheckIns'));
        $this->assertSame(1, $response->viewData('todayCheckOuts'));
        $this->assertSame(3, $response->viewData('recentTransactions')->count());
    }

    public function test_dashboard_revenue_uses_only_dp_and_settlement_in_selected_period(): void
    {
        $transaction = $this->makeTransaction();

        $this->makePayment($transaction, 100000, Payment::TYPE_DP, $this->owner, [
            'paid_at' => now(),
        ]);
        $this->makePayment($transaction, 50000, Payment::TYPE_PELUNASAN, $this->owner, [
            'paid_at' => now(),
        ]);
        $this->makePayment($transaction, 70000, Payment::TYPE_CHARGE, $this->owner, [
            'paid_at' => now(),
        ]);
        $this->makePayment($transaction, 30000, Payment::TYPE_REFUND, $this->owner, [
            'paid_at' => now(),
        ]);
        $this->makePayment($transaction, 90000, Payment::TYPE_DP, $this->owner, [
            'paid_at' => now()->subMonths(2),
        ]);

        $today = $this->actingAs($this->owner)
            ->get(route('dashboard.index', ['preset' => 'today']))
            ->assertOk();
        $this->assertSame('today', $today->viewData('preset'));
        $this->assertSame(150000.0, (float) $today->viewData('revenue'));

        $this->actingAs($this->owner)
            ->get(route('dashboard.index', ['preset' => 'week']))
            ->assertViewHas('revenue', fn ($revenue) => (float) $revenue === 150000.0);

        $this->actingAs($this->owner)
            ->get(route('dashboard.index', ['preset' => 'month']))
            ->assertViewHas('revenue', fn ($revenue) => (float) $revenue === 150000.0);
    }

    public function test_dashboard_custom_date_range_overrides_preset_and_uses_full_days(): void
    {
        $transaction = $this->makeTransaction();
        $this->makePayment($transaction, 250000, Payment::TYPE_DP, $this->owner, [
            'paid_at' => now()->subDays(3)->setTime(23, 30),
        ]);

        $response = $this->actingAs($this->owner)
            ->get(route('dashboard.index', [
                'preset' => 'today',
                'start_date' => now()->subDays(3)->toDateString(),
                'end_date' => now()->subDays(3)->toDateString(),
            ]))
            ->assertOk();

        $this->assertSame('custom', $response->viewData('preset'));
        $this->assertSame(250000.0, (float) $response->viewData('revenue'));
        $this->assertSame(now()->subDays(3)->startOfDay()->toDateTimeString(), $response->viewData('revenueStart')->toDateTimeString());
        $this->assertSame(now()->subDays(3)->endOfDay()->toDateTimeString(), $response->viewData('revenueEnd')->toDateTimeString());
    }

    public function test_dashboard_with_only_one_custom_date_falls_back_to_preset(): void
    {
        $response = $this->actingAs($this->owner)
            ->get(route('dashboard.index', [
                'start_date' => now()->subDay()->toDateString(),
            ]))
            ->assertOk();

        $this->assertSame('month', $response->viewData('preset'));
        $this->assertSame(now()->startOfMonth()->toDateTimeString(), $response->viewData('revenueStart')->toDateTimeString());
    }

    public function test_reports_include_archived_monthly_revenue(): void
    {
        \App\Models\MonthlyRevenue::create([
            'month' => now()->format('Y-m'),
            'revenue' => 500000,
            'check_outs' => 2,
            'room_nights' => 4,
        ]);

        $response = $this->actingAs($this->owner)
            ->get(route('reports.index'))
            ->assertOk();

        $this->assertSame(500000.0, (float) $response->viewData('totalRevenue'));
        $this->assertSame(2, $response->viewData('checkOuts'));
        $this->assertSame(4, $response->viewData('totalRoomNights'));
    }

    public function test_reports_default_period_and_custom_filter(): void
    {
        $room = $this->makeRoom();
        $checkedOut = $this->makeTransaction([
            'room' => $room,
            'status' => Transaction::STATUS_CHECKED_OUT,
            'check_in_date' => now()->subDays(2)->toDateString(),
            'check_out_date' => now()->toDateString(),
            'total_days' => 2,
            'final_price' => 300000,
        ]);
        $this->makePayment($checkedOut, 300000, Payment::TYPE_PELUNASAN, $this->owner, [
            'paid_at' => now()->startOfDay(),
        ]);
        $outside = $this->makeTransaction([
            'status' => Transaction::STATUS_CHECKED_OUT,
            'check_in_date' => now()->subMonths(2)->toDateString(),
            'check_out_date' => now()->subMonths(2)->toDateString(),
            'total_days' => 1,
        ]);
        $this->makePayment($outside, 100000, Payment::TYPE_DP, $this->owner, [
            'paid_at' => now()->subMonths(2)->setTime(23, 30),
        ]);

        $default = $this->actingAs($this->owner)->get(route('reports.index'))->assertOk();
        $this->assertSame(300000.0, (float) $default->viewData('totalRevenue'));
        $this->assertSame(1, $default->viewData('checkOuts'));
        $this->assertSame(2, (int) $default->viewData('totalRoomNights'));
        $this->assertSame(1, $default->viewData('payments')->count());

        $custom = $this->actingAs($this->owner)->get(route('reports.index', [
            'start_date' => now()->subMonths(2)->toDateString(),
            'end_date' => now()->subMonths(2)->toDateString(),
        ]))->assertOk();
        $this->assertSame(100000.0, (float) $custom->viewData('totalRevenue'));
        $this->assertSame(1, $custom->viewData('checkOuts'));
    }
}
