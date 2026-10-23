<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Room;
use App\Models\RoomTransfer;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

class RoomAndRoomTypeTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    private User $frontOffice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->frontOffice = $this->makeUser(User::ROLE_RESEPSIONIS);
    }

    public function test_room_store_creates_available_room_and_logs_activity(): void
    {
        $type = $this->makeRoomType();

        $this->actingAs($this->frontOffice)
            ->from(route('rooms.index'))
            ->post(route('rooms.store'), [
                'room_type_id' => $type->id,
                'room_number' => '101',
            ])
            ->assertRedirect(route('rooms.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('rooms', [
            'room_type_id' => $type->id,
            'room_number' => '101',
            'status' => Room::STATUS_AVAILABLE,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'Tambah kamar',
            'category' => ActivityLog::CATEGORY_KAMAR,
        ]);
    }

    public function test_room_store_rejects_duplicate_number_and_invalid_type(): void
    {
        $existing = $this->makeRoom(null, '101');

        $this->actingAs($this->frontOffice)
            ->post(route('rooms.store'), [
                'room_type_id' => $existing->room_type_id,
                'room_number' => '101',
            ])
            ->assertSessionHasErrors('room_number');

        $this->actingAs($this->frontOffice)
            ->post(route('rooms.store'), [
                'room_type_id' => 999999,
                'room_number' => '102',
            ])
            ->assertSessionHasErrors('room_type_id');
    }

    public function test_room_update_allows_own_number_but_rejects_other_duplicate(): void
    {
        $room = $this->makeRoom(null, '101');
        $other = $this->makeRoom(null, '102');

        $this->actingAs($this->frontOffice)
            ->put(route('rooms.update', $room), [
                'room_type_id' => $room->room_type_id,
                'room_number' => '101',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($this->frontOffice)
            ->put(route('rooms.update', $room), [
                'room_type_id' => $room->room_type_id,
                'room_number' => $other->room_number,
            ])
            ->assertSessionHasErrors('room_number');
    }

    public function test_room_status_update_accepts_enum_and_logs_old_new_status(): void
    {
        $room = $this->makeRoom(null, '101', Room::STATUS_AVAILABLE);

        $this->actingAs($this->frontOffice)
            ->patch(route('rooms.update-status', $room), ['status' => Room::STATUS_MAINTENANCE])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(Room::STATUS_MAINTENANCE, $room->fresh()->status);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'Ubah status kamar',
            'description' => 'Kamar 101: available → maintenance',
        ]);
    }

    public function test_room_status_update_rejects_invalid_status(): void
    {
        $room = $this->makeRoom();

        $this->actingAs($this->frontOffice)
            ->patch(route('rooms.update-status', $room), ['status' => 'broken'])
            ->assertSessionHasErrors('status');
    }

    public function test_room_delete_is_blocked_by_transaction_history(): void
    {
        $room = $this->makeRoom();
        $this->makeTransaction(['room' => $room]);

        $this->actingAs($this->makeUser(User::ROLE_OWNER))
            ->from(route('rooms.index'))
            ->delete(route('rooms.destroy', $room))
            ->assertRedirect(route('rooms.index'))
            ->assertSessionHasErrors();

        $this->assertDatabaseHas('rooms', ['id' => $room->id]);
        $this->assertSame(0, ActivityLog::where('action', 'Hapus kamar')->count());
    }

    public function test_room_delete_is_blocked_by_transfer_history(): void
    {
        $room = $this->makeRoom(null, '101');
        $other = $this->makeRoom(null, '102');
        $transaction = $this->makeTransaction(['room' => $other]);
        RoomTransfer::create([
            'transaction_id' => $transaction->id,
            'from_room_id' => $room->id,
            'to_room_id' => $other->id,
            'reason' => 'Pindah',
            'transferred_at' => now(),
        ]);

        $this->actingAs($this->makeUser(User::ROLE_OWNER))
            ->delete(route('rooms.destroy', $room))
            ->assertSessionHasErrors();

        $this->assertDatabaseHas('rooms', ['id' => $room->id]);
    }

    public function test_room_delete_succeeds_when_no_history_and_logs_activity(): void
    {
        $room = $this->makeRoom(null, '777');

        $this->actingAs($this->makeUser(User::ROLE_OWNER))
            ->delete(route('rooms.destroy', $room))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('rooms', ['id' => $room->id]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'Hapus kamar',
            'description' => 'Kamar 777',
        ]);
    }

    public function test_room_type_store_and_update_validate_and_log(): void
    {
        $owner = $this->makeUser(User::ROLE_OWNER);

        $this->actingAs($owner)->post(route('room-types.store'), [
            'name' => 'Suite',
            'price' => 500000,
            'description' => 'Mewah',
        ])->assertRedirect()->assertSessionHas('success');

        $type = RoomType::firstOrFail();
        $this->assertSame(500000.0, (float) $type->price);

        $this->actingAs($owner)->put(route('room-types.update', $type), [
            'name' => 'Suite Plus',
            'price' => 600000,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame('Suite Plus', $type->fresh()->name);
        $this->assertSame(600000.0, (float) $type->fresh()->price);
        $this->assertDatabaseHas('activity_logs', ['action' => 'Ubah jenis kamar']);

        $this->actingAs($owner)->post(route('room-types.store'), [
            'name' => '',
            'price' => -1,
        ])->assertSessionHasErrors(['name', 'price']);
    }

    public function test_room_type_delete_is_blocked_while_rooms_exist(): void
    {
        $type = $this->makeRoomType();
        $this->makeRoom($type, '101');

        $this->actingAs($this->makeUser(User::ROLE_OWNER))
            ->delete(route('room-types.destroy', $type))
            ->assertSessionHasErrors();

        $this->assertDatabaseHas('room_types', ['id' => $type->id]);
    }

    public function test_room_type_delete_succeeds_when_empty(): void
    {
        $type = $this->makeRoomType();

        $this->actingAs($this->makeUser(User::ROLE_OWNER))
            ->delete(route('room-types.destroy', $type))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('room_types', ['id' => $type->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'Hapus jenis kamar']);
    }
}
