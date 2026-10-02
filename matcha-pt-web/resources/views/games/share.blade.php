@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <!-- Breadcrumb / Back Link -->
    <div class="flex items-center justify-between">
        <a href="{{ route('games.index') }}" class="text-xs text-slate-500 hover:text-slate-800 inline-flex items-center gap-1.5 transition-colors">
            <i class="fa-solid fa-arrow-left"></i> Lihat Semua Jadwal Mabar
        </a>
        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200/80 text-emerald-800 text-xs font-semibold">
            <i class="fa-solid fa-globe text-emerald-600"></i> Undangan Mabar Publik
        </div>
    </div>

    <!-- Header Card -->
    <div class="clean-card rounded-2xl p-6 sm:p-7 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <x-badge :type="strtolower($game['sport']) === 'tennis' ? 'tennis' : 'padel'">
                        {{ $game['sport'] }}
                    </x-badge>
                    <x-badge :type="str_contains(strtolower($game['status']), 'selesai') || !empty($game['is_finished']) ? 'finished' : (str_contains(strtolower($game['status']), 'sedang') || str_contains(strtolower($game['status']), 'in progress') ? 'playing' : (str_contains(strtolower($game['status']), 'ready') ? 'full' : 'open'))">
                        {{ $game['status'] }}
                    </x-badge>
                </div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight pt-1">
                    {{ $game['title'] }}
                </h1>
                <p class="text-xs text-slate-500">
                    Diselenggarakan oleh <strong class="text-slate-700">{{ $game['host']['name'] }}</strong>
                </p>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center gap-2 shrink-0">
                <button
                    type="button"
                    onclick="openShareModal('{{ $shareUrl }}', '{{ addslashes($game['title']) }}', '{{ $game['sport'] }}', '{{ \Carbon\Carbon::parse($game['date'])->isoFormat('D MMM Y') }}', '{{ $game['time'] }}', '{{ addslashes($game['venue_name']) }}')"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 font-bold text-xs shadow-2xs transition-all hover:scale-[1.01] active:scale-95 cursor-pointer"
                >
                    <i class="fa-solid fa-share-nodes text-emerald-600"></i>
                    <span>Bagikan Jadwal</span>
                </button>

                @php
                    $isFull = count($game['participants']) >= $game['quota'];
                @endphp

                @if(empty($isJoinedByMe) && !$isFull && empty($hasDrawingStarted) && empty($isFinished))
                    <button
                        type="button"
                        onclick="showJoinModal('{{ $game['id'] }}', '{{ addslashes($game['title']) }}')"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-extrabold text-xs shadow-md shadow-[#063B00]/15 transition-all hover:scale-[1.02] active:scale-95 cursor-pointer"
                    >
                        <i class="fa-solid fa-user-plus text-[#A8E63A]"></i>
                        <span>Ikut Mabar</span>
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Details & Participants (2 Cols) -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Informasi Pelaksanaan -->
            <div class="clean-card rounded-xl p-5 sm:p-6 space-y-4">
                <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-circle-info text-[#063B00]"></i>
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
                    <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-users text-[#063B00]"></i>
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
                            @forelse($game['participants'] as $index => $player)
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
                            @empty
                                <tr>
                                    <td colspan="5" class="py-6 text-center text-slate-400 text-xs">
                                        Belum ada pemain yang terdaftar. Jadilah yang pertama bergabung!
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Sidebar / Join Panel -->
        <div class="space-y-4">
            <div class="glass-card rounded-3xl p-5 sm:p-6 space-y-4 border border-white/90 shadow-2xs">
                <div class="space-y-1">
                    <h3 class="text-sm font-bold text-[#050608]">Ikut Sesi Mabar</h3>
                    <p class="text-xs text-slate-500 leading-relaxed">
                        @if(!empty($isFinished))
                            Sesi mabar ini telah selesai.
                        @elseif(!empty($hasDrawingStarted))
                            Pertandingan sedang berlangsung atau drawing sudah dimulai.
                        @elseif($isFull)
                            Slot sesi ini sudah penuh ({{ count($game['participants']) }}/{{ $game['quota'] }} pemain).
                        @else
                            Masih tersedia <strong class="text-emerald-700 font-bold">{{ max(0, $game['quota'] - count($game['participants'])) }} slot</strong> lagi. Siapapun dapat bergabung!
                        @endif
                    </p>
                </div>

                <div class="space-y-2 pt-2">
                    @if(!empty($isJoinedByMe))
                        <div class="w-full text-center py-2.5 px-3 rounded-xl bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-semibold flex items-center justify-center gap-1.5">
                            <i class="fa-solid fa-circle-check text-emerald-600"></i> Anda Sudah Terdaftar di Sesi Ini
                        </div>
                    @elseif(!empty($isFinished))
                        <div class="w-full text-center py-2.5 px-3 rounded-xl bg-slate-100 text-slate-600 border border-slate-200 text-xs font-semibold flex items-center justify-center gap-1.5">
                            <i class="fa-solid fa-flag-checkered text-slate-500"></i> Sesi Telah Selesai
                        </div>
                    @elseif(!empty($hasDrawingStarted))
                        <div class="w-full text-center py-2.5 px-3 rounded-xl bg-amber-50 text-amber-800 border border-amber-200 text-xs font-semibold flex items-center justify-center gap-1.5">
                            <i class="fa-solid fa-stopwatch text-amber-600"></i> Pendaftaran Ditutup (Drawing Dimulai)
                        </div>
                    @elseif($isFull)
                        <div class="w-full text-center py-2.5 px-3 rounded-xl bg-amber-50 text-amber-800 border border-amber-200 text-xs font-semibold flex items-center justify-center gap-1.5">
                            <i class="fa-solid fa-ban text-amber-600"></i> Kuota Sudah Penuh
                        </div>
                    @else
                        <button
                            type="button"
                            onclick="showJoinModal('{{ $game['id'] }}', '{{ addslashes($game['title']) }}')"
                            class="w-full text-center py-3 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-extrabold text-xs shadow-md transition-all hover:scale-[1.01] active:scale-95 flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <i class="fa-solid fa-user-plus text-[#A8E63A]"></i>
                            <span>Gabung Sesi Mabar Sekarang</span>
                        </button>
                    @endif

                    <button
                        type="button"
                        onclick="openShareModal('{{ $shareUrl }}', '{{ addslashes($game['title']) }}', '{{ $game['sport'] }}', '{{ \Carbon\Carbon::parse($game['date'])->isoFormat('D MMM Y') }}', '{{ $game['time'] }}', '{{ addslashes($game['venue_name']) }}')"
                        class="w-full text-center py-2.5 rounded-xl bg-white hover:bg-slate-50 text-[#063B00] font-bold text-xs border border-slate-200 transition-all shadow-2xs flex items-center justify-center gap-2 cursor-pointer active:scale-95"
                    >
                        <i class="fa-solid fa-share-nodes text-emerald-600 text-xs"></i>
                        <span>Bagikan ke Teman</span>
                    </button>
                </div>

                <div class="pt-3 border-t border-slate-100 text-[11px] text-slate-500 space-y-1.5">
                    <p class="flex items-center gap-1.5">
                        <i class="fa-solid fa-check text-[#063B00]"></i> Terbuka untuk Member dan Guest
                    </p>
                    <p class="flex items-center gap-1.5">
                        <i class="fa-solid fa-check text-[#063B00]"></i> Drawing otomatis berimbang di lapangan
                    </p>
                    <p class="flex items-center gap-1.5">
                        <i class="fa-solid fa-check text-[#063B00]"></i> Skor real-time tersimpan secara resmi
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<x-share-modal
    :shareUrl="$shareUrl"
    :gameTitle="$game['title']"
    :sport="$game['sport']"
    :date="\Carbon\Carbon::parse($game['date'])->isoFormat('dddd, D MMM Y')"
    :time="$game['time']"
    :venue="$game['venue_name']"
/>

<x-join-modal />
@endsection
