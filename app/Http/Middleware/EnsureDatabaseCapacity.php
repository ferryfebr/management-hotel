<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnsureDatabaseCapacity
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || $request->routeIs('admin.*', 'login.*', 'logout', 'customers.export')) {
            return $next($request);
        }

        try {
            $emergency = (bool) Cache::get('retention:emergency', false);
        } catch (Throwable) {
            $emergency = false;
        }

        if ($emergency) {
            abort(503, 'Kapasitas database dalam mode darurat. Superadmin perlu menjalankan retensi sebelum data baru dapat disimpan.');
        }

        return $next($request);
    }
}
