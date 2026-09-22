<?php

namespace App\Http\Controllers\Venue;

use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\Sport;
use App\Models\Venue;
use App\Services\MatchaDummyDataService;
use App\Services\SupabaseStorageService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
        @set_time_limit(120);
        $dbVenue = Venue::with(['courts.sport', 'owner'])->findOrFail((int) $id);

        $rawPhotos = array_values(array_filter(array_map('trim', explode(',', $dbVenue->foto ?? ''))));
        $validPhotos = [];
        $validRawPhotos = [];

        foreach ($rawPhotos as $p) {
            if (str_starts_with($p, 'http://') || str_starts_with($p, 'https://')) {
                $validPhotos[] = $p;
                $validRawPhotos[] = $p;
            } else {
                $clean = ltrim($p, '/\\');
                if ($clean !== '' && ! str_contains($clean, '\\\\') && ! str_contains($clean, ':') && file_exists(public_path($clean))) {
                    $validPhotos[] = asset($clean);
                    $validRawPhotos[] = $p;
                }
            }
        }

        $resolvedPhotos = $validPhotos;
        $mainPhoto = $resolvedPhotos[0] ?? null;

        $sports = $dbVenue->courts->pluck('sport.nama_sport')->filter()->unique()->values();
        if ($sports->count() === 1) {
            $sportName = $sports->first();
        } elseif ($sports->count() > 1) {
            $sportName = 'Padel & Tennis';
        } else {
            $sportName = null;
        }

        $isMine = Auth::check() && ($dbVenue->owner_user_id == Auth::id());

        $facilities = array_values(array_filter(array_map('trim', explode(',', $dbVenue->fasilitas ?? ''))));

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
            'raw_photos' => $validRawPhotos,
            'facilities' => $facilities,
            'description' => $dbVenue->alamat,
            'google_maps_url' => $dbVenue->google_maps_url,
            'sport_type' => $dbVenue->sport_type ?: $sportName,
            'jumlah_court' => $dbVenue->jumlah_court ?: $dbVenue->courts->count(),
            'tipe_arena' => $dbVenue->tipe_arena,
            'jenis_permukaan' => $dbVenue->jenis_permukaan,
            'courts' => $dbVenue->courts->map(function ($court) {
                $type = $court->tipe_court;
                if (empty($type) && ! empty($court->deskripsi)) {
                    if (preg_match('/Tipe:\s*(Indoor|Outdoor|Semi-Indoor)/i', $court->deskripsi, $matches)) {
                        $type = $matches[1];
                    }
                }
                $harga = $court->harga_per_jam;
                if (empty($harga) && ! empty($court->deskripsi)) {
                    if (preg_match('/(?:Rp|IDR)\s*([\d\.,]+)/i', $court->deskripsi, $pMatches)) {
                        $harga = (float) str_replace(['.', ','], '', $pMatches[1]);
                    }
                }

                return [
                    'id' => $court->court_id,
                    'name' => $court->nama_court,
                    'sport' => $court->sport?->nama_sport,
                    'status' => $court->status_ketersediaan ?? 'Available',
                    'type' => $type ?? 'Tidak ditentukan',
                    'harga_per_jam' => (float) ($harga ?? 0),
                ];
            }),
        ];

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
            'existing_photos' => 'nullable|array',
            'existing_photos.*' => 'string',
            'new_photos' => 'nullable|array|max:12',
            'new_photos.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $finalPhotos = [];

        // 1. Simpan foto lama yang tidak dihapus
        if ($request->has('existing_photos') && is_array($request->input('existing_photos'))) {
            $finalPhotos = array_values(array_filter(array_map('trim', $request->input('existing_photos'))));
        }

        // 2. Upload foto-foto baru
        if ($request->hasFile('new_photos')) {
            $supabase = app(SupabaseStorageService::class);
            $uploadDir = public_path('uploads/venues');
            if (! file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            foreach ($request->file('new_photos') as $file) {
                if ($file && $file->isValid()) {
                    $supabaseUrl = $supabase->upload($file, 'venues');
                    if ($supabaseUrl) {
                        $finalPhotos[] = $supabaseUrl;
                    } else {
                        $filename = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();
                        $file->move($uploadDir, $filename);
                        $finalPhotos[] = 'uploads/venues/'.$filename;
                    }
                }
            }
        }

        $fotoString = ! empty($finalPhotos) ? implode(', ', $finalPhotos) : null;
        Venue::where('venue_id', $venue->venue_id)->update(['foto' => $fotoString]);

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

        if (! $user || $user->role !== 'venue_owner') {
            return redirect()->route('venues.index')->with('error', 'Akses ditolak.');
        }

        $validated = $request->validate([
            'nama_venue' => ['required', 'string', 'max:100', 'not_regex:/<[^>]*script/i', 'not_regex:/[<>]/', 'unique:tb_venue,nama_venue'],
            'alamat' => ['required', 'string', 'not_regex:/<[^>]*script/i'],
            'kota_wilayah' => 'nullable|string|max:150',
            'kota' => 'nullable|string|max:150',
            'google_maps_url' => 'nullable|string|max:500',
            'sport_type' => 'nullable|string|max:100',
            'jumlah_court' => 'nullable|integer|min:1|max:50',
            'tipe_arena' => 'nullable|string|max:100',
            'arena_type' => 'nullable|string|max:100',
            'jenis_permukaan' => 'nullable|string|max:100',
            'jenis_permukaan_lainnya' => 'nullable|string|max:100',
            'jam_operasional' => 'nullable|string|max:100',
            'hari_buka' => 'nullable|string|max:100',
            'nama_pic' => ['nullable', 'string', 'max:150', 'not_regex:/<[^>]*script/i', 'not_regex:/[<>]/'],
            'no_whatsapp' => 'nullable|string|max:50',
            'catatan' => ['nullable', 'string', 'not_regex:/<[^>]*script/i'],
            'fasilitas' => 'nullable|array',
            'fasilitas.*' => ['string', 'max:100', 'not_regex:/<[^>]*script/i'],
            'facilities' => 'nullable|array',
            'facilities.*' => ['string', 'max:100', 'not_regex:/<[^>]*script/i'],
            'foto' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'fotos' => 'nullable|array|max:12',
            'fotos.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ], [
            'nama_venue.unique' => 'Nama venue sudah terdaftar. Silakan gunakan nama venue yang lain.',
            'nama_venue.max' => 'Nama venue maksimal 100 karakter.',
            'nama_venue.not_regex' => 'Nama venue tidak boleh mengandung tag script atau karakter khusus (< >).',
            'alamat.not_regex' => 'Alamat venue tidak boleh mengandung tag script.',
            'nama_pic.not_regex' => 'Nama PIC tidak boleh mengandung tag script atau karakter khusus (< >).',
            'catatan.not_regex' => 'Catatan tidak boleh mengandung tag script.',
        ]);

        $savedPaths = [];
        $supabase = app(SupabaseStorageService::class);
        $uploadDir = public_path('uploads/venues');
        if (! file_exists($uploadDir)) {
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
                    $filename = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();
                    $file->move($uploadDir, $filename);
                    $savedPaths[] = 'uploads/venues/'.$filename;
                }
            }
        }

        $fotoString = ! empty($savedPaths) ? implode(', ', $savedPaths) : null;

        $kotaFinal = $request->input('kota') ?? $request->input('kota_wilayah');
        $fasilitasArr = $request->input('facilities') ?? $request->input('fasilitas') ?? [];
        $catatanInput = $request->input('catatan') ?? $request->input('maintenance_note');

        $rawSurface = $request->input('jenis_permukaan');
        $surfaceFinal = ($rawSurface === 'Other' && $request->filled('jenis_permukaan_lainnya'))
            ? $request->input('jenis_permukaan_lainnya')
            : $rawSurface;

        $tipeArenaFinal = $request->input('tipe_arena') ?? $request->input('arena_type', 'Semi-Indoor');
        $sportTypeFinal = $request->input('sport_type', 'Padel');
        $jumlahCourtFinal = (int) ($request->input('jumlah_court', 1));

        $venue = Venue::create([
            'nama_venue' => strip_tags($validated['nama_venue']),
            'alamat' => strip_tags($validated['alamat']),
            'kota' => strip_tags($kotaFinal ?? ''),
            'google_maps_url' => $request->input('google_maps_url') ? strip_tags($request->input('google_maps_url')) : null,
            'sport_type' => strip_tags($sportTypeFinal),
            'jumlah_court' => $jumlahCourtFinal,
            'tipe_arena' => $tipeArenaFinal ? strip_tags($tipeArenaFinal) : null,
            'jenis_permukaan' => $surfaceFinal ? strip_tags($surfaceFinal) : null,
            'jam_operasional' => strip_tags($request->input('jam_operasional') ?: '06:00 - 23:00 WIB'),
            'hari_buka' => strip_tags($request->input('hari_buka') ?: 'Setiap Hari (Senin - Minggu)'),
            'nama_pic' => strip_tags($request->input('nama_pic') ?? ''),
            'no_whatsapp' => strip_tags($request->input('no_whatsapp') ?? ''),
            'catatan' => strip_tags($catatanInput ?? ''),
            'foto' => $fotoString,
            'owner_user_id' => $user->user_id,
            'fasilitas' => implode(', ', array_map('strip_tags', $fasilitasArr)),
        ]);

        return redirect()->route('venues.courts.create', [
            'id' => $venue->venue_id,
            'count' => $jumlahCourtFinal,
            'sport' => $sportTypeFinal,
            'type' => $tipeArenaFinal ?: 'Indoor',
        ])->with('success', 'Venue berhasil didaftarkan. Silakan lengkapi data lapangan Anda.');
    }

    /**
     * Quick store venue & courts on-the-fly from session create wizard.
     * Route: POST /venues/quick-store
     */
    public function quickStore(Request $request)
    {
        $validated = $request->validate([
            'nama_venue' => ['required', 'string', 'max:100', 'not_regex:/<[^>]*script/i', 'not_regex:/[<>]/', 'unique:tb_venue,nama_venue'],
            'sport' => 'required|string|in:Padel,Tennis',
            'jumlah_court' => 'required|integer|min:1|max:10',
            'kota' => ['nullable', 'string', 'max:100', 'not_regex:/<[^>]*script/i'],
            'alamat' => ['nullable', 'string', 'max:255', 'not_regex:/<[^>]*script/i'],
        ], [
            'nama_venue.required' => 'Nama venue wajib diisi.',
            'nama_venue.unique' => 'Nama venue sudah terdaftar, silakan pilih dari daftar atau gunakan nama lain.',
            'nama_venue.not_regex' => 'Nama venue tidak boleh mengandung tag script atau karakter khusus (< >).',
            'sport.required' => 'Cabang olahraga wajib dipilih.',
            'jumlah_court.min' => 'Minimal harus ada 1 court.',
        ]);

        try {
            DB::beginTransaction();

            $sport = Sport::where('nama_sport', $validated['sport'])->first();
            if (! $sport) {
                $sport = Sport::create([
                    'nama_sport' => $validated['sport'],
                    'status_sport' => 'Active',
                ]);
            }

            $venue = Venue::create([
                'owner_user_id' => Auth::id(),
                'nama_venue' => strip_tags($validated['nama_venue']),
                'alamat' => strip_tags($validated['alamat'] ?: $validated['nama_venue']),
                'kota' => strip_tags($validated['kota'] ?: 'Jakarta'),
                'jam_operasional' => '06:00 - 23:00 WIB',
                'hari_buka' => 'Setiap Hari (Senin - Minggu)',
                'fasilitas' => 'Parkir, Toilet, Ruang Ganti',
                'catatan' => 'Didaftarkan cepat untuk sesi mabar.',
            ]);

            $jumlah = (int) $validated['jumlah_court'];
            for ($i = 1; $i <= $jumlah; $i++) {
                Court::create([
                    'venue_id' => $venue->venue_id,
                    'sport_id' => $sport->sport_id,
                    'nama_court' => "Court {$i}",
                    'status_ketersediaan' => 'Available',
                    'tipe_court' => 'Indoor',
                    'deskripsi' => 'Tipe: Indoor',
                ]);
            }

            DB::commit();

            // Load relations so front-end JSON matches what create.blade.php expects
            $venue->load('courts.sport');

            return response()->json([
                'success' => true,
                'message' => "Venue \"{$venue->nama_venue}\" berhasil ditambahkan!",
                'venue' => $venue,
                'courts' => $venue->courts,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan venue: '.$e->getMessage(),
            ], 422);
        }
    }
}
