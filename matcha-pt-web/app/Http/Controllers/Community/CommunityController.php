<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Services\MatchaDummyDataService;
use Illuminate\Http\Request;

class CommunityController extends Controller
{
    public function index()
    {
        $communities = MatchaDummyDataService::getCommunities();
        return view('communities.index', compact('communities'));
    }

    public function create()
    {
        return view('communities.create');
    }
}
