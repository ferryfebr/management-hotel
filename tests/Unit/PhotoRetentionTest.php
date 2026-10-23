<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\RoomLog;
use App\Support\PhotoRetention;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

class PhotoRetentionTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    public function test_prune_does_nothing_when_file_count_below_limit(): void
    {
        Storage::fake('private');
        $disk = Storage::disk('private');
        $disk->put('id-cards/a.jpg', 'a');
        $disk->put('id-cards/b.png', 'b');

        PhotoRetention::prune('id-cards', 2);

        $disk->assertExists('id-cards/a.jpg');
        $disk->assertExists('id-cards/b.png');
    }

    public function test_prune_deletes_oldest_images_and_nulls_database_reference(): void
    {
        Storage::fake('private');
        $disk = Storage::disk('private');

        $old = Customer::create([
            'name' => 'Lama',
            'phone' => '081000000001',
            'id_card_photo' => 'id-cards/old.jpg',
        ]);
        $new = Customer::create([
            'name' => 'Baru',
            'phone' => '081000000002',
            'id_card_photo' => 'id-cards/new.jpg',
        ]);

        foreach ([
            'id-cards/old.jpg' => 1000000000,
            'id-cards/middle1.jpg' => 1100000000,
            'id-cards/middle2.jpg' => 1200000000,
            'id-cards/new.jpg' => 1300000000,
        ] as $path => $mtime) {
            $disk->put($path, 'x');
            touch($disk->path($path), $mtime);
        }

        PhotoRetention::prune('id-cards', 2);

        $disk->assertMissing('id-cards/old.jpg');
        $disk->assertMissing('id-cards/middle1.jpg');
        $disk->assertExists('id-cards/middle2.jpg');
        $disk->assertExists('id-cards/new.jpg');

        $this->assertNull($old->fresh()->id_card_photo);
        $this->assertSame('id-cards/new.jpg', $new->fresh()->id_card_photo);
    }

    public function test_prune_respects_byte_budget(): void
    {
        Storage::fake('private');
        $disk = Storage::disk('private');

        foreach (['old' => 1000000000, 'middle' => 1100000000, 'new' => 1200000000] as $name => $mtime) {
            $path = "id-cards/{$name}.jpg";
            $disk->put($path, str_repeat('x', 400 * 1024));
            touch($disk->path($path), $mtime);
        }

        PhotoRetention::prune('id-cards', 10, 900 * 1024);

        $disk->assertMissing('id-cards/old.jpg');
        $disk->assertExists('id-cards/middle.jpg');
        $disk->assertExists('id-cards/new.jpg');

        $remaining = $disk->size('id-cards/middle.jpg') + $disk->size('id-cards/new.jpg');
        $this->assertLessThanOrEqual(900 * 1024, $remaining);
    }

    public function test_prune_counts_only_image_extensions(): void
    {
        Storage::fake('private');
        $disk = Storage::disk('private');

        $disk->put('room-proofs/.htaccess', 'deny');
        $disk->put('room-proofs/index.html', '<html></html>');
        $disk->put('room-proofs/one.jpg', '1');
        $disk->put('room-proofs/two.webp', '2');
        $disk->put('room-proofs/three.jpeg', '3');

        PhotoRetention::prune('room-proofs', 3);

        $disk->assertExists('room-proofs/one.jpg');
        $disk->assertExists('room-proofs/two.webp');
        $disk->assertExists('room-proofs/three.jpeg');
        $disk->assertExists('room-proofs/.htaccess');
        $disk->assertExists('room-proofs/index.html');
    }

    public function test_prune_nulls_room_log_reference_for_room_proofs(): void
    {
        Storage::fake('private');
        $disk = Storage::disk('private');
        $log = RoomLog::create([
            'room_id' => $this->makeRoom()->id,
            'user_id' => $this->makeUser()->id,
            'status_reported' => 'clean',
            'proof_photo' => 'room-proofs/old.jpg',
        ]);
        $newLog = RoomLog::create([
            'room_id' => $this->makeRoom(null, '102')->id,
            'user_id' => $log->user_id,
            'status_reported' => 'clean',
            'proof_photo' => 'room-proofs/new.jpg',
        ]);

        foreach (['old' => 1000000000, 'new' => 1100000000] as $name => $mtime) {
            $path = "room-proofs/{$name}.jpg";
            $disk->put($path, 'x');
            touch($disk->path($path), $mtime);
        }

        PhotoRetention::prune('room-proofs', 1);

        $this->assertNull($log->fresh()->proof_photo);
        $this->assertSame('room-proofs/new.jpg', $newLog->fresh()->proof_photo);
    }

    public function test_prune_unknown_folder_does_not_touch_database(): void
    {
        Storage::fake('private');
        $disk = Storage::disk('private');
        $disk->put('misc/a.jpg', 'a');
        $disk->put('misc/b.jpg', 'b');

        PhotoRetention::prune('misc', 1);

        $disk->assertMissing('misc/a.jpg');
        $disk->assertExists('misc/b.jpg');
    }
}
