{{-- Tabel data di balik grafik (aksesibilitas & angka persis). Butuh: $chart, $headers, $rows (iterable of arrays) --}}
@unless($chart['empty'])
    <details class="mt-2 text-sm">
        <summary class="cursor-pointer text-xs font-semibold text-emerald-700 hover:underline">Lihat tabel data</summary>
        <div class="relative mt-2 max-h-64 overflow-auto rounded-lg border border-slate-200">
            <table class="w-full text-xs tabular-nums">
                <thead class="bg-slate-50 text-slate-600 sticky top-0">
                    <tr>
                        @foreach($headers as $h)
                            <th scope="col" class="px-3 py-1.5 font-semibold {{ $loop->first ? 'text-left' : 'text-right' }}">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($rows as $row)
                        <tr>
                            @foreach($row as $cell)
                                <td class="px-3 py-1.5 {{ $loop->first ? 'text-left text-slate-700' : 'text-right text-slate-900' }}">{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
@endunless
