<?php

namespace App\Http\Controllers\Venue;

use App\Http\Controllers\Controller;
use App\Services\MatchaDummyDataService;
use Illuminate\Http\Request;

class VenueController extends Controller
{
    public function index()
    {
        $venues = MatchaDummyDataService::getVenues();
        return view('venues.index', compact('venues'));
    }

    public function show($id)
    {
        $venues = MatchaDummyDataService::getVenues();
        $venue = collect($venues)->firstWhere('id', (int) $id) ?? $venues[0];
        return view('venues.show', compact('venue'));
    }

    public function create()
    {
        return view('venues.create');
    }
}
