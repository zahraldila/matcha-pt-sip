<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Game\GameController;
use App\Http\Controllers\Venue\VenueController;
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

    // Protected: Create Game (Only for Authenticated Users / Host)
    Route::middleware('auth')->group(function () {
        Route::get('/create', [GameController::class, 'create'])->name('create');
    });
});

// Venues & Courts
Route::prefix('venues')->name('venues.')->group(function () {
    Route::get('/', [VenueController::class, 'index'])->name('index');
    Route::get('/{id}', [VenueController::class, 'show'])->whereNumber('id')->name('show');

    // Protected: Register Venue (Only for Authenticated Users / Venue Owner)
    Route::middleware('auth')->group(function () {
        Route::get('/create', [VenueController::class, 'create'])->name('create');
    });
});

// Live Match Scoring Console & Recap
Route::prefix('scoring')->name('scoring.')->group(function () {
    Route::get('/live/{id?}', [ScoringController::class, 'live'])->name('live');
    Route::get('/recap/{id?}', [ScoringController::class, 'recap'])->name('recap');
});

// Protected: Player Profile & Strava-like Recap
Route::prefix('player')->name('player.')->middleware('auth')->group(function () {
    Route::get('/profile', [PlayerController::class, 'profile'])->name('profile');
    Route::get('/recap', [PlayerController::class, 'recap'])->name('recap');
});

// Community
Route::prefix('communities')->name('communities.')->group(function () {
    Route::get('/', [CommunityController::class, 'index'])->name('index');

    // Protected: Create Community (Only for Authenticated Members)
    Route::middleware('auth')->group(function () {
        Route::get('/create', [CommunityController::class, 'create'])->name('create');
    });
});
