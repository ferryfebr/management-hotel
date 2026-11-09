<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    public function test_login_response_has_security_headers(): void
    {
        $response = $this->get(route('login'))->assertOk();

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
    }

    public function test_authenticated_pages_are_not_cached(): void
    {
        $owner = $this->makeUser(User::ROLE_OWNER);

        $response = $this->actingAs($owner)
            ->get(route('dashboard.index'))
            ->assertOk();

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
    }

    public function test_https_response_has_hsts_header(): void
    {
        $this->get('https://localhost/login')
            ->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        config(['auth.login_throttle_per_minute' => 5]);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.attempt'), [
                'email' => 'tidak-ada@hotel.test',
                'password' => 'salah',
            ])->assertRedirect();
        }

        $this->from(route('login'))
            ->post(route('login.attempt'), [
                'email' => 'tidak-ada@hotel.test',
                'password' => 'salah',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertStringContainsString(
            'Terlalu banyak percobaan login',
            (string) session('errors')->first('email')
        );
    }

    public function test_cloudflare_forwarded_https_scheme_is_respected(): void
    {
        $response = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders([
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-For' => '203.0.113.10',
            ])
            ->get('/login')
            ->assertOk();

        $this->assertStringContainsString('action="https://', $response->getContent());
    }

    public function test_customer_search_query_is_bound_not_interpolated(): void
    {
        $owner = $this->makeUser(User::ROLE_OWNER);
        Customer::create(['name' => 'Aman', 'phone' => '081000000001']);
        Customer::create(['name' => 'Aman Dua', 'phone' => '081000000002']);

        $this->actingAs($owner)
            ->getJson(route('customers.search', ['q' => "' OR 1=1 --"]))
            ->assertOk()
            ->assertExactJson([]);
    }
}
