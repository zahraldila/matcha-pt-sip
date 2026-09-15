<?php

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Player;
use App\Models\SessionModel;
use App\Models\User;
use App\Services\MatchaDummyDataService;
use App\Services\SupabaseStorageService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlayerController extends Controller
{
    public function profile()
    {
        $user = Auth::user();
        $player = null;

        if ($user) {
            $player = Player::with('community')
                ->where('user_id', $user->user_id)
                ->orWhere('email', $user->email)
                ->first();

            // If player record doesn't exist yet, create one from user details
            if (! $player) {
                $player = Player::create([
                    'user_id' => $user->user_id,
                    'nama' => $user->nama,
                    'email' => $user->email,
                    'no_hp' => $user->no_hp,
                    'gender' => 'Male',
                    'usia' => 25,
                    'level' => 'Intermediate',
                    'rating' => 1.00,
                ]);
            }
        }

        $communities = Community::all();
        $recap = MatchaDummyDataService::getPlayerRecap($user->nama ?? 'Pemain Matcha');
        if ($user) {
            $recap['player']['name'] = $user->nama;
            $recap['player']['username'] = '@'.Str::slug($user->nama, '_');
            $recap['player']['level'] = $player->level ?? 'Intermediate';
            $recap['player']['community'] = $player->community->nama_community ?? 'Personal (Non-Community)';
            $recap['player']['role'] = $user->role === 'venue_owner' ? 'Venue Owner' : ($user->is_host ? 'Host Game' : 'Member');
        }

        return view('players.profile', compact('recap', 'user', 'player', 'communities'));
    }

    public function updateProfile(Request $request, SupabaseStorageService $storageService)
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('login');
        }

        // Sanitize no_hp
        if ($request->has('no_hp')) {
            $cleanNoHp = preg_replace('/[^0-9]/', '', (string) $request->no_hp);
            $request->merge(['no_hp' => $cleanNoHp]);
        }

        $request->validate([
            'nama' => 'required|string|max:255',
            'no_hp' => [
                'required',
                'string',
                'regex:/^[0-9]{9,15}$/',
                'unique:tb_user,no_hp,'.$user->user_id.',user_id',
            ],
            'gender' => 'required|in:Male,Female',
            'usia' => 'required|integer|min:10|max:90',
            'level' => 'required|in:Newbie,Beginner,Intermediate,Advanced',
            'community_id' => 'nullable',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ], [
            'nama.required' => 'Nama lengkap wajib diisi.',
            'no_hp.required' => 'Nomor WhatsApp / HP wajib diisi.',
            'no_hp.regex' => 'Nomor WhatsApp hanya boleh berupa angka (9 - 15 digit).',
            'no_hp.unique' => 'Nomor WhatsApp ini sudah digunakan oleh akun lain.',
            'gender.required' => 'Jenis kelamin wajib dipilih.',
            'usia.required' => 'Usia wajib diisi.',
            'usia.min' => 'Usia minimal adalah 10 tahun.',
            'usia.max' => 'Usia maksimal adalah 90 tahun.',
            'level.required' => 'Kategori skill level wajib dipilih.',
            'foto.image' => 'File yang diunggah harus berupa gambar.',
            'foto.mimes' => 'Format foto harus JPEG, PNG, JPG, atau WEBP.',
            'foto.max' => 'Ukuran foto profil maksimal 2 MB.',
        ]);

        try {
            DB::beginTransaction();

            $cleanNoHp = preg_replace('/[^0-9]/', '', (string) $request->no_hp);
            $communityId = ($request->community_id && $request->community_id !== 'none') ? (int) $request->community_id : null;

            // Handle Avatar Upload / Remove
            $fotoUrl = $user->foto;

            if ($request->hasFile('foto')) {
                $uploadResult = $storageService->uploadAvatar($request->file('foto'));
                if (! $uploadResult['success']) {
                    DB::rollBack();

                    return back()->withErrors(['foto' => $uploadResult['message'] ?? 'Gagal mengunggah foto profil.'])->withInput();
                }

                // Hapus foto lama jika ada
                if (! empty($user->foto)) {
                    $storageService->deleteAvatar($user->foto);
                }

                $fotoUrl = $uploadResult['url'];
            } elseif ($request->input('hapus_foto') === '1') {
                if (! empty($user->foto)) {
                    $storageService->deleteAvatar($user->foto);
                }
                $fotoUrl = null;
            }

            // 1. Update tb_user
            $user->nama = trim($request->nama);
            $user->no_hp = $cleanNoHp;
            $user->foto = $fotoUrl;
            $user->save();

            // 2. Update or Create tb_player
            $player = Player::where('user_id', $user->user_id)
                ->orWhere('email', $user->email)
                ->first();

            if ($player) {
                $player->update([
                    'user_id' => $user->user_id,
                    'nama' => trim($request->nama),
                    'no_hp' => $cleanNoHp,
                    'gender' => $request->gender,
                    'usia' => (int) $request->usia,
                    'level' => $request->level,
                    'community_id' => $communityId,
                    'foto' => $fotoUrl,
                ]);
            } else {
                Player::create([
                    'user_id' => $user->user_id,
                    'community_id' => $communityId,
                    'nama' => trim($request->nama),
                    'usia' => (int) $request->usia,
                    'gender' => $request->gender,
                    'level' => $request->level,
                    'rating' => 1.00,
                    'no_hp' => $cleanNoHp,
                    'email' => strtolower(trim($user->email)),
                    'foto' => $fotoUrl,
                ]);
            }

            DB::commit();

            return back()->with('success', 'Profil pemain berhasil diperbarui!');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withErrors(['error' => 'Gagal memperbarui profil: '.$e->getMessage()]);
        }
    }

    public function recap(Request $request)
    {
        $user = Auth::user();
        $isHost = $user && (bool) $user->is_host;
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

            $mappedSessions = $dbSessions->map(function ($s) use (&$totalPlayers, &$completedCount, &$venueCounts) {
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
            $favoriteVenue = ! empty($venueCounts) ? array_key_first($venueCounts) : 'Bonang Padel Arena';

            $totalCount = $mappedSessions->count();
            $hostStats = [
                'total_sessions' => $totalCount,
                'total_players' => $totalPlayers,
                'completed_sessions' => $completedCount,
                'favorite_venue' => $favoriteVenue,
            ];

            // Pagination (5 sesi per halaman)
            $perPage = 5;
            $currentPage = (int) $request->input('page', 1);
            $currentItems = $mappedSessions->slice(($currentPage - 1) * $perPage, $perPage)->values();

            $hostSessions = new LengthAwarePaginator(
                $currentItems,
                $totalCount,
                $perPage,
                $currentPage,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        }

        // 2. Data Rekap Karir Pemain (Personal Career Stats)
        $recap = MatchaDummyDataService::getPlayerRecap($user->nama ?? 'Pemain Matcha');
        if ($user) {
            $player = Player::with('community')->where('user_id', $user->user_id)->orWhere('email', $user->email)->first();
            $recap['player']['name'] = $user->nama;
            $recap['player']['username'] = '@'.Str::slug($user->nama, '_');
            $recap['player']['role'] = $user->role === 'venue_owner' ? 'Venue Owner' : ($user->is_host ? 'Host Game' : 'Member');
            if (! empty($user->foto)) {
                $recap['player']['avatar'] = $user->foto;
            }
            if ($player) {
                $recap['player']['level'] = $player->level ?? 'Intermediate';
                $recap['player']['community'] = $player->community->nama_community ?? 'Personal (Non-Community)';
                if (! empty($player->foto)) {
                    $recap['player']['avatar'] = $player->foto;
                }
            }
        }

        return view('players.recap', compact('user', 'isHost', 'activeTab', 'hostSessions', 'hostStats', 'recap'));
    }

    public function toggleHost(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->route('login');
        }

        // Balikkan nilai boolean is_host
        $user->is_host = ! $user->is_host;
        $user->save();

        $statusMsg = $user->is_host
            ? 'Mode Host berhasil diaktifkan! Sekarang kamu bisa membuat pertandingan.'
            : 'Mode Host dinonaktifkan. Status kamu kembali menjadi Pemain biasa.';

        return back()->with('success', $statusMsg);
    }
}
