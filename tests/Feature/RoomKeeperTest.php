<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\RoomLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

class RoomKeeperTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    private User $keeper;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        $this->keeper = $this->makeUser(User::ROLE_ROOM_KEEPER);
    }

    public function test_index_orders_rooms_by_housekeeping_priority(): void
    {
        $this->makeRoom(null, '105', Room::STATUS_AVAILABLE);
        $this->makeRoom(null, '101', Room::STATUS_DIRTY);
        $this->makeRoom(null, '103', Room::STATUS_OCCUPIED);
        $this->makeRoom(null, '102', Room::STATUS_MAINTENANCE);

        $response = $this->actingAs($this->keeper)->get(route('room-keeper.index'))->assertOk();

        $statuses = $response->viewData('rooms')->pluck('status')->all();
        $this->assertSame([
            Room::STATUS_DIRTY,
            Room::STATUS_MAINTENANCE,
            Room::STATUS_OCCUPIED,
            Room::STATUS_AVAILABLE,
        ], $statuses);
    }

    public function test_clean_status_requires_photo_and_makes_room_available(): void
    {
        $room = $this->makeRoom(null, '201', Room::STATUS_DIRTY);

        $this->actingAs($this->keeper)
            ->from(route('room-keeper.index'))
            ->patch(route('room-keeper.update-status', $room), [
                'status_reported' => 'clean',
                'notes' => 'Sudah dibersihkan',
                'proof_photo' => UploadedFile::fake()->image('proof.jpg', 1200, 900),
            ])
            ->assertRedirect(route('room-keeper.index'))
            ->assertSessionHas('success');

        $this->assertSame(Room::STATUS_AVAILABLE, $room->fresh()->status);

        $log = RoomLog::firstOrFail();
        $this->assertSame('clean', $log->status_reported);
        $this->assertSame($this->keeper->id, $log->user_id);
        $this->assertNotNull($log->proof_photo);
        Storage::disk('private')->assertExists($log->proof_photo);

        $info = getimagesizefromstring(Storage::disk('private')->get($log->proof_photo));
        $this->assertNotFalse($info);
        $this->assertLessThanOrEqual(800, $info[0]);
        $this->assertSame('image/jpeg', $info['mime']);
    }

    public function test_maintenance_requires_photo_and_sets_maintenance_status(): void
    {
        $room = $this->makeRoom(null, '202', Room::STATUS_DIRTY);

        $this->actingAs($this->keeper)->patch(route('room-keeper.update-status', $room), [
            'status_reported' => 'maintenance',
            'notes' => 'AC rusak',
            'proof_photo' => UploadedFile::fake()->image('proof.jpg'),
        ])->assertRedirect();

        $this->assertSame(Room::STATUS_MAINTENANCE, $room->fresh()->status);
    }

    public function test_dirty_status_does_not_require_photo(): void
    {
        $room = $this->makeRoom(null, '203', Room::STATUS_AVAILABLE);

        $this->actingAs($this->keeper)->patch(route('room-keeper.update-status', $room), [
            'status_reported' => 'dirty',
        ])->assertRedirect();

        $this->assertSame(Room::STATUS_DIRTY, $room->fresh()->status);
        $this->assertNull(RoomLog::firstOrFail()->proof_photo);
    }

    public function test_clean_and_maintenance_require_photo(): void
    {
        $room = $this->makeRoom(null, '204', Room::STATUS_DIRTY);

        $this->actingAs($this->keeper)
            ->patch(route('room-keeper.update-status', $room), ['status_reported' => 'clean'])
            ->assertSessionHasErrors('proof_photo');

        $this->actingAs($this->keeper)
            ->patch(route('room-keeper.update-status', $room), ['status_reported' => 'maintenance'])
            ->assertSessionHasErrors('proof_photo');

        $this->assertSame(0, RoomLog::count());
        $this->assertSame(Room::STATUS_DIRTY, $room->fresh()->status);
    }

    public function test_ajax_maintenance_without_photo_returns_clear_validation_error(): void
    {
        $room = $this->makeRoom(null, '206', Room::STATUS_DIRTY);

        $this->actingAs($this->keeper)
            ->patchJson(route('room-keeper.update-status', $room), [
                'status_reported' => 'maintenance',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('proof_photo')
            ->assertJsonFragment(['message' => 'Foto bukti wajib diupload untuk status ini.']);

        $this->assertSame(0, RoomLog::count());
        $this->assertSame(Room::STATUS_DIRTY, $room->fresh()->status);
    }

    public function test_invalid_status_is_rejected(): void
    {
        $room = $this->makeRoom();

        $this->actingAs($this->keeper)
            ->patch(route('room-keeper.update-status', $room), ['status_reported' => 'occupied'])
            ->assertSessionHasErrors('status_reported');

        $this->assertSame(0, RoomLog::count());
    }

    public function test_ajax_update_returns_redirect_json(): void
    {
        $room = $this->makeRoom(null, '205', Room::STATUS_DIRTY);

        $this->actingAs($this->keeper)
            ->patchJson(route('room-keeper.update-status', $room), [
                'status_reported' => 'clean',
                'proof_photo' => UploadedFile::fake()->image('proof.jpg'),
            ])
            ->assertOk()
            ->assertJson(['redirect' => route('room-keeper.index')]);
    }

    public function test_proof_photo_returns_404_when_missing(): void
    {
        $log = RoomLog::create([
            'room_id' => $this->makeRoom()->id,
            'user_id' => $this->keeper->id,
            'status_reported' => 'dirty',
        ]);

        $this->actingAs($this->keeper)
            ->get(route('room-logs.photo', $log))
            ->assertNotFound();
    }

    public function test_proof_photo_is_streamed_to_allowed_roles(): void
    {
        Storage::disk('private')->put('room-proofs/bukti.jpg', 'jpeg-bytes');
        $log = RoomLog::create([
            'room_id' => $this->makeRoom()->id,
            'user_id' => $this->keeper->id,
            'status_reported' => 'clean',
            'proof_photo' => 'room-proofs/bukti.jpg',
        ]);

        foreach ([User::ROLE_OWNER, User::ROLE_RESEPSIONIS, User::ROLE_ROOM_KEEPER] as $role) {
            $response = $this->actingAs($this->makeUser($role))
                ->get(route('room-logs.photo', $log))
                ->assertOk();

            $this->assertSame('jpeg-bytes', $response->streamedContent());
        }
    }

    public function test_front_office_cannot_update_room_keeper_status(): void
    {
        $room = $this->makeRoom();

        $this->actingAs($this->makeUser(User::ROLE_RESEPSIONIS))
            ->patch(route('room-keeper.update-status', $room), ['status_reported' => 'dirty'])
            ->assertForbidden();
    }
}
