@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8 relative">

    <!-- Ambient Glowing Orbs Background -->
    <div class="absolute w-96 h-96 bg-[#A8E63A]/20 rounded-full blur-3xl pointer-events-none -top-12 -left-12 -z-10"></div>
    <div class="absolute w-96 h-96 bg-[#063B00]/10 rounded-full blur-3xl pointer-events-none top-1/2 -right-12 -z-10"></div>
    <div class="absolute w-80 h-80 bg-emerald-400/10 rounded-full blur-3xl pointer-events-none -bottom-10 left-1/3 -z-10"></div>

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-200/60 relative z-10">
        <div>
            <a href="{{ route('venues.index') }}" class="inline-flex items-center gap-2 text-xs font-semibold text-slate-500 hover:text-[#063B00] transition-colors mb-3 group">
                <span class="w-7 h-7 rounded-xl bg-white/80 border border-slate-200/80 flex items-center justify-center text-slate-600 group-hover:bg-[#063B00] group-hover:text-white transition-all shadow-2xs">
                    <i class="fa-solid fa-arrow-left text-[11px]"></i>
                </span>
                Kembali ke Daftar Venue
            </a>
            <div class="flex items-center gap-2.5">
                <span class="px-2.5 py-0.5 rounded-full bg-[#EBF8D8] border border-[#063B00]/20 text-[#063B00] text-[10px] font-extrabold uppercase tracking-wider">
                    Venue Owner Portal
                </span>
                <span class="text-xs text-slate-400">•</span>
                <span class="text-xs font-medium text-slate-500">Registrasi Arena Olahraga</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight mt-1">
                Form Pendaftaran Venue Baru
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1 max-w-xl leading-relaxed">
                Lengkapi informasi lapangan olahraga Anda untuk ditampilkan pada direktori Matcha dan diakses oleh ratusan komunitas Tennis & Padel.
            </p>
        </div>

        <div class="hidden sm:flex flex-col items-end gap-2">
            <div class="px-4 py-2 rounded-2xl bg-white/60 backdrop-blur-md border border-white/80 shadow-2xs flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-[#063B00] to-emerald-900 text-white flex items-center justify-center font-bold text-sm shadow-xs">
                    <i class="fa-solid fa-building-circle-check text-[#A8E63A]"></i>
                </div>
                <div class="text-right">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Status Mitra</p>
                    <p class="text-xs font-black text-[#063B00]">Verified Venue Partner</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Glassmorphism Form Card -->
    <div class="backdrop-blur-2xl bg-white/80 border border-white/90 rounded-3xl p-6 sm:p-10 shadow-[0_20px_50px_rgba(6,59,0,0.06)] relative z-10 space-y-8">

        <form id="venueForm" action="{{ route('venues.store') }}" method="POST" class="space-y-8 text-xs">
            @csrf
            
            <!-- SECTION 1: Informasi Utama Venue -->
            <div class="space-y-4">
                <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100">
                    <span class="w-6 h-6 rounded-lg bg-[#063B00] text-white flex items-center justify-center text-xs font-black">1</span>
                    <h3 class="text-sm font-black text-slate-900 uppercase tracking-wide">Informasi Utama Venue</h3>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2 space-y-1.5">
                        <label class="block font-bold text-slate-800">
                            Nama Tempat / Venue <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <i class="fa-solid fa-building absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input type="text" id="venueName" name="nama_venue" value="{{ old('nama_venue') }}" placeholder="Contoh: Gelora Racquet & Padel Club" class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-10 pr-4 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs" required>
                        </div>
                    </div>

                    <div class="sm:col-span-2 space-y-1.5">
                        <label class="block font-bold text-slate-800">
                            Alamat Lengkap Venue <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <i class="fa-solid fa-map-location-dot absolute left-4 top-3.5 text-slate-400 text-xs"></i>
                            <textarea id="venueAddress" name="alamat" rows="2" placeholder="Jl. Raya Utama No. 88, Kebayoran Baru, Jakarta Selatan..." class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-10 pr-4 py-2.5 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs" required>{{ old('alamat') }}</textarea>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">Kota / Wilayah</label>
                        <div class="relative">
                            <i class="fa-solid fa-city absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input type="text" value="Jakarta Selatan" class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-10 pr-4 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs">
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">Link Google Maps (Opsional)</label>
                        <div class="relative">
                            <i class="fa-solid fa-link absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input type="url" placeholder="https://maps.app.goo.gl/..." class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-10 pr-4 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs">
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 2: Cabang Olahraga & Tipe Lapangan -->
            <div class="space-y-4 pt-2">
                <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100">
                    <span class="w-6 h-6 rounded-lg bg-[#063B00] text-white flex items-center justify-center text-xs font-black">2</span>
                    <h3 class="text-sm font-black text-slate-900 uppercase tracking-wide">Cabang Olahraga & Karakteristik Lapangan</h3>
                </div>

                <div class="space-y-2">
                    <label class="block font-bold text-slate-800">Pilih Kategori Lapangan Utama <span class="text-rose-500">*</span></label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <label class="cursor-pointer">
                            <input type="radio" name="sport_type" value="Padel" class="peer sr-only" checked>
                            <div class="p-4 rounded-2xl border border-slate-200/80 bg-slate-50/60 peer-checked:bg-gradient-to-b peer-checked:from-[#EBF8D8]/80 peer-checked:to-white peer-checked:border-[#063B00] peer-checked:ring-2 peer-checked:ring-[#063B00]/10 transition-all flex flex-col items-center text-center gap-2 group hover:border-slate-300">
                                <div class="w-10 h-10 rounded-2xl bg-white border border-slate-200/80 flex items-center justify-center text-lg text-[#063B00] shadow-2xs group-hover:scale-105 transition-transform">
                                    <i class="fa-solid fa-table-tennis-paddle-ball"></i>
                                </div>
                                <div>
                                    <h4 class="font-extrabold text-slate-900 text-xs">Padel Court</h4>
                                    <p class="text-[10px] text-slate-500">Khusus Lapangan Padel</p>
                                </div>
                            </div>
                        </label>

                        <label class="cursor-pointer">
                            <input type="radio" name="sport_type" value="Tennis" class="peer sr-only">
                            <div class="p-4 rounded-2xl border border-slate-200/80 bg-slate-50/60 peer-checked:bg-gradient-to-b peer-checked:from-[#EBF8D8]/80 peer-checked:to-white peer-checked:border-[#063B00] peer-checked:ring-2 peer-checked:ring-[#063B00]/10 transition-all flex flex-col items-center text-center gap-2 group hover:border-slate-300">
                                <div class="w-10 h-10 rounded-2xl bg-white border border-slate-200/80 flex items-center justify-center text-lg text-[#063B00] shadow-2xs group-hover:scale-105 transition-transform">
                                    <i class="fa-solid fa-baseball"></i>
                                </div>
                                <div>
                                    <h4 class="font-extrabold text-slate-900 text-xs">Tennis Court</h4>
                                    <p class="text-[10px] text-slate-500">Khusus Lapangan Tenis</p>
                                </div>
                            </div>
                        </label>

                        <label class="cursor-pointer">
                            <input type="radio" name="sport_type" value="Both" class="peer sr-only">
                            <div class="p-4 rounded-2xl border border-slate-200/80 bg-slate-50/60 peer-checked:bg-gradient-to-b peer-checked:from-[#EBF8D8]/80 peer-checked:to-white peer-checked:border-[#063B00] peer-checked:ring-2 peer-checked:ring-[#063B00]/10 transition-all flex flex-col items-center text-center gap-2 group hover:border-slate-300">
                                <div class="w-10 h-10 rounded-2xl bg-white border border-slate-200/80 flex items-center justify-center text-lg text-[#063B00] shadow-2xs group-hover:scale-105 transition-transform">
                                    <i class="fa-solid fa-trophy"></i>
                                </div>
                                <div>
                                    <h4 class="font-extrabold text-slate-900 text-xs">Multi-Racquet</h4>
                                    <p class="text-[10px] text-slate-500">Padel & Tennis Gabungan</p>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-1">
                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">Jumlah Lapangan (Court)</label>
                        <div class="relative">
                            <select class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl px-4 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none appearance-none transition-all shadow-2xs">
                                <option value="1">1 Court</option>
                                <option value="2" selected>2 Courts</option>
                                <option value="3">3 Courts</option>
                                <option value="4">4 Courts</option>
                                <option value="6">6+ Courts (Arena Besar)</option>
                            </select>
                            <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">Tipe Arena</label>
                        <div class="relative">
                            <select class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl px-4 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none appearance-none transition-all shadow-2xs">
                                <option value="Semi-Indoor" selected>Semi-Indoor (Atap Pelindung)</option>
                                <option value="Indoor">Indoor (Full AC / Tertutup)</option>
                                <option value="Outdoor">Outdoor (Terbuka)</option>
                            </select>
                            <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">Jenis Permukaan</label>
                        <div class="relative">
                            <select class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl px-4 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none appearance-none transition-all shadow-2xs">
                                <option value="Artificial Turf" selected>Artificial Turf (Rumput Sintetis Padel)</option>
                                <option value="Hard Court">Hard Court (Plexipave / Acrylic)</option>
                                <option value="Clay">Clay Court (Tanah Liat)</option>
                                <option value="Grass">Grass Court (Rumput Alami)</option>
                            </select>
                            <i class="fa-solid fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 3: Jam Operasional & Kontak Pengelola (PIC) -->
            <div class="space-y-4 pt-2">
                <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100">
                    <span class="w-6 h-6 rounded-lg bg-[#063B00] text-white flex items-center justify-center text-xs font-black">3</span>
                    <h3 class="text-sm font-black text-slate-900 uppercase tracking-wide">Jam Operasional & Kontak Pengelola</h3>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">Jam Operasional Reguler <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <i class="fa-regular fa-clock absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input type="text" value="06:00 - 23:00 WIB" class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-10 pr-4 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs" required>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">Hari Buka</label>
                        <div class="relative">
                            <i class="fa-regular fa-calendar-check absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input type="text" value="Setiap Hari (Senin - Minggu)" class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-10 pr-4 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs">
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">Nama PIC Venue / Pengelola <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <i class="fa-solid fa-user-tie absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input type="text" value="{{ Auth::user()->nama ?? 'Paulus Simatupang' }}" class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-10 pr-4 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs" required>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block font-bold text-slate-800">Nomor WhatsApp PIC Venue <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <i class="fa-brands fa-whatsapp absolute left-4 top-1/2 -translate-y-1/2 text-emerald-600 text-xs font-bold"></i>
                            <input type="tel" value="{{ Auth::user()->no_hp ?? '0812-9988-7766' }}" class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-10 pr-4 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs" required>
                        </div>
                    </div>

                    <div class="sm:col-span-2 space-y-1.5">
                        <label class="block font-bold text-slate-800">Ketentuan Ketersediaan Khusus / Maintenance Rutin</label>
                        <div class="relative">
                            <i class="fa-solid fa-circle-info absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input type="text" placeholder="Contoh: Court outdoor maintenance setiap Selasa jam 08.00-10.00" class="w-full bg-slate-50/70 border border-slate-200/80 rounded-2xl pl-10 pr-4 py-3 text-slate-900 font-semibold focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs">
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION 4: Fasilitas Tersedia (Pills Toggle) -->
            <div class="space-y-4 pt-2">
                <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100">
                    <span class="w-6 h-6 rounded-lg bg-[#063B00] text-white flex items-center justify-center text-xs font-black">4</span>
                    <h3 class="text-sm font-black text-slate-900 uppercase tracking-wide">Fasilitas yang Tersedia</h3>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                    @php
                        $facilities = [
                            ['icon' => 'fa-lightbulb', 'name' => 'Lampu Malam (LED)'],
                            ['icon' => 'fa-shower', 'name' => 'Shower & Toilet'],
                            ['icon' => 'fa-mug-hot', 'name' => 'Kantin / Cafe'],
                            ['icon' => 'fa-car', 'name' => 'Parkir Mobil/Motor'],
                            ['icon' => 'fa-snowflake', 'name' => 'AC Waiting Lounge'],
                            ['icon' => 'fa-table-tennis-paddle-ball', 'name' => 'Rental Raket & Bola'],
                            ['icon' => 'fa-lock', 'name' => 'Loker Barang'],
                            ['icon' => 'fa-wifi', 'name' => 'Free High-speed WiFi'],
                        ];
                    @endphp

                    @foreach($facilities as $index => $fac)
                        <label class="cursor-pointer">
                            <input type="checkbox" name="facilities[]" value="{{ $fac['name'] }}" class="peer sr-only" {{ $index < 4 ? 'checked' : '' }}>
                            <div class="p-3 rounded-2xl border border-slate-200/70 bg-slate-50/50 peer-checked:bg-[#EBF8D8]/70 peer-checked:border-[#063B00] peer-checked:text-[#063B00] transition-all flex items-center gap-2.5 group hover:border-slate-300">
                                <div class="w-7 h-7 rounded-xl bg-white border border-slate-200/80 flex items-center justify-center text-xs text-slate-600 peer-checked:text-[#063B00] shadow-2xs">
                                    <i class="fa-solid {{ $fac['icon'] }}"></i>
                                </div>
                                <span class="text-[11px] font-bold text-slate-700 peer-checked:text-[#063B00]">{{ $fac['name'] }}</span>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- SECTION 5: Foto Cover Venue -->
            <div class="space-y-4 pt-2">
                <div class="flex items-center gap-2.5 pb-2 border-b border-slate-100">
                    <span class="w-6 h-6 rounded-lg bg-[#063B00] text-white flex items-center justify-center text-xs font-black">5</span>
                    <h3 class="text-sm font-black text-slate-900 uppercase tracking-wide">Foto Venue & Banner Lapangan</h3>
                </div>

                <div class="border-2 border-dashed border-slate-200 hover:border-[#063B00] rounded-3xl p-6 sm:p-8 bg-slate-50/50 text-center transition-all cursor-pointer group">
                    <div class="w-14 h-14 rounded-3xl bg-white border border-slate-200 flex items-center justify-center text-slate-400 group-hover:text-[#063B00] group-hover:scale-110 transition-all mx-auto mb-3 shadow-xs">
                        <i class="fa-solid fa-cloud-arrow-up text-xl"></i>
                    </div>
                    <p class="text-xs font-bold text-slate-800">Klik untuk upload atau drag & drop foto lapangan</p>
                    <p class="text-[10px] text-slate-400 mt-1">Format JPG, PNG, atau WEBP maksimal 5MB. Resolusi lanskap disarankan (16:9).</p>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="pt-6 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3">
                <p class="text-[11px] text-slate-500 flex items-center gap-1.5">
                    <i class="fa-solid fa-shield-halved text-[#063B00]"></i>
                    Informasi venue akan ditinjau oleh tim kurasi Matcha sebelum tampil publik.
                </p>

                <div class="flex items-center gap-3 w-full sm:w-auto">
                    <button type="reset" class="px-5 py-3 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-all w-full sm:w-auto">
                        Reset Form
                    </button>
                    <button type="submit" class="px-7 py-3 rounded-2xl bg-[#063B00] hover:bg-[#042a00] text-white font-black text-xs shadow-md transition-all hover:scale-[1.02] active:scale-95 flex items-center justify-center gap-2 cursor-pointer w-full sm:w-auto">
                        <i class="fa-solid fa-paper-plane text-[#A8E63A]"></i>
                        <span>Daftarkan Venue Sekarang</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

@endsection
