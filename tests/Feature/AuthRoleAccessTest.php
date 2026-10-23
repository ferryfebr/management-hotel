<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

class AuthRoleAccessTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    public function test_login_page_is_reachable_for_guest(): void
    {
        $this->get(route('login'))->assertOk();
    }

    public function test_owner_is_redirected_to_dashboard_after_login(): void
    {
        $user = $this->makeUser(User::ROLE_OWNER);

        $this->post(route('login.attempt'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_resepsionis_is_redirected_to_active_transactions_after_login(): void
    {
        $user = $this->makeUser(User::ROLE_RESEPSIONIS);

        $this->post(route('login.attempt'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('transactions.active'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_room_keeper_is_redirected_to_room_keeper_page_after_login(): void
    {
        $user = $this->makeUser(User::ROLE_ROOM_KEEPER);

        $this->post(route('login.attempt'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('room-keeper.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_keep_user_guest_and_show_error(): void
    {
        $user = $this->makeUser(User::ROLE_OWNER);

        $this->from(route('login'))
            ->post(route('login.attempt'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = $this->makeUser(User::ROLE_RESEPSIONIS, false);

        $this->from(route('login'))
            ->post(route('login.attempt'), [
                'email' => $user->email,
                'password' => 'password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_validation_rejects_missing_email_and_password(): void
    {
        $this->post(route('login.attempt'), [])
            ->assertSessionHasErrors(['email', 'password']);
    }

    public function test_guest_is_redirected_to_login_for_protected_route(): void
    {
        $this->get(route('dashboard.index'))->assertRedirect(route('login'));
    }

    public function test_resepsionis_cannot_access_owner_only_routes(): void
    {
        $user = $this->makeUser(User::ROLE_RESEPSIONIS);

        $this->actingAs($user)->get(route('dashboard.index'))->assertForbidden();
        $this->actingAs($user)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($user)->get(route('room-types.index'))->assertForbidden();
        $this->actingAs($user)->get(route('users.index'))->assertForbidden();
    }

    public function test_room_keeper_cannot_access_owner_or_front_office_routes(): void
    {
        $user = $this->makeUser(User::ROLE_ROOM_KEEPER);

        $this->actingAs($user)->get(route('dashboard.index'))->assertForbidden();
        $this->actingAs($user)->get(route('rooms.index'))->assertForbidden();
        $this->actingAs($user)->get(route('customers.index'))->assertForbidden();
        $this->actingAs($user)->get(route('reservations.index'))->assertForbidden();
        $this->actingAs($user)->get(route('transactions.active'))->assertForbidden();
    }

    public function test_owner_cannot_access_room_keeper_only_route(): void
    {
        $user = $this->makeUser(User::ROLE_OWNER);

        $this->actingAs($user)->get(route('room-keeper.index'))->assertForbidden();
    }

    public function test_allowed_roles_can_access_shared_routes(): void
    {
        $owner = $this->makeUser(User::ROLE_OWNER);
        $frontOffice = $this->makeUser(User::ROLE_RESEPSIONIS);
        $keeper = $this->makeUser(User::ROLE_ROOM_KEEPER);

        $this->actingAs($owner)->get(route('rooms.index'))->assertOk();
        $this->actingAs($frontOffice)->get(route('rooms.index'))->assertOk();
        $this->actingAs($keeper)->get(route('room-keeper.index'))->assertOk();
    }

    public function test_logout_invalidates_authentication(): void
    {
        $user = $this->makeUser(User::ROLE_OWNER);

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('success');

        $this->assertGuest();
    }
}
