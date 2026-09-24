@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

    <!-- Flash Messages -->
    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50/90 px-4 py-3 text-xs font-bold text-emerald-800 flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800 cursor-pointer"><i class="fa-solid fa-xmark"></i></button>
        </div>
    @endif
    @if(session('info'))
        <div class="rounded-2xl border border-blue-200 bg-blue-50/90 px-4 py-3 text-xs font-bold text-blue-800 flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-circle-info text-blue-600 text-sm"></i>
                <span>{{ session('info') }}</span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-blue-600 hover:text-blue-800 cursor-pointer"><i class="fa-solid fa-xmark"></i></button>
        </div>
    @endif
    @if(session('error'))
        <div class="rounded-2xl border border-rose-200 bg-rose-50/90 px-4 py-3 text-xs font-bold text-rose-800 flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation text-rose-600 text-sm"></i>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-rose-600 hover:text-rose-800 cursor-pointer"><i class="fa-solid fa-xmark"></i></button>
        </div>
    @endif

    <!-- Header -->
    <div class="border-b border-slate-200 pb-4">
        <a href="{{ route('communities.index') }}" class="text-xs text-slate-500 hover:text-slate-800 inline-flex items-center gap-1.5 mb-2 transition-colors">
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Semua Komunitas
        </a>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3.5 min-w-0">
                @if(!empty($community->logo))
                    <img src="{{ $community->logo }}" alt="{{ $community->nama_community }}" class="w-12 h-12 rounded-xl object-cover border border-slate-200 shadow-2xs shrink-0">
                @else
                    <img src="{{ asset('images/default-community.jpg') }}" alt="{{ $community->nama_community }}" class="w-12 h-12 rounded-xl object-cover border border-slate-200 shadow-2xs shrink-0">
                @endif
                <div class="min-w-0">
                    <h1 class="text-2xl font-bold text-[#050608] break-all break-words" title="{{ $community->nama_community }}">
                        {{ $community->nama_community }}
                    </h1>
                    <p class="text-xs text-slate-500 mt-0.5 flex items-center gap-1.5 flex-wrap">
                        <i class="fa-solid fa-users text-slate-400"></i> Komunitas Padel &amp; Tennis
                        @if(!empty($community->jadwal_rutin))
                            <span class="text-slate-300">•</span>
                            <span class="text-slate-600 font-medium">Jadwal: {{ $community->jadwal_rutin }}</span>
                        @endif
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2.5 flex-wrap">
                <x-badge :type="strtolower($community->sport_utama) === 'tennis' ? 'tennis' : 'padel'">
                    {{ $community->sport_utama }}
                </x-badge>

                @auth
                    @if(Auth::user()->role === 'admin' || (int) Auth::id() === (int) $community->created_by)
                        <a href="{{ route('communities.edit', $community->community_id) }}" class="px-4 py-2 rounded-xl bg-white border border-slate-200 hover:border-[#063B00] text-slate-800 hover:text-[#063B00] font-bold text-xs shadow-2xs transition-all inline-flex items-center gap-1.5 cursor-pointer">
                            <i class="fa-solid fa-pen-to-square text-[#063B00]"></i> Edit Komunitas
                        </a>
                        <button type="button" onclick="openDeleteCommunityModal()" class="px-3.5 py-2 rounded-xl bg-rose-50 border border-rose-200 hover:bg-rose-100 text-rose-700 font-bold text-xs shadow-2xs transition-all inline-flex items-center gap-1.5 cursor-pointer">
                            <i class="fa-solid fa-trash-can text-rose-600"></i> Hapus / Nonaktifkan
                        </button>
                    @endif
                @endauth
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Details & Members Column (2 Cols) -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Card Tentang Komunitas -->
            <div class="glass-card rounded-3xl p-6 sm:p-8 space-y-5 border border-white/90 shadow-sm">
                <h3 class="text-base font-bold text-slate-900">
                    Tentang Komunitas
                </h3>
                <p class="text-xs sm:text-sm text-slate-600 leading-relaxed break-words">{{ $community->deskripsi ?: 'Tidak ada deskripsi tersedia.' }}</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs pt-1">
                    <div class="bg-slate-50/80 p-3.5 rounded-xl border border-slate-200 min-w-0">
                        <span class="text-slate-500 block mb-0.5">Cabang Olahraga Utama</span>
                        <strong class="text-slate-900 block truncate">{{ $community->sport_utama }}</strong>
                    </div>
                    <div class="bg-slate-50/80 p-3.5 rounded-xl border border-slate-200 min-w-0">
                        <span class="text-slate-500 block mb-0.5">Jadwal Rutin Mabar</span>
                        <strong class="text-slate-900 block truncate">{{ $community->jadwal_rutin ?: 'Sesuai kesepakatan anggota' }}</strong>
                    </div>
                </div>
            </div>

            <!-- Card Daftar Anggota Komunitas -->
            <div class="glass-card rounded-3xl p-6 sm:p-8 space-y-5 border border-white/90 shadow-sm">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-bold text-slate-900">
                        Daftar Anggota Komunitas
                    </h3>
                    <span class="text-xs font-semibold text-emerald-800 bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-200 inline-flex items-center gap-1">
                        <i class="fa-solid fa-users text-emerald-600 text-[10px]"></i> {{ $community->players ? $community->players->count() : 0 }} Anggota
                    </span>
                </div>

                @if($community->players && $community->players->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="text-slate-500 bg-slate-50/80 uppercase tracking-wider text-[10px] border-b border-slate-200">
                                <tr>
                                    <th class="py-2.5 px-3">#</th>
                                    <th class="py-2.5 px-3">Nama</th>
                                    <th class="py-2.5 px-3">Status</th>
                                    <th class="py-2.5 px-3">Skill Level</th>
                                    <th class="py-2.5 px-3">Gender / Usia</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($community->players as $index => $player)
                                    @php
                                        $lvl = $player->level;
                                        $gender = $player->gender;
                                        $usia = $player->usia;
                                        if (empty($lvl) || empty($gender) || empty($usia)) {
                                            $fallback = \App\Models\Player::where('user_id', $player->user_id)
                                                ->where(function($q) {
                                                    $q->whereNotNull('level')->orWhereNotNull('gender');
                                                })
                                                ->first();
                                            if ($fallback) {
                                                $lvl = $lvl ?: $fallback->level;
                                                $gender = $gender ?: $fallback->gender;
                                                $usia = $usia ?: $fallback->usia;
                                            }
                                        }
                                    @endphp
                                    <tr class="hover:bg-slate-50/80 transition-colors">
                                        <td class="py-2.5 px-3 text-slate-400 font-medium">{{ $index + 1 }}</td>
                                        <td class="py-2.5 px-3">
                                            <div class="flex items-center gap-2">
                                                @php $avatar = $player->foto ?? ($player->user->foto ?? null); @endphp
                                                @if($avatar)
                                                    <img src="{{ $avatar }}" alt="{{ $player->nama }}" class="w-6 h-6 rounded-full object-cover">
                                                @else
                                                    <div class="w-6 h-6 rounded-full bg-[#063B00] text-white flex items-center justify-center font-bold text-[10px] shrink-0">
                                                        {{ strtoupper(substr($player->nama ?? 'M', 0, 1)) }}
                                                    </div>
                                                @endif
                                                <span class="font-semibold text-slate-800">{{ $player->nama ?? 'Member' }}</span>
                                            </div>
                                        </td>
                                        <td class="py-2.5 px-3">
                                            @if($player->user_id)
                                                <span class="text-[10px] font-semibold px-2 py-0.5 rounded bg-emerald-50 text-emerald-800 border border-emerald-200">Member</span>
                                            @else
                                                <span class="text-[10px] font-semibold px-2 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200">Guest</span>
                                            @endif
                                        </td>
                                        <td class="py-2.5 px-3">
                                            @if($lvl)
                                                <x-badge :type="strtolower($lvl)">{{ $lvl }}</x-badge>
                                            @else
                                                <span class="text-slate-400">—</span>
                                            @endif
                                        </td>
                                        <td class="py-2.5 px-3 text-slate-500">
                                            {{ $gender ?? '-' }}{{ !empty($usia) ? ', ' . $usia . ' th' : '' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-8 text-slate-400 text-xs bg-slate-50/80 rounded-2xl border border-dashed border-slate-200 space-y-2">
                        <i class="fa-solid fa-users-slash text-slate-300 text-2xl"></i>
                        <p class="font-medium text-slate-500">Belum ada anggota bergabung di komunitas ini.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Sidebar Column -->
        <div class="space-y-6">
            <!-- Join / Leave / Status Card -->
            @php
                $isMember = Auth::check() && \App\Models\Player::where('user_id', Auth::id())
                    ->where('community_id', $community->community_id)
                    ->exists();
            @endphp

            <div class="glass-card rounded-3xl p-6 space-y-5 border border-white/90 shadow-sm">
                @if(Auth::check())
                    @if(Auth::user()->isAdmin())
                        {{-- Mode Administrator --}}
                        <div class="space-y-3">
                            <h3 class="text-sm font-bold text-slate-900">Mode Administrator</h3>
                            <div class="bg-amber-50 p-3.5 rounded-xl border border-amber-200 text-amber-900 space-y-1">
                                <span class="font-semibold block flex items-center gap-1.5 text-xs">
                                    <i class="fa-solid fa-crown text-amber-600"></i> Akses Pengelola
                                </span>
                                <p class="text-[11px] leading-relaxed text-amber-800">
                                    Admin mengelola komunitas ini secara sistem dan tidak bergabung sebagai anggota pemain.
                                </p>
                            </div>
                        </div>
                    @elseif($isMember)
                        <!-- Status Keanggotaan -->
                        <div class="space-y-3">
                            <h3 class="text-sm font-bold text-slate-900">Status Keanggotaan</h3>
                            <div class="bg-emerald-50 p-3.5 rounded-xl border border-emerald-200 text-emerald-900 space-y-1">
                                <span class="font-semibold block flex items-center gap-1.5 text-xs">
                                    <i class="fa-solid fa-circle-check text-emerald-600"></i> Anggota Komunitas
                                </span>
                                <p class="text-[11px] leading-relaxed text-emerald-800">
                                    Anda telah terdaftar sebagai anggota resmi komunitas ini.
                                </p>
                            </div>

                            <form action="{{ route('communities.leave', $community->community_id) }}" method="POST">
                                @csrf
                                <button type="submit" class="w-full px-4 py-2.5 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold text-xs transition-all border border-rose-200 flex items-center justify-center gap-2 cursor-pointer">
                                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                                    Keluar dari Komunitas
                                </button>
                            </form>
                        </div>
                    @else
                        <!-- Join Button -->
                        <div class="space-y-3">
                            <h3 class="text-sm font-bold text-slate-900">Gabung Komunitas</h3>
                            <p class="text-xs text-slate-500 leading-relaxed">Bergabunglah dengan komunitas ini untuk mengikuti sesi mabar dan turnamen!</p>

                            <form action="{{ route('communities.join', $community->community_id) }}" method="POST">
                                @csrf
                                <button type="submit" class="w-full px-4 py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-xs shadow-xs transition-all flex items-center justify-center gap-2 cursor-pointer">
                                    <i class="fa-solid fa-user-plus text-[#A8E63A]"></i>
                                    <span>Bergabung Sekarang</span>
                                </button>
                            </form>
                        </div>
                    @endif
                @else
                    <!-- Login Required -->
                    <div class="space-y-3">
                        <h3 class="text-sm font-bold text-slate-900">Akses Komunitas</h3>
                        <p class="text-xs text-slate-500 leading-relaxed">Silakan login terlebih dahulu untuk bergabung dengan komunitas ini.</p>

                        <a href="{{ route('login') }}" class="w-full px-4 py-2.5 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-xs shadow-xs transition-all inline-flex items-center justify-center gap-2">
                            <i class="fa-solid fa-arrow-right-to-bracket text-[#A8E63A]"></i>
                            <span>Login Terlebih Dahulu</span>
                        </a>
                    </div>
                @endif

                <!-- Info Box -->
                <div class="pt-3 border-t border-slate-100 flex items-start gap-2 text-[11px] text-slate-500">
                    <i class="fa-solid fa-circle-info text-emerald-700 text-xs mt-0.5 shrink-0"></i>
                    <p class="leading-relaxed">Sebagai anggota, Anda dapat mengikuti sesi mabar, turnamen, dan melihat leaderboard komunitas.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DELETE COMMUNITY CONFIRMATION -->
<div id="deleteCommunityModal" class="fixed inset-0 items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto animate-in fade-in duration-150" style="display: none; z-index: 99999;" onclick="if(event.target === this) closeDeleteCommunityModal();">
    <div class="bg-white rounded-3xl p-6 shadow-2xl space-y-4 my-8 border border-slate-100 flex flex-col" style="max-width: 440px; width: 100%; box-sizing: border-box;" onclick="event.stopPropagation();">
        <div class="flex items-start gap-3.5">
            <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center text-base border border-rose-100/80 shadow-2xs shrink-0 mt-0.5">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div class="space-y-1">
                <h3 class="text-sm font-black text-slate-900">Hapus / Nonaktifkan Komunitas</h3>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Apakah Anda yakin ingin menghapus atau menonaktifkan komunitas <strong class="text-slate-800 font-extrabold">{{ $community->nama_community }}</strong>? Jika komunitas memiliki anggota terdaftar, statusnya akan dinonaktifkan (Inactive) untuk menjaga keutuhan relasi pemain.
                </p>
            </div>
        </div>

        <form id="deleteCommunityForm" action="{{ route('communities.destroy', $community->community_id) }}" method="POST" class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5">
            @csrf
            @method('DELETE')
            <button
                type="button"
                onclick="closeDeleteCommunityModal()"
                class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors cursor-pointer"
            >
                Batal
            </button>
            <button
                type="submit"
                class="px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-extrabold text-xs shadow-md shadow-rose-600/20 flex items-center gap-1.5 transition-all cursor-pointer hover:scale-[1.01]"
            >
                <i class="fa-solid fa-trash-can text-xs"></i> Ya, Hapus / Nonaktifkan
            </button>
        </form>
    </div>
</div>

<script>
    function openDeleteCommunityModal() {
        const m = document.getElementById('deleteCommunityModal');
        if (m) m.style.display = 'flex';
    }
    function closeDeleteCommunityModal() {
        const m = document.getElementById('deleteCommunityModal');
        if (m) m.style.display = 'none';
    }
</script>
@endsection
