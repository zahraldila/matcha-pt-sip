@props(['paginator'])

@if ($paginator->hasPages())
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pt-6 mt-4 border-t border-slate-200/70">
        <!-- Result Summary -->
        <div class="text-xs text-slate-500 font-medium text-center sm:text-left">
            Menampilkan <span class="font-bold text-[#050608]">{{ $paginator->firstItem() }}</span> - <span class="font-bold text-[#050608]">{{ $paginator->lastItem() }}</span> dari total <span class="font-bold text-[#063B00]">{{ $paginator->total() }}</span> data
        </div>

        <!-- Navigation Buttons -->
        <nav role="navigation" aria-label="Pagination Navigation" class="flex items-center justify-center gap-1.5 select-none">
            {{-- Previous Page Link --}}
            @if ($paginator->onFirstPage())
                <span class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold text-slate-300 bg-slate-50/80 border border-slate-200/50 cursor-not-allowed">
                    <i class="fa-solid fa-chevron-left text-[10px]"></i>
                    <span class="hidden sm:inline">Sebelumnya</span>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 border border-slate-200 shadow-2xs hover:shadow-xs hover:text-[#063B00] transition-all hover:scale-[1.02]">
                    <i class="fa-solid fa-chevron-left text-[10px]"></i>
                    <span class="hidden sm:inline">Sebelumnya</span>
                </a>
            @endif

            {{-- Pagination Elements / Page Numbers --}}
            @php
                $current = $paginator->currentPage();
                $last = $paginator->lastPage();
                $start = max(1, $current - 1);
                $end = min($last, $current + 1);

                if ($current <= 2) {
                    $end = min($last, 3);
                }
                if ($current >= $last - 1) {
                    $start = max(1, $last - 2);
                }
            @endphp

            {{-- First page button if needed --}}
            @if ($start > 1)
                <a href="{{ $paginator->url(1) }}" class="w-9 h-9 flex items-center justify-center rounded-xl text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 border border-slate-200 shadow-2xs hover:text-[#063B00] transition-all hover:scale-105">
                    1
                </a>
                @if ($start > 2)
                    <span class="w-7 text-center text-xs text-slate-400 font-bold">...</span>
                @endif
            @endif

            {{-- Numbered Page Links --}}
            @for ($page = $start; $page <= $end; $page++)
                @if ($page == $current)
                    <span aria-current="page" class="w-9 h-9 flex items-center justify-center rounded-xl text-xs font-black text-white bg-[#063B00] border border-[#063B00] shadow-sm ring-2 ring-[#063B00]/20">
                        {{ $page }}
                    </span>
                @else
                    <a href="{{ $paginator->url($page) }}" class="w-9 h-9 flex items-center justify-center rounded-xl text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 border border-slate-200 shadow-2xs hover:text-[#063B00] transition-all hover:scale-105">
                        {{ $page }}
                    </a>
                @endif
            @endfor

            {{-- Last page button if needed --}}
            @if ($end < $last)
                @if ($end < $last - 1)
                    <span class="w-7 text-center text-xs text-slate-400 font-bold">...</span>
                @endif
                <a href="{{ $paginator->url($last) }}" class="w-9 h-9 flex items-center justify-center rounded-xl text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 border border-slate-200 shadow-2xs hover:text-[#063B00] transition-all hover:scale-105">
                    {{ $last }}
                </a>
            @endif

            {{-- Next Page Link --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 border border-slate-200 shadow-2xs hover:shadow-xs hover:text-[#063B00] transition-all hover:scale-[1.02]">
                    <span class="hidden sm:inline">Berikutnya</span>
                    <i class="fa-solid fa-chevron-right text-[10px]"></i>
                </a>
            @else
                <span class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold text-slate-300 bg-slate-50/80 border border-slate-200/50 cursor-not-allowed">
                    <span class="hidden sm:inline">Berikutnya</span>
                    <i class="fa-solid fa-chevron-right text-[10px]"></i>
                </span>
            @endif
        </nav>
    </div>
@elseif ($paginator->total() > 0)
    <div class="pt-4 border-t border-slate-200/70 text-center sm:text-left">
        <p class="text-xs text-slate-400">Menampilkan semua <span class="font-bold text-slate-600">{{ $paginator->total() }}</span> data</p>
    </div>
@endif
