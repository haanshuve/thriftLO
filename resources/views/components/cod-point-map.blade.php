@props(['seller', 'compact' => false])

@php
    // Peta statis tanpa API key dan tanpa JavaScript: 3x3 tile OpenStreetMap yang digeser
    // supaya titik COD tepat di tengah kotak, lalu pin diletakkan di tengahnya.
    $lat = (float) $seller->cod_latitude;
    $lng = (float) $seller->cod_longitude;
    $zoom = 16;
    $n = 2 ** $zoom;
    $x = ($lng + 180) / 360 * $n;
    $latRad = deg2rad(max(-85.0511, min(85.0511, $lat)));
    $y = (1 - log(tan($latRad) + 1 / cos($latRad)) / M_PI) / 2 * $n;
    $tileX = (int) floor($x);
    $tileY = (int) floor($y);
    // Posisi titik di dalam grid 768x768 piksel (tile tengah = kolom/baris ke-2)
    $offsetX = round(256 + ($x - $tileX) * 256);
    $offsetY = round(256 + ($y - $tileY) * 256);
    $mapsUrl = $seller->codPointMapsUrl();
@endphp

<div {{ $attributes->merge(['class' => 'space-y-2']) }}>
    <div class="relative {{ $compact ? 'h-28' : 'h-40' }} w-full overflow-hidden rounded-lg border border-slate-200 bg-slate-100"
         role="img" aria-label="Peta titik COD: {{ $seller->cod_address }}">
        <div class="absolute" style="width:768px;height:768px;left:calc(50% - {{ $offsetX }}px);top:calc(50% - {{ $offsetY }}px)">
            @for($row = 0; $row < 3; $row++)
                @for($col = 0; $col < 3; $col++)
                    <img src="https://tile.openstreetmap.org/{{ $zoom }}/{{ ($tileX - 1 + $col + $n) % $n }}/{{ $tileY - 1 + $row }}.png"
                         alt="" loading="lazy" width="256" height="256" draggable="false"
                         class="absolute max-w-none select-none" style="left:{{ $col * 256 }}px;top:{{ $row * 256 }}px">
                @endfor
            @endfor
        </div>
        <!-- Pin: ujung bawahnya tepat di titik COD -->
        <svg class="absolute left-1/2 top-1/2 w-7 h-9 -translate-x-1/2 -translate-y-full drop-shadow" viewBox="0 0 24 32" aria-hidden="true">
            <path d="M12 0C5.4 0 0 5.4 0 12c0 9 12 20 12 20s12-11 12-20C24 5.4 18.6 0 12 0z" fill="#059669"/>
            <circle cx="12" cy="12" r="4.5" fill="#fff"/>
        </svg>
        <span class="absolute bottom-0 right-0 bg-white/85 px-1 text-[10px] text-slate-600">
            © <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener" class="underline">OpenStreetMap</a>
        </span>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-xs text-slate-600 min-w-0 break-words">📍 {{ $seller->cod_address }}</p>
        <a href="{{ $mapsUrl }}" target="_blank" rel="noopener"
           class="shrink-0 h-8 px-3 inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 text-xs font-semibold">
            Buka di Google Maps
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9M19 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h5"/></svg>
            <span class="sr-only">(buka di tab baru)</span>
        </a>
    </div>
</div>
