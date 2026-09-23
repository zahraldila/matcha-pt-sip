<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Game\GameController;
use App\Models\Community;
use App\Models\SessionModel;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $activeTab = $request->query('tab', 'mabar');
        $selectedSport = $request->query('sport', 'all');
        $selectedCity = $request->query('city', 'all');
        $selectedDate = $request->query('date');

        $startOfToday = now()->startOfDay()->toDateTimeString();

        // 1. Ambil data Sesi Mabar dari Database Supabase (diurutkan dari terdekat: hari ini, besok, lusa, dst)
        $sessionQuery = SessionModel::with(['sport', 'venue', 'courts', 'players.user', 'host'])
            ->orderByRaw("CASE WHEN status_session NOT IN ('Finished', 'Completed') AND datetime >= ? THEN 0 ELSE 1 END", [$startOfToday])
            ->orderByRaw("CASE WHEN status_session NOT IN ('Finished', 'Completed') AND datetime >= ? THEN datetime END ASC", [$startOfToday])
            ->orderBy('datetime', 'desc');

        if ($selectedSport && $selectedSport !== 'all') {
            $sessionQuery->whereHas('sport', function ($q) use ($selectedSport) {
                $q->whereRaw('LOWER(nama_sport) LIKE ?', ['%'.strtolower($selectedSport).'%']);
            });
        }

        if ($selectedCity && $selectedCity !== 'all') {
            $sessionQuery->whereHas('venue', function ($q) use ($selectedCity) {
                $q->whereRaw('LOWER(alamat) LIKE ?', ['%'.strtolower($selectedCity).'%']);
            });
        }

        if ($selectedDate) {
            $sessionQuery->whereDate('datetime', $selectedDate);
        }

        $dbSessions = $sessionQuery->take(6)->get();

        $user = Auth::user();
        $userId = $user?->user_id;
        $userEmail = $user ? strtolower(trim($user->email ?? '')) : null;
        $userName = $user ? strtolower(trim($user->nama ?? '')) : null;

        $games = $dbSessions->map(function ($s) use ($userId, $userEmail, $userName) {
            $joinedCount = $s->players->count();
            $quota = (int) ($s->jumlah_pemain ?? 6);
            $slotLeft = max(0, $quota - $joinedCount);
            $isFinished = in_array(strtolower(trim((string) $s->status_session)), ['finished', 'completed'], true);
            $status = $isFinished
                ? 'Selesai Mabar'
                : ($slotLeft === 0 ? 'Ready for Drawing' : "Open ({$slotLeft} Slot Left)");
            $isJoinedByMe = $s->players->contains(function ($p) use ($userId, $userEmail, $userName) {
                if ($userId && $p->user_id && $p->user_id == $userId) {
                    return true;
                }
                if ($userEmail && $p->email && strtolower(trim($p->email)) === $userEmail) {
                    return true;
                }
                if ($userName && $p->nama && strtolower(trim($p->nama)) === $userName) {
                    return true;
                }

                return false;
            });

            return [
                'id' => $s->session_id,
                'title' => $s->nama_session,
                'sport' => $s->sport->nama_sport ?? 'Padel',
                'venue_id' => $s->venue_id,
                'venue_name' => $s->venue->nama_venue ?? 'Arena Olahraga',
                'court_name' => $s->courts->first()->nama_court ?? 'Court 1',
                'date' => $s->datetime ? $s->datetime->format('Y-m-d') : date('Y-m-d'),
                'time' => GameController::resolveSessionDisplayTime($s),
                'duration' => GameController::resolveSessionDuration($s),
                'quota' => $quota,
                'joined_count' => $joinedCount,
                'is_joined_by_me' => $isJoinedByMe,
                'status' => $status,
                'is_finished' => $isFinished,
                'level_recommendation' => 'All Level Welcome',
                'match_format' => 'Americano / Double',
                'scoring_system' => $s->scoring_system ?? 'Total of 3',
                'host' => [
                    'name' => $s->host->nama ?? 'Host Matcha',
                    'role' => 'Host Game',
                    'level' => 'Intermediate',
                    'phone' => $s->host->no_hp ?? '-',
                    'avatar' => $s->host->foto ?? null,
                ],
                'participants' => $s->players->map(function ($p) {
                    return [
                        'name' => $p->nama,
                        'gender' => $p->gender ?? 'Male',
                        'age' => $p->usia,
                        'level' => $p->level ?? 'Intermediate',
                        'is_member' => ! empty($p->user_id),
                        'phone' => $p->no_hp,
                        'avatar' => $p->foto ?? ($p->user->foto ?? null),
                    ];
                })->toArray(),
                'drawing' => null,
            ];
        })->toArray();

        // 2. Ambil data Venue Rekomendasi dari Database Supabase
        $venueQuery = Venue::with(['courts.sport', 'owner'])->latest();

        if ($selectedSport && $selectedSport !== 'all') {
            $venueQuery->whereHas('courts.sport', function ($q) use ($selectedSport) {
                $q->whereRaw('LOWER(nama_sport) LIKE ?', ['%'.strtolower($selectedSport).'%']);
            });
        }

        if ($selectedCity && $selectedCity !== 'all') {
            $venueQuery->whereRaw('LOWER(alamat) LIKE ?', ['%'.strtolower($selectedCity).'%']);
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
        $communityQuery = Community::with(['players.user', 'creator'])->latest();

        if ($selectedSport && $selectedSport !== 'all') {
            $communityQuery->where(function ($q) use ($selectedSport) {
                $q->whereRaw('LOWER(nama_community) LIKE ?', ['%'.strtolower($selectedSport).'%'])
                    ->orWhereRaw('LOWER(deskripsi) LIKE ?', ['%'.strtolower($selectedSport).'%']);
            });
        }

        if ($selectedCity && $selectedCity !== 'all') {
            $communityQuery->where(function ($q) use ($selectedCity) {
                $q->whereRaw('LOWER(kota_homebase) LIKE ?', ['%'.strtolower($selectedCity).'%'])
                    ->orWhereRaw('LOWER(nama_community) LIKE ?', ['%'.strtolower($selectedCity).'%'])
                    ->orWhereRaw('LOWER(deskripsi) LIKE ?', ['%'.strtolower($selectedCity).'%']);
            });
        }

        $dbCommunities = $communityQuery->take(6)->get();
        $communities = $dbCommunities->map(function ($c) {
            $sport = $c->sport;

            return [
                'id' => $c->community_id,
                'name' => $c->nama_community,
                'sport' => $sport,
                'city' => $c->kota_homebase ?: (str_contains(strtolower($c->nama_community.' '.$c->deskripsi), 'bandung') ? 'Bandung' : 'Jakarta'),
                'members_count' => $c->players->count(),
                'admin_name' => $c->admin_name,
                'image' => $c->logo ?: asset('images/default-community.jpg'),
                'tagline' => $c->tagline ?: 'Komunitas Olahraga Matcha',
                'description' => $c->deskripsi ?? ('Komunitas mabar '.$sport.' di Matcha Match Arena.'),
                'schedule' => $c->jadwal_rutin ?: 'Rutin Setiap Pekan',
                'status' => $c->status_keanggotaan ?: 'Active',
            ];
        })->toArray();

        // 4. Ambil daftar kota dari venue (kota) dan komunitas (kota_homebase), lalu merge
        $venueCities = Venue::query()
            ->whereNotNull('kota')
            ->where('kota', '!=', '')
            ->pluck('kota');

        $communityCities = Community::query()
            ->whereNotNull('kota_homebase')
            ->where('kota_homebase', '!=', '')
            ->pluck('kota_homebase');

        $cities = $venueCities->merge($communityCities)
            ->map(function ($city) {
                $c = trim($city);
                if (ctype_lower($c) || ctype_upper($c)) {
                    return ucwords(strtolower($c));
                }

                return $c;
            })
            ->filter()
            ->unique(fn ($city) => strtolower($city))
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        if ($cities->isEmpty()) {
            $cities = collect(['Jakarta', 'Bandung']);
        }

        return view('dashboard.index', compact('games', 'venues', 'communities', 'cities', 'selectedSport', 'selectedCity', 'selectedDate', 'activeTab'));
    }
}
