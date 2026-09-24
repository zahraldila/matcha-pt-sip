@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

    {{-- Header --}}
    <div class="border-b border-slate-200 pb-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2.5 mb-1">
                    <span class="w-8 h-8 rounded-xl bg-amber-100 border border-amber-200 flex items-center justify-center text-amber-700 text-sm">
                        <i class="fa-solid fa-users-gear"></i>
                    </span>
                    <h1 class="text-2xl font-bold text-slate-900">Manajemen Pengguna</h1>
                </div>
                <p class="text-xs text-slate-500">Daftar seluruh akun pengguna terdaftar di aplikasi Matcha.</p>
            </div>
            <span class="px-3 py-1.5 rounded-xl bg-amber-100 border border-amber-200 text-amber-800 text-[11px] font-extrabold uppercase tracking-wider inline-flex items-center gap-1.5">
                <i class="fa-solid fa-crown text-amber-500"></i> Administrator View
            </span>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50/90 px-4 py-3 text-xs font-bold text-emerald-800 flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800"><i class="fa-solid fa-xmark"></i></button>
        </div>
    @endif

    {{-- Filter & Search --}}
    <form method="GET" action="{{ route('admin.users') }}" class="flex flex-col sm:flex-row gap-2.5">
        <div class="relative flex-1">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
            <input
                type="text"
                name="q"
                value="{{ $search }}"
                placeholder="Cari nama, email, atau nomor HP..."
                class="w-full pl-8 pr-4 py-2 rounded-xl border border-slate-200 bg-white text-xs text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-[#063B00]/20 focus:border-[#063B00] transition-all"
            >
        </div>
        <select name="role" class="px-3 py-2 rounded-xl border border-slate-200 bg-white text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#063B00]/20 focus:border-[#063B00] transition-all">
            <option value="all" {{ $filterRole === 'all' ? 'selected' : '' }}>Semua Role</option>
            <option value="member" {{ $filterRole === 'member' ? 'selected' : '' }}>Member</option>
            <option value="host" {{ $filterRole === 'host' ? 'selected' : '' }}>Host</option>
            <option value="venue_owner" {{ $filterRole === 'venue_owner' ? 'selected' : '' }}>Venue Owner</option>
            <option value="admin" {{ $filterRole === 'admin' ? 'selected' : '' }}>Admin</option>
        </select>
        <button type="submit" class="px-4 py-2 rounded-xl bg-[#063B00] hover:bg-[#042a00] text-white font-bold text-xs transition-all shadow-xs flex items-center gap-1.5">
            <i class="fa-solid fa-filter text-[10px] text-[#A8E63A]"></i> Filter
        </button>
        @if($search || $filterRole !== 'all')
            <a href="{{ route('admin.users') }}" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-all flex items-center gap-1.5">
                <i class="fa-solid fa-xmark text-[10px]"></i> Reset
            </a>
        @endif
    </form>

    {{-- Stats Bar --}}
    <div class="flex items-center gap-3 text-xs text-slate-500">
        <span><strong class="text-slate-800">{{ $users->total() }}</strong> pengguna ditemukan</span>
        @if($search || $filterRole !== 'all')
            <span class="text-slate-300">•</span>
            <span class="text-amber-700 font-semibold">Filter aktif</span>
        @endif
    </div>

    {{-- User Table --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/80">
                        <th class="px-4 py-3 text-left font-bold text-slate-500 uppercase tracking-wider text-[10px]">ID</th>
                        <th class="px-4 py-3 text-left font-bold text-slate-500 uppercase tracking-wider text-[10px]">Pengguna</th>
                        <th class="px-4 py-3 text-left font-bold text-slate-500 uppercase tracking-wider text-[10px]">Email</th>
                        <th class="px-4 py-3 text-left font-bold text-slate-500 uppercase tracking-wider text-[10px]">No. HP</th>
                        <th class="px-4 py-3 text-left font-bold text-slate-500 uppercase tracking-wider text-[10px]">Role</th>
                        <th class="px-4 py-3 text-left font-bold text-slate-500 uppercase tracking-wider text-[10px]">Host</th>
                        <th class="px-4 py-3 text-left font-bold text-slate-500 uppercase tracking-wider text-[10px]">Player ID</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($users as $user)
                        @php
                            $roleBadge = match($user->role) {
                                'admin'       => 'bg-amber-100 text-amber-800 border-amber-200',
                                'venue_owner' => 'bg-sky-100 text-sky-800 border-sky-200',
                                'host'        => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                default       => 'bg-slate-100 text-slate-600 border-slate-200',
                            };
                            $roleLabel = match($user->role) {
                                'admin'       => 'Administrator',
                                'venue_owner' => 'Venue Owner',
                                'host'        => 'Host',
                                default       => 'Member',
                            };
                        @endphp
                        <tr class="hover:bg-slate-50/60 transition-colors {{ $user->role === 'admin' ? 'bg-amber-50/40' : '' }}">
                            <td class="px-4 py-3 text-slate-400 font-mono">{{ $user->user_id }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full bg-[#063B00] flex items-center justify-center text-white font-black text-xs shrink-0 overflow-hidden border border-[#A8E63A]/30">
                                        @if(!empty($user->foto))
                                            <img src="{{ $user->foto }}" alt="{{ $user->nama }}" class="w-full h-full object-cover">
                                        @else
                                            {{ strtoupper(substr($user->nama ?? 'U', 0, 1)) }}
                                        @endif
                                    </div>
                                    <span class="font-semibold text-slate-800 truncate max-w-[140px]" title="{{ $user->nama }}">{{ $user->nama }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-slate-600 truncate max-w-[180px]" title="{{ $user->email }}">{{ $user->email }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $user->no_hp ?: '-' }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full border text-[10px] font-bold {{ $roleBadge }}">
                                    {{ $roleLabel }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                @if($user->is_host)
                                    <span class="inline-flex items-center gap-1 text-emerald-700 font-bold">
                                        <i class="fa-solid fa-circle-check text-emerald-500 text-xs"></i> Aktif
                                    </span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-400 font-mono">
                                {{ $user->player?->player_id ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center">
                                <div class="flex flex-col items-center gap-3 text-slate-400">
                                    <i class="fa-solid fa-users-slash text-3xl"></i>
                                    <p class="text-sm font-semibold">Tidak ada pengguna ditemukan</p>
                                    @if($search || $filterRole !== 'all')
                                        <a href="{{ route('admin.users') }}" class="text-xs text-[#063B00] hover:underline font-bold">Hapus filter</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($users->hasPages())
            <div class="px-4 py-3 border-t border-slate-100 flex items-center justify-between gap-2 flex-wrap gap-y-2">
                <p class="text-[11px] text-slate-500">
                    Menampilkan {{ $users->firstItem() }}–{{ $users->lastItem() }} dari {{ $users->total() }} pengguna
                </p>
                {{ $users->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
