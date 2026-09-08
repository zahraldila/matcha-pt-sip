<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\SessionModel;
use App\Models\Venue;
use App\Models\Community;
use App\Services\MatchaDummyDataService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // 1. Fetch real sessions from Supabase
        $dbSessions = SessionModel::with(['sport', 'venue', 'courts', 'players', 'host'])->latest('created_at')->get();
        
        $formattedDbGames = $dbSessions->map(function ($s) {
            $joinedCount = $s->players->count();
            $quota = (int) ($s->jumlah_pemain ?? 6);
            $slotLeft = max(0, $quota - $joinedCount);
            $status = $slotLeft === 0 ? 'Ready for Drawing' : "Open ({$slotLeft} Slot Left)";

            return [
                'id' => $s->session_id,
                'title' => $s->nama_session,
                'sport' => $s->sport->nama_sport ?? 'Padel',
                'venue_id' => $s->venue_id,
                'venue_name' => $s->venue->nama_venue ?? 'Arena Olahraga',
                'court_name' => $s->courts->first()->nama_court ?? 'Court 1',
                'date' => $s->datetime ? $s->datetime->format('Y-m-d') : date('Y-m-d'),
                'time' => $s->waktu_session ?? '18:30 WIB',
                'duration' => '2 Jam',
                'quota' => $quota,
                'joined_count' => $joinedCount > 0 ? $joinedCount : 1,
                'status' => $status,
                'level_recommendation' => 'All Level Welcome',
                'match_format' => 'Americano / Double',
                'scoring_system' => 'Americano 32 Points',
                'host' => [
                    'name' => $s->host->nama ?? 'Host Matcha',
                    'role' => 'Host Game',
                    'level' => 'Intermediate',
                    'phone' => $s->host->no_hp ?? '-',
                    'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80',
                ],
                'participants' => $s->players->map(function ($p) {
                    return [
                        'name' => $p->nama,
                        'gender' => $p->gender ?? 'Male',
                        'age' => $p->usia ?? 25,
                        'level' => $p->level ?? 'Intermediate',
                        'is_member' => true,
                        'phone' => $p->no_hp,
                        'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80',
                    ];
                })->toArray(),
                'drawing' => null,
            ];
        })->toArray();

        $dummyGames = MatchaDummyDataService::getGames();
        $games = array_merge($formattedDbGames, $dummyGames);

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
