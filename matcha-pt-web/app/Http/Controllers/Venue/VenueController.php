<?php

namespace App\Http\Controllers\Venue;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use App\Services\MatchaDummyDataService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;

class VenueController extends Controller
{
    public function index(Request $request)
    {
        $currentUserId = Auth::id();
        $activeTab = $request->get('tab', 'all');
        $selectedSport = $request->get('sport', 'all');
        $search = trim($request->get('q', $request->get('search', '')));

        $dbVenues = Venue::with(['courts.sport', 'owner'])->latest()->get();
        if ($dbVenues->isNotEmpty()) {
            $allVenues = $dbVenues->map(function ($v) use ($currentUserId) {
                $rawPhotos = array_filter(array_map('trim', explode(',', $v->foto ?? '')));
                $resolvedPhotos = array_values(array_map(function ($p) {
                    return str_starts_with($p, 'http') ? $p : asset($p);
                }, $rawPhotos));
                $mainPhoto = $resolvedPhotos[0] ?? 'https://images.unsplash.com/photo-1595435934249-5df7ed86e1c0?auto=format&fit=crop&w=800&q=80';

                $sports = $v->courts->pluck('sport.nama_sport')->filter()->unique()->values();
                if ($sports->count() === 1) {
                    $sportName = $sports->first();
                } elseif ($sports->count() > 1) {
                    $sportName = 'Padel & Tennis';
                } else {
                    $sportName = 'Padel';
                }

                $isMine = $currentUserId && ($v->owner_user_id == $currentUserId);

                return [
                    'id' => $v->venue_id,
                    'owner_user_id' => $v->owner_user_id,
                    'is_mine' => $isMine,
                    'name' => $v->nama_venue,
                    'sport' => $sportName,
                    'address' => $v->alamat,
                    'city' => $v->kota ?: 'Jakarta',
                    'pic_name' => $v->nama_pic ?: ($v->owner->nama ?? 'PIC Venue'),
                    'pic_phone' => $v->no_whatsapp ?: ($v->owner->no_hp ?? '-'),
                    'operating_hours' => $v->jam_operasional ?: '07:00 - 22:00',
                    'image' => $mainPhoto,
                    'gallery' => $resolvedPhotos,
                    'facilities' => explode(',', $v->fasilitas ?? 'WC, Kantin, Parkir'),
                    'description' => $v->alamat,
                    'courts' => $v->courts,
                ];
            });
        } else {
            $allVenues = collect(MatchaDummyDataService::getVenues());
        }

        // Sport filter
        if ($selectedSport !== 'all') {
            $allVenues = $allVenues->filter(function ($v) use ($selectedSport) {
                return str_contains(strtolower($v['sport']), strtolower($selectedSport));
            });
        }

        // Search filter (name, city, address, facilities, pic_name)
        if ($search !== '') {
            $searchLower = strtolower($search);
            $allVenues = $allVenues->filter(function ($v) use ($searchLower) {
                $inName = str_contains(strtolower($v['name'] ?? ''), $searchLower);
                $inCity = str_contains(strtolower($v['city'] ?? ''), $searchLower);
                $inAddress = str_contains(strtolower($v['address'] ?? ''), $searchLower);
                $inPic = str_contains(strtolower($v['pic_name'] ?? ''), $searchLower);
                $inFacilities = str_contains(strtolower(implode(' ', $v['facilities'] ?? [])), $searchLower);
                $inSport = str_contains(strtolower($v['sport'] ?? ''), $searchLower);
                return $inName || $inCity || $inAddress || $inPic || $inFacilities || $inSport;
            });
        }

        // Tab counts (reflecting search results if active)
        $totalVenuesCount = $allVenues->count();
        $myVenuesCount = $allVenues->where('is_mine', true)->count();

        // Active tab filter
        if ($activeTab === 'my_venues') {
            $filteredVenues = $allVenues->where('is_mine', true)->values();
        } else {
            $filteredVenues = $allVenues->values();
        }

        // Pagination: 6 items per page
        $perPage = 6;
        $currentPage = Paginator::resolveCurrentPage('page') ?: 1;
        $totalItems = $filteredVenues->count();
        $currentItems = $filteredVenues->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $venues = new LengthAwarePaginator(
            $currentItems,
            $totalItems,
            $perPage,
            $currentPage,
            ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page']
        );
        $venues->withQueryString();

        return view('venues.index', compact('venues', 'activeTab', 'selectedSport', 'search', 'myVenuesCount', 'totalVenuesCount'));
    }

    public function show($id)
    {
        $dbVenue = Venue::with(['courts.sport', 'owner'])->find((int) $id);
        if ($dbVenue) {
            $rawPhotos = array_filter(array_map('trim', explode(',', $dbVenue->foto ?? '')));
            $resolvedPhotos = array_values(array_map(function ($p) {
                return str_starts_with($p, 'http') ? $p : asset($p);
            }, $rawPhotos));
            $mainPhoto = $resolvedPhotos[0] ?? 'https://images.unsplash.com/photo-1595435934249-5df7ed86e1c0?auto=format&fit=crop&w=800&q=80';

            $sports = $dbVenue->courts->pluck('sport.nama_sport')->filter()->unique()->values();
            if ($sports->count() === 1) {
                $sportName = $sports->first();
            } elseif ($sports->count() > 1) {
                $sportName = 'Padel & Tennis';
            } else {
                $sportName = 'Padel';
            }

            $isMine = Auth::check() && ($dbVenue->owner_user_id == Auth::id());

            $venue = [
                'id' => $dbVenue->venue_id,
                'owner_user_id' => $dbVenue->owner_user_id,
                'is_mine' => $isMine,
                'name' => $dbVenue->nama_venue,
                'sport' => $sportName,
                'address' => $dbVenue->alamat,
                'city' => $dbVenue->kota ?: 'Jakarta',
                'pic_name' => $dbVenue->nama_pic ?: ($dbVenue->owner->nama ?? 'PIC Venue'),
                'pic_phone' => $dbVenue->no_whatsapp ?: ($dbVenue->owner->no_hp ?? '-'),
                'operating_hours' => $dbVenue->jam_operasional ?: '07:00 - 22:00',
                'hari_buka' => $dbVenue->hari_buka ?: 'Setiap Hari (Senin - Minggu)',
                'unavailability_note' => $dbVenue->catatan ?: 'Sesuai jadwal ketersediaan lapangan reguler.',
                'catatan' => $dbVenue->catatan,
                'image' => $mainPhoto,
                'gallery' => $resolvedPhotos,
                'raw_photos' => $rawPhotos,
                'facilities' => explode(',', $dbVenue->fasilitas ?? 'WC, Kantin, Parkir'),
                'description' => $dbVenue->alamat,
                'courts' => $dbVenue->courts->map(fn ($court) => [
                    'id' => $court->court_id,
                    'name' => $court->nama_court,
                    'sport' => $court->sport->nama_sport ?? 'Padel',
                    'status' => $court->status_ketersediaan ?? 'Available',
                    'type' => $court->tipe_court ?? 'Tidak ditentukan',
                ]),
            ];
        } else {
            $venues = MatchaDummyDataService::getVenues();
            $venue = collect($venues)->firstWhere('id', (int) $id) ?? $venues[0];
            if (!isset($venue['gallery'])) {
                $venue['gallery'] = [$venue['image'] ?? 'https://images.unsplash.com/photo-1595435934249-5df7ed86e1c0?auto=format&fit=crop&w=800&q=80'];
            }
            $venue['raw_photos'] = $venue['gallery'];
            $venue['is_mine'] = false;
        }

        return view('venues.show', compact('venue'));
    }

    /**
     * Update galeri foto venue (Tambah / Hapus foto).
     * Route: POST /venues/{id}/photos
     */
    public function updatePhotos(Request $request, $id)
    {
        @set_time_limit(120);

        $venue = Venue::where('venue_id', $id)
            ->where('owner_user_id', Auth::id())
            ->firstOrFail();

        $request->validate([
            'existing_photos'   => 'nullable|array',
            'existing_photos.*' => 'string',
            'new_photos'        => 'nullable|array|max:12',
            'new_photos.*'      => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $finalPhotos = [];

        // 1. Simpan foto lama yang tidak dihapus
        if ($request->has('existing_photos') && is_array($request->input('existing_photos'))) {
            $finalPhotos = array_values(array_filter(array_map('trim', $request->input('existing_photos'))));
        }

        // 2. Upload foto-foto baru
        if ($request->hasFile('new_photos')) {
            $supabase = app(\App\Services\SupabaseStorageService::class);
            $uploadDir = public_path('uploads/venues');
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            foreach ($request->file('new_photos') as $file) {
                if ($file && $file->isValid()) {
                    $supabaseUrl = $supabase->upload($file, 'venues');
                    if ($supabaseUrl) {
                        $finalPhotos[] = $supabaseUrl;
                    } else {
                        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                        $file->move($uploadDir, $filename);
                        $finalPhotos[] = 'uploads/venues/' . $filename;
                    }
                }
            }
        }

        $fotoString = !empty($finalPhotos) ? implode(', ', $finalPhotos) : null;
        $venue->update(['foto' => $fotoString]);

        return redirect()->route('venues.show', $venue->venue_id)
            ->with('success', 'Galeri foto venue berhasil diperbarui!');
    }

    public function create()
    {
        if (Auth::user()->role !== 'venue_owner') {
            return redirect()->route('venues.index')->with('error', 'Akses ditolak: Fitur pendaftaran venue khusus untuk Pemilik Venue.');
        }

        return view('venues.create');
    }

    /**
     * Simpan venue baru ke database.
     * Route: POST /venues
     */
    public function store(Request $request)
    {
        @set_time_limit(120);
        $user = Auth::user();

        if (!$user || $user->role !== 'venue_owner') {
            return redirect()->route('venues.index')->with('error', 'Akses ditolak.');
        }

        $validated = $request->validate([
            'nama_venue'      => 'required|string|max:255',
            'alamat'          => 'required|string',
            'kota_wilayah'    => 'nullable|string|max:150',
            'kota'            => 'nullable|string|max:150',
            'jam_operasional' => 'nullable|string|max:100',
            'hari_buka'       => 'nullable|string|max:100',
            'nama_pic'        => 'nullable|string|max:150',
            'no_whatsapp'     => 'nullable|string|max:50',
            'catatan'         => 'nullable|string',
            'fasilitas'       => 'nullable|array',
            'fasilitas.*'     => 'string|max:100',
            'facilities'      => 'nullable|array',
            'facilities.*'    => 'string|max:100',
            'foto'            => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'fotos'           => 'nullable|array|max:12',
            'fotos.*'         => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $savedPaths = [];
        $supabase = app(\App\Services\SupabaseStorageService::class);
        $uploadDir = public_path('uploads/venues');
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filesToUpload = [];
        if ($request->hasFile('fotos')) {
            $filesToUpload = $request->file('fotos');
        } elseif ($request->hasFile('foto')) {
            $filesToUpload = [$request->file('foto')];
        }

        foreach ($filesToUpload as $file) {
            if ($file && $file->isValid()) {
                // Try Supabase Storage first
                $supabaseUrl = $supabase->upload($file, 'venues');
                if ($supabaseUrl) {
                    $savedPaths[] = $supabaseUrl;
                } else {
                    // Fallback to local storage
                    $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                    $file->move($uploadDir, $filename);
                    $savedPaths[] = 'uploads/venues/' . $filename;
                }
            }
        }

        $fotoString = !empty($savedPaths) ? implode(', ', $savedPaths) : null;

        $kotaFinal = $request->input('kota') ?? $request->input('kota_wilayah');
        $fasilitasArr = $request->input('facilities') ?? $request->input('fasilitas') ?? [];
        $catatanInput = $request->input('catatan') ?? $request->input('maintenance_note');

        $venue = Venue::create([
            'nama_venue'      => $validated['nama_venue'],
            'alamat'          => $validated['alamat'],
            'kota'            => $kotaFinal,
            'jam_operasional' => $request->input('jam_operasional') ?: '06:00 - 23:00 WIB',
            'hari_buka'       => $request->input('hari_buka') ?: 'Setiap Hari (Senin - Minggu)',
            'nama_pic'        => $request->input('nama_pic'),
            'no_whatsapp'     => $request->input('no_whatsapp'),
            'catatan'         => $catatanInput,
            'foto'            => $fotoString,
            'owner_user_id'   => $user->user_id,
            'fasilitas'       => implode(', ', $fasilitasArr),
        ]);

        return redirect()->route('venues.courts.create', [
            'id'    => $venue->venue_id,
            'count' => $request->input('jumlah_court', 1),
            'sport' => $request->input('sport_type', 'Padel'),
            'type'  => $request->input('arena_type', 'Indoor'),
        ])->with('success', 'Venue berhasil didaftarkan. Silakan lengkapi data lapangan Anda.');
    }
}
