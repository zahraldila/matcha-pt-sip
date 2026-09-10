<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Game\GameController;
use App\Http\Controllers\Venue\VenueController;
use App\Http\Controllers\Venue\CourtController;       // [SMK 2] Court management
use App\Http\Controllers\Scoring\ScoringController;
use App\Http\Controllers\Player\PlayerController;
use App\Http\Controllers\Community\CommunityController;
use App\Http\Controllers\Auth\AuthController;

// Auth Routes (Login, Register, Logout)
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
    Route::post('/{id}/join', [GameController::class, 'joinSession'])->whereNumber('id')->name('join');

    // Protected: Create Game Wizard (Instant Host) & Schedule Sesi Mabar
    Route::middleware('auth')->group(function () {
    Route::get('/create', [GameController::class, 'create'])->name('create');

    Route::get('/players/search', [GameController::class, 'searchPlayers'])
        ->name('players.search');

    Route::post('/', [GameController::class, 'store'])->name('store');

    Route::get('/schedule', [GameController::class, 'createSchedule'])->name('schedule');
    Route::post('/schedule', [GameController::class, 'storeSchedule'])->name('schedule.post');
});
});

// Venues & Courts
Route::prefix('venues')->name('venues.')->group(function () {
    Route::get('/', [VenueController::class, 'index'])->name('index');
    Route::get('/{id}', [VenueController::class, 'show'])->whereNumber('id')->name('show');

    // Protected: SMK 2 — Venue & Court Manager
    Route::middleware('auth')->group(function () {
        Route::get('/create', [VenueController::class, 'create'])->name('create');
        Route::post('/', [VenueController::class, 'store'])->name('store');             // [SMK 2] Simpan venue baru ke DB
        Route::post('/{id}/photos', [VenueController::class, 'updatePhotos'])->whereNumber('id')->name('photos.update'); // Tambah/Hapus foto venue
        Route::get('/{id}/courts', [CourtController::class, 'index'])->whereNumber('id')->name('courts.index');   // [SMK 2] List court per venue
        Route::get('/{id}/courts/create', [CourtController::class, 'create'])->whereNumber('id')->name('courts.create'); // [SMK 2] Form tambah court
        Route::post('/{id}/courts', [CourtController::class, 'store'])->whereNumber('id')->name('courts.store');  // [SMK 2] Simpan court baru ke DB
        Route::delete('/{id}/courts/{courtId}', [CourtController::class, 'destroy'])->whereNumber(['id', 'courtId'])->name('courts.destroy'); // Hapus court
    });
});

// Live Match Scoring Console & Recap
Route::prefix('scoring')->name('scoring.')->group(function () {
    Route::get('/live/{id?}', [ScoringController::class, 'live'])->name('live');
    Route::get('/recap/{id?}', [ScoringController::class, 'recap'])->name('recap');
    Route::get('/get-score/{gameId}/{round?}', [ScoringController::class, 'getScore'])->name('get-score'); // JSON polling
    Route::post('/update-score', [ScoringController::class, 'updateScore'])->name('update-score'); // AJAX endpoint
    Route::post('/finish', [ScoringController::class, 'finishSession'])->name('finish');           // Selesaikan sesi
});

// Protected: Player Profile & Strava-like Recap
Route::prefix('player')->name('player.')->middleware('auth')->group(function () {
    Route::get('/profile', [PlayerController::class, 'profile'])->name('profile');
    Route::get('/recap', [PlayerController::class, 'recap'])->name('recap');
});

// Community
Route::prefix('communities')->name('communities.')->group(function () {
    Route::get('/', [CommunityController::class, 'index'])->name('index');
    Route::get('/{id}', [CommunityController::class, 'show'])->whereNumber('id')->name('show'); // [SMK 3] Detail komunitas

    // Protected: SMK 3 — Community Manager
    Route::middleware('auth')->group(function () {
        Route::get('/create', [CommunityController::class, 'create'])->name('create');
        Route::post('/upload-logo', [CommunityController::class, 'uploadLogo'])->name('upload-logo');
        Route::post('/', [CommunityController::class, 'store'])->name('store');                  // [SMK 3] Simpan komunitas baru ke DB
        Route::post('/{id}/join', [CommunityController::class, 'join'])->whereNumber('id')->name('join');   // [SMK 3] Join komunitas
        Route::post('/{id}/leave', [CommunityController::class, 'leave'])->whereNumber('id')->name('leave'); // [SMK 3] Leave komunitas
    });
});
