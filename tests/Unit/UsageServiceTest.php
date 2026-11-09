<?php

namespace Tests\Unit;

use App\Support\UsageService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class UsageServiceTest extends TestCase
{
    public function test_directory_size_counts_nested_files(): void
    {
        $path = storage_path('framework/testing/usage-'.uniqid());
        File::ensureDirectoryExists($path.'/nested');
        file_put_contents($path.'/a.txt', str_repeat('a', 100));
        file_put_contents($path.'/nested/b.txt', str_repeat('b', 250));

        $service = new UsageService;

        $this->assertSame(350, $service->directorySize($path));
        $this->assertSame(0, $service->directorySize($path.'/missing'));

        File::deleteDirectory($path);
    }

    public function test_snapshot_has_quota_categories_and_database_data(): void
    {
        config([
            'hosting.quota_mb' => 10,
            'hosting.reserve_mb' => 1,
            'retention.db_budget_mb' => 1,
        ]);

        $service = new UsageService;
        $snapshot = $service->snapshot(true);

        $this->assertSame(10, $snapshot['quota_mb']);
        $this->assertSame(1, $snapshot['reserve_mb']);
        $this->assertSame(1, $snapshot['db_budget_mb']);
        $this->assertGreaterThanOrEqual(0, $snapshot['used_bytes']);
        $this->assertGreaterThanOrEqual(0, $snapshot['db_used_bytes']);

        $keys = array_column($snapshot['categories'], 'key');
        $this->assertSame([
            'code',
            'database',
            'id_cards',
            'room_proofs',
            'archives',
            'backups',
            'logs',
            'other',
        ], $keys);
    }

    public function test_database_size_is_non_negative(): void
    {
        $this->assertGreaterThanOrEqual(0, (new UsageService)->databaseSizeBytes());
    }
}
