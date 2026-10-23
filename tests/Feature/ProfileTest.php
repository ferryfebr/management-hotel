<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    public function test_owner_can_update_name_and_email_with_current_password(): void
    {
        $owner = $this->makeUser(User::ROLE_OWNER);

        $this->actingAs($owner)
            ->from(route('profile.edit'))
            ->put(route('profile.update'), [
                'name' => 'Owner Baru',
                'email' => 'owner.baru@hotel.test',
                'current_password' => 'password',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('success');

        $owner->refresh();
        $this->assertSame('Owner Baru', $owner->name);
        $this->assertSame('owner.baru@hotel.test', $owner->email);
    }

    public function test_wrong_current_password_is_rejected_without_changes(): void
    {
        $owner = $this->makeUser(User::ROLE_OWNER);
        $originalEmail = $owner->email;

        $this->actingAs($owner)
            ->from(route('profile.edit'))
            ->put(route('profile.update'), [
                'name' => 'Tidak Tersimpan',
                'email' => 'ganti@hotel.test',
                'current_password' => 'salah',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('current_password');

        $owner->refresh();
        $this->assertSame($originalEmail, $owner->email);
        $this->assertNotSame('Tidak Tersimpan', $owner->name);
    }

    public function test_optional_password_update_is_validated_and_hashed(): void
    {
        $owner = $this->makeUser(User::ROLE_OWNER);

        $this->actingAs($owner)
            ->put(route('profile.update'), [
                'name' => $owner->name,
                'email' => $owner->email,
                'current_password' => 'password',
                'password' => 'newpass123',
                'password_confirmation' => 'newpass123',
            ])
            ->assertRedirect();

        $this->assertTrue(Hash::check('newpass123', $owner->fresh()->password));

        $this->actingAs($owner)
            ->put(route('profile.update'), [
                'name' => $owner->name,
                'email' => $owner->email,
                'current_password' => 'newpass123',
                'password' => 'short',
                'password_confirmation' => 'different',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_email_must_be_unique_and_valid(): void
    {
        $owner = $this->makeUser(User::ROLE_OWNER);
        $other = $this->makeUser(User::ROLE_RESEPSIONIS);

        $this->actingAs($owner)
            ->put(route('profile.update'), [
                'name' => $owner->name,
                'email' => $other->email,
                'current_password' => 'password',
            ])
            ->assertSessionHasErrors('email');

        $this->actingAs($owner)
            ->put(route('profile.update'), [
                'name' => $owner->name,
                'email' => 'bukan-email',
                'current_password' => 'password',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_current_password_is_required(): void
    {
        $owner = $this->makeUser(User::ROLE_OWNER);

        $this->actingAs($owner)
            ->put(route('profile.update'), [
                'name' => $owner->name,
                'email' => $owner->email,
            ])
            ->assertSessionHasErrors('current_password');
    }
}
