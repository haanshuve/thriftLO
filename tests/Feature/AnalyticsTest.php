<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\AnalyticsController;
use App\Models\Booking;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $sellerA;
    private User $sellerB;
    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // Waktu tetap: Jumat 20 Nov 2026 12:00
        Carbon::setTestNow(Carbon::parse('2026-11-20 12:00:00'));

        $this->admin = User::factory()->create(['role' => 'admin', 'created_at' => '2026-01-01']);
        $this->sellerA = User::factory()->create(['role' => 'penjual', 'seller_status' => 'verified', 'nama_toko' => 'Toko A', 'lokasi_lapak' => 'Nagoya', 'created_at' => '2026-01-01']);
        $this->sellerB = User::factory()->create(['role' => 'penjual', 'seller_status' => 'verified', 'nama_toko' => 'Toko B', 'lokasi_lapak' => 'Luar Batam', 'kota_lapak' => 'Jakarta', 'created_at' => '2026-11-05 09:00']);
        $this->buyer = User::factory()->create(['role' => 'pembeli', 'email' => 'raihanbuyer@contoh.com', 'created_at' => '2026-11-10 08:00']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function product(User $seller, int $price, string $kategori, string $status = 'Sold Out', string $title = 'Barang'): Product
    {
        $p = new Product();
        $p->forceFill([
            'user_id' => $seller->id, 'title' => $title, 'price' => $price, 'kategori' => $kategori,
            'status' => $status, 'created_at' => '2026-01-02', 'updated_at' => '2026-01-02',
        ])->save();

        return $p;
    }

    private function booking(Product $p, string $status, string $createdAt, string $updatedAt, string $fee = Booking::FEE_PAID): Booking
    {
        $b = new Booking();
        $b->forceFill([
            'product_id' => $p->id, 'user_id' => $this->buyer->id, 'qr_token' => 'TL-' . uniqid(),
            'cod_location' => 'Nagoya', 'cod_schedule' => $createdAt, 'status_cod' => $status,
            'booking_fee' => 2000, 'fee_status' => $fee, 'paid_at' => $createdAt,
            'created_at' => $createdAt, 'updated_at' => $updatedAt,
        ])->save();

        return $b;
    }

    private function order(Product $p, string $createdAt, ?string $releasedAt, int $commission = 3000): Order
    {
        $o = new Order();
        $o->forceFill([
            'product_id' => $p->id, 'user_id' => $this->buyer->id, 'courier' => 'JNE', 'shipping_cost' => 15000,
            'item_price' => $p->price, 'total_price' => $p->price + 15000, 'shipping_address' => 'Jl. Contoh',
            'status' => $releasedAt ? Order::COMPLETED : Order::SHIPPED, 'shipped_at' => $createdAt,
            'released_at' => $releasedAt, 'biaya_platform' => $releasedAt ? $commission : 0,
            'created_at' => $createdAt, 'updated_at' => $releasedAt ?? $createdAt,
        ])->save();

        return $o;
    }

    /**
     * Data kecil, periode 30 hari = 22 Okt – 20 Nov 2026; periode lalu = 22 Sep – 21 Okt 2026.
     */
    private function seedSmallDataset(): void
    {
        $codDone = $this->booking($this->product($this->sellerA, 100000, 'Fashion'), 'Completed', '2026-11-18 19:00:00', '2026-11-18 19:30:00');
        $this->booking($this->product($this->sellerA, 50000, 'Fashion', 'Available'), 'Cancelled', '2026-11-17 10:00:00', '2026-11-17 11:00:00', Booking::FEE_FORFEITED);
        $kirim = $this->order($this->product($this->sellerB, 200000, 'Sepatu'), '2026-11-15 20:00:00', '2026-11-19 09:00:00');
        // Pesanan Kirim yang belum cair: bukan transaksi selesai, komisi belum dihitung
        $this->order($this->product($this->sellerB, 999000, 'Sepatu'), '2026-11-19 08:00:00', null);
        // Periode lalu
        $this->booking($this->product($this->sellerA, 80000, 'Tas'), 'Completed', '2026-10-10 12:00:00', '2026-10-10 15:00:00');
        // Di luar kedua periode
        $this->booking($this->product($this->sellerA, 70000, 'Tas'), 'Completed', '2026-08-01 12:00:00', '2026-08-01 15:00:00');

        SubscriptionPayment::create(['user_id' => $this->sellerA->id, 'amount' => 5000, 'days' => 30, 'paid_at' => '2026-11-10 10:00:00', 'source' => 'simulasi']);
        Review::create(['order_id' => $kirim->id, 'product_id' => $kirim->product_id, 'seller_id' => $this->sellerB->id, 'buyer_id' => $this->buyer->id, 'rating' => 4]);
        Review::create(['booking_id' => $codDone->id, 'product_id' => $codDone->product_id, 'seller_id' => $this->sellerA->id, 'buyer_id' => $this->buyer->id, 'rating' => 5]);
    }

    private function analytics(array $query = []): array
    {
        $p = AnalyticsService::period($query);

        return ['period' => $p, ...app(AnalyticsService::class)->build($p['start'], $p['end'], $p['prevStart'], $p['prevEnd'], $p['grouping'])];
    }

    // ---------------------------------------------------------------- Akses

    public function test_only_admin_can_open_analytics_and_export(): void
    {
        $this->get(route('admin.analytics'))->assertRedirect('/login');
        $this->get(route('admin.analytics.export'))->assertRedirect('/login');

        foreach (['pembeli', 'penjual'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('admin.analytics'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.analytics.export'))->assertForbidden();
        }

        $this->actingAs($this->admin)->get(route('admin.analytics'))->assertOk()
            ->assertSee('Analitik')
            ->assertSee('href="' . route('admin.analytics') . '"', false) // menu sidebar
            ->assertSee('Ekspor CSV');
    }

    // ---------------------------------------------------------------- Metrik

    public function test_kpis_are_computed_from_the_small_dataset(): void
    {
        $this->seedSmallDataset();
        $kpis = $this->analytics()['kpis'];

        $this->assertSame(300000, $kpis['gmv']['value']);          // 100.000 COD + 200.000 Kirim
        $this->assertSame(80000, $kpis['gmv']['previous']);
        $this->assertSame(['value' => 275.0, 'unit' => '%'], $kpis['gmv']['change']);
        $this->assertSame(2, $kpis['completed']['value']);
        $this->assertSame(12000, $kpis['revenue']['value']);       // 2.000 + 2.000 hangus + 3.000 komisi + 5.000 langganan
        $this->assertSame(2000, $kpis['revenue']['previous']);
        $this->assertSame(2, $kpis['newUsers']['value']);          // sellerB + buyer (admin tidak dihitung)
        $this->assertSame(2, $kpis['verifiedSellers']['value']);
        $this->assertSame(1, $kpis['verifiedSellers']['previous']); // sellerB belum terdaftar di akhir periode lalu
        $this->assertSame(50.0, $kpis['cancelRate']['value']);     // 1 dari 2 booking yang dibuat
        $this->assertSame(['value' => 50.0, 'unit' => 'poin'], $kpis['cancelRate']['change']);
        $this->assertSame(150000, $kpis['aov']['value']);
    }

    public function test_charts_and_insights_are_computed_from_the_small_dataset(): void
    {
        $this->seedSmallDataset();
        ['charts' => $c, 'heatmap' => $heatmap] = $this->analytics();

        $this->assertSame(300000, array_sum($c['sales']['total']));
        $this->assertCount(30, $c['sales']['labels']);             // 30 hari, harian

        $this->assertSame(['Biaya booking' => 4000, 'Komisi Kirim' => 3000, 'Langganan' => 5000], $c['revenue']['totals']);
        $this->assertStringContainsString('Sumber pendapatan terbesar adalah langganan', $c['revenue']['insight']);

        $this->assertSame(['Sepatu', 'Pakaian'], $c['categories']['labels']); // label dari tabel kategori (slug Fashion)
        $this->assertStringContainsString('Kategori terlaris adalah Sepatu dengan Rp200.000 dari 1 transaksi', $c['categories']['insight']);

        $this->assertSame(['COD' => 1, 'Kirim' => 1], $c['channels']['totals']);

        $this->assertSame(['Toko B (★4,0)', 'Toko A (★5,0)'], $c['sellers']['labels']);
        $this->assertStringContainsString('Penjual teratas adalah Toko B dengan Rp200.000 dari 1 transaksi, rating 4,0', $c['sellers']['insight']);

        $this->assertSame([2, 2, 1], $c['funnel']['values']);

        // 2 booking + 2 pesanan dibuat pada periode: Rabu 19.00, Selasa 10.00, Minggu 20.00, Kamis 08.00
        $this->assertSame(4, array_sum(array_map('array_sum', array_column($heatmap['rows'], 'hours'))));
        $this->assertSame('Rabu', $heatmap['rows'][2]['day']);
        $this->assertSame(1, $heatmap['rows'][2]['hours'][19]);

        $this->assertSame(1, array_sum($c['users']['buyers']));
        $this->assertSame(1, array_sum($c['users']['sellers']));
    }

    public function test_page_shows_kpis_with_comparison_colors_and_insights(): void
    {
        $this->seedSmallDataset();

        $html = $this->actingAs($this->admin)->get(route('admin.analytics'))->assertOk()
            ->assertSee('Rp300.000')
            ->assertSee('Rp12.000')
            ->assertSee('275%')
            ->assertSee('Kategori terlaris adalah Sepatu')
            ->assertSee('id="chart-sales"', false)
            ->assertSee('data-heatmap', false)
            ->assertSee('Data "dilihat" belum direkam', false)
            ->getContent();

        // Tingkat pembatalan naik = buruk (merah); nilai transaksi naik = baik (hijau)
        $this->assertMatchesRegularExpression('/data-kpi="cancelRate".*?bg-rose-50 text-rose-700/s', $html);
        $this->assertMatchesRegularExpression('/data-kpi="gmv".*?bg-emerald-50 text-emerald-700/s', $html);
    }

    // ---------------------------------------------------------------- Filter tanggal

    public function test_date_filters_change_the_period(): void
    {
        $this->seedSmallDataset();

        $p = AnalyticsService::period(['rentang' => '7']);
        $this->assertSame(['2026-11-14', '2026-11-20', '2026-11-07', '2026-11-13'],
            [$p['start']->toDateString(), $p['end']->toDateString(), $p['prevStart']->toDateString(), $p['prevEnd']->toDateString()]);

        $p = AnalyticsService::period(['rentang' => 'bulan_ini']);
        $this->assertSame('2026-11-01', $p['start']->toDateString());

        $p = AnalyticsService::period(['rentang' => '90']);
        $this->assertSame('mingguan', $p['grouping']);

        // Kustom: hanya 18 Nov -> hanya COD selesai Rp100.000
        $kpis = $this->analytics(['rentang' => 'kustom', 'dari' => '2026-11-18', 'sampai' => '2026-11-18'])['kpis'];
        $this->assertSame(100000, $kpis['gmv']['value']);
        $this->assertSame(1, $kpis['completed']['value']);

        // Periode lalu (Okt) saja
        $kpis = $this->analytics(['rentang' => 'kustom', 'dari' => '2026-10-01', 'sampai' => '2026-10-31'])['kpis'];
        $this->assertSame(80000, $kpis['gmv']['value']);

        $this->actingAs($this->admin)->get(route('admin.analytics', ['rentang' => 'kustom', 'dari' => '2026-11-18', 'sampai' => '2026-11-18']))
            ->assertOk()->assertSee('18 Nov 2026 – 18 Nov 2026')->assertSee('Rp100.000');
    }

    public function test_invalid_custom_range_falls_back_to_30_days_with_message(): void
    {
        foreach ([
            ['dari' => '2026-11-18', 'sampai' => '2026-11-01'],
            ['dari' => '2026-11-01', 'sampai' => '2026-12-31'],
            ['dari' => 'kemarin', 'sampai' => '2026-11-01'],
            ['dari' => '2024-01-01', 'sampai' => '2026-11-01'],
        ] as $q) {
            $p = AnalyticsService::period(['rentang' => 'kustom', ...$q]);
            $this->assertNotNull($p['error']);
            $this->assertSame('30', $p['range']);
            $this->assertSame('2026-10-22', $p['start']->toDateString());
        }

        $this->actingAs($this->admin)->get(route('admin.analytics', ['rentang' => 'kustom', 'dari' => '2026-11-18', 'sampai' => '2026-11-01']))
            ->assertOk()->assertSee('Tanggal mulai tidak boleh setelah tanggal selesai.');
    }

    // ---------------------------------------------------------------- Kosong

    public function test_empty_period_renders_without_errors(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.analytics', ['rentang' => '7']))->assertOk()
            ->assertSee('Belum ada data pada periode ini')
            ->assertSee('Belum ada pembanding')
            ->assertDontSee('<canvas', false)
            ->assertDontSee('data-heatmap', false)
            ->getContent();

        $this->assertSame(8, substr_count($html, 'Belum ada data pada periode ini'));
    }

    public function test_query_count_does_not_grow_with_data(): void
    {
        $this->seedSmallDataset();
        $count = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->analytics();

            return count(DB::getQueryLog());
        };
        $small = $count();

        foreach (range(1, 12) as $i) {
            $seller = User::factory()->create(['role' => 'penjual', 'seller_status' => 'verified', 'nama_toko' => "Toko {$i}"]);
            $this->booking($this->product($seller, 10000 * $i, 'Tas'), 'Completed', '2026-11-1' . ($i % 9) . ' 12:00:00', '2026-11-19 12:00:00');
        }

        $this->assertSame($small, $count(), 'Jumlah query harus tetap (agregat, tanpa N+1)');
    }

    // ---------------------------------------------------------------- Ekspor CSV

    public function test_csv_export_has_expected_columns_and_masks_email(): void
    {
        $this->seedSmallDataset();
        $this->product($this->sellerA, 1000, 'Fashion'); // produk tanpa transaksi tidak ikut
        $evil = $this->product($this->sellerA, 1000, 'Fashion', 'Sold Out', '=HYPERLINK("http://contoh")');
        $this->booking($evil, 'Completed', '2026-11-19 10:00:00', '2026-11-19 11:00:00');

        $response = $this->actingAs($this->admin)->get(route('admin.analytics.export', ['rentang' => '30']))->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('thriftlo-transaksi-20261022-20261120.csv', $response->headers->get('Content-Disposition'));

        $csv = $response->streamedContent();
        $lines = array_map('str_getcsv', array_values(array_filter(explode("\n", ltrim($csv, "\xEF\xBB\xBF")))));

        $this->assertSame(AnalyticsController::CSV_COLUMNS, $lines[0]);
        // 3 booking + 2 pesanan dibuat dalam 30 hari (booking Okt/Agus tidak ikut)
        $this->assertCount(1 + 5, $lines);
        $this->assertSame(['COD', 'COD', 'COD', 'Kirim', 'Kirim'], array_column(array_slice($lines, 1), 1));

        $this->assertStringNotContainsString('raihanbuyer@contoh.com', $csv);
        $this->assertStringContainsString('ra*********@contoh.com', $csv);
        $this->assertStringNotContainsStringIgnoringCase('ktp', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);      // rumus Excel dinetralkan

        $kirim = collect($lines)->first(fn ($l) => $l[1] === 'Kirim' && $l[8] === '200000');
        $this->assertSame(['Sepatu', 'Toko B', '200000', '15000', '0', '3000'], [$kirim[5], $kirim[6], $kirim[8], $kirim[9], $kirim[10], $kirim[11]]);
    }

    public function test_csv_export_of_empty_period_has_only_header(): void
    {
        $csv = $this->actingAs($this->admin)->get(route('admin.analytics.export'))->assertOk()->streamedContent();

        $this->assertSame(implode(',', AnalyticsController::CSV_COLUMNS), trim(ltrim($csv, "\xEF\xBB\xBF")));
    }

    // ---------------------------------------------------------------- Data demo

    public function test_demo_seeder_refuses_outside_local_environment(): void
    {
        foreach (['testing', 'production'] as $env) {
            $this->app['env'] = $env;
            $this->artisan('thriftlo:seed-demo')->expectsOutputToContain('Ditolak')->assertFailed();
        }

        $this->assertSame(0, User::where('email', 'like', '%@demo.thriftlo.test')->count());
    }

    public function test_demo_seeder_creates_marked_data_locally_and_can_remove_it(): void
    {
        $this->app['env'] = 'local';

        $this->artisan('thriftlo:seed-demo', ['--hari' => 30])->assertSuccessful();

        $demoUsers = User::where('email', 'like', '%@demo.thriftlo.test')->get();
        $this->assertGreaterThan(0, $demoUsers->count());
        $this->assertTrue($demoUsers->every(fn ($u) => str_starts_with($u->name, 'demo_')));
        $this->assertTrue(Product::whereIn('user_id', $demoUsers->pluck('id'))->get()->every(fn ($p) => str_starts_with($p->title, '[DEMO] ')));
        $this->assertGreaterThan(0, Booking::where('qr_token', 'like', 'DEMO-%')->count());
        $this->assertGreaterThan(0, $this->analytics()['kpis']['completed']['value']);

        $this->artisan('thriftlo:seed-demo', ['--hapus' => true])->assertSuccessful();

        $this->assertSame(0, User::where('email', 'like', '%@demo.thriftlo.test')->count());
        $this->assertSame(0, Product::where('title', 'like', '[DEMO]%')->count());
        $this->assertModelExists($this->sellerA);                  // data asli tidak tersentuh
        $this->assertModelExists($this->admin);
    }

    public function test_demo_seeder_is_not_run_automatically(): void
    {
        foreach ([database_path('seeders/DatabaseSeeder.php'), base_path('routes/console.php'), base_path('composer.json')] as $file) {
            $this->assertStringNotContainsString('seed-demo', file_get_contents($file));
            $this->assertStringNotContainsString('SeedDemoAnalytics', file_get_contents($file));
        }
    }
}
