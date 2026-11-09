<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\RoomLog;
use App\Models\Transaction;
use App\Support\RetentionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        // Retensi tetap berjalan tanpa queue/cron lewat event setelah commit.
        // Saat test, service dijalankan eksplisit supaya hasil test deterministik.
        if (app()->runningUnitTests()) {
            return;
        }

        $run = function (): void {
            try {
                DB::afterCommit(fn () => app(RetentionService::class)->autoRun());
            } catch (Throwable) {
                app(RetentionService::class)->autoRun();
            }
        };

        foreach ([
            Transaction::class,
            Payment::class,
            RoomLog::class,
            ActivityLog::class,
            Customer::class,
        ] as $model) {
            $model::created($run);
        }
    }
}
