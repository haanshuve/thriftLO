{{--
    Wadah satu grafik Chart.js + insight. Grafik digambar resources/js/analytics.js dari data JSON halaman.
    Butuh: $id (kunci di $charts), $chart (data grafik, punya 'empty' & 'insight'), $label (deskripsi untuk pembaca layar),
    opsional $height (kelas tinggi, default h-64).
--}}
@unless($chart['empty'])
    <div class="relative mt-3 {{ $height ?? 'h-64' }}">
        <canvas id="chart-{{ $id }}" data-chart="{{ $id }}" role="img" aria-label="{{ $label }}"></canvas>
    </div>
@endunless
@include('admin.analytics.insight', ['text' => $chart['insight']])
