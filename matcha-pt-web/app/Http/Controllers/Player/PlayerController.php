<?php

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use App\Models\SessionModel;
use App\Models\Player;
use App\Services\MatchaDummyDataService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PlayerController extends Controller
{
    public function profile()
    {
        $user = Auth::user();
        $recap = MatchaDummyDataService::getPlayerRecap($user->nama ?? 'Billy Santoso');
        if ($user) {
            $recap['player']['name'] = $user->nama;
            $recap['player']['username'] = '@' . Str::slug($user->nama, '_');
        }
        return view('players.profile', compact('recap', 'user'));
    }

    public function recap(Request $request)
    {
        $user = Auth::user();
        $isHost = $user && ($user->role === 'host');
        $activeTab = $request->query('tab', $isHost ? 'host' : 'career');

        // 1. Data Riwayat Hosting (Untuk Host Game)
        $hostSessions = collect([]);
        $hostStats = [
            'total_sessions' => 0,
            'total_players' => 0,
            'completed_sessions' => 0,
            'favorite_venue' => '-',
        ];

        if ($isHost) {
            $dbSessions = SessionModel::where('host_user_id', $user->user_id)
                ->with(['sport', 'venue', 'courts', 'players'])
                ->latest('created_at')
                ->get();

            $totalPlayers = 0;
            $completedCount = 0;
            $venueCounts = [];

            $hostSessions = $dbSessions->map(function ($s) use (&$totalPlayers, &$completedCount, &$venueCounts) {
                $joinedCount = $s->players->count();
                $totalPlayers += $joinedCount;
                
                $status = $s->status_session ?? 'Open';
                if (in_array(strtolower($status), ['ready for drawing', 'in progress', 'completed', 'finished'])) {
                    $completedCount++;
                }

                $venueName = $s->venue->nama_venue ?? 'Arena Olahraga';
                $venueCounts[$venueName] = ($venueCounts[$venueName] ?? 0) + 1;

                return [
                    'id' => $s->session_id,
                    'title' => $s->nama_session,
                    'sport' => $s->sport->nama_sport ?? 'Padel',
                    'venue' => $venueName,
                    'court' => $s->courts->first()->nama_court ?? 'Court 1',
                    'date' => $s->datetime ? $s->datetime->format('d M Y') : date('d M Y'),
                    'time' => $s->waktu_session ?? '18:30 WIB',
                    'quota' => (int) ($s->jumlah_pemain ?? 6),
                    'joined_count' => $joinedCount,
                    'status' => $status,
                    'scoring_system' => $s->scoring_system ?? 'Total of 3',
                    'players' => $s->players->map(function ($p) {
                        return [
                            'name' => $p->nama,
                            'gender' => $p->gender ?? 'Male',
                            'level' => $p->level ?? 'Intermediate',
                            'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80',
                        ];
                    })->toArray(),
                ];
            });

            // Cari venue terfavorit
            arsort($venueCounts);
            $favoriteVenue = !empty($venueCounts) ? array_key_first($venueCounts) : 'Bonang Padel Arena';

            $hostStats = [
                'total_sessions' => $hostSessions->count(),
                'total_players' => $totalPlayers,
                'completed_sessions' => $completedCount,
                'favorite_venue' => $favoriteVenue,
            ];
        }

        // 2. Data Rekap Karir Pemain (Personal Career Stats)
        $recap = MatchaDummyDataService::getPlayerRecap($user->nama ?? 'Billy Santoso');
        if ($user) {
            $recap['player']['name'] = $user->nama;
            $recap['player']['username'] = '@' . Str::slug($user->nama, '_');
            $recap['player']['role'] = ucfirst($user->role ?? 'Member');
        }

        return view('players.recap', compact('user', 'isHost', 'activeTab', 'hostSessions', 'hostStats', 'recap'));
    }
}
