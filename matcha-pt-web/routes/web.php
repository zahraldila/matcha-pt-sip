<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Community\CommunityController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Game\GameController;
use App\Http\Controllers\Player\PlayerController;
use App\Http\Controllers\Scoring\ScoringController;
use App\Http\Controllers\Venue\CourtController;
use App\Http\Controllers\Venue\VenueController;
use Illuminate\Support\Facades\Route;

// Auth Routes (Login, Register, Logout)
Route::get('/csrf-token', fn () => response()->json(['token' => csrf_token()]))->name('csrf.token');
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->name('register.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Dashboard & Home (Public)
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

// Game & Mabar
Route::prefix('games')->name('games.')->group(function () {
    Route::get('/', [GameController::class, 'index'])->name('index');
    Route::get('/{id}', [GameController::class, 'show'])->whereNumber('id')->name('show');
    Route::get('/{id}/drawing', [GameController::class, 'drawing'])->whereNumber('id')->name('drawing');
    Route::post('/{id}/join', [GameController::class, 'joinSession'])->whereNumber('id')->name('join'); // Support Member & Guest Player join

    // Protected: Lock Drawing, Create Game Wizard, Schedule Sesi Mabar
    Route::middleware('auth')->group(function () {
        Route::post('/{id}/lock', [GameController::class, 'lockDrawing'])->whereNumber('id')->name('lock');
        Route::get('/create', [GameController::class, 'create'])->name('create');
        Route::get('/players/search', [GameController::class, 'searchPlayers'])->name('players.search');
        Route::post('/', [GameController::class, 'store'])->name('store');
        Route::get('/schedule', [GameController::class, 'createSchedule'])->name('schedule');
        Route::post('/schedule', [GameController::class, 'storeSchedule'])->name('schedule.post');
        Route::post('/{id}/cancel', [GameController::class, 'cancelSession'])->whereNumber('id')->name('cancel');
    });
});

// Venues & Courts
Route::prefix('venues')->name('venues.')->group(function () {
    Route::get('/', [VenueController::class, 'index'])->name('index');
    Route::get('/{id}', [VenueController::class, 'show'])->whereNumber('id')->name('show');

    // Protected: SMK 2 — Venue & Court Manager
    Route::middleware('auth')->group(function () {
        Route::post('/quick-store', [VenueController::class, 'quickStore'])->name('quickStore'); // Quick Add Venue from Session Wizard
        Route::get('/create', [VenueController::class, 'create'])->name('create');
        Route::post('/', [VenueController::class, 'store'])->name('store');             // [SMK 2] Simpan venue baru ke DB
        Route::get('/{id}/edit', [VenueController::class, 'edit'])->whereNumber('id')->name('edit');     // Form edit venue
        Route::put('/{id}', [VenueController::class, 'update'])->whereNumber('id')->name('update');      // Simpan perubahan venue ke DB
        Route::delete('/{id}', [VenueController::class, 'destroy'])->whereNumber('id')->name('destroy');   // Hapus / nonaktifkan venue
        Route::post('/{id}/photos', [VenueController::class, 'updatePhotos'])->whereNumber('id')->name('photos.update'); // Tambah/Hapus foto venue
        Route::get('/{id}/photos', fn ($id) => redirect()->route('venues.show', $id)); // Graceful fallback if opened via GET
        Route::get('/{id}/courts', [CourtController::class, 'index'])->whereNumber('id')->name('courts.index');   // [SMK 2] List court per venue
        Route::get('/{id}/courts/create', [CourtController::class, 'create'])->whereNumber('id')->name('courts.create'); // [SMK 2] Form tambah court
        Route::post('/{id}/courts', [CourtController::class, 'store'])->whereNumber('id')->name('courts.store');  // [SMK 2] Simpan court baru ke DB
        Route::put('/{id}/courts/{courtId}', [CourtController::class, 'update'])->whereNumber(['id', 'courtId'])->name('courts.update'); // Update court
        Route::delete('/{id}/courts/{courtId}', [CourtController::class, 'destroy'])->whereNumber(['id', 'courtId'])->name('courts.destroy'); // Hapus court
    });
});

// Live Match Scoring Console & Recap
Route::prefix('scoring')->name('scoring.')->group(function () {
    Route::get('/live/{id?}', [ScoringController::class, 'live'])->name('live');
    Route::get('/recap/{id?}', [ScoringController::class, 'recap'])->name('recap');
    Route::get('/get-score/{gameId}/{round?}', [ScoringController::class, 'getScore'])->name('get-score'); // JSON polling
    Route::post('/update-score', [ScoringController::class, 'updateScore'])->name('update-score'); // AJAX endpoint
    Route::post('/next-round', [ScoringController::class, 'nextRound'])->name('next-round');       // Lanjut ke set/ronde berikutnya
    Route::post('/finish', [ScoringController::class, 'finishSession'])->name('finish');           // Selesaikan sesi
    Route::post('/kudos/toggle', [ScoringController::class, 'toggleKudos'])->name('kudos.toggle'); // Simpan / Cabut Kudos permanen ke DB
});

// Protected: Player Profile & Strava-like Recap
// Protected: Player Profile & Strava-like Recap
Route::prefix('player')->name('player.')->middleware('auth')->group(function () {
    Route::get('/profile', [PlayerController::class, 'profile'])->name('profile');
    Route::post('/profile', [PlayerController::class, 'updateProfile'])->name('profile.update');
    Route::post('/profile/toggle-host', [PlayerController::class, 'toggleHost'])->name('profile.toggle-host'); // <--- Tambahkan route ini
    Route::get('/recap', [PlayerController::class, 'recap'])->name('recap');
});

// Community
Route::prefix('communities')->name('communities.')->group(function () {
    Route::get('/', [CommunityController::class, 'index'])->name('index');
    Route::get('/show', fn () => abort(404, 'ID Komunitas diperlukan.'))->name('show.empty');
    Route::get('/detail', fn () => abort(404, 'ID Komunitas diperlukan.'))->name('detail.empty');
    Route::get('/{id}', [CommunityController::class, 'show'])->whereNumber('id')->name('show'); // [SMK 3] Detail komunitas

    // Protected: SMK 3 — Community Manager
    Route::middleware('auth')->group(function () {
        Route::get('/create', [CommunityController::class, 'create'])->name('create');
        Route::post('/upload-logo', [CommunityController::class, 'uploadLogo'])->name('upload-logo');
        Route::post('/', [CommunityController::class, 'store'])->name('store');                  // [SMK 3] Simpan komunitas baru ke DB
        Route::get('/{id}/edit', [CommunityController::class, 'edit'])->whereNumber('id')->name('edit');     // Form edit komunitas
        Route::put('/{id}', [CommunityController::class, 'update'])->whereNumber('id')->name('update');      // Simpan perubahan komunitas
        Route::delete('/{id}', [CommunityController::class, 'destroy'])->whereNumber('id')->name('destroy'); // Nonaktif / Hapus komunitas
        Route::post('/{id}/join', [CommunityController::class, 'join'])->whereNumber('id')->name('join');   // [SMK 3] Join komunitas
        Route::post('/{id}/leave', [CommunityController::class, 'leave'])->whereNumber('id')->name('leave'); // [SMK 3] Leave komunitas
    });
});

Route::get('/community/{id?}', function ($id = null) {
    if (! $id || ! is_numeric($id)) {
        abort(404, 'Komunitas tidak ditemukan.');
    }

    return redirect()->route('communities.show', (int) $id);
});

// Admin — Manajemen Pengguna
Route::get('/admin/users', [PlayerController::class, 'manageUsers'])->name('admin.users')->middleware('auth');
