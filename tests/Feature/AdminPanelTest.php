<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ArchiveIndex;
use App\Models\Customer;
use App\Models\MonthlyRevenue;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomLog;
use App\Models\RoomTransfer;
use App\Models\StayExtension;
use App\Models\Transaction;
use App\Models\User;
use App\Support\RetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;
use ZipArchive;

class AdminPanelTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
    }

    public function test_super_admin_is_redirected_to_admin_panel_and_can_access_owner_pages(): void
    {
        $superAdmin = $this->makeUser(User::ROLE_SUPER_ADMIN);

        $this->post(route('login.attempt'), [
            'email' => $superAdmin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->actingAs($superAdmin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($superAdmin)->get(route('dashboard.index'))->assertOk();
        $this->actingAs($superAdmin)->get(route('reports.index'))->assertOk();
        $this->actingAs($superAdmin)->get(route('users.index'))->assertOk();
        $this->actingAs($superAdmin)->get(route('customers.index'))->assertOk();
    }

    public function test_owner_and_staff_cannot_access_super_admin_panel(): void
    {
        $owner = $this->makeUser(User::ROLE_OWNER);
        $frontOffice = $this->makeUser(User::ROLE_RESEPSIONIS);

        $this->actingAs($owner)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($owner)->get(route('admin.archives'))->assertForbidden();
        $this->actingAs($frontOffice)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_panel_shows_quota_and_retention_limits(): void
    {
        $superAdmin = $this->makeUser(User::ROLE_SUPER_ADMIN);

        $this->actingAs($superAdmin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Kuota Hosting')
            ->assertSee('Database Aktif')
            ->assertSee('Batas Data Aktif')
            ->assertSee('Panel Admin');
    }

    public function test_retention_archives_oldest_final_transaction_and_keeps_revenue_summary(): void
    {
        config([
            'retention.limits.transactions' => 2,
            'retention.batch.transactions' => 1,
            'retention.limits.customers' => 100,
            'retention.limits.room_logs' => 100,
            'retention.limits.activity_logs' => 100,
        ]);

        $archived = $this->makeTransaction([
            'status' => Transaction::STATUS_CHECKED_OUT,
            'check_in_date' => now()->subDays(2)->toDateString(),
            'check_out_date' => now()->toDateString(),
            'total_days' => 2,
            'final_price' => 200000,
        ]);
        $this->makePayment($archived, 200000, Payment::TYPE_PELUNASAN);
        $this->makeTransaction(['status' => Transaction::STATUS_CHECKED_OUT]);
        $this->makeTransaction(['status' => Transaction::STATUS_CHECKED_OUT]);

        $summary = app(RetentionService::class)->enforce();

        $this->assertSame(1, $summary['transactions']);
        $this->assertSame(2, Transaction::count());
        $this->assertDatabaseMissing('transactions', ['id' => $archived->id]);
        $this->assertDatabaseMissing('payments', ['transaction_id' => $archived->id]);

        $archive = ArchiveIndex::where('category', ArchiveIndex::CATEGORY_TRANSACTIONS)->firstOrFail();
        Storage::disk('private')->assertExists($archive->file_path);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('private')->path($archive->file_path)) === true);
        $csv = $zip->getFromName('transactions.csv');
        $zip->close();

        $this->assertNotFalse($csv);
        $this->assertStringContainsString($archived->code, $csv);

        $revenue = MonthlyRevenue::where('month', now()->format('Y-m'))->firstOrFail();
        $this->assertSame(200000.0, (float) $revenue->revenue);
        $this->assertSame(2, $revenue->room_nights);
    }

    public function test_transaction_archive_contains_related_payment_extension_and_transfer(): void
    {
        config([
            'retention.limits.transactions' => 0,
            'retention.batch.transactions' => 1,
            'retention.limits.customers' => 100,
            'retention.limits.room_logs' => 100,
            'retention.limits.activity_logs' => 100,
        ]);

        $room = $this->makeRoom(null, 'A1', Room::STATUS_DIRTY);
        $toRoom = $this->makeRoom(null, 'A2');
        $transaction = $this->makeTransaction([
            'room' => $room,
            'status' => Transaction::STATUS_CHECKED_OUT,
            'check_in_date' => now()->subDays(2)->toDateString(),
            'check_out_date' => now()->toDateString(),
            'total_days' => 2,
            'final_price' => 200000,
        ]);
        $this->makePayment($transaction, 200000, Payment::TYPE_PELUNASAN);
        StayExtension::create([
            'transaction_id' => $transaction->id,
            'old_checkout_date' => now()->subDay()->toDateString(),
            'new_checkout_date' => now()->toDateString(),
            'additional_days' => 1,
            'additional_price' => 100000,
            'extended_by' => $transaction->user_id,
        ]);
        RoomTransfer::create([
            'transaction_id' => $transaction->id,
            'from_room_id' => $room->id,
            'to_room_id' => $toRoom->id,
            'reason' => 'AC rusak',
            'transferred_by' => $transaction->user_id,
            'transferred_at' => now(),
        ]);

        app(RetentionService::class)->enforce();

        $archive = ArchiveIndex::where('category', ArchiveIndex::CATEGORY_TRANSACTIONS)->firstOrFail();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('private')->path($archive->file_path)) === true);

        $this->assertStringContainsString($transaction->code, (string) $zip->getFromName('payments.csv'));
        $this->assertStringContainsString($transaction->code, (string) $zip->getFromName('stay_extensions.csv'));
        $this->assertStringContainsString('AC rusak', (string) $zip->getFromName('room_transfers.csv'));
        $zip->close();
    }

    public function test_room_log_archive_without_existing_photo_file_does_not_fail(): void
    {
        config([
            'retention.limits.transactions' => 100,
            'retention.limits.customers' => 100,
            'retention.limits.room_logs' => 0,
            'retention.batch.room_logs' => 1,
            'retention.limits.activity_logs' => 100,
        ]);

        $room = $this->makeRoom();
        $keeper = $this->makeUser(User::ROLE_ROOM_KEEPER);
        $log = RoomLog::create([
            'room_id' => $room->id,
            'user_id' => $keeper->id,
            'status_reported' => 'clean',
            'proof_photo' => 'room-proofs/missing.jpg',
        ]);

        $summary = app(RetentionService::class)->enforce();

        $this->assertSame(1, $summary['room_logs']);
        $this->assertDatabaseMissing('room_logs', ['id' => $log->id]);

        $archive = ArchiveIndex::where('category', ArchiveIndex::CATEGORY_ROOM_LOGS)->firstOrFail();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('private')->path($archive->file_path)) === true);
        $this->assertNotFalse($zip->getFromName('room_logs.csv'));
        $zip->close();
    }

    public function test_retention_can_be_run_from_super_admin_panel(): void
    {
        config([
            'retention.limits.transactions' => 1,
            'retention.batch.transactions' => 1,
            'retention.limits.customers' => 100,
            'retention.limits.room_logs' => 100,
            'retention.limits.activity_logs' => 100,
        ]);

        $this->makeTransaction(['status' => Transaction::STATUS_CHECKED_OUT]);
        $this->makeTransaction(['status' => Transaction::STATUS_CHECKED_OUT]);

        $superAdmin = $this->makeUser(User::ROLE_SUPER_ADMIN);

        $this->actingAs($superAdmin)
            ->post(route('admin.retention.run'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(1, Transaction::count());
    }

    public function test_retention_archives_room_logs_with_proof_photos(): void
    {
        config([
            'retention.limits.transactions' => 100,
            'retention.limits.customers' => 100,
            'retention.limits.room_logs' => 1,
            'retention.batch.room_logs' => 1,
            'retention.limits.activity_logs' => 100,
        ]);

        $keeper = $this->makeUser(User::ROLE_ROOM_KEEPER);
        $room = $this->makeRoom();
        $old = RoomLog::create([
            'room_id' => $room->id,
            'user_id' => $keeper->id,
            'status_reported' => 'clean',
            'proof_photo' => 'room-proofs/old.jpg',
        ]);
        $new = RoomLog::create([
            'room_id' => $room->id,
            'user_id' => $keeper->id,
            'status_reported' => 'dirty',
        ]);
        Storage::disk('private')->put('room-proofs/old.jpg', 'proof-bytes');

        $summary = app(RetentionService::class)->enforce();

        $this->assertSame(1, $summary['room_logs']);
        $this->assertDatabaseMissing('room_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('room_logs', ['id' => $new->id]);
        Storage::disk('private')->assertMissing('room-proofs/old.jpg');

        $archive = ArchiveIndex::where('category', ArchiveIndex::CATEGORY_ROOM_LOGS)->firstOrFail();
        Storage::disk('private')->assertExists($archive->file_path);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('private')->path($archive->file_path)) === true);
        $this->assertNotFalse($zip->getFromName('room_logs.csv'));
        $this->assertNotFalse($zip->getFromName('proofs/'.$old->id.'-old.jpg'));
        $zip->close();
    }

    public function test_retention_archives_old_customer_and_photo_without_transactions(): void
    {
        config([
            'retention.limits.transactions' => 100,
            'retention.limits.customers' => 2,
            'retention.batch.customers' => 1,
            'retention.limits.room_logs' => 100,
            'retention.limits.activity_logs' => 100,
        ]);

        Storage::disk('private')->put('id-cards/c1.jpg', 'ktp-1');
        Storage::disk('private')->put('id-cards/c2.jpg', 'ktp-2');
        Storage::disk('private')->put('id-cards/c3.jpg', 'ktp-3');

        $first = Customer::create([
            'name' => 'Old', 'phone' => '081000000001', 'id_card_photo' => 'id-cards/c1.jpg',
        ]);
        Customer::create(['name' => 'Two', 'phone' => '081000000002', 'id_card_photo' => 'id-cards/c2.jpg']);
        Customer::create(['name' => 'Three', 'phone' => '081000000003', 'id_card_photo' => 'id-cards/c3.jpg']);

        $summary = app(RetentionService::class)->enforce();

        $this->assertSame(1, $summary['customers']);
        $this->assertDatabaseMissing('customers', ['id' => $first->id]);
        Storage::disk('private')->assertMissing('id-cards/c1.jpg');

        $archive = ArchiveIndex::where('category', ArchiveIndex::CATEGORY_CUSTOMERS)->firstOrFail();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('private')->path($archive->file_path)) === true);
        $this->assertNotFalse($zip->getFromName('customers.csv'));
        $this->assertNotFalse($zip->getFromName('ktp/c1.jpg'));
        $zip->close();
    }

    public function test_retention_archives_activity_logs(): void
    {
        config([
            'retention.limits.transactions' => 100,
            'retention.limits.customers' => 100,
            'retention.limits.room_logs' => 100,
            'retention.limits.activity_logs' => 1,
            'retention.batch.activity_logs' => 1,
        ]);

        $user = $this->makeUser(User::ROLE_OWNER);
        $old = ActivityLog::create([
            'user_id' => $user->id, 'action' => 'Lama', 'category' => 'kamar',
        ]);
        ActivityLog::create([
            'user_id' => $user->id, 'action' => 'Baru', 'category' => 'kamar',
        ]);

        $summary = app(RetentionService::class)->enforce();

        $this->assertSame(1, $summary['activity_logs']);
        $this->assertDatabaseMissing('activity_logs', ['id' => $old->id]);

        $archive = ArchiveIndex::where('category', ArchiveIndex::CATEGORY_ACTIVITY_LOGS)->firstOrFail();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('private')->path($archive->file_path)) === true);
        $this->assertNotFalse($zip->getFromName('activity_logs.csv'));
        $zip->close();
    }

    public function test_customer_export_creates_zip_with_csv_and_ktp_and_shows_reminder(): void
    {
        config([
            'retention.customer_export.batch' => 2,
            'retention.customer_export.reminder' => 2,
        ]);

        $owner = $this->makeUser(User::ROLE_OWNER);

        foreach (range(1, 3) as $i) {
            $photo = 'id-cards/ktp-'.$i.'.jpg';
            Storage::disk('private')->put($photo, 'ktp-'.$i);

            $customer = Customer::create([
                'name' => 'Customer '.$i,
                'phone' => '08120000000'.$i,
                'id_card_number' => '111111111111111'.$i,
                'id_card_photo' => $photo,
            ]);
        }

        $this->actingAs($owner)
            ->get(route('customers.index'))
            ->assertOk()
            ->assertSee('belum diarsipkan');

        $this->actingAs($owner)
            ->post(route('customers.export'))
            ->assertOk();

        $archive = ArchiveIndex::where('category', ArchiveIndex::CATEGORY_CUSTOMER_EXPORT)->firstOrFail();
        Storage::disk('private')->assertExists($archive->file_path);
        $this->assertSame(2, $archive->record_count);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('private')->path($archive->file_path)) === true);
        $this->assertNotFalse($zip->getFromName('customers.csv'));
        $this->assertNotFalse($zip->getFromName('ktp/ktp-1.jpg'));
        $this->assertNotFalse($zip->getFromName('ktp/ktp-2.jpg'));
        $zip->close();

        $this->assertSame(1, app(RetentionService::class)->pendingCustomerExportCount());
    }

    public function test_resepsionis_cannot_export_customer_ktp_archive(): void
    {
        $frontOffice = $this->makeUser(User::ROLE_RESEPSIONIS);

        $this->actingAs($frontOffice)
            ->post(route('customers.export'))
            ->assertForbidden();
    }

    public function test_super_admin_can_create_and_download_database_backup(): void
    {
        $superAdmin = $this->makeUser(User::ROLE_SUPER_ADMIN);

        $this->actingAs($superAdmin)
            ->post(route('admin.backup'))
            ->assertRedirect(route('admin.archives'))
            ->assertSessionHas('success');

        $archive = ArchiveIndex::where('category', ArchiveIndex::CATEGORY_BACKUP)->firstOrFail();
        Storage::disk('private')->assertExists($archive->file_path);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('private')->path($archive->file_path)) === true);
        $this->assertNotFalse($zip->getFromName('users.csv'));
        $this->assertNotFalse($zip->getFromName('transactions.csv'));
        $zip->close();

        $this->actingAs($superAdmin)
            ->get(route('admin.archives.download', $archive))
            ->assertOk();
    }

    public function test_archive_download_is_super_admin_only(): void
    {
        $owner = $this->makeUser(User::ROLE_OWNER);
        $superAdmin = $this->makeUser(User::ROLE_SUPER_ADMIN);
        Storage::disk('private')->put('archives/sample.zip', 'zip');

        $archive = ArchiveIndex::create([
            'category' => ArchiveIndex::CATEGORY_TRANSACTIONS,
            'file_name' => 'sample.zip',
            'file_path' => 'archives/sample.zip',
            'size_bytes' => 3,
            'record_count' => 1,
            'status' => ArchiveIndex::STATUS_READY,
        ]);

        $this->actingAs($owner)
            ->get(route('admin.archives.download', $archive))
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->get(route('admin.archives.download', $archive))
            ->assertOk();
    }

    public function test_emergency_mode_blocks_writes_but_keeps_super_admin_cleanup_available(): void
    {
        $owner = $this->makeUser(User::ROLE_OWNER);
        $superAdmin = $this->makeUser(User::ROLE_SUPER_ADMIN);

        Cache::put('retention:emergency', true, 3600);

        $this->actingAs($owner)
            ->post(route('rooms.store'), [
                'room_type_id' => 1,
                'room_number' => '999',
            ])
            ->assertStatus(503);

        $this->actingAs($superAdmin)
            ->post(route('admin.retention.run'))
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_retention_does_not_archive_active_or_future_transactions(): void
    {
        config([
            'retention.limits.transactions' => 1,
            'retention.batch.transactions' => 1,
            'retention.limits.customers' => 100,
            'retention.limits.room_logs' => 100,
            'retention.limits.activity_logs' => 100,
        ]);

        $active = $this->makeTransaction(['status' => Transaction::STATUS_CHECKED_IN]);
        $reserved = $this->makeTransaction(['status' => Transaction::STATUS_RESERVED]);

        $summary = app(RetentionService::class)->enforce();

        $this->assertSame(0, $summary['transactions']);
        $this->assertDatabaseHas('transactions', ['id' => $active->id]);
        $this->assertDatabaseHas('transactions', ['id' => $reserved->id]);
        $this->assertSame(0, ArchiveIndex::where('category', ArchiveIndex::CATEGORY_TRANSACTIONS)->count());
    }

    public function test_retention_does_not_archive_customer_with_active_transaction(): void
    {
        config([
            'retention.limits.transactions' => 100,
            'retention.limits.customers' => 1,
            'retention.batch.customers' => 1,
            'retention.limits.room_logs' => 100,
            'retention.limits.activity_logs' => 100,
        ]);

        $first = $this->makeTransaction(['status' => Transaction::STATUS_CHECKED_IN]);
        $second = $this->makeTransaction(['status' => Transaction::STATUS_CHECKED_IN]);

        $summary = app(RetentionService::class)->enforce();

        $this->assertSame(0, $summary['customers']);
        $this->assertDatabaseHas('customers', ['id' => $first->customer_id]);
        $this->assertDatabaseHas('customers', ['id' => $second->customer_id]);
    }

    public function test_old_archive_file_is_rotated_when_archive_budget_exceeded(): void
    {
        config([
            'retention.archive.budget_mb' => 1,
            'retention.limits.transactions' => 100,
            'retention.limits.customers' => 100,
            'retention.limits.room_logs' => 100,
            'retention.limits.activity_logs' => 100,
        ]);

        Storage::disk('private')->put('archives/old.zip', str_repeat('x', 2 * 1024 * 1024));
        Storage::disk('private')->put('archives/new.zip', str_repeat('y', 100 * 1024));

        $old = ArchiveIndex::create([
            'category' => ArchiveIndex::CATEGORY_TRANSACTIONS,
            'file_name' => 'old.zip',
            'file_path' => 'archives/old.zip',
            'size_bytes' => 2 * 1024 * 1024,
            'record_count' => 10,
            'status' => ArchiveIndex::STATUS_READY,
        ]);
        $old->forceFill(['created_at' => now()->subDay()])->saveQuietly();

        $new = ArchiveIndex::create([
            'category' => ArchiveIndex::CATEGORY_CUSTOMER_EXPORT,
            'file_name' => 'new.zip',
            'file_path' => 'archives/new.zip',
            'size_bytes' => 100 * 1024,
            'record_count' => 5,
            'status' => ArchiveIndex::STATUS_READY,
        ]);

        app(RetentionService::class)->enforce();

        $this->assertSame(ArchiveIndex::STATUS_DELETED, $old->fresh()->status);
        $this->assertSame(ArchiveIndex::STATUS_READY, $new->fresh()->status);
        Storage::disk('private')->assertMissing('archives/old.zip');
        Storage::disk('private')->assertExists('archives/new.zip');
    }

    public function test_customer_export_without_new_customers_returns_error(): void
    {
        $owner = $this->makeUser(User::ROLE_OWNER);

        $this->actingAs($owner)
            ->from(route('customers.index'))
            ->post(route('customers.export'))
            ->assertRedirect(route('customers.index'))
            ->assertSessionHasErrors();

        $this->assertSame(0, ArchiveIndex::where('category', ArchiveIndex::CATEGORY_CUSTOMER_EXPORT)->count());
    }

    public function test_customer_export_reports_storage_problem_clearly(): void
    {
        config(['retention.archive.disk' => 'missing-archive-disk']);
        $owner = $this->makeUser(User::ROLE_OWNER);

        $this->actingAs($owner)
            ->from(route('customers.index'))
            ->post(route('customers.export'))
            ->assertRedirect(route('customers.index'))
            ->assertSessionHasErrors();

        $errors = session('errors');
        $this->assertStringContainsString('Export customer gagal', $errors->first());
        $this->assertStringContainsString('tidak bisa ditulis', $errors->first());
    }

    public function test_download_returns_404_when_archive_file_is_missing(): void
    {
        $superAdmin = $this->makeUser(User::ROLE_SUPER_ADMIN);

        $archive = ArchiveIndex::create([
            'category' => ArchiveIndex::CATEGORY_BACKUP,
            'file_name' => 'missing.zip',
            'file_path' => 'backups/missing.zip',
            'size_bytes' => 10,
            'record_count' => 1,
            'status' => ArchiveIndex::STATUS_READY,
        ]);

        $this->actingAs($superAdmin)
            ->get(route('admin.archives.download', $archive))
            ->assertNotFound();
    }

    public function test_retention_lock_prevents_concurrent_run(): void
    {
        $lock = Cache::lock('retention:lock', 60);
        $this->assertTrue($lock->get());

        try {
            $summary = app(RetentionService::class)->enforce();
            $this->assertTrue($summary['skipped'] ?? false);
        } finally {
            $lock->release();
        }
    }

    public function test_retention_command_runs_successfully(): void
    {
        config([
            'retention.limits.transactions' => 1,
            'retention.batch.transactions' => 1,
            'retention.limits.customers' => 100,
            'retention.limits.room_logs' => 100,
            'retention.limits.activity_logs' => 100,
        ]);

        $this->makeTransaction(['status' => Transaction::STATUS_CHECKED_OUT]);
        $this->makeTransaction(['status' => Transaction::STATUS_CHECKED_OUT]);

        $this->artisan('retention:run')->assertSuccessful();
        $this->assertSame(1, Transaction::count());
    }
}
