<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    public function test_success_flash_is_rendered_as_toast(): void
    {
        $owner = $this->makeUser(User::ROLE_OWNER);

        $this->actingAs($owner)
            ->withSession(['success' => 'Data berhasil disimpan.'])
            ->get(route('dashboard.index'))
            ->assertOk()
            ->assertSee('toast-container', false)
            ->assertSee('Data berhasil disimpan.', false)
            ->assertSee('window.showToast', false);
    }

    public function test_validation_errors_are_rendered_as_toast(): void
    {
        $owner = $this->makeUser(User::ROLE_OWNER);

        $this->actingAs($owner)
            ->post(route('room-types.store'), ['name' => '', 'price' => -1])
            ->assertSessionHasErrors(['name', 'price']);

        $this->actingAs($owner)
            ->get(route('room-types.index'))
            ->assertOk()
            ->assertSee('toast-container', false)
            ->assertSee('window.showToast(\'error\'', false);
    }

    public function test_operational_pages_no_longer_reload_automatically(): void
    {
        $owner = $this->makeUser(User::ROLE_OWNER);
        $frontOffice = $this->makeUser(User::ROLE_RESEPSIONIS);
        $keeper = $this->makeUser(User::ROLE_ROOM_KEEPER);

        $this->actingAs($owner)->get(route('dashboard.index'))
            ->assertOk()->assertDontSee('location.reload', false);
        $this->actingAs($owner)->get(route('users.activities'))
            ->assertOk()->assertDontSee('location.reload', false);
        $this->actingAs($frontOffice)->get(route('transactions.active'))
            ->assertOk()->assertDontSee('location.reload', false);
        $this->actingAs($keeper)->get(route('room-keeper.index'))
            ->assertOk()->assertDontSee('location.reload', false);
    }
}
