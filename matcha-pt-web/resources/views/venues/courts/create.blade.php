@extends('layouts.app')

@section('content')
<div class="min-h-screen py-10 px-4 sm:px-6 lg:px-8 relative">

    <!-- Ambient Glowing Orbs Background -->
    <div class="absolute w-96 h-96 bg-[#A8E63A]/20 rounded-full blur-3xl pointer-events-none -top-12 -left-12 -z-10"></div>
    <div class="absolute w-96 h-96 bg-[#063B00]/10 rounded-full blur-3xl pointer-events-none top-1/2 -right-12 -z-10"></div>

    <div class="mx-auto max-w-3xl space-y-6">

        <!-- Navigation Link -->
        <a href="{{ route('venues.show', $venue->venue_id) }}" class="inline-flex items-center gap-2 text-xs font-semibold text-slate-500 hover:text-[#063B00] transition-colors group">
            <span class="w-7 h-7 rounded-xl bg-white/80 border border-slate-200/80 flex items-center justify-center text-slate-600 group-hover:bg-[#063B00] group-hover:text-white transition-all shadow-2xs">
                <i class="fa-solid fa-arrow-left text-[11px]"></i>
            </span>
            Kembali ke Detail Venue
        </a>

        <!-- Main Card -->
        <div class="rounded-3xl border border-white/90 bg-white/85 p-6 sm:p-10 shadow-[0_20px_50px_rgba(6,59,0,0.08)] backdrop-blur-2xl space-y-8">

            <!-- Header Section -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-100">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="rounded-full border border-[#063B00]/20 bg-[#EBF8D8] px-2.5 py-0.5 text-[10px] font-extrabold uppercase tracking-wider text-[#063B00]">
                            Venue Owner Portal
                        </span>
                        <span class="text-xs text-slate-400">•</span>
                        <span class="text-xs font-semibold text-slate-500">
                            Konfigurasi Lapangan
                        </span>
                    </div>

                    <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-slate-900">
                        Daftarkan Lapangan / Court
                    </h1>

                    <p class="mt-1 text-xs sm:text-sm text-slate-500">
                        Lengkapi rincian lapangan untuk venue <strong class="text-[#063B00] font-extrabold">{{ $venue->nama_venue }}</strong>.
                    </p>
                </div>

                <div class="hidden sm:flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-[#EBF8D8]/60 border border-[#063B00]/15 text-[#063B00] text-xs font-bold shrink-0">
                    <i class="fa-solid fa-table-tennis-paddle-ball text-sm"></i>
                    <span>Total <span id="courtCounter">{{ $count ?? 1 }}</span> Lapangan</span>
                </div>
            </div>

            <!-- Flash Success / Error Messages -->
            @if(session('success'))
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50/90 px-4 py-3 text-xs font-bold text-emerald-800 flex items-center gap-2.5">
                    <i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if($errors->any())
                <div class="rounded-2xl border border-rose-200 bg-rose-50/90 px-4 py-3 text-xs text-rose-700 space-y-1">
                    <p class="font-bold flex items-center gap-2">
                        <i class="fa-solid fa-circle-exclamation text-rose-600"></i>
                        Periksa kembali data court:
                    </p>
                    <ul class="list-disc pl-5 text-[11px] space-y-0.5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Multi-Court Form -->
            <form id="multiCourtForm" action="{{ route('venues.courts.store', $venue->venue_id) }}" method="POST" class="space-y-6">
                @csrf

                <!-- Container Kartu-Kartu Lapangan -->
                <div id="courtCardsContainer" class="space-y-4">
                    <!-- Populated dynamically via PHP / JS -->
                </div>

                <!-- Tombol Tambah Lapangan Baru -->
                <div class="pt-2">
                    <button
                        type="button"
                        id="addCourtBtn"
                        class="w-full py-3.5 px-4 rounded-2xl border-2 border-dashed border-slate-200 hover:border-[#063B00] bg-slate-50/60 hover:bg-[#EBF8D8]/30 text-slate-700 hover:text-[#063B00] font-bold text-xs transition-all flex items-center justify-center gap-2 cursor-pointer group"
                    >
                        <span class="w-6 h-6 rounded-lg bg-white border border-slate-200 group-hover:border-[#063B00] flex items-center justify-center text-xs text-[#063B00] transition-all shadow-2xs">
                            <i class="fa-solid fa-plus"></i>
                        </span>
                        <span>+ Tambah Lapangan Lain</span>
                    </button>
                </div>

                <!-- Footer Buttons -->
                <div class="flex flex-col sm:flex-row items-center justify-between gap-3 border-t border-slate-100 pt-6">
                    <a
                        href="{{ route('venues.show', $venue->venue_id) }}"
                        class="px-5 py-3 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-all w-full sm:w-auto text-center"
                    >
                        Lewati & Lihat Venue
                    </a>

                    <button
                        type="submit"
                        class="px-7 py-3 rounded-2xl bg-[#063B00] hover:bg-[#042a00] text-white font-black text-xs shadow-md transition-all hover:scale-[1.02] active:scale-95 flex items-center justify-center gap-2 cursor-pointer w-full sm:w-auto whitespace-nowrap"
                    >
                        <i class="fa-solid fa-floppy-disk text-[#A8E63A]"></i>
                        <span id="submitBtnText">Simpan Semua Lapangan</span>
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<!-- TEMPLATE SCRIPT MULTI-COURT DYNAMIC CARDS -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('courtCardsContainer');
    const addBtn = document.getElementById('addCourtBtn');
    const counterSpan = document.getElementById('courtCounter');
    const submitBtnText = document.getElementById('submitBtnText');

    // Initial config dari backend
    const initialCount = {{ $count ?? 1 }};
    const defaultSport = "{{ $sport ?? 'Padel' }}";
    const defaultType = "{{ $type ?? 'Indoor' }}";

    let courtIndex = 0;

    function createCourtCard(index, courtName, sportVal, typeVal, priceVal) {
        const card = document.createElement('div');
        card.className = 'court-card rounded-2xl border border-slate-200/90 bg-white p-5 shadow-xs space-y-4 relative transition-all';
        card.dataset.index = index;

        card.innerHTML = `
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2.5">
                    <span class="court-badge w-6 h-6 rounded-lg bg-[#063B00] text-white flex items-center justify-center text-xs font-black">
                        ${index + 1}
                    </span>
                    <h3 class="court-title text-xs font-black uppercase tracking-wider text-slate-800">
                        Lapangan #${index + 1}
                    </h3>
                </div>

                <button
                    type="button"
                    class="remove-court-btn w-7 h-7 rounded-xl bg-slate-50 hover:bg-rose-50 text-slate-400 hover:text-rose-600 border border-slate-200/80 hover:border-rose-200 flex items-center justify-center text-xs transition-all cursor-pointer"
                    title="Hapus lapangan ini"
                >
                    <i class="fa-solid fa-trash-can text-[11px]"></i>
                </button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
                <!-- Nama Court -->
                <div class="space-y-1">
                    <label class="block font-bold text-slate-700">Nama Court / Lapangan <span class="text-rose-500">*</span></label>
                    <input
                        type="text"
                        name="courts[${index}][nama_court]"
                        value="${courtName}"
                        required
                        maxlength="100"
                        placeholder="Contoh: Court 1 / Court A"
                        class="w-full bg-slate-50/70 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-bold text-slate-900 focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs"
                    >
                </div>

                <!-- Cabang Olahraga -->
                <div class="space-y-1">
                    <label class="block font-bold text-slate-700">Cabang Olahraga</label>
                    <select
                        name="courts[${index}][sport_name]"
                        class="w-full bg-slate-50/70 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-bold text-slate-900 focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs"
                    >
                        <option value="Padel" ${sportVal === 'Padel' ? 'selected' : ''}>Padel</option>
                        <option value="Tennis" ${sportVal === 'Tennis' ? 'selected' : ''}>Tennis</option>
                    </select>
                </div>

                <!-- Tipe Arena -->
                <div class="space-y-1">
                    <label class="block font-bold text-slate-700">Tipe Arena</label>
                    <select
                        name="courts[${index}][tipe_court]"
                        class="w-full bg-slate-50/70 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs font-bold text-slate-900 focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs"
                    >
                        <option value="Indoor" ${typeVal === 'Indoor' ? 'selected' : ''}>Indoor</option>
                        <option value="Outdoor" ${typeVal === 'Outdoor' ? 'selected' : ''}>Outdoor</option>
                        <option value="Semi-Indoor" ${typeVal === 'Semi-Indoor' ? 'selected' : ''}>Semi-Indoor</option>
                    </select>
                </div>

                <!-- Harga Sewa per Jam -->
                <div class="space-y-1">
                    <label class="block font-bold text-slate-700">Harga / Jam (Rp) <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[10px] font-extrabold text-slate-400 pointer-events-none">Rp</span>
                        <input
                            type="number"
                            name="courts[${index}][harga_per_jam]"
                            value="${priceVal !== undefined && priceVal !== null ? priceVal : ''}"
                            required
                            min="0"
                            step="5000"
                            placeholder="Contoh: 150000"
                            class="w-full bg-slate-50/70 border border-slate-200 rounded-xl pl-8 pr-3 py-2.5 text-xs font-bold text-slate-900 focus:bg-white focus:border-[#063B00] focus:ring-2 focus:ring-[#A8E63A]/25 focus:outline-none transition-all shadow-2xs"
                        >
                    </div>
                </div>
            </div>
        `;

        const removeBtn = card.querySelector('.remove-court-btn');
        removeBtn.addEventListener('click', () => {
            const totalCards = container.querySelectorAll('.court-card').length;
            if (totalCards <= 1) {
                alert('Minimal harus ada 1 lapangan untuk didaftarkan.');
                return;
            }
            card.remove();
            reindexCards();
        });

        return card;
    }

    function reindexCards() {
        const cards = container.querySelectorAll('.court-card');
        cards.forEach((card, idx) => {
            card.dataset.index = idx;
            const badge = card.querySelector('.court-badge');
            const title = card.querySelector('.court-title');
            if (badge) badge.textContent = idx + 1;
            if (title) title.textContent = `Lapangan #${idx + 1}`;

            // Update input names
            card.querySelectorAll('input, select').forEach(input => {
                const name = input.getAttribute('name');
                if (name) {
                    input.setAttribute('name', name.replace(/courts\[\d+\]/, `courts[${idx}]`));
                }
            });
        });

        if (counterSpan) counterSpan.textContent = cards.length;
        if (submitBtnText) submitBtnText.textContent = `Simpan Semua Lapangan (${cards.length} Court)`;
    }

    // Inisialisasi awal sejumlah initialCount
    for (let i = 0; i < initialCount; i++) {
        const courtName = `Court ${i + 1}`;
        const card = createCourtCard(i, courtName, defaultSport, defaultType, '');
        container.appendChild(card);
    }
    reindexCards();

    // Event tambah court
    addBtn.addEventListener('click', function () {
        const currentCount = container.querySelectorAll('.court-card').length;
        const newIndex = currentCount;
        const newCourtName = `Court ${newIndex + 1}`;
        const newCard = createCourtCard(newIndex, newCourtName, defaultSport, defaultType, '');
        container.appendChild(newCard);
        reindexCards();

        // Scroll to new card smoothly
        newCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
});
</script>
@endsection
