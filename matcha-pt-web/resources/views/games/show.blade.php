@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-4">
        <div>
            <a href="{{ route('games.index') }}" class="text-xs text-slate-500 hover:text-slate-800 inline-flex items-center gap-1.5 mb-2 transition-colors">
                <i class="fa-solid fa-arrow-left"></i> Kembali ke Semua Jadwal
            </a>
            <h1 class="text-2xl font-bold text-slate-900">
                {{ $game['title'] }}
            </h1>
        </div>

        <div class="flex items-center gap-2">
            <x-badge :type="strtolower($game['sport']) === 'tennis' ? 'tennis' : 'padel'">
                {{ $game['sport'] }}
            </x-badge>
            <x-badge :type="str_contains(strtolower($game['status']), 'selesai') || !empty($game['is_finished']) ? 'finished' : (str_contains(strtolower($game['status']), 'sedang') || str_contains(strtolower($game['status']), 'in progress') ? 'playing' : (str_contains(strtolower($game['status']), 'ready') ? 'full' : 'open'))">
                {{ $game['status'] }}
            </x-badge>
        </div>
    </div>

    <!-- Main Content -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Details & Participants (2 Cols) -->
        <div class="lg:col-span-2 space-y-6">
            <div class="clean-card rounded-xl p-5 sm:p-6 space-y-4">
                <h3 class="text-sm font-bold text-slate-900">
                    Informasi Pelaksanaan
                </h3>

                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-200 min-w-0">
                        <span class="text-slate-500 block mb-0.5">Venue</span>
                        <strong class="text-slate-900 block min-w-0 truncate whitespace-nowrap" title="{{ $game['venue_name'] }}">{{ $game['venue_name'] }}</strong>
                        <span class="text-emerald-700 text-[11px] font-medium block min-w-0 truncate whitespace-nowrap" title="{{ $game['court_name'] }}">{{ $game['court_name'] }}</span>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-200">
                        <span class="text-slate-500 block mb-0.5">Jadwal</span>
                        <strong class="text-slate-900 block">{{ $game['time'] }} WIB</strong>
                        <span class="text-slate-500 text-[11px]">{{ \Carbon\Carbon::parse($game['date'])->isoFormat('dddd, D MMM Y') }}</span>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-200">
                        <span class="text-slate-500 block mb-0.5">Durasi & Kuota</span>
                        <strong class="text-slate-900 block">{{ $game['duration'] ?? '-' }}</strong>
                        <span class="text-slate-700 font-semibold text-[11px]">{{ $game['joined_count'] }} / {{ $game['quota'] }} Pemain</span>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-200">
                        <span class="text-slate-500 block mb-0.5">Format</span>
                        <strong class="text-slate-900 block">{{ $game['match_format'] }}</strong>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-200 sm:col-span-2">
                        <span class="text-slate-500 block mb-0.5">Sistem Scoring</span>
                        <strong class="text-slate-900 block">{{ $game['scoring_system'] }}</strong>
                    </div>
                </div>

                <!-- Host info -->
                <div class="p-3.5 rounded-lg bg-emerald-50 border border-emerald-100 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        @if(!empty($game['host']['avatar']))
                            <img src="{{ $game['host']['avatar'] }}" alt="{{ $game['host']['name'] }}" class="w-9 h-9 rounded-full object-cover ring-1 ring-emerald-600">
                        @else
                            <div class="w-9 h-9 rounded-full bg-[#063B00] text-white flex items-center justify-center font-bold text-xs shrink-0 ring-1 ring-emerald-600">
                                {{ strtoupper(substr($game['host']['name'] ?? 'H', 0, 1)) }}
                            </div>
                        @endif
                        <div>
                            <p class="text-[11px] text-emerald-800 font-medium">Host Sesi Mabar:</p>
                            <p class="text-xs font-bold text-slate-900">{{ $game['host']['name'] }} <span class="text-slate-500 font-normal">({{ $game['host']['level'] }})</span></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Participant Table -->
            <div class="clean-card rounded-xl p-5 sm:p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-900">
                        Daftar Peserta ({{ count($game['participants']) }}/{{ $game['quota'] }})
                    </h3>
                    @if(count($game['participants']) >= $game['quota'])
                        <span class="text-xs font-semibold text-emerald-800 bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-200 inline-flex items-center gap-1">
                            <i class="fa-solid fa-circle-check text-emerald-600 text-[10px]"></i> Kuota Lengkap
                        </span>
                    @else
                        <span class="text-xs font-semibold text-amber-800 bg-amber-50 px-2.5 py-0.5 rounded-full border border-amber-200 inline-flex items-center gap-1">
                            <i class="fa-solid fa-user-clock text-amber-600 text-[10px]"></i> Tersisa {{ max(0, $game['quota'] - count($game['participants'])) }} Slot
                        </span>
                    @endif
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="text-slate-500 bg-slate-50 uppercase tracking-wider text-[10px] border-b border-slate-200">
                            <tr>
                                <th class="py-2.5 px-3">#</th>
                                <th class="py-2.5 px-3">Nama</th>
                                <th class="py-2.5 px-3">Status</th>
                                <th class="py-2.5 px-3">Skill Level</th>
                                <th class="py-2.5 px-3">Gender / Usia</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($game['participants'] as $index => $player)
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="py-2.5 px-3 text-slate-400 font-medium">{{ $index + 1 }}</td>
                                    <td class="py-2.5 px-3">
                                        <div class="flex items-center gap-2">
                                            @if(!empty($player['avatar']))
                                                <img src="{{ $player['avatar'] }}" alt="{{ $player['name'] }}" class="w-6 h-6 rounded-full object-cover">
                                            @else
                                                <div class="w-6 h-6 rounded-full bg-[#063B00] text-white flex items-center justify-center font-bold text-[10px] shrink-0">
                                                    {{ strtoupper(substr($player['name'] ?? 'P', 0, 1)) }}
                                                </div>
                                            @endif
                                            <span class="font-semibold text-slate-800">{{ $player['name'] }}</span>
                                        </div>
                                    </td>
                                    <td class="py-2.5 px-3">
                                        @if($player['is_member'])
                                             <span class="text-[10px] font-semibold px-2 py-0.5 rounded bg-emerald-50 text-emerald-800 border border-emerald-200">Member</span>
                                        @else
                                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200">Guest</span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3">
                                        @php
                                            $lvl = strtolower($player['level']);
                                        @endphp
                                        <x-badge :type="$lvl">{{ $player['level'] }}</x-badge>
                                    </td>
                                    <td class="py-2.5 px-3 text-slate-500">
                                        {{ $player['gender'] }}{{ !empty($player['age']) ? ', ' . $player['age'] . ' th' : '' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Sidebar Actions -->
        <div class="space-y-4">
            @if(!empty($game['is_finished']) || str_contains(strtolower($game['status']), 'selesai'))
                <div class="glass-card rounded-3xl p-5 space-y-4 border border-amber-200/60 bg-gradient-to-br from-amber-50/50 via-white to-emerald-50/30 shadow-xs">
                    <div class="space-y-1.5">
                        <div class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-900 border border-amber-200 text-[10px] font-extrabold uppercase tracking-wider">
                            <i class="fa-solid fa-trophy text-amber-600 text-[10px]"></i> Selesai Mabar
                        </div>
                        <h3 class="text-sm font-extrabold text-slate-900">Hasil Akhir &amp; Podium</h3>
                        <p class="text-xs text-slate-500 leading-relaxed">
                            Seluruh pertandingan pada sesi mabar ini telah selesai dimainkan dan skor akhir telah direkam secara resmi.
                        </p>
                    </div>

                    <div class="space-y-2 pt-2">
                        <a href="{{ route('scoring.recap', $game['id']) }}"
                           style="background-color: #063B00 !important; color: #ffffff !important;"
                           class="w-full text-center py-3 px-4 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-extrabold text-xs shadow-md transition-all hover:scale-[1.01] active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                            <i class="fa-solid fa-trophy text-xs text-[#A8E63A]" style="color: #A8E63A !important;"></i>
                            <span class="text-white font-extrabold" style="color: #ffffff !important;">Buka Hasil Akhir &amp; Podium</span>
                        </a>
                    </div>

                    <div class="pt-3 border-t border-slate-100 text-[11px] text-slate-500 space-y-1.5">
                        <p class="flex items-center gap-1.5 text-emerald-800 font-medium">
                            <i class="fa-solid fa-circle-check text-emerald-600"></i> Rekap skor &amp; statistik tersimpan
                        </p>
                        <p class="flex items-center gap-1.5 text-amber-800 font-medium">
                            <i class="fa-solid fa-medal text-amber-600"></i> Peringkat klasemen &amp; juara tersedia
                        </p>
                    </div>
                </div>
            @else
                @php
                    $isFull = count($game['participants']) >= $game['quota'];
                @endphp
                <div class="glass-card rounded-3xl p-5 space-y-4 border border-white/90 shadow-2xs">
                    <div class="space-y-1">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-bold text-[#050608]">Drawing &amp; Pertandingan</h3>
                            @if(!empty($hasDrawingStarted))
                                <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200">
                                    Drawing Siap
                                </span>
                            @else
                                <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-full {{ $isFull ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-slate-100 text-slate-600 border border-slate-200' }}">
                                    {{ $isFull ? 'Siap Drawing' : 'Pendaftaran' }}
                                </span>
                            @endif
                        </div>
                        <p class="text-xs text-slate-500 leading-relaxed">
                            @if(!empty($hasDrawingStarted))
                                Sesi drawing telah dibuat oleh Host. Kamu dapat melihat bagan pertandingan, rotasi pemain, dan jadwal lapangan.
                            @elseif($isFull)
                                Kuota pemain telah lengkap ({{ count($game['participants']) }}/{{ $game['quota'] }}). Host dapat memulai drawing tim sekarang.
                            @else
                                Sesi mabar masih membuka pendaftaran ({{ count($game['participants']) }}/{{ $game['quota'] }}). Masih dibutuhkan {{ max(0, $game['quota'] - count($game['participants'])) }} pemain lagi.
                            @endif
                        </p>
                    </div>

                    <div class="space-y-2 pt-2">
                        @if(!empty($isHost))
                            {{-- TAMPILAN KHUSUS HOST --}}
                            @php $isAdminUser = Auth::check() && Auth::user()->isAdmin(); @endphp

                            @if(!$isAdminUser)
                                @if(empty($isJoinedByMe) && !$isFull && empty($hasDrawingStarted) && empty($isFinished))
                                    <button type="button" onclick="showJoinModal('{{ $game['id'] }}', '{{ addslashes($game['title']) }}')" class="w-full text-center py-2.5 rounded-xl bg-[#EBF8D8] hover:bg-[#A8E63A]/30 text-[#063B00] border border-[#063B00]/30 font-bold text-xs shadow-xs transition-all hover:scale-[1.01] flex items-center justify-center gap-1.5 cursor-pointer">
                                        <i class="fa-solid fa-user-plus text-[#063B00]"></i> Ikut Serta Bermain (+ Add Yourself)
                                    </button>
                                @elseif(!empty($isJoinedByMe))
                                    <div class="w-full text-center py-2 px-3 rounded-xl bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-semibold flex items-center justify-center gap-1.5">
                                        <i class="fa-solid fa-circle-check text-emerald-600"></i> Anda Terdaftar Sebagai Pemain
                                    </div>
                                @endif
                            @endif

                            @if(!empty($hasDrawingStarted))
                                <a href="{{ route('games.drawing', $game['id']) }}" class="w-full text-center py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-semibold text-xs shadow-xs transition-all hover:scale-[1.01] flex items-center justify-center gap-1.5 cursor-pointer">
                                    <i class="fa-solid fa-shuffle text-[11px] text-[#A8E63A]"></i> Kelola Drawing Tim
                                </a>

                                <a href="{{ route('scoring.live', $game['id']) }}" class="w-full text-center py-2.5 rounded-xl bg-white hover:bg-slate-50 text-[#063B00] font-semibold text-xs border-1.5 border-[#063B00] transition-all shadow-xs flex items-center justify-center gap-1.5 cursor-pointer">
                                    <i class="fa-solid fa-stopwatch text-[11px]"></i> Live Match Scoring
                                </a>
                            @elseif(!$isAdminUser)
                                {{-- Hanya Host non-admin yang bisa memulai drawing --}}
                                @if($isFull)
                                    <a href="{{ route('games.drawing', $game['id']) }}" class="w-full text-center py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-semibold text-xs shadow-xs transition-all hover:scale-[1.01] flex items-center justify-center gap-1.5 cursor-pointer">
                                        <i class="fa-solid fa-shuffle text-[11px] text-[#A8E63A]"></i> Buka Drawing Tim
                                    </a>
                                @else
                                    <button type="button" onclick="openEarlyDrawingModal()" class="w-full text-center py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-semibold text-xs shadow-xs transition-all hover:scale-[1.01] flex items-center justify-center gap-1.5 cursor-pointer">
                                        <i class="fa-solid fa-shuffle text-[11px] text-[#A8E63A]"></i> Mulai Drawing ({{ count($game['participants']) }}/{{ $game['quota'] }} Pemain)
                                    </button>
                                @endif
                            @else
                                {{-- Admin: drawing belum dimulai, tampilkan info --}}
                                <div class="w-full text-center py-2.5 rounded-xl bg-slate-50 border border-slate-200 text-slate-500 text-xs font-semibold flex items-center justify-center gap-1.5 select-none">
                                    <i class="fa-solid fa-hourglass-half text-[11px]"></i> Menunggu Host Memulai Drawing
                                </div>
                            @endif

                        @else
                            {{-- TAMPILAN PESERTA & VENUE OWNER (NON-HOST) --}}
                            @if(empty($isJoinedByMe) && !$isFull && empty($hasDrawingStarted) && empty($isFinished) && (Auth::guest() || (Auth::user()->role !== 'venue_owner' && Auth::user()->role !== 'admin')))
                                <button type="button" onclick="showJoinModal('{{ $game['id'] }}', '{{ addslashes($game['title']) }}')" class="w-full text-center py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-xs shadow-xs transition-all hover:scale-[1.01] flex items-center justify-center gap-1.5 cursor-pointer">
                                    <i class="fa-solid fa-user-plus text-[#A8E63A]"></i> Gabung Sesi Mabar
                                </button>
                            @elseif(!empty($isJoinedByMe))
                                <div class="w-full text-center py-2 px-3 rounded-xl bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-semibold flex items-center justify-center gap-1.5">
                                    <i class="fa-solid fa-circle-check text-emerald-600"></i> Kamu Sudah Terdaftar di Sesi Ini
                                </div>
                            @endif

                            @if(!empty($hasDrawingStarted))
                                <a href="{{ route('games.drawing', $game['id']) }}" class="w-full text-center py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-semibold text-xs shadow-xs transition-all hover:scale-[1.01] flex items-center justify-center gap-1.5 cursor-pointer">
                                    <i class="fa-solid fa-eye text-[11px] text-[#A8E63A]"></i> Lihat Jadwal &amp; Rotasi Drawing
                                </a>
                            @else
                                <button type="button" disabled class="w-full text-center py-2.5 rounded-xl bg-slate-100 text-slate-400 font-semibold text-xs border border-slate-200 cursor-not-allowed flex items-center justify-center gap-1.5 select-none" title="Menunggu Host memulai sesi drawing">
                                    <i class="fa-solid fa-lock text-[11px]"></i> Menunggu Host Memulai Drawing
                                </button>
                                <p class="text-[10px] text-slate-400 text-center italic">
                                    Jadwal dan rotasi court akan muncul setelah Host melakukan drawing.
                                </p>
                            @endif
                        @endif

                        @if(Auth::check() && Auth::user()->role === 'admin' && empty($isFinished))
                            <button type="button" onclick="openDeleteSessionModal()" class="w-full text-center py-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 font-bold text-xs shadow-2xs transition-all flex items-center justify-center gap-1.5 cursor-pointer mt-2">
                                <i class="fa-solid fa-trash-can text-rose-600"></i> Hapus Jadwal Mabar
                            </button>
                        @endif
                    </div>

                    <div class="pt-3 border-t border-slate-100 text-[11px] text-slate-500 space-y-1.5">
                        <p class="flex items-center gap-1.5">
                            <i class="fa-solid fa-check text-[#063B00]"></i> Drawing otomatis seimbang
                        </p>
                        <p class="flex items-center gap-1.5">
                            <i class="fa-solid fa-check text-[#063B00]"></i> Visualisasi lapangan tennis/padel
                        </p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Modal Konfirmasi Host: Mulai Drawing Meskipun Kuota Belum Lengkap --}}
@if(!empty($isHost) && empty($hasDrawingStarted))
<div id="earlyDrawingModal" class="fixed inset-0 z-[100] hidden bg-slate-950/60 backdrop-blur-sm flex items-center justify-center p-4 transition-all">
    <div class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-7 space-y-5 relative border border-slate-200 shadow-2xl animate-in fade-in zoom-in duration-200">
        <!-- Close Button -->
        <button onclick="closeEarlyDrawingModal()" class="absolute top-5 right-5 w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition-colors cursor-pointer" title="Tutup">
            <i class="fa-solid fa-xmark text-sm"></i>
        </button>

        <!-- Icon & Header -->
        <div class="flex items-start gap-3.5">
            <div class="w-11 h-11 rounded-2xl bg-amber-50 border border-amber-200 flex items-center justify-center text-amber-600 text-lg shrink-0 shadow-2xs">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div class="space-y-1 pr-4">
                <h3 class="text-base font-black text-slate-900 tracking-tight">Mulai Drawing Sekarang?</h3>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Kuota sesi ini baru terisi <strong class="text-slate-800">{{ count($game['participants']) }} dari {{ $game['quota'] }} pemain</strong> (kurang {{ max(0, $game['quota'] - count($game['participants'])) }} slot).
                </p>
            </div>
        </div>

        <!-- Warning Notice Box -->
        <div class="p-3.5 rounded-2xl bg-amber-50/70 border border-amber-200/80 text-xs text-amber-900 leading-relaxed space-y-1">
            <p class="font-bold flex items-center gap-1.5 text-xs">
                <i class="fa-solid fa-circle-info text-amber-600"></i> Catatan untuk Host:
            </p>
            <p class="text-[11px] text-amber-800 leading-relaxed">
                Jika kamu memulai drawing sekarang, sistem akan menyesuaikan rotasi pertandingan dengan jumlah pemain yang ada atau mengisi pemain cadangan di lapangan.
            </p>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-2.5 pt-1">
            <button type="button" onclick="closeEarlyDrawingModal()" class="flex-1 py-2.5 rounded-xl bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-bold transition-all cursor-pointer shadow-2xs">
                Tunggu Pemain Lain
            </button>
            <a href="{{ route('games.drawing', $game['id']) }}" class="flex-1 py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer shadow-md hover:scale-[1.01]">
                <span>Ya, Lanjutkan</span> <i class="fa-solid fa-arrow-right text-[10px] text-[#A8E63A]"></i>
            </a>
        </div>
    </div>
</div>

<script>
    function openEarlyDrawingModal() {
        const modal = document.getElementById('earlyDrawingModal');
        if (modal) modal.classList.remove('hidden');
    }
    function closeEarlyDrawingModal() {
        const modal = document.getElementById('earlyDrawingModal');
        if (modal) modal.classList.add('hidden');
    }
</script>
@endif

@if(Auth::check() && Auth::user()->role === 'admin')
<!-- MODAL DELETE SESSION CONFIRMATION -->
<div id="deleteSessionModal" class="fixed inset-0 items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto animate-in fade-in duration-150" style="display: none; z-index: 99999;" onclick="if(event.target === this) closeDeleteSessionModal();">
    <div class="bg-white rounded-3xl p-6 shadow-2xl space-y-4 my-8 border border-slate-100 flex flex-col" style="max-width: 440px; width: 100%; box-sizing: border-box;" onclick="event.stopPropagation();">
        <div class="flex items-start gap-3.5">
            <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center text-base border border-rose-100/80 shadow-2xs shrink-0 mt-0.5">
                <i class="fa-solid fa-trash-can"></i>
            </div>
            <div class="space-y-1">
                <h3 class="text-sm font-black text-slate-900">Hapus Jadwal Mabar</h3>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Apakah Anda yakin ingin menghapus jadwal sesi mabar <strong class="text-slate-800 font-extrabold">{{ $game['title'] }}</strong>? Jadwal ini akan dihapus secara permanen dari daftar dan seluruh data drawing terkait akan dibersihkan.
                </p>
            </div>
        </div>

        <form id="deleteSessionForm" action="{{ route('games.destroy', $game['id']) }}" method="POST" class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5">
            @csrf
            @method('DELETE')
            <button
                type="button"
                onclick="closeDeleteSessionModal()"
                class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors cursor-pointer"
            >
                Batal
            </button>
            <button
                type="submit"
                class="px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-extrabold text-xs shadow-md shadow-rose-600/20 flex items-center gap-1.5 transition-all cursor-pointer hover:scale-[1.01]"
            >
                <i class="fa-solid fa-trash-can text-xs"></i> Ya, Hapus Jadwal
            </button>
        </form>
    </div>
</div>

<script>
    function openDeleteSessionModal() {
        const m = document.getElementById('deleteSessionModal');
        if (m) m.style.display = 'flex';
    }
    function closeDeleteSessionModal() {
        const m = document.getElementById('deleteSessionModal');
        if (m) m.style.display = 'none';
    }
</script>
@endif

<x-join-modal />
@endsection
