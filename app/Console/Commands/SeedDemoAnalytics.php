<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Data CONTOH untuk mencoba Dashboard Analitik di laptop. Hanya jalan di APP_ENV=local, tidak dipanggil
 * DatabaseSeeder/scheduler/deploy. Semua data ditandai jelas:
 *   - pengguna: nama berawalan "demo_", email @demo.thriftlo.test
 *   - produk  : judul berawalan "[DEMO] ", deskripsi berisi "[DATA DEMO ANALITIK]"
 *   - booking : token QR berawalan "DEMO-"; pembayaran langganan: source "demo"
 *
 *   php artisan thriftlo:seed-demo           (hapus data demo lama, lalu buat ulang)
 *   php artisan thriftlo:seed-demo --hapus   (hanya hapus data demo)
 */
class SeedDemoAnalytics extends Command
{
    public const NAME_PREFIX = 'demo_';
    public const EMAIL_DOMAIN = 'demo.thriftlo.test';
    public const TITLE_PREFIX = '[DEMO] ';
    public const MARKER = '[DATA DEMO ANALITIK]';

    protected $signature = 'thriftlo:seed-demo
                            {--hapus : Hanya hapus data demo, tanpa membuat yang baru}
                            {--hari=120 : Rentang hari ke belakang untuk transaksi contoh (30-365)}
                            {--seed=2026 : Angka acak tetap supaya hasilnya bisa diulang}';

    protected $description = 'Buat data CONTOH (bertanda demo_) untuk Dashboard Analitik. Hanya untuk APP_ENV=local.';

    private const SHOPS = [
        // [nama toko, lokasi lapak, kota (luar Batam)]
        ['Lapak Nagoya', 'Nagoya', null],
        ['Thrift Batam Center', 'Batam Center', null],
        ['Vintage Botania', 'Botania', null],
        ['Rak Jodoh', 'Jodoh', null],
        ['Jakarta Retro', 'Luar Batam', 'Jakarta'],
        ['Surabaya Second', 'Luar Batam', 'Surabaya'],
    ];

    private const ITEMS = ['Jaket Denim', 'Kemeja Flanel', 'Sepatu Kets', 'Tas Selempang', 'Kamera Analog', 'Jam Tangan', 'Hoodie', 'Celana Cargo', 'Topi Bucket', 'Walkman', 'Rok Plisket', 'Sweater Rajut'];

    private const COMMENTS = [
        'Barang sesuai video, penjual ramah.', 'Kondisi bagus, COD tepat waktu.', 'Paket rapi dan cepat sampai.',
        'Ada sedikit noda tapi sudah dijelaskan.', 'Recommended!', null, null,
    ];

    public function handle(): int
    {
        // Pengaman utama: data contoh tidak boleh masuk database selain lokal (production, staging, testing, ...)
        if (!app()->environment('local')) {
            $this->error('Ditolak: thriftlo:seed-demo hanya boleh dijalankan saat APP_ENV=local (sekarang: ' . app()->environment() . ').');

            return self::FAILURE;
        }

        $removed = $this->clearDemoData();
        $this->info("Data demo lama dihapus: {$removed} akun demo.");

        if ($this->option('hapus')) {
            return self::SUCCESS;
        }

        $days = max(30, min(365, (int) $this->option('hari')));
        mt_srand((int) $this->option('seed'));

        $counts = DB::transaction(fn () => $this->generate($days));

        $this->table(['Data', 'Jumlah'], collect($counts)->map(fn ($n, $k) => [$k, $n])->values()->all());
        $this->info('Selesai. Buka Panel Admin → Analitik. Hapus lagi dengan: php artisan thriftlo:seed-demo --hapus');

        return self::SUCCESS;
    }

    /** Hapus semua akun demo; produk, booking, pesanan, ulasan & langganannya ikut terhapus (cascade). */
    private function clearDemoData(): int
    {
        $demoUsers = User::where('email', 'like', '%@' . self::EMAIL_DOMAIN)->where('name', 'like', self::NAME_PREFIX . '%');
        $count = (clone $demoUsers)->count();

        DB::transaction(function () use ($demoUsers) {
            $ids = (clone $demoUsers)->pluck('id');
            // Ulasan menyimpan referensi barang/booking secara nullable, jadi dihapus eksplisit lebih dulu
            Review::whereIn('seller_id', $ids)->orWhereIn('buyer_id', $ids)->delete();
            $demoUsers->delete();
        });

        return $count;
    }

    private function generate(int $days): array
    {
        $now = now();
        $start = $now->copy()->startOfDay()->subDays($days);
        $categories = Category::slugs() ?: ['Fashion', 'Sepatu', 'Tas', 'Vintage Tech'];
        $password = Hash::make(Str::random(40)); // akun demo tidak untuk login
        $counts = ['Penjual' => 0, 'Pembeli' => 0, 'Produk' => 0, 'Booking COD' => 0, 'Pesanan Kirim' => 0, 'Ulasan' => 0, 'Pembayaran langganan' => 0];

        $sellers = collect(self::SHOPS)->map(function (array $shop, int $i) use ($start, $password, &$counts) {
            $counts['Penjual']++;

            return $this->user([
                'name'          => self::NAME_PREFIX . 'penjual_' . ($i + 1),
                'email'         => self::NAME_PREFIX . 'penjual_' . ($i + 1) . '@' . self::EMAIL_DOMAIN,
                'password'      => $password,
                'role'          => 'penjual',
                'seller_status' => $i === 3 ? 'pending' : 'verified',
                'nama_toko'     => self::NAME_PREFIX . $shop[0],
                'lokasi_lapak'  => $shop[1],
                'kota_lapak'    => $shop[2],
            ], $start->copy()->addDays($i * 3)->setTime(10, 0));
        });
        $verifiedSellers = $sellers->where('seller_status', 'verified')->values();

        // Pendaftar pembeli makin banyak ke arah hari ini (tren tumbuh)
        $buyers = collect(range(1, 40))->map(function (int $n) use ($start, $days, $password, &$counts) {
            $counts['Pembeli']++;
            $offset = (int) round($days * sqrt(mt_rand(0, 1000) / 1000));

            return $this->user([
                'name'     => self::NAME_PREFIX . 'pembeli_' . $n,
                'email'    => self::NAME_PREFIX . 'pembeli_' . $n . '@' . self::EMAIL_DOMAIN,
                'password' => $password,
                'role'     => 'pembeli',
            ], $start->copy()->addDays(min($offset, $days))->setTime(mt_rand(7, 22), mt_rand(0, 59))->min(now()));
        });

        $product = function (User $seller, Carbon $at, string $status) use ($categories, &$counts): Product {
            $counts['Produk']++;
            $p = new Product();
            $p->forceFill([
                'user_id'     => $seller->id,
                'title'       => self::TITLE_PREFIX . self::ITEMS[mt_rand(0, count(self::ITEMS) - 1)] . ' #' . mt_rand(100, 999),
                'description' => self::MARKER . ' Produk contoh untuk mencoba dashboard analitik.',
                'kategori'    => $categories[mt_rand(0, count($categories) - 1)],
                'price'       => mt_rand(4, 60) * 10000,
                'status'      => $status,
                'grade'       => 'Grade A (Like New)',
                'created_at'  => $at,
                'updated_at'  => $at,
            ])->save();

            return $p;
        };

        $fee = (int) config('thriftlo.booking.fee');

        for ($d = 0; $d <= $days; $d++) {
            $day = $start->copy()->addDays($d);
            // Ramai di akhir pekan, naik pelan menuju hari ini
            $perDay = mt_rand(0, $day->isWeekend() ? 5 : 3) + (int) ($d / max(1, $days) * 2);

            for ($t = 0; $t < $perDay; $t++) {
                // Jam ramai: siang & malam hari
                $hour = [9, 10, 11, 12, 12, 13, 15, 16, 18, 19, 19, 20, 20, 21, 22][mt_rand(0, 14)];
                $at = $day->copy()->setTime($hour, mt_rand(0, 59));
                if ($at->gt($now)) {
                    continue;
                }

                $seller = $verifiedSellers[mt_rand(0, $verifiedSellers->count() - 1)];
                $buyer = $buyers[mt_rand(0, $buyers->count() - 1)];
                $inBatam = $seller->lokasi_lapak !== 'Luar Batam';
                $recent = $at->diffInHours($now) < 72;

                if ($inBatam && mt_rand(1, 100) <= 80) {
                    // ---- COD
                    $roll = mt_rand(1, 100);
                    $status = $recent && $roll <= 40 ? 'Confirmed' : ($roll <= 78 ? 'Completed' : 'Cancelled');
                    $p = $product($seller, $at->copy()->subDays(mt_rand(1, 10)), match ($status) {
                        'Completed' => 'Sold Out',
                        'Confirmed' => 'Booked',
                        default     => 'Available',
                    });
                    $done = $at->copy()->addHours(mt_rand(5, 48))->min($now);

                    $b = new Booking();
                    $b->forceFill([
                        'product_id'   => $p->id,
                        'user_id'      => $buyer->id,
                        'qr_token'     => 'DEMO-' . strtoupper(Str::random(10)),
                        'cod_location' => 'Nagoya Hill Shopping Mall',
                        'cod_schedule' => $at->copy()->addDay(),
                        'status_cod'   => $status,
                        'booking_fee'  => $fee,
                        'fee_status'   => $status === 'Cancelled' ? Booking::FEE_FORFEITED : Booking::FEE_PAID,
                        'paid_at'      => $at->copy()->addMinutes(mt_rand(2, 20))->min($now),
                        'cancelled_by' => $status === 'Cancelled' ? [Booking::CANCELLED_BY_BUYER, Booking::CANCELLED_BY_SELLER][mt_rand(0, 1)] : null,
                        'cancelled_at' => $status === 'Cancelled' ? $done : null,
                        'created_at'   => $at,
                        'updated_at'   => $status === 'Confirmed' ? $at : $done,
                    ])->save();
                    $counts['Booking COD']++;

                    if ($status === 'Completed') {
                        $counts['Ulasan'] += $this->maybeReview($seller, $buyer, $p, $done, ['booking_id' => $b->id]);
                    }
                } else {
                    // ---- Kirim (escrow)
                    $releaseAt = $at->copy()->addDays(mt_rand(3, 6))->setTime(mt_rand(8, 21), mt_rand(0, 59));
                    $released = $releaseAt->lte($now);
                    $p = $product($seller, $at->copy()->subDays(mt_rand(1, 10)), 'Sold Out');
                    $shipping = mt_rand(10, 35) * 1000;

                    $o = new Order();
                    $o->forceFill([
                        'product_id'       => $p->id,
                        'user_id'          => $buyer->id,
                        'courier'          => ['JNE', 'J&T', 'SiCepat'][mt_rand(0, 2)],
                        'shipping_cost'    => $shipping,
                        'item_price'       => $p->price,
                        'total_price'      => $p->price + $shipping,
                        'shipping_address' => 'Alamat contoh (data demo)',
                        'status'           => $released ? Order::COMPLETED : Order::SHIPPED,
                        'shipped_at'       => $at->copy()->addDay()->min($now),
                        'released_at'      => $released ? $releaseAt : null,
                        'release_method'   => $released ? [Order::RELEASED_BY_BUYER, Order::RELEASED_AUTOMATICALLY][mt_rand(0, 1)] : null,
                        'biaya_platform'   => $released ? Order::platformFeeFor($p->price + $shipping) : 0,
                        'created_at'       => $at,
                        'updated_at'       => $released ? $releaseAt : $at,
                    ])->save();
                    $counts['Pesanan Kirim']++;

                    if ($released) {
                        $counts['Ulasan'] += $this->maybeReview($seller, $buyer, $p, $releaseAt, ['order_id' => $o->id]);
                    }
                }
            }
        }

        // Beberapa barang yang masih tayang (produk aktif)
        foreach (range(1, 15) as $i) {
            $product($verifiedSellers[$i % $verifiedSellers->count()], $now->copy()->subDays(mt_rand(0, $days)), 'Available');
        }

        // Langganan bulanan untuk dua penjual pertama
        $subDays = max(1, (int) config('thriftlo.subscription.days'));
        foreach ($verifiedSellers->take(2) as $seller) {
            for ($paid = $start->copy()->addDays(5)->setTime(11, 0); $paid->lte($now); $paid->addDays($subDays)) {
                $s = new SubscriptionPayment();
                $s->forceFill([
                    'user_id'        => $seller->id,
                    'amount'         => (int) config('thriftlo.subscription.price'),
                    'days'           => $subDays,
                    'paid_at'        => $paid->copy(),
                    'period_ends_at' => $paid->copy()->addDays($subDays),
                    'source'         => 'demo',
                    'created_at'     => $paid->copy(),
                    'updated_at'     => $paid->copy(),
                ])->save();
                $counts['Pembayaran langganan']++;
            }
        }

        return $counts;
    }

    private function user(array $attributes, Carbon $at): User
    {
        $user = new User();
        $user->forceFill([...$attributes, 'email_verified_at' => $at, 'created_at' => $at, 'updated_at' => $at])->save();

        return $user;
    }

    private function maybeReview(User $seller, User $buyer, Product $product, Carbon $at, array $transaction): int
    {
        if (mt_rand(1, 100) > 60) {
            return 0;
        }

        $reviewedAt = $at->copy()->addHours(mt_rand(1, 24))->min(now());
        $r = new Review();
        $r->forceFill([
            ...$transaction,
            'product_id' => $product->id,
            'seller_id'  => $seller->id,
            'buyer_id'   => $buyer->id,
            'rating'     => [3, 4, 4, 5, 5, 5][mt_rand(0, 5)],
            'comment'    => self::COMMENTS[mt_rand(0, count(self::COMMENTS) - 1)],
            'created_at' => $reviewedAt,
            'updated_at' => $reviewedAt,
        ])->save();

        return 1;
    }
}
