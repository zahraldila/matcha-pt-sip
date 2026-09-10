<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\SessionModel;
use App\Models\Venue;
use App\Models\Community;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $activeTab = $request->query('tab', 'mabar');
        $selectedSport = $request->query('sport', 'all');
        $selectedCity = $request->query('city', 'all');
        $selectedDate = $request->query('date');

        // 1. Ambil data Sesi Mabar dari Database Supabase (diutamakan sesi Open)
        $sessionQuery = SessionModel::with(['sport', 'venue', 'courts', 'players', 'host'])
            ->orderByRaw("CASE WHEN status_session = 'Open' THEN 0 WHEN status_session = 'Ready for Drawing' THEN 1 ELSE 2 END")
            ->latest('created_at');

        if ($selectedSport && $selectedSport !== 'all') {
            $sessionQuery->whereHas('sport', function ($q) use ($selectedSport) {
                $q->whereRaw('LOWER(nama_sport) LIKE ?', ['%' . strtolower($selectedSport) . '%']);
            });
        }

        if ($selectedCity && $selectedCity !== 'all') {
            $sessionQuery->whereHas('venue', function ($q) use ($selectedCity) {
                $q->whereRaw('LOWER(alamat) LIKE ?', ['%' . strtolower($selectedCity) . '%']);
            });
        }

        if ($selectedDate) {
            $sessionQuery->whereDate('datetime', $selectedDate);
        }

        $dbSessions = $sessionQuery->take(6)->get();

        $games = $dbSessions->map(function ($s) {
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
                'joined_count' => $joinedCount,
                'status' => $status,
                'level_recommendation' => 'All Level Welcome',
                'match_format' => 'Americano / Double',
                'scoring_system' => $s->scoring_system ?? 'Total of 3',
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
                        'is_member' => !empty($p->user_id),
                        'phone' => $p->no_hp,
                        'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80',
                    ];
                })->toArray(),
                'drawing' => null,
            ];
        })->toArray();

        // 2. Ambil data Venue Rekomendasi dari Database Supabase
        $venueQuery = Venue::with(['courts.sport', 'owner'])->latest();

        if ($selectedSport && $selectedSport !== 'all') {
            $venueQuery->whereHas('courts.sport', function ($q) use ($selectedSport) {
                $q->whereRaw('LOWER(nama_sport) LIKE ?', ['%' . strtolower($selectedSport) . '%']);
            });
        }

        if ($selectedCity && $selectedCity !== 'all') {
            $venueQuery->whereRaw('LOWER(alamat) LIKE ?', ['%' . strtolower($selectedCity) . '%']);
        }

        $dbVenues = $venueQuery->take(6)->get();
        $venues = $dbVenues->map(function ($v) {
            $firstCourt = $v->courts->first();
            $sportName = $firstCourt->sport->nama_sport ?? 'Padel & Tennis';
            $city = str_contains(strtolower($v->alamat ?? ''), 'bandung') ? 'Bandung' : 'Jakarta';

            return [
                'id' => $v->venue_id,
                'name' => $v->nama_venue,
                'sport' => $sportName,
                'address' => $v->alamat ?? 'Lokasi Olahraga',
                'city' => $city,
                'pic_name' => $v->owner->nama ?? 'PIC Venue',
                'pic_phone' => $v->owner->no_hp ?? '-',
                'operating_hours' => '07:00 - 22:00',
                'image' => $v->foto ?: 'https://images.unsplash.com/photo-1595435934249-5df7ed86e1c0?auto=format&fit=crop&w=800&q=80',
                'courts' => $v->courts,
            ];
        })->toArray();

        // 3. Ambil data Komunitas dari Database Supabase
        $communityQuery = Community::with('players')->latest();

        if ($selectedSport && $selectedSport !== 'all') {
            $communityQuery->where(function ($q) use ($selectedSport) {
                $q->whereRaw('LOWER(nama_community) LIKE ?', ['%' . strtolower($selectedSport) . '%'])
                  ->orWhereRaw('LOWER(deskripsi) LIKE ?', ['%' . strtolower($selectedSport) . '%']);
            });
        }

        if ($selectedCity && $selectedCity !== 'all') {
            $communityQuery->where(function ($q) use ($selectedCity) {
                $q->whereRaw('LOWER(nama_community) LIKE ?', ['%' . strtolower($selectedCity) . '%'])
                  ->orWhereRaw('LOWER(deskripsi) LIKE ?', ['%' . strtolower($selectedCity) . '%']);
            });
        }

        $dbCommunities = $communityQuery->take(6)->get();
        $communities = $dbCommunities->map(function ($c) {
            $sport = str_contains(strtolower($c->nama_community . ' ' . $c->deskripsi), 'tennis') ? 'Tennis' : 'Padel';
            if (str_contains(strtolower($c->nama_community . ' ' . $c->deskripsi), 'tennis') && str_contains(strtolower($c->nama_community . ' ' . $c->deskripsi), 'padel')) {
                $sport = 'Padel & Tennis';
            }
            return [
                'id' => $c->community_id,
                'name' => $c->nama_community,
                'sport' => $sport,
                'city' => str_contains(strtolower($c->nama_community . ' ' . $c->deskripsi), 'bandung') ? 'Bandung' : 'Jakarta',
                'members_count' => $c->players->count(),
                'admin_name' => $c->players->first()?->nama ?? 'Admin Matcha',
                'image' => $c->logo ?: 'https://images.unsplash.com/photo-1543852786-1cf6624b9987?auto=format&fit=crop&w=800&q=80',
                'tagline' => 'Komunitas Olahraga Matcha',
                'description' => $c->deskripsi ?? 'Komunitas mabar Padel & Tennis di Matcha Match Arena.',
                'schedule' => 'Rutin Setiap Pekan',
                'status' => 'Active',
            ];
        })->toArray();

        return view('dashboard.index', compact('games', 'venues', 'communities', 'selectedSport', 'selectedCity', 'selectedDate', 'activeTab'));
    }
}
