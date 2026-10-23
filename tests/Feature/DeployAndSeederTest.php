<?php

namespace Tests\Feature;

use App\Http\Controllers\DeployController;
use App\Models\User;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\CreatesDomainData;
use Tests\TestCase;

class DeployAndSeederTest extends TestCase
{
    use CreatesDomainData, RefreshDatabase;

    public function test_deploy_rejects_when_token_is_not_configured(): void
    {
        config(['app.deploy_token' => null]);

        try {
            (new DeployController)(Request::create('/deploy/anything', 'POST'), 'anything');
            $this->fail('Deploy endpoint should abort when token is not configured.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_deploy_rejects_wrong_token(): void
    {
        config(['app.deploy_token' => 'rahasia']);

        try {
            (new DeployController)(Request::create('/deploy/salah', 'POST'), 'salah');
            $this->fail('Deploy endpoint should abort for a wrong token.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_deploy_runs_migration_only_with_valid_token(): void
    {
        config(['app.deploy_token' => 'rahasia']);

        Artisan::shouldReceive('call')->once()->with('migrate', ['--force' => true]);
        Artisan::shouldReceive('output')->once()->andReturn('migrated <ok>');

        $response = (new DeployController)(Request::create('/deploy/rahasia', 'POST'), 'rahasia');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('MIGRATE:', $response->getContent());
        $this->assertStringContainsString('migrated &lt;ok&gt;', $response->getContent());
    }

    public function test_deploy_runs_production_seeder_when_seed_flag_is_set(): void
    {
        config(['app.deploy_token' => 'rahasia']);

        Artisan::shouldReceive('call')->once()->with('migrate', ['--force' => true]);
        Artisan::shouldReceive('call')->once()->with('db:seed', [
            '--class' => 'Database\\Seeders\\ProductionSeeder',
            '--force' => true,
        ]);
        Artisan::shouldReceive('output')->twice()->andReturn('done');

        $response = (new DeployController)(
            Request::create('/deploy/rahasia?seed=1', 'POST'),
            'rahasia'
        );

        $this->assertStringContainsString('SEED:', $response->getContent());
    }

    public function test_production_seeder_creates_accounts_from_config_and_is_idempotent(): void
    {
        config([
            'production.super_admin.name' => 'Super Admin Asli',
            'production.super_admin.email' => 'super@production.test',
            'production.super_admin.password' => 'superpass',
            'production.owner.name' => 'Owner Asli',
            'production.owner.email' => 'owner@production.test',
            'production.owner.password' => 'ownerpass',
            'production.resepsionis.name' => 'Front Office',
            'production.resepsionis.email' => 'fo@production.test',
            'production.resepsionis.password' => 'fopass',
            'production.room_keeper.name' => 'Keeper',
            'production.room_keeper.email' => 'keeper@production.test',
            'production.room_keeper.password' => 'keeperpass',
        ]);

        $seeder = new ProductionSeeder;
        $seeder->run();
        $seeder->run();

        $this->assertSame(4, User::count());
        $this->assertDatabaseHas('users', [
            'email' => 'super@production.test',
            'role' => User::ROLE_SUPER_ADMIN,
            'name' => 'Super Admin Asli',
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'owner@production.test',
            'role' => User::ROLE_OWNER,
            'name' => 'Owner Asli',
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'fo@production.test',
            'role' => User::ROLE_RESEPSIONIS,
            'name' => 'Front Office',
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'keeper@production.test',
            'role' => User::ROLE_ROOM_KEEPER,
            'name' => 'Keeper',
        ]);
        $this->assertTrue(Hash::check('ownerpass', User::where('email', 'owner@production.test')->first()->password));
    }

    public function test_production_seeder_skips_accounts_without_credentials(): void
    {
        config([
            'production.super_admin.email' => null,
            'production.super_admin.password' => null,
            'production.owner.email' => null,
            'production.owner.password' => null,
            'production.resepsionis.email' => null,
            'production.resepsionis.password' => null,
            'production.room_keeper.email' => null,
            'production.room_keeper.password' => null,
        ]);

        (new ProductionSeeder)->run();

        $this->assertSame(0, User::count());
    }
}
