@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/60 pb-4">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#A8E63A]/20 border border-[#063B00]/25 text-[#050608] text-xs font-semibold shadow-xs mb-2">
                <span class="w-1.5 h-1.5 rounded-full bg-[#063B00]"></span>
                Administrator Panel
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-[#050608] tracking-tight">
                Manajemen Pengguna
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-0.5">Daftar seluruh akun pengguna terdaftar di aplikasi Matcha beserta pengelolaan data dan peran.</p>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50/90 px-4 py-3 text-xs font-bold text-emerald-800 flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800 cursor-pointer"><i class="fa-solid fa-xmark"></i></button>
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-2xl border border-rose-200 bg-rose-50/90 px-4 py-3 text-xs font-bold text-rose-800 flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-triangle-exclamation text-rose-600 text-sm"></i>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-rose-600 hover:text-rose-800 cursor-pointer"><i class="fa-solid fa-xmark"></i></button>
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50/90 px-4 py-3 text-xs font-bold text-rose-800 flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-triangle-exclamation text-rose-600 text-sm"></i>
                <div class="space-y-0.5">
                    @foreach($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-rose-600 hover:text-rose-800 cursor-pointer"><i class="fa-solid fa-xmark"></i></button>
        </div>
    @endif

    {{-- 1. Top Controls Bar: Role Filter Tabs & Search Bar --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
        <!-- Role Filter Tabs -->
        <div class="flex items-center gap-2 overflow-x-auto scrollbar-none text-xs font-semibold py-1">
            <a href="{{ route('admin.users', ['role' => 'all', 'q' => $search ?? '']) }}"
               class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 whitespace-nowrap {{ ($filterRole ?? 'all') === 'all' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608] hover:bg-white' }}">
                <i class="fa-solid fa-users text-xs {{ ($filterRole ?? 'all') === 'all' ? 'text-[#A8E63A]' : 'text-slate-400' }}"></i> 
                <span>Semua Role</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ ($filterRole ?? 'all') === 'all' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600' }}">
                    {{ $counts['all'] ?? $users->total() }}
                </span>
            </a>
            <a href="{{ route('admin.users', ['role' => 'member', 'q' => $search ?? '']) }}"
               class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 whitespace-nowrap {{ ($filterRole ?? '') === 'member' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608] hover:bg-white' }}">
                <i class="fa-solid fa-user text-xs {{ ($filterRole ?? '') === 'member' ? 'text-[#A8E63A]' : 'text-slate-400' }}"></i>
                <span>Member</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ ($filterRole ?? '') === 'member' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600' }}">
                    {{ $counts['member'] ?? 0 }}
                </span>
            </a>
            <a href="{{ route('admin.users', ['role' => 'host', 'q' => $search ?? '']) }}"
               class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 whitespace-nowrap {{ ($filterRole ?? '') === 'host' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608] hover:bg-white' }}">
                <i class="fa-solid fa-bolt text-xs {{ ($filterRole ?? '') === 'host' ? 'text-[#A8E63A]' : 'text-amber-500' }}"></i>
                <span>Host</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ ($filterRole ?? '') === 'host' ? 'bg-[#A8E63A] text-[#063B00]' : 'bg-amber-50 text-amber-800 border border-amber-200' }}">
                    {{ $counts['host'] ?? 0 }}
                </span>
            </a>
            <a href="{{ route('admin.users', ['role' => 'venue_owner', 'q' => $search ?? '']) }}"
               class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 whitespace-nowrap {{ ($filterRole ?? '') === 'venue_owner' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608] hover:bg-white' }}">
                <i class="fa-solid fa-building text-xs {{ ($filterRole ?? '') === 'venue_owner' ? 'text-[#A8E63A]' : 'text-sky-500' }}"></i>
                <span>Venue Owner</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ ($filterRole ?? '') === 'venue_owner' ? 'bg-white/20 text-white' : 'bg-sky-50 text-sky-800 border border-sky-200' }}">
                    {{ $counts['venue_owner'] ?? 0 }}
                </span>
            </a>
            <a href="{{ route('admin.users', ['role' => 'admin', 'q' => $search ?? '']) }}"
               class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-2 whitespace-nowrap {{ ($filterRole ?? '') === 'admin' ? 'bg-[#063B00] text-white shadow-xs font-bold' : 'glass-card text-slate-600 hover:text-[#050608] hover:bg-white' }}">
                <i class="fa-solid fa-shield-halved text-xs {{ ($filterRole ?? '') === 'admin' ? 'text-[#A8E63A]' : 'text-purple-500' }}"></i>
                <span>Admin</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ ($filterRole ?? '') === 'admin' ? 'bg-white/20 text-white' : 'bg-purple-50 text-purple-800 border border-purple-200' }}">
                    {{ $counts['admin'] ?? 0 }}
                </span>
            </a>
        </div>

        <!-- Search Bar -->
        <form method="GET" action="{{ route('admin.users') }}" class="relative w-full md:w-80 shrink-0">
            <input type="hidden" name="role" value="{{ $filterRole ?? 'all' }}">
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                <input type="text" name="q" value="{{ $search ?? '' }}" placeholder="Cari nama, email, no. hp..." 
                       class="w-full pl-9 pr-8 py-2 text-xs rounded-xl bg-white border border-slate-200/90 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#063B00]/20 focus:border-[#063B00] shadow-2xs transition-all">
                @if(!empty($search))
                    <a href="{{ route('admin.users', ['role' => $filterRole ?? 'all']) }}" 
                       class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition-colors"
                       title="Hapus pencarian">
                        <i class="fa-solid fa-circle-xmark text-xs"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- 2. Sub-Filters & Result Counter --}}
    <div class="glass-card p-3 sm:p-3.5 rounded-2xl flex flex-wrap items-center justify-between gap-3 border border-white/90 shadow-2xs">
        <div class="text-xs text-slate-500 flex items-center gap-2">
            @if(!empty($search))
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-[11px] font-semibold">
                    <i class="fa-solid fa-magnifying-glass text-[9px]"></i> "{{ $search }}"
                </span>
            @endif
            <span>
                Menampilkan <strong class="text-[#050608]">{{ $users->total() }}</strong> pengguna
                @if(($filterRole ?? 'all') !== 'all')
                    <span>dengan role <strong class="capitalize text-[#063B00]">{{ str_replace('_', ' ', $filterRole) }}</strong></span>
                @endif
            </span>
        </div>
        @if(!empty($search) || ($filterRole ?? 'all') !== 'all')
            <a href="{{ route('admin.users') }}" class="text-xs text-[#063B00] hover:underline font-semibold flex items-center gap-1">
                <i class="fa-solid fa-rotate-left text-[10px]"></i> Reset Filter
            </a>
        @endif
    </div>

    {{-- 3. User Table --}}
    <div class="glass-card rounded-2xl border border-white/80 shadow-2xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-slate-200/70 bg-slate-50/80">
                        <th class="px-4 py-3 text-left font-bold text-slate-500 uppercase tracking-wider text-[10px]">ID</th>
                        <th class="px-4 py-3 text-left font-bold text-slate-500 uppercase tracking-wider text-[10px]">Pengguna</th>
                        <th class="px-4 py-3 text-left font-bold text-slate-500 uppercase tracking-wider text-[10px]">Email</th>
                        <th class="px-4 py-3 text-left font-bold text-slate-500 uppercase tracking-wider text-[10px]">No. HP</th>
                        <th class="px-4 py-3 text-left font-bold text-slate-500 uppercase tracking-wider text-[10px]">Role</th>
                        <th class="px-4 py-3 text-left font-bold text-slate-500 uppercase tracking-wider text-[10px]">Host</th>
                        <th class="px-4 py-3 text-center font-bold text-slate-500 uppercase tracking-wider text-[10px]">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($users as $user)
                        @php
                            $roleBadge = match($user->role) {
                                'admin'       => 'bg-purple-100 text-purple-800 border-purple-200',
                                'venue_owner' => 'bg-sky-100 text-sky-800 border-sky-200',
                                'host'        => 'bg-amber-100 text-amber-800 border-amber-200',
                                default       => 'bg-slate-100 text-slate-600 border-slate-200',
                            };
                            $roleLabel = match($user->role) {
                                'admin'       => 'Administrator',
                                'venue_owner' => 'Venue Owner',
                                'host'        => 'Host',
                                default       => 'Member',
                            };
                        @endphp
                        <tr class="hover:bg-slate-50/60 transition-colors {{ $user->role === 'admin' ? 'bg-amber-50/30' : '' }}">
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
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button
                                        type="button"
                                        onclick="openEditUserModal({{ json_encode([
                                            'user_id' => $user->user_id,
                                            'nama' => $user->nama,
                                            'email' => $user->email,
                                            'no_hp' => $user->no_hp,
                                            'role' => $user->role,
                                            'is_host' => (bool)$user->is_host,
                                        ]) }})"
                                        class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-emerald-50 text-slate-600 hover:text-[#063B00] border border-slate-200/60 hover:border-emerald-200 flex items-center justify-center transition-all cursor-pointer"
                                        title="Edit Pengguna"
                                    >
                                        <i class="fa-solid fa-pen-to-square text-[11px]"></i>
                                    </button>

                                    @if(Auth::id() !== $user->user_id)
                                        <button
                                            type="button"
                                            onclick="openDeleteUserModal({{ $user->user_id }}, '{{ addslashes($user->nama) }}')"
                                            class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-rose-50 text-slate-600 hover:text-rose-600 border border-slate-200/60 hover:border-rose-200 flex items-center justify-center transition-all cursor-pointer"
                                            title="Hapus Pengguna"
                                        >
                                            <i class="fa-solid fa-trash-can text-[11px]"></i>
                                        </button>
                                    @else
                                        <span class="w-7 h-7 rounded-lg bg-slate-50 text-slate-300 border border-slate-100 flex items-center justify-center cursor-not-allowed" title="Tidak dapat menghapus akun sendiri">
                                            <i class="fa-solid fa-trash-can text-[11px]"></i>
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center">
                                <div class="flex flex-col items-center gap-3 text-slate-400">
                                    <i class="fa-solid fa-users-slash text-3xl text-slate-300"></i>
                                    <p class="text-sm font-semibold text-slate-600">Tidak ada pengguna ditemukan</p>
                                    @if(!empty($search) || ($filterRole ?? 'all') !== 'all')
                                        <a href="{{ route('admin.users') }}" class="text-xs text-[#063B00] hover:underline font-bold">
                                            <i class="fa-solid fa-rotate-left mr-1"></i> Reset Filter
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- 4. Consistent Pagination Component --}}
    <x-pagination :paginator="$users" />

</div>

<!-- MODAL EDIT USER -->
<div id="editUserModal" class="fixed inset-0 items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto animate-in fade-in duration-150" style="display: none; z-index: 99999;" onclick="if(event.target === this) closeEditUserModal();">
    <div class="bg-white rounded-3xl p-6 sm:p-7 shadow-2xl space-y-5 my-8 border border-slate-100 flex flex-col" style="max-width: 480px; width: 100%; box-sizing: border-box;" onclick="event.stopPropagation();">
        <div class="flex items-center justify-between pb-3.5 border-b border-slate-100">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-emerald-50 text-[#063B00] flex items-center justify-center text-sm border border-emerald-100/80 shadow-2xs">
                    <i class="fa-solid fa-user-pen"></i>
                </div>
                <div>
                    <h3 class="text-sm font-black text-slate-900">Edit Data Pengguna</h3>
                    <p class="text-[11px] text-slate-500">Perbarui profil, role, atau hak akses pengguna.</p>
                </div>
            </div>
            <button type="button" onclick="closeEditUserModal()" class="w-7 h-7 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center text-xs transition-colors cursor-pointer">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form id="editUserForm" action="" method="POST" class="space-y-4 text-xs">
            @csrf
            @method('PUT')

            <!-- Nama Lengkap -->
            <div class="space-y-1.5">
                <label class="block font-bold text-slate-800">
                    Nama Lengkap <span class="text-rose-500">*</span>
                </label>
                <input
                    type="text"
                    id="editUserName"
                    name="nama"
                    required
                    maxlength="100"
                    placeholder="Nama lengkap pengguna"
                    class="w-full bg-slate-50/70 border border-slate-200/80 rounded-xl px-3.5 py-2.5 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs text-xs"
                >
            </div>

            <!-- Email -->
            <div class="space-y-1.5">
                <label class="block font-bold text-slate-800">
                    Alamat Email <span class="text-rose-500">*</span>
                </label>
                <input
                    type="email"
                    id="editUserEmail"
                    name="email"
                    required
                    maxlength="100"
                    placeholder="contoh@domain.com"
                    class="w-full bg-slate-50/70 border border-slate-200/80 rounded-xl px-3.5 py-2.5 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs text-xs"
                >
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <!-- No. HP -->
                <div class="space-y-1.5">
                    <label class="block font-bold text-slate-800">
                        Nomor HP / WhatsApp
                    </label>
                    <input
                        type="text"
                        id="editUserNoHp"
                        name="no_hp"
                        maxlength="20"
                        placeholder="Contoh: 08123456789"
                        class="w-full bg-slate-50/70 border border-slate-200/80 rounded-xl px-3.5 py-2.5 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs text-xs"
                    >
                </div>

                <!-- Role -->
                <div class="space-y-1.5">
                    <label class="block font-bold text-slate-800">
                        Role Akun <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <select
                            id="editUserRole"
                            name="role"
                            required
                            class="w-full appearance-none bg-slate-50/70 border border-slate-200/80 rounded-xl px-3.5 py-2.5 pr-8 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs text-xs"
                        >
                            <option value="member">Member</option>
                            <option value="host">Host</option>
                            <option value="venue_owner">Venue Owner</option>
                            <option value="admin">Administrator</option>
                        </select>
                        <i class="fa-solid fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-[10px] text-slate-400 pointer-events-none"></i>
                    </div>
                </div>
            </div>

            <!-- Status Host Toggle -->
            <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/70 flex items-center justify-between">
                <div>
                    <span class="block font-bold text-slate-800 text-xs">Status Host Aktif</span>
                    <span class="text-[11px] text-slate-500">Izinkan pengguna membuat dan memandu sesi mabar</span>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="editUserIsHost" name="is_host" value="1" class="sr-only peer">
                    <div class="w-10 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#063B00]"></div>
                </label>
            </div>

            <!-- Password Baru (Opsional) -->
            <div class="space-y-1.5 pt-1">
                <label class="block font-bold text-slate-800">
                    Password Baru <span class="text-slate-400 font-normal">(opsional)</span>
                </label>
                <input
                    type="password"
                    id="editUserPassword"
                    name="password"
                    minlength="6"
                    placeholder="Kosongkan jika tidak ingin mengubah password"
                    class="w-full bg-slate-50/70 border border-slate-200/80 rounded-xl px-3.5 py-2.5 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs text-xs"
                >
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5">
                <button
                    type="button"
                    onclick="closeEditUserModal()"
                    class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors cursor-pointer"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    class="px-5 py-2 rounded-xl bg-gradient-to-r from-[#063B00] to-emerald-900 hover:opacity-95 text-white font-extrabold text-xs shadow-md flex items-center gap-1.5 transition-all cursor-pointer hover:scale-[1.01]"
                >
                    <i class="fa-solid fa-check text-[#A8E63A]"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL DELETE USER CONFIRMATION -->
<div id="deleteUserModal" class="fixed inset-0 items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto animate-in fade-in duration-150" style="display: none; z-index: 99999;" onclick="if(event.target === this) closeDeleteUserModal();">
    <div class="bg-white rounded-3xl p-6 shadow-2xl space-y-4 my-8 border border-slate-100 flex flex-col" style="max-width: 420px; width: 100%; box-sizing: border-box;" onclick="event.stopPropagation();">
        <div class="flex items-start gap-3.5">
            <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center text-base border border-rose-100/80 shadow-2xs shrink-0 mt-0.5">
                <i class="fa-solid fa-trash-can"></i>
            </div>
            <div class="space-y-1">
                <h3 class="text-sm font-black text-slate-900">Hapus Akun Pengguna</h3>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Apakah Anda yakin ingin menghapus akun pengguna <strong id="deleteUserNameDisplay" class="text-slate-800 font-extrabold"></strong>? Tindakan ini tidak dapat dibatalkan.
                </p>
            </div>
        </div>

        <form id="deleteUserForm" action="" method="POST" class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5">
            @csrf
            @method('DELETE')
            <button
                type="button"
                onclick="closeDeleteUserModal()"
                class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors cursor-pointer"
            >
                Batal
            </button>
            <button
                type="submit"
                class="px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-extrabold text-xs shadow-md shadow-rose-600/20 flex items-center gap-1.5 transition-all cursor-pointer hover:scale-[1.01]"
            >
                <i class="fa-solid fa-trash-can text-xs"></i> Hapus Akun
            </button>
        </form>
    </div>
</div>

<script>
    function openEditUserModal(user) {
        const modal = document.getElementById('editUserModal');
        const form = document.getElementById('editUserForm');
        form.action = `/admin/users/${user.user_id}`;

        document.getElementById('editUserName').value = user.nama || '';
        document.getElementById('editUserEmail').value = user.email || '';
        document.getElementById('editUserNoHp').value = user.no_hp || '';
        document.getElementById('editUserRole').value = user.role || 'member';
        document.getElementById('editUserIsHost').checked = !!user.is_host;
        document.getElementById('editUserPassword').value = '';

        if (modal) {
            modal.style.display = 'flex';
        }
    }

    function closeEditUserModal() {
        const modal = document.getElementById('editUserModal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    function openDeleteUserModal(userId, userName) {
        const modal = document.getElementById('deleteUserModal');
        const form = document.getElementById('deleteUserForm');
        const nameDisplay = document.getElementById('deleteUserNameDisplay');

        form.action = `/admin/users/${userId}`;
        if (nameDisplay) {
            nameDisplay.textContent = userName || 'ini';
        }

        if (modal) {
            modal.style.display = 'flex';
        }
    }

    function closeDeleteUserModal() {
        const modal = document.getElementById('deleteUserModal');
        if (modal) {
            modal.style.display = 'none';
        }
    }
</script>
@endsection
