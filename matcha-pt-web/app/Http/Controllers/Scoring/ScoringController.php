<?php

namespace App\Http\Controllers\Scoring;

use App\Http\Controllers\Controller;
use App\Services\MatchaDummyDataService;
use Illuminate\Http\Request;

class ScoringController extends Controller
{
    public function live($id = 1)
    {
        $games = MatchaDummyDataService::getGames();
        $game = collect($games)->firstWhere('id', (int) $id) ?? $games[0];
        return view('scoring.live', compact('game'));
    }

    public function recap($id = 1)
    {
        $games = MatchaDummyDataService::getGames();
        $game = collect($games)->firstWhere('id', (int) $id) ?? $games[0];
        $playerRecap = MatchaDummyDataService::getPlayerRecap();
        return view('scoring.recap', compact('game', 'playerRecap'));
    }
}
