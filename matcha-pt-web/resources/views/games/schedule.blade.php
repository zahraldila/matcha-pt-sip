@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6 relative">

    <!-- Ambient Glowing Orbs -->
    <div class="absolute w-80 h-80 bg-[#A8E63A]/20 rounded-full blur-3xl pointer-events-none -top-10 -left-10"></div>
    <div class="absolute w-80 h-80 bg-[#063B00]/15 rounded-full blur-3xl pointer-events-none bottom-10 -right-10"></div>

    <!-- Header Section -->
    <div class="flex items-center justify-between pb-4 border-b border-slate-200/60 relative z-10">
        <div class="flex items-center gap-3">
            <a href="{{ route('games.index') }}" class="w-9 h-9 rounded-2xl bg-white border border-slate-200 text-slate-700 flex items-center justify-center hover:bg-slate-50 transition-colors shadow-2xs">
                <i class="fa-solid fa-arrow-left text-xs"></i>
            </a>
            <div>
                <span class="text-[10px] font-black uppercase tracking-widest text-[#063B00]">Host Open Schedule</span>
                <h1 class="text-xl sm:text-2xl font-black text-[#050608] leading-tight">
                    Buka Jadwal Mabar Baru
                </h1>
            </div>
        </div>
        <span class="hidden sm:inline-flex px-3 py-1 rounded-full bg-[#EBF8D8] border border-[#063B00]/20 text-[#063B00] text-xs font-bold">
            <i class="fa-solid fa-calendar-plus mr-1.5 text-xs"></i> Publikasikan ke Komunitas
        </span>
    </div>

    <!-- Main Glassmorphism Form Card -->
    <div class="glass-card !bg-white/85 !backdrop-blur-2xl rounded-3xl p-6 sm:p-8 space-y-6 border border-white shadow-[0_12px_40px_-10px_rgba(6,59,0,0.08)] relative z-10">

        @if($errors->any())
            <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs space-y-1">
                <div class="font-bold flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span>Terdapat data yang belum sesuai:</span>
                </div>
                <ul class="list-disc list-inside pl-1 text-[11px] text-rose-600 space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('games.schedule.post') }}" method="POST" class="space-y-6 text-xs">
            @csrf

            <!-- 1. Cabang Olahraga -->
            <div class="space-y-2.5">
                <label class="block font-black text-slate-900 uppercase tracking-wider text-[11px] flex items-center gap-1.5">
                    <span class="w-5 h-5 rounded-full bg-[#063B00] text-white flex items-center justify-center text-[10px] font-bold">1</span>
                    Pilih Cabang Olahraga
                </label>
                <div class="grid grid-cols-2 gap-3">
                    @foreach($sports as $sport)
                        <label class="cursor-pointer">
                            <input type="radio" name="sport_id" value="{{ $sport->sport_id }}" class="peer sr-only" {{ $loop->first ? 'checked' : '' }} onchange="filterCourtsBySport({{ $sport->sport_id }})">
                            <div class="p-3.5 rounded-2xl border border-slate-200/80 bg-slate-50/70 peer-checked:bg-gradient-to-b peer-checked:from-[#EBF8D8]/80 peer-checked:to-white peer-checked:border-[#063B00] peer-checked:shadow-sm transition-all flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-white border border-slate-200/70 flex items-center justify-center text-base text-[#063B00] shadow-2xs">
                                    <i class="fa-solid {{ strtolower($sport->nama_sport) === 'padel' ? 'fa-table-tennis-paddle-ball' : 'fa-baseball' }}"></i>
                                </div>
                                <div>
                                    <h4 class="font-extrabold text-slate-900 text-xs">{{ $sport->nama_sport }}</h4>
                                    <p class="text-[10px] text-slate-500">Pertandingan {{ $sport->nama_sport }}</p>
                                </div>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- 2. Informasi Sesi Mabar -->
            <div class="space-y-3 pt-2 border-t border-slate-100">
                <label class="block font-black text-slate-900 uppercase tracking-wider text-[11px] flex items-center gap-1.5">
                    <span class="w-5 h-5 rounded-full bg-[#063B00] text-white flex items-center justify-center text-[10px] font-bold">2</span>
                    Informasi & Judul Mabar
                </label>

                <div class="space-y-1.5">
                    <label class="block font-bold text-slate-800">Judul Sesi Mabar</label>
                    <input type="text" name="nama_session" value="{{ old('nama_session', 'Mabar Padel Weekend Fun') }}" placeholder="Contoh: Mabar Padel JTK Bonang 6 Players" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs" required>
                    <span class="text-[10px] text-slate-400 block font-medium">*Nama ini akan menjadi judul kartu mabar di Dashboard dan Jadwal Mabar.</span>
                </div>
            </div>

            <!-- 3. Lokasi Venue & Lapangan -->
            <div class="space-y-3 pt-2 border-t border-slate-100">
                <label class="block font-black text-slate-900 uppercase tracking-wider text-[11px] flex items-center gap-1.5">
                    <span class="w-5 h-5 rounded-full bg-[#063B00] text-white flex items-center justify-center text-[10px] font-bold">3</span>
                    Lokasi Venue & Lapangan
                </label>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">Pilih Venue / Tempat</label>
                        <div class="relative">
                            <select name="venue_id" id="venueSelect" onchange="updateCourtsDropdown()" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none appearance-none transition-all shadow-2xs" required>
                                @foreach($venues as $venue)
                                    <option value="{{ $venue->venue_id }}">{{ $venue->nama_venue }} ({{ $venue->alamat }})</option>
                                @endforeach
                            </select>
                            <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">Pilih Court / Lapangan</label>
                        <div class="relative">
                            <select name="court_id" id="courtSelect" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none appearance-none transition-all shadow-2xs" required>
                                <!-- Populated dynamically by JS based on selected venue -->
                                @if($venues->first() && $venues->first()->courts)
                                    @foreach($venues->first()->courts as $court)
                                        <option value="{{ $court->court_id }}">{{ $court->nama_court }}</option>
                                    @endforeach
                                @endif
                            </select>
                            <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. Waktu & Kuota Peserta -->
            <div class="space-y-3 pt-2 border-t border-slate-100">
                <label class="block font-black text-slate-900 uppercase tracking-wider text-[11px] flex items-center gap-1.5">
                    <span class="w-5 h-5 rounded-full bg-[#063B00] text-white flex items-center justify-center text-[10px] font-bold">4</span>
                    Jadwal & Kuota Pemain
                </label>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">Tanggal Mabar</label>
                        <input type="date" name="tanggal" value="{{ date('Y-m-d', strtotime('+1 day')) }}" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs" required>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">Jam Mulai</label>
                        <input type="time" name="jam" value="18:30" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs" required>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">Durasi</label>
                        <div class="relative">
                            <select name="durasi" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none appearance-none transition-all shadow-2xs">
                                <option value="1 Jam">1 Jam</option>
                                <option value="2 Jam" selected>2 Jam</option>
                                <option value="3 Jam">3 Jam</option>
                                <option value="4 Jam">4 Jam</option>
                            </select>
                            <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 pt-1">
                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">Kuota Maksimal Pemain</label>
                        <div class="relative">
                            <select name="jumlah_pemain" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none appearance-none transition-all shadow-2xs">
                                <option value="4">4 Pemain (1 Court Non-Stop)</option>
                                <option value="6" selected>6 Pemain (1 Court Rotasi Bench 2 Istirahat)</option>
                                <option value="8">8 Pemain (1 Court / 2 Court Americano)</option>
                                <option value="12">12 Pemain (Multi-Court Tournament)</option>
                            </select>
                            <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">Rekomendasi Level</label>
                        <div class="relative">
                            <select name="level_rekomendasi" class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none appearance-none transition-all shadow-2xs">
                                <option value="All Level">All Level Welcome (Bebas Semua Level)</option>
                                <option value="Newbie - Beginner">Newbie & Beginner Only</option>
                                <option value="Intermediate">Intermediate Only</option>
                                <option value="Advanced">Advanced / Competitive Only</option>
                            </select>
                            <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 5. Catatan Deskripsi Mabar -->
            <div class="space-y-1.5 pt-2 border-t border-slate-100">
                <label class="block font-bold text-slate-800">Catatan Khusus / Deskripsi Mabar</label>
                <textarea name="deskripsi" rows="3" placeholder="Contoh: Harap hadir 15 menit sebelum mabar dimulai. Bola sudah disediakan oleh host, sewa raket tersedia di tempat." class="w-full bg-slate-50/80 border border-slate-200/80 rounded-2xl px-4 py-2.5 text-xs text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs"></textarea>
            </div>

            <!-- Submit Button -->
            <div class="pt-3 border-t border-slate-100">
                <button type="submit" class="w-full py-3.5 rounded-2xl bg-[#063B00] hover:bg-[#042a00] text-white font-black text-xs shadow-md transition-all hover:scale-[1.01] active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                    <i class="fa-solid fa-paper-plane text-[#A8E63A] text-xs"></i>
                    <span>Publikasikan Sesi Mabar ke Jadwal</span>
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    // Venue and Courts mapping
    const venuesData = @json($venues);

    function updateCourtsDropdown() {
        const venueId = parseInt(document.getElementById('venueSelect').value);
        const courtSelect = document.getElementById('courtSelect');
        courtSelect.innerHTML = '';

        const selectedVenue = venuesData.find(v => v.venue_id === venueId);
        if (selectedVenue && selectedVenue.courts && selectedVenue.courts.length > 0) {
            selectedVenue.courts.forEach(court => {
                const opt = document.createElement('option');
                opt.value = court.court_id;
                opt.innerText = court.nama_court;
                courtSelect.appendChild(opt);
            });
        } else {
            const opt = document.createElement('option');
            opt.value = '1';
            opt.innerText = 'Court 1 (Default)';
            courtSelect.appendChild(opt);
        }
    }
</script>
@endpush
@endsection
