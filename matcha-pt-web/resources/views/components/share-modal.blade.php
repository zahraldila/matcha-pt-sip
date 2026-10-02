@props([
    'shareUrl' => null,
    'gameTitle' => null,
    'sport' => null,
    'date' => null,
    'time' => null,
    'venue' => null,
])

<!-- Modal Share Jadwal Mabar -->
<div id="shareModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs hidden items-center justify-center p-4 transition-all" onclick="if(event.target === this) closeShareModal();">
    <div class="glass-card !bg-white/95 max-w-md w-full rounded-3xl p-6 border border-white space-y-5 shadow-2xl relative animate-in fade-in zoom-in duration-150" onclick="event.stopPropagation();">
        <!-- Close Button -->
        <button type="button" onclick="closeShareModal()" class="absolute top-5 right-5 w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-400 hover:text-slate-700 flex items-center justify-center transition-colors cursor-pointer" title="Tutup">
            <i class="fa-solid fa-xmark text-sm"></i>
        </button>

        <!-- Header -->
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-emerald-50 border border-emerald-200/80 flex items-center justify-center text-[#063B00] text-base shrink-0 shadow-2xs">
                <i class="fa-solid fa-share-nodes"></i>
            </div>
            <div>
                <h3 class="text-base font-bold text-slate-900">Bagikan Jadwal Mabar</h3>
                <p class="text-xs text-slate-500">Ajak teman dan pemain lain untuk bergabung ke sesi ini.</p>
            </div>
        </div>

        <!-- Session Mini Preview -->
        <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-1.5 text-xs">
            <div class="flex items-center justify-between gap-2">
                <strong id="shareModalTitle" class="text-slate-900 font-bold truncate text-sm">
                    {{ $gameTitle ?? 'Sesi Mabar Matcha' }}
                </strong>
                <span id="shareModalSport" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#EBF8D8] text-[#063B00] border border-[#063B00]/20 shrink-0">
                    {{ $sport ?? 'Padel' }}
                </span>
            </div>
            <div class="flex items-center gap-3 text-slate-500 text-[11px] pt-0.5">
                <span id="shareModalSchedule" class="flex items-center gap-1 truncate">
                    <i class="fa-regular fa-calendar text-slate-400"></i>
                    <span>{{ $date ?? '' }} {{ $time ? '• ' . $time . ' WIB' : '' }}</span>
                </span>
                <span id="shareModalVenue" class="flex items-center gap-1 truncate">
                    <i class="fa-solid fa-location-dot text-slate-400"></i>
                    <span class="truncate">{{ $venue ?? 'Arena Olahraga' }}</span>
                </span>
            </div>
        </div>

        <!-- Share Link Box -->
        <div class="space-y-1.5">
            <label class="block text-slate-700 font-semibold text-xs">Link Jadwal Mabar</label>
            <div class="flex items-center gap-2">
                <div class="relative flex-1">
                    <input
                        type="text"
                        id="shareModalLinkInput"
                        readonly
                        value="{{ $shareUrl ?? '' }}"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-700 font-mono text-xs focus:outline-none select-all pr-8 truncate"
                        onclick="this.select();"
                    >
                </div>
                <button
                    type="button"
                    id="copyShareBtn"
                    onclick="copyShareLink()"
                    class="px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs transition-all shadow-xs flex items-center gap-1.5 cursor-pointer shrink-0 active:scale-95"
                >
                    <i id="copyBtnIcon" class="fa-regular fa-copy text-xs"></i>
                    <span id="copyBtnText">Copy</span>
                </button>
            </div>
            <p id="copySuccessMsg" class="text-[11px] text-emerald-600 font-medium hidden flex items-center gap-1 animate-in fade-in">
                <i class="fa-solid fa-circle-check"></i> Link berhasil disalin ke clipboard!
            </p>
        </div>

        <!-- Action Buttons -->
        <div class="space-y-2 pt-1 border-t border-slate-100">
            <!-- Share to WhatsApp -->
            <a
                id="whatsappShareBtn"
                href="#"
                target="_blank"
                rel="noopener noreferrer"
                class="w-full py-2.5 px-4 rounded-xl bg-[#25D366] hover:bg-[#1EBE5D] text-white font-bold text-xs shadow-xs transition-all flex items-center justify-center gap-2 cursor-pointer hover:scale-[1.01] active:scale-95"
            >
                <i class="fa-brands fa-whatsapp text-sm"></i>
                <span>Share ke WhatsApp</span>
            </a>

            <!-- Native Web Share (if supported) -->
            <button
                type="button"
                id="nativeShareBtn"
                onclick="triggerNativeShare()"
                class="w-full py-2.5 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors flex items-center justify-center gap-2 cursor-pointer hidden"
            >
                <i class="fa-solid fa-arrow-up-from-bracket text-xs"></i>
                <span>Bagikan via Aplikasi Lain</span>
            </button>
        </div>
    </div>
</div>

<script>
    let activeShareData = {
        url: "{{ $shareUrl ?? '' }}",
        title: "{{ $gameTitle ?? 'Sesi Mabar' }}",
        sport: "{{ $sport ?? 'Padel' }}",
        date: "{{ $date ?? '' }}",
        time: "{{ $time ?? '' }}",
        venue: "{{ $venue ?? '' }}",
    };

    function openShareModal(url, title, sport, date, time, venue) {
        if (url) activeShareData.url = url;
        if (title) activeShareData.title = title;
        if (sport) activeShareData.sport = sport;
        if (date) activeShareData.date = date;
        if (time) activeShareData.time = time;
        if (venue) activeShareData.venue = venue;

        const modal = document.getElementById('shareModal');
        const input = document.getElementById('shareModalLinkInput');
        const titleEl = document.getElementById('shareModalTitle');
        const sportEl = document.getElementById('shareModalSport');
        const scheduleEl = document.getElementById('shareModalSchedule');
        const venueEl = document.getElementById('shareModalVenue');
        const waBtn = document.getElementById('whatsappShareBtn');
        const nativeBtn = document.getElementById('nativeShareBtn');

        if (input) input.value = activeShareData.url;
        if (titleEl) titleEl.textContent = activeShareData.title;
        if (sportEl) sportEl.textContent = activeShareData.sport;
        if (scheduleEl) {
            scheduleEl.innerHTML = `<i class="fa-regular fa-calendar text-slate-400"></i> <span>${activeShareData.date || ''} ${activeShareData.time ? '• ' + activeShareData.time + ' WIB' : ''}</span>`;
        }
        if (venueEl) {
            venueEl.innerHTML = `<i class="fa-solid fa-location-dot text-slate-400"></i> <span class="truncate">${activeShareData.venue || ''}</span>`;
        }

        // WhatsApp message template
        const waText = `Halo! Yuk gabung sesi mabar *${activeShareData.title}* (${activeShareData.sport}) 🎾\n` +
            `📍 *Venue:* ${activeShareData.venue}\n` +
            `🗓 *Jadwal:* ${activeShareData.date} • ${activeShareData.time} WIB\n\n` +
            `Slot terbatas! Cek jadwal lengkap & ikutan join di sini:\n${activeShareData.url}`;
        if (waBtn) {
            waBtn.href = `https://api.whatsapp.com/send?text=${encodeURIComponent(waText)}`;
        }

        // Check Web Share API
        if (navigator.share && nativeBtn) {
            nativeBtn.classList.remove('hidden');
        }

        resetCopyBtn();
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
    }

    function closeShareModal() {
        const modal = document.getElementById('shareModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    }

    function resetCopyBtn() {
        const icon = document.getElementById('copyBtnIcon');
        const text = document.getElementById('copyBtnText');
        const msg = document.getElementById('copySuccessMsg');
        if (icon) icon.className = 'fa-regular fa-copy text-xs';
        if (text) text.textContent = 'Copy';
        if (msg) msg.classList.add('hidden');
    }

    async function copyShareLink() {
        const input = document.getElementById('shareModalLinkInput');
        const url = input ? input.value : activeShareData.url;

        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(url);
            } else {
                input.select();
                document.execCommand('copy');
            }

            const icon = document.getElementById('copyBtnIcon');
            const text = document.getElementById('copyBtnText');
            const msg = document.getElementById('copySuccessMsg');

            if (icon) icon.className = 'fa-solid fa-check text-emerald-400 text-xs';
            if (text) text.textContent = 'Tersalin!';
            if (msg) msg.classList.remove('hidden');

            setTimeout(() => {
                resetCopyBtn();
            }, 3000);
        } catch (err) {
            console.error('Gagal menyalin link: ', err);
            if (input) {
                input.select();
            }
        }
    }

    async function triggerNativeShare() {
        if (!navigator.share) return;
        try {
            await navigator.share({
                title: activeShareData.title,
                text: `Yuk gabung mabar ${activeShareData.title} di ${activeShareData.venue}!`,
                url: activeShareData.url
            });
        } catch (e) {
            // User cancelled or share failed
        }
    }
</script>
