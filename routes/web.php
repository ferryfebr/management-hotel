<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomKeeperController;
use App\Http\Controllers\RoomTypeController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

// ==== Guest ====
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    // Rate limit anti brute-force: maks 5 percobaan login/menit per IP.
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login.attempt');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// ============================================================================
// DEPLOY TANPA SSH — ROUTE SENGAJA DINONAKTIFKAN (security.md §1.1 & §9).
// Jangan biarkan aktif di production: siapa pun yang tahu token bisa menjalankan
// migration/seeder. Kalau butuh menjalankan migration saat deploy, aktifkan
// sementara baris di bawah, pakai sekali, lalu NONAKTIFKAN LAGI dan upload ulang.
// ============================================================================
// Route::post('/deploy/{token}', App\Http\Controllers\DeployController::class);

Route::get('/', fn () => redirect()->route('login'));

// ==== Semua role yang sudah login ====
Route::middleware('auth')->group(function () {

    // ---- Owner only ----
    Route::middleware('role:owner')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

        Route::get('/room-types', [RoomTypeController::class, 'index'])->name('room-types.index');
        Route::post('/room-types', [RoomTypeController::class, 'store'])->name('room-types.store');
        Route::put('/room-types/{roomType}', [RoomTypeController::class, 'update'])->name('room-types.update');
        Route::delete('/room-types/{roomType}', [RoomTypeController::class, 'destroy'])->name('room-types.destroy');

        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

        // ---- Manajemen akun & aktivitas pekerja (owner only) ----
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');

        // ---- Profil owner (ganti nama/email & password sendiri) ----
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
        Route::delete('/rooms/{room}', [RoomController::class, 'destroy'])->name('rooms.destroy');
    });

    // ---- Owner & Resepsionis ----
    Route::middleware('role:owner,resepsionis')->group(function () {
        Route::get('/rooms', [RoomController::class, 'index'])->name('rooms.index');
        Route::post('/rooms', [RoomController::class, 'store'])->name('rooms.store');
        Route::put('/rooms/{room}', [RoomController::class, 'update'])->name('rooms.update');
        Route::patch('/rooms/{room}/status', [RoomController::class, 'updateStatus'])->name('rooms.update-status');

        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::post('/customers/check-by-id-card', [CustomerController::class, 'checkByCard'])->name('customers.check-id-card');
        Route::get('/customers/search', [CustomerController::class, 'searchByName'])->name('customers.search');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::get('/customers/{customer}/id-card', [CustomerController::class, 'idCardPhoto'])->name('customers.id-card');

        Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
        Route::get('/reservations/create', [ReservationController::class, 'create'])->name('reservations.create');
        Route::post('/reservations', [ReservationController::class, 'store'])->name('reservations.store');
        Route::get('/reservations/{reservation}/edit', [ReservationController::class, 'edit'])->name('reservations.edit');
        Route::patch('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->name('reservations.cancel');
        Route::patch('/reservations/{reservation}/no-show', [ReservationController::class, 'markNoShow'])->name('reservations.no-show');

        Route::get('/transactions/active', [TransactionController::class, 'active'])->name('transactions.active');
        Route::get('/transactions/checkin', [TransactionController::class, 'createCheckInForm'])->name('transactions.checkin-form');
        Route::post('/transactions/checkin', [TransactionController::class, 'createCheckIn'])->name('transactions.checkin');
        Route::patch('/transactions/{transaction}/extend', [TransactionController::class, 'extendStay'])->name('transactions.extend');
        Route::patch('/transactions/{transaction}/transfer', [TransactionController::class, 'transferRoom'])->name('transactions.transfer');
        Route::post('/transactions/{transaction}/payments', [TransactionController::class, 'addPayment'])->name('transactions.add-payment');
        Route::patch('/transactions/{transaction}/checkout', [TransactionController::class, 'processCheckOut'])->name('transactions.checkout');

        // Aktivitas sistem: owner & resepsionis (resepsionis melihat aksi operasional saja).
        Route::get('/users/activities', [UserManagementController::class, 'activities'])->name('users.activities');
    });

    // ---- Room Keeper only ----
    Route::middleware('role:room_keeper')->group(function () {
        Route::get('/room-keeper', [RoomKeeperController::class, 'index'])->name('room-keeper.index');
        Route::patch('/room-keeper/{room}/status', [RoomKeeperController::class, 'updateStatus'])->name('room-keeper.update-status');
    });

    // ---- Foto bukti kerja: owner, resepsionis, room keeper ----
    Route::middleware('role:owner,resepsionis,room_keeper')->group(function () {
        Route::get('/room-logs/{roomLog}/photo', [RoomKeeperController::class, 'proofPhoto'])->name('room-logs.photo');
    });
});