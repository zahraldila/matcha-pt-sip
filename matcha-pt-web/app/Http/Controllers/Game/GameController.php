<?php

namespace App\Http\Controllers\Game;

use App\Http\Controllers\Controller;
use App\Models\SessionModel;
use App\Models\Venue;
use App\Models\Court;
use App\Models\Sport;
use App\Models\Player;
use App\Services\MatchaDummyDataService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GameController extends Controller
{
    public function index(Request $request)
    {
        $selectedSport = $request->query('sport', 'all');

        // Fetch real sessions from Supabase
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

        // Merge with dummy games so there's rich data, prioritizing real DB sessions
        $dummyGames = MatchaDummyDataService::getGames();
        $games = array_merge($formattedDbGames, $dummyGames);

        if ($selectedSport !== 'all') {
            $games = array_filter($games, fn($g) => strtolower($g['sport']) === strtolower($selectedSport));
        }

        return view('games.index', compact('games', 'selectedSport'));
    }

    public function create()
    {
        if (Auth::user()->role !== 'host') {
            return redirect()->route('games.index')->with('error', 'Akses ditolak: Fitur ini khusus untuk akun Host Game.');
        }

        $venues = Venue::with('courts')->get();
        if ($venues->isEmpty()) {
            $venues = MatchaDummyDataService::getVenues();
        }
        return view('games.create', compact('venues'));
    }

    public function createSchedule()
    {
        if (Auth::user()->role !== 'host') {
            return redirect()->route('games.index')->with('error', 'Akses ditolak: Fitur pembukaan sesi mabar khusus untuk akun Host Game.');
        }

        $venues = Venue::with('courts')->get();
        $sports = Sport::all();
        return view('games.schedule', compact('venues', 'sports'));
    }

    public function storeSchedule(Request $request)
    {
        if (Auth::user()->role !== 'host') {
            return redirect()->route('games.index')->with('error', 'Akses ditolak: Fitur pembukaan sesi mabar khusus untuk akun Host Game.');
        }

        $request->validate([
            'nama_session' => 'required|string|max:255',
            'sport_id' => 'required|integer',
            'venue_id' => 'required|integer',
            'court_id' => 'required|integer',
            'tanggal' => 'required|date',
            'jam' => 'required|string',
            'durasi' => 'required|string',
            'jumlah_pemain' => 'required|integer|min:4|max:20',
            'level_rekomendasi' => 'nullable|string',
            'deskripsi' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $waktuSession = "{$request->jam} WIB ({$request->durasi})";
            $dateTime = "{$request->tanggal} {$request->jam}:00";

            // 1. Create tb_session
            $session = SessionModel::create([
                'host_user_id' => Auth::id() ?? 1,
                'sport_id' => $request->sport_id,
                'venue_id' => $request->venue_id,
                'nama_session' => $request->nama_session,
                'waktu_session' => $waktuSession,
                'datetime' => $dateTime,
                'status_session' => 'Open',
                'jumlah_pemain' => (string) $request->jumlah_pemain,
            ]);

            // 2. Attach court
            $session->courts()->attach($request->court_id);

            // 3. Attach current user/host to session players
            $player = Player::where('user_id', Auth::id())->first();
            if ($player) {
                $session->players()->attach($player->player_id);
            }

            DB::commit();

            return redirect()->route('games.index')->with('success', 'Sesi mabar baru berhasil dibuat dan dipublikasikan!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->withErrors([
                'error' => 'Gagal membuat sesi mabar: ' . $e->getMessage(),
            ]);
        }
    }

    public function show($id)
    {
        $dbSession = SessionModel::with(['sport', 'venue', 'courts', 'players', 'host'])->find((int) $id);

        if ($dbSession) {
            $quota = (int) ($dbSession->jumlah_pemain ?? 6);
            $joinedCount = $dbSession->players->count();
            $slotLeft = max(0, $quota - $joinedCount);
            $status = $slotLeft === 0 ? 'Ready for Drawing' : "Open ({$slotLeft} Slot Left)";

            $game = [
                'id' => $dbSession->session_id,
                'title' => $dbSession->nama_session,
                'sport' => $dbSession->sport->nama_sport ?? 'Padel',
                'venue_id' => $dbSession->venue_id,
                'venue_name' => $dbSession->venue->nama_venue ?? 'Arena Olahraga',
                'court_name' => $dbSession->courts->first()->nama_court ?? 'Court 1',
                'date' => $dbSession->datetime ? $dbSession->datetime->format('Y-m-d') : date('Y-m-d'),
                'time' => $dbSession->waktu_session ?? '18:30 WIB',
                'duration' => '2 Jam',
                'quota' => $quota,
                'joined_count' => $joinedCount > 0 ? $joinedCount : 1,
                'status' => $status,
                'level_recommendation' => 'All Level Welcome',
                'match_format' => 'Americano / Double',
                'scoring_system' => 'Americano 32 Points',
                'host' => [
                    'name' => $dbSession->host->nama ?? 'Host Matcha',
                    'role' => 'Host Game',
                    'level' => 'Intermediate',
                    'phone' => $dbSession->host->no_hp ?? '-',
                    'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80',
                ],
                'participants' => $dbSession->players->map(function ($p) {
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
        } else {
            $games = MatchaDummyDataService::getGames();
            $game = collect($games)->firstWhere('id', (int) $id) ?? $games[0];
        }

        return view('games.show', compact('game'));
    }

    public function drawing($id)
    {
        $games = MatchaDummyDataService::getGames();
        $game = collect($games)->firstWhere('id', (int) $id) ?? $games[0];
        return view('games.drawing', compact('game'));
    }
}
