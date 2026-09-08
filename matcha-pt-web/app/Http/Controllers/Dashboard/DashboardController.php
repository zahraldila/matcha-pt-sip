<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\MatchaDummyDataService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $games = MatchaDummyDataService::getGames();
        $venues = MatchaDummyDataService::getVenues();
        $communities = MatchaDummyDataService::getCommunities();
        $playerRecap = MatchaDummyDataService::getPlayerRecap();

        // Sport filter if requested
        $selectedSport = $request->query('sport', 'all');
        if ($selectedSport !== 'all') {
            $games = array_filter($games, fn($g) => strtolower($g['sport']) === strtolower($selectedSport));
        }

        return view('dashboard.index', compact('games', 'venues', 'communities', 'playerRecap', 'selectedSport'));
    }
}
