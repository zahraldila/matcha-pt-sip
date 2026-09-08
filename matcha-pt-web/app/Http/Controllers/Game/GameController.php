<?php

namespace App\Http\Controllers\Game;

use App\Http\Controllers\Controller;
use App\Services\MatchaDummyDataService;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function index(Request $request)
    {
        $games = MatchaDummyDataService::getGames();
        $selectedSport = $request->query('sport', 'all');
        if ($selectedSport !== 'all') {
            $games = array_filter($games, fn($g) => strtolower($g['sport']) === strtolower($selectedSport));
        }

        return view('games.index', compact('games', 'selectedSport'));
    }

    public function create()
    {
        $venues = MatchaDummyDataService::getVenues();
        return view('games.create', compact('venues'));
    }

    public function show($id)
    {
        $games = MatchaDummyDataService::getGames();
        $game = collect($games)->firstWhere('id', (int) $id) ?? $games[0];
        return view('games.show', compact('game'));
    }

    public function drawing($id)
    {
        $games = MatchaDummyDataService::getGames();
        $game = collect($games)->firstWhere('id', (int) $id) ?? $games[0];
        return view('games.drawing', compact('game'));
    }
}
