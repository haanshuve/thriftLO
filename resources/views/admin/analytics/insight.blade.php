{{-- Kotak insight satu kalimat di bawah grafik. Butuh: $text (string|null; null = data kosong) --}}
<p class="mt-3 flex items-start gap-2 rounded-lg px-3 py-2 text-sm {{ $text ? 'bg-emerald-50 text-emerald-900 border border-emerald-100' : 'bg-slate-50 text-slate-500 border border-slate-100' }}" data-insight>
    <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 18h6m-5 3h4M12 3a6 6 0 0 0-3.5 10.9c.6.5 1 1.2 1 2V16h5v-.1c0-.8.4-1.5 1-2A6 6 0 0 0 12 3Z"/></svg>
    <span><span class="font-semibold">Insight:</span> {{ $text ?? 'Belum ada data pada periode ini' }}</span>
</p>
