<?php

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use App\Services\MatchaDummyDataService;
use Illuminate\Http\Request;

class PlayerController extends Controller
{
    public function profile()
    {
        $recap = MatchaDummyDataService::getPlayerRecap('Billy Santoso');
        return view('players.profile', compact('recap'));
    }

    public function recap()
    {
        $recap = MatchaDummyDataService::getPlayerRecap('Billy Santoso');
        return view('players.recap', compact('recap'));
    }
}
