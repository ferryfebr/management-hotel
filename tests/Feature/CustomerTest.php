<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    private User $frontOffice;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        $this->frontOffice = $this->makeUser(User::ROLE_RESEPSIONIS);
    }

    public function test_index_search_matches_name_phone_or_id_card(): void
    {
        $name = Customer::create(['name' => 'Alpha Satu', 'phone' => '081111111111', 'id_card_number' => '1111111111111111']);
        $name->forceFill(['visit_count' => 3])->save();
        $phone = Customer::create(['name' => 'Beta', 'phone' => '089999999999', 'id_card_number' => '2222222222222222']);
        $phone->forceFill(['visit_count' => 1])->save();
        $card = Customer::create(['name' => 'Gamma', 'phone' => '087777777777', 'id_card_number' => '3333333333333333']);
        $card->forceFill(['visit_count' => 2])->save();

        $this->actingAs($this->frontOffice)
            ->get(route('customers.index', ['search' => 'Alpha']))
            ->assertViewHas('customers', fn ($p) => $p->pluck('id')->all() === [$name->id]);

        $this->actingAs($this->frontOffice)
            ->get(route('customers.index', ['search' => '089999']))
            ->assertViewHas('customers', fn ($p) => $p->pluck('id')->all() === [$phone->id]);

        $this->actingAs($this->frontOffice)
            ->get(route('customers.index', ['search' => '333333']))
            ->assertViewHas('customers', fn ($p) => $p->pluck('id')->all() === [$card->id]);
    }

    public function test_index_without_search_orders_by_visit_count_desc(): void
    {
        $low = Customer::create(['name' => 'Low', 'phone' => '080000000001']);
        $low->forceFill(['visit_count' => 1])->save();
        $high = Customer::create(['name' => 'High', 'phone' => '080000000002']);
        $high->forceFill(['visit_count' => 9])->save();

        $this->actingAs($this->frontOffice)
            ->get(route('customers.index'))
            ->assertViewHas('customers', fn ($p) => $p->pluck('id')->all() === [$high->id, $low->id]);
    }

    public function test_update_changes_rating_and_notes_and_records_activity(): void
    {
        $customer = Customer::create([
            'name' => 'Tamu',
            'phone' => '081234567890',
            'rating_status' => Customer::RATING_REGULAR,
            'notes' => 'lama',
        ]);

        $this->actingAs($this->frontOffice)
            ->from(route('customers.show', $customer))
            ->put(route('customers.update', $customer), [
                'rating_status' => Customer::RATING_BLACKLISTED,
                'notes' => 'baru',
            ])
            ->assertRedirect(route('customers.show', $customer))
            ->assertSessionHas('success');

        $customer->refresh();
        $this->assertSame(Customer::RATING_BLACKLISTED, $customer->rating_status);
        $this->assertSame('baru', $customer->notes);

        $log = ActivityLog::firstOrFail();
        $this->assertSame('Ubah data pelanggan', $log->action);
        $this->assertSame(ActivityLog::CATEGORY_PELANGGAN, $log->category);
        $this->assertStringContainsString('blacklisted', $log->description);
        $this->assertStringContainsString('catatan diubah', $log->description);
        $this->assertSame($this->frontOffice->id, $log->user_id);
    }

    public function test_update_validation_rejects_invalid_rating(): void
    {
        $customer = Customer::create(['name' => 'Tamu', 'phone' => '081234567890']);

        $this->actingAs($this->frontOffice)
            ->put(route('customers.update', $customer), ['rating_status' => 'unknown'])
            ->assertSessionHasErrors('rating_status');
    }

    public function test_destroy_soft_deletes_customer_and_records_activity(): void
    {
        $customer = Customer::create(['name' => 'Hapus Saya', 'phone' => '081234567890']);

        $this->actingAs($this->makeUser(User::ROLE_OWNER))
            ->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.index'));

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'Hapus pelanggan',
            'category' => ActivityLog::CATEGORY_PELANGGAN,
        ]);
    }

    public function test_search_by_name_requires_two_characters_and_limits_results(): void
    {
        foreach (range(1, 8) as $i) {
            $customer = Customer::create([
                'name' => 'Sama '.$i,
                'phone' => '08100000000'.$i,
            ]);
            $customer->forceFill(['visit_count' => $i])->save();
        }

        $this->actingAs($this->frontOffice)
            ->getJson(route('customers.search', ['q' => 'S']))
            ->assertOk()
            ->assertExactJson([]);

        $this->actingAs($this->frontOffice)
            ->getJson(route('customers.search', ['q' => 'Sama']))
            ->assertOk()
            ->assertJsonCount(6)
            ->assertJsonStructure([['name', 'phone', 'id_card_number', 'visit_count']]);
    }

    public function test_check_by_card_rejects_invalid_number(): void
    {
        $this->actingAs($this->frontOffice)
            ->postJson(route('customers.check-id-card'), ['id_card_number' => '123'])
            ->assertOk()
            ->assertExactJson(['found' => false, 'valid' => false]);
    }

    public function test_check_by_card_strips_non_digits_before_lookup(): void
    {
        $customer = Customer::create([
            'name' => 'KTP',
            'phone' => '081234567890',
            'id_card_number' => '1234567890123456',
            'rating_status' => Customer::RATING_VIP,
        ]);
        $customer->forceFill(['visit_count' => 4])->save();

        $this->actingAs($this->frontOffice)
            ->postJson(route('customers.check-id-card'), [
                'id_card_number' => '1234-5678-9012-3456',
            ])
            ->assertOk()
            ->assertJson([
                'found' => true,
                'name' => 'KTP',
                'rating_status' => Customer::RATING_VIP,
                'visit_count' => 4,
                'has_photo' => false,
            ]);

        $this->assertSame($customer->id, Customer::first()->id);
    }

    public function test_check_by_card_reports_when_ktp_photo_is_available(): void
    {
        Storage::disk('private')->put('id-cards/saved.jpg', 'ktp');
        Customer::create([
            'name' => 'Punya Foto',
            'phone' => '081234567890',
            'id_card_number' => '1234567890123456',
            'id_card_photo' => 'id-cards/saved.jpg',
        ]);

        $this->actingAs($this->frontOffice)
            ->postJson(route('customers.check-id-card'), [
                'id_card_number' => '1234567890123456',
            ])
            ->assertOk()
            ->assertJson([
                'found' => true,
                'has_photo' => true,
            ]);
    }

    public function test_check_by_card_returns_not_found_for_unknown_valid_number(): void
    {
        $this->actingAs($this->frontOffice)
            ->postJson(route('customers.check-id-card'), ['id_card_number' => '9999999999999999'])
            ->assertOk()
            ->assertExactJson(['found' => false]);
    }

    public function test_id_card_photo_returns_404_when_absent(): void
    {
        $customer = Customer::create(['name' => 'Tanpa Foto', 'phone' => '081234567890']);

        $this->actingAs($this->frontOffice)
            ->get(route('customers.id-card', $customer))
            ->assertNotFound();
    }

    public function test_id_card_photo_is_streamed_from_private_disk(): void
    {
        Storage::disk('private')->put('id-cards/ktp.jpg', 'ktp-bytes');
        $customer = Customer::create([
            'name' => 'Dengan Foto',
            'phone' => '081234567890',
            'id_card_photo' => 'id-cards/ktp.jpg',
        ]);

        $response = $this->actingAs($this->frontOffice)
            ->get(route('customers.id-card', $customer))
            ->assertOk();

        $this->assertSame('ktp-bytes', $response->streamedContent());
    }
}
