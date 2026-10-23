<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Payment;
use App\Models\RoomLog;
use App\Models\RoomTransfer;
use App\Models\StayExtension;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->makeUser(User::ROLE_OWNER);
    }

    public function test_owner_can_create_staff_account(): void
    {
        $this->actingAs($this->owner)
            ->post(route('users.store'), [
                'name' => 'Resep Baru',
                'email' => 'resep.baru@hotel.test',
                'role' => User::ROLE_RESEPSIONIS,
                'password' => 'secret123',
                'password_confirmation' => 'secret123',
            ])
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success');

        $user = User::where('email', 'resep.baru@hotel.test')->firstOrFail();
        $this->assertSame(User::ROLE_RESEPSIONIS, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('secret123', $user->password));
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'Tambah akun',
            'category' => ActivityLog::CATEGORY_AKUN,
        ]);
    }

    public function test_create_staff_validates_unique_email_role_and_password(): void
    {
        $this->actingAs($this->owner)->post(route('users.store'), [
            'name' => 'X',
            'email' => $this->owner->email,
            'role' => User::ROLE_OWNER,
            'password' => '123',
            'password_confirmation' => '456',
        ])->assertSessionHasErrors(['email', 'role', 'password']);
    }

    public function test_update_staff_changes_profile_and_deactivates_when_flag_missing(): void
    {
        $staff = $this->makeUser(User::ROLE_RESEPSIONIS);

        $this->actingAs($this->owner)
            ->put(route('users.update', $staff), [
                'name' => 'Nama Baru',
                'email' => 'nama.baru@hotel.test',
                'role' => User::ROLE_ROOM_KEEPER,
            ])
            ->assertRedirect(route('users.index'));

        $staff->refresh();
        $this->assertSame('Nama Baru', $staff->name);
        $this->assertSame('nama.baru@hotel.test', $staff->email);
        $this->assertSame(User::ROLE_ROOM_KEEPER, $staff->role);
        $this->assertFalse($staff->is_active);
        $this->assertStringContainsString('dinonaktifkan', ActivityLog::first()->description);
    }

    public function test_update_staff_can_keep_active_flag_and_change_password_conditionally(): void
    {
        $staff = $this->makeUser(User::ROLE_RESEPSIONIS);
        $oldPassword = $staff->password;

        $this->actingAs($this->owner)->put(route('users.update', $staff), [
            'name' => $staff->name,
            'email' => $staff->email,
            'role' => $staff->role,
            'is_active' => '1',
        ])->assertRedirect();

        $staff->refresh();
        $this->assertTrue($staff->is_active);
        $this->assertSame($oldPassword, $staff->password);

        $this->actingAs($this->owner)->put(route('users.update', $staff), [
            'name' => $staff->name,
            'email' => $staff->email,
            'role' => $staff->role,
            'is_active' => '1',
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('newpass123', $staff->fresh()->password));
    }

    public function test_update_rejects_owner_account_and_non_staff_role(): void
    {
        $this->actingAs($this->owner)
            ->put(route('users.update', $this->owner), [
                'name' => 'Hack',
                'email' => 'hack@hotel.test',
                'role' => User::ROLE_RESEPSIONIS,
            ])
            ->assertForbidden();
    }

    public function test_destroy_deactivates_staff_and_keeps_record(): void
    {
        $staff = $this->makeUser(User::ROLE_ROOM_KEEPER);

        $this->actingAs($this->owner)
            ->delete(route('users.destroy', $staff))
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['id' => $staff->id, 'is_active' => false]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'Nonaktifkan akun']);
    }

    public function test_destroy_rejects_owner_account(): void
    {
        $this->actingAs($this->owner)
            ->delete(route('users.destroy', $this->owner))
            ->assertForbidden();
    }

    public function test_owner_sees_all_activity_categories(): void
    {
        ActivityLog::create([
            'user_id' => $this->owner->id,
            'action' => 'Tambah akun',
            'category' => ActivityLog::CATEGORY_AKUN,
        ]);
        ActivityLog::create([
            'user_id' => $this->owner->id,
            'action' => 'Tambah kamar',
            'category' => ActivityLog::CATEGORY_KAMAR,
        ]);
        ActivityLog::create([
            'user_id' => $this->owner->id,
            'action' => 'Ubah status kamar',
            'category' => ActivityLog::CATEGORY_KAMAR,
        ]);

        $response = $this->actingAs($this->owner)->get(route('users.activities'))->assertOk();
        $actions = collect($response->viewData('activities')->items())->pluck('action')->all();

        $this->assertContains('Tambah akun', $actions);
        $this->assertContains('Tambah kamar', $actions);
        $this->assertContains('Ubah status kamar', $actions);
    }

    public function test_resepsionis_activity_view_hides_owner_only_categories_and_actions(): void
    {
        $frontOffice = $this->makeUser(User::ROLE_RESEPSIONIS);
        ActivityLog::create([
            'user_id' => $frontOffice->id,
            'action' => 'Tambah akun',
            'category' => ActivityLog::CATEGORY_AKUN,
        ]);
        ActivityLog::create([
            'user_id' => $frontOffice->id,
            'action' => 'Hapus kamar',
            'category' => ActivityLog::CATEGORY_KAMAR,
        ]);
        ActivityLog::create([
            'user_id' => $frontOffice->id,
            'action' => 'Ubah status kamar',
            'category' => ActivityLog::CATEGORY_KAMAR,
        ]);
        ActivityLog::create([
            'user_id' => $frontOffice->id,
            'action' => 'Ubah jenis kamar',
            'category' => ActivityLog::CATEGORY_JENIS_KAMAR,
        ]);

        $response = $this->actingAs($frontOffice)->get(route('users.activities'))->assertOk();
        $actions = collect($response->viewData('activities')->items())->pluck('action')->all();

        $this->assertNotContains('Tambah akun', $actions);
        $this->assertNotContains('Hapus kamar', $actions);
        $this->assertNotContains('Ubah jenis kamar', $actions);
        $this->assertContains('Ubah status kamar', $actions);
    }

    public function test_activity_collector_includes_domain_tables_and_user_filter(): void
    {
        $staff = $this->makeUser(User::ROLE_RESEPSIONIS);
        $other = $this->makeUser(User::ROLE_RESEPSIONIS);
        $room = $this->makeRoom();
        $transaction = $this->makeTransaction([
            'room' => $room,
            'user' => $staff,
            'status' => Transaction::STATUS_CHECKED_IN,
        ]);
        $this->makePayment($transaction, 50000, Payment::TYPE_DP, $staff);
        StayExtension::create([
            'transaction_id' => $transaction->id,
            'old_checkout_date' => now()->toDateString(),
            'new_checkout_date' => now()->addDay()->toDateString(),
            'additional_days' => 1,
            'additional_price' => 100000,
            'extended_by' => $staff->id,
        ]);
        RoomTransfer::create([
            'transaction_id' => $transaction->id,
            'from_room_id' => $room->id,
            'to_room_id' => $this->makeRoom(null, '999')->id,
            'reason' => 'Pindah',
            'transferred_by' => $staff->id,
            'transferred_at' => now(),
        ]);
        RoomLog::create([
            'room_id' => $room->id,
            'user_id' => $staff->id,
            'status_reported' => 'clean',
            'proof_photo' => 'room-proofs/x.jpg',
        ]);
        ActivityLog::create([
            'user_id' => $other->id,
            'action' => 'Aksi Orang Lain',
            'category' => ActivityLog::CATEGORY_KAMAR,
        ]);

        $response = $this->actingAs($this->owner)
            ->get(route('users.activities', ['user' => $staff->id]))
            ->assertOk();

        $rows = collect($response->viewData('activities')->items());
        $types = $rows->pluck('type')->unique()->values()->all();

        $this->assertContains('transaksi', $types);
        $this->assertContains('pembayaran', $types);
        $this->assertContains('perpanjangan', $types);
        $this->assertContains('transfer', $types);
        $this->assertContains('kamar', $types);
        $this->assertNotContains('Aksi Orang Lain', $rows->pluck('action')->all());
        $this->assertTrue($rows->every(fn ($row) => $row['user']?->id === $staff->id));
        $this->assertNotNull($rows->firstWhere('type', 'kamar')['photo']);
    }
}
