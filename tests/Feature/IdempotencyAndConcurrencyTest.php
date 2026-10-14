<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Order;
use App\Models\Product;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Support\FormToken;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IdempotencyAndConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;
    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seller = User::factory()->create(['role' => 'penjual', 'seller_status' => 'verified', 'nama_toko' => 'Lapak Nagoya', 'lokasi_lapak' => 'Nagoya']);
        $this->buyer = User::factory()->create(['role' => 'pembeli']);
    }

    private function product(string $title = 'Jaket Denim'): Product
    {
        return Product::create([
            'user_id' => $this->seller->id, 'title' => $title, 'price' => 100000, 'image_url' => 'products/x.jpg',
            'status' => 'Available', 'shipping_options' => [['courier' => 'JNE', 'cost' => 15000]],
        ]);
    }

    private function book(Product $product, string $key, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->buyer)->post(route('product.book', $product->id), [
            'idempotency_key' => $key, 'lokasi_cod' => 'Nagoya Hill', 'waktu_cod' => now()->addDay()->toDateTimeString(), 'qris_paid' => 1,
        ]);
    }

    private function checkout(Product $product, string $key)
    {
        return $this->actingAs($this->buyer)->post(route('order.store', $product), [
            'idempotency_key' => $key, 'shipping_option' => 0, 'shipping_address' => 'Pekanbaru',
        ]);
    }

    private function activeBooking(Product $product, ?User $buyer = null): Booking
    {
        return Booking::create([
            'product_id' => $product->id, 'user_id' => ($buyer ?? $this->buyer)->id, 'qr_token' => 'TL-' . strtoupper(substr(md5(uniqid()), 0, 8)),
            'cod_location' => 'Nagoya', 'cod_schedule' => now()->addDay(), 'status_cod' => 'Pending', 'booking_fee' => 2000, 'fee_status' => Booking::FEE_PAID,
        ]);
    }

    // ---------------------------------------------------------------- Booking COD

    public function test_booking_submitted_twice_with_same_form_creates_one_booking_and_one_fee(): void
    {
        $product = $this->product();
        $key = $this->formKey();

        $this->book($product, $key)->assertRedirect(route('bookings.index'))->assertSessionHas('success');
        $this->book($product, $key)->assertRedirect(route('bookings.index'))
            ->assertSessionHas('success', fn ($msg) => str_contains($msg, 'Booking ini sudah dibuat'));

        $this->assertSame(1, Booking::count());
        $this->assertSame(2000, (int) Booking::sum('booking_fee'));
        $this->assertSame($product->id, Booking::sole()->active_product_id);
    }

    public function test_booking_form_reused_for_another_product_is_rejected(): void
    {
        $key = $this->formKey();
        $this->book($this->product('Barang A'), $key);

        $other = $this->product('Barang B');
        $this->book($other, $key)->assertSessionHas('error', FormToken::REUSED_MESSAGE);

        $this->assertSame(1, Booking::count());
        $this->assertSame('Available', $other->fresh()->status);
    }

    public function test_booking_without_form_token_is_rejected_with_friendly_message(): void
    {
        $product = $this->product();

        $this->actingAs($this->buyer)->post(route('product.book', $product->id), [
            'lokasi_cod' => 'Nagoya', 'waktu_cod' => now()->addDay()->toDateTimeString(), 'qris_paid' => 1,
        ])->assertSessionHasErrors(['idempotency_key' => FormToken::EXPIRED_MESSAGE]);

        $this->assertSame(0, Booking::count());
    }

    public function test_second_buyer_cannot_book_same_product(): void
    {
        $product = $this->product();
        $this->book($product, $this->formKey());

        $other = User::factory()->create(['role' => 'pembeli']);
        $this->book($product, $this->formKey(), $other)->assertSessionHas('error');

        $this->assertSame(1, Booking::count());
    }

    // Simulasi balapan: request kedua sudah lolos cek status sebelum request pertama menyimpan booking.
    // Kunci baris + unique index tetap menolaknya dengan pesan ramah, tanpa error 500.
    public function test_database_rejects_second_active_booking_for_same_product_during_race(): void
    {
        $product = $this->product();
        $this->activeBooking($product); // booking "pemenang" yang belum sempat mengubah status produk

        $this->book($product, $this->formKey(), User::factory()->create(['role' => 'pembeli']))
            ->assertSessionHas('error', fn ($msg) => str_contains($msg, 'Barang sudah tidak tersedia'));

        $this->assertSame(1, Booking::count());
    }

    public function test_unique_index_allows_only_one_active_booking_per_product(): void
    {
        $product = $this->product();
        $first = $this->activeBooking($product);

        try {
            $this->activeBooking($product, User::factory()->create());
            $this->fail('Booking aktif kedua untuk produk yang sama seharusnya ditolak database.');
        } catch (UniqueConstraintViolationException) {
            $this->assertSame(1, Booking::count());
        }

        // Setelah dibatalkan, produk boleh di-booking lagi (riwayat batal tidak menghalangi)
        $this->assertTrue($first->cancelByBuyer(null));
        $this->assertNull($first->fresh()->active_product_id);
        $this->activeBooking($product, User::factory()->create());
        $this->assertSame(2, Booking::count());
    }

    public function test_booking_after_checkout_for_same_product_is_rejected(): void
    {
        $product = $this->product();
        $this->checkout($product, $this->formKey());

        $this->book($product, $this->formKey(), User::factory()->create(['role' => 'pembeli']))->assertSessionHas('error');

        $this->assertSame(0, Booking::count());
        $this->assertSame(1, Order::count());
    }

    // ---------------------------------------------------------------- Verifikasi QR vs pembatalan

    public function test_qr_verification_after_cancellation_is_rejected_and_state_stays_consistent(): void
    {
        $product = $this->product();
        $this->book($product, $this->formKey());
        $booking = Booking::sole();

        $this->actingAs($this->buyer)->patch(route('booking.cancel', $booking), ['setuju_konsekuensi' => '1']);

        $this->actingAs($this->seller)->post(route('booking.verify'), ['qr_code_token' => $booking->qr_token])
            ->assertSessionHas('error', 'Token QR ini sudah tidak berlaku karena booking-nya dibatalkan.');

        $booking->refresh();
        $this->assertSame('Cancelled', $booking->status_cod);
        $this->assertSame(Booking::FEE_FORFEITED, $booking->fee_status);
        $this->assertSame('Available', $product->fresh()->status);
    }

    public function test_qr_verified_twice_completes_once_and_frees_active_slot(): void
    {
        $product = $this->product();
        $this->book($product, $this->formKey());
        $booking = Booking::sole();

        $this->actingAs($this->seller)->post(route('booking.verify'), ['qr_code_token' => $booking->qr_token])->assertSessionHas('success');
        $this->actingAs($this->seller)->post(route('booking.verify'), ['qr_code_token' => $booking->qr_token])
            ->assertSessionHas('error', 'Token QR ini sudah pernah diverifikasi sebelumnya!');

        $this->assertSame('Completed', $booking->fresh()->status_cod);
        $this->assertNull($booking->fresh()->active_product_id);
        $this->assertSame('Sold Out', $product->fresh()->status);

        // Booking yang sudah selesai tidak bisa dibatalkan lagi
        $this->actingAs($this->buyer)->patch(route('booking.cancel', $booking), ['setuju_konsekuensi' => '1'])->assertSessionHas('error');
        $this->assertSame('Completed', $booking->fresh()->status_cod);
    }

    // ---------------------------------------------------------------- Checkout Kirim

    public function test_checkout_submitted_twice_with_same_form_creates_one_order(): void
    {
        $product = $this->product();
        $key = $this->formKey();

        $this->checkout($product, $key)->assertRedirect(route('bookings.index'))->assertSessionHas('success');
        $this->checkout($product, $key)->assertRedirect(route('bookings.index'))
            ->assertSessionHas('success', fn ($msg) => str_contains($msg, 'Pesanan ini sudah dibuat'));

        $this->assertSame(1, Order::count());
        $this->assertSame($key, Order::sole()->idempotency_key);
    }

    public function test_second_checkout_with_new_form_says_product_unavailable(): void
    {
        $product = $this->product();
        $this->checkout($product, $this->formKey());

        $this->checkout($product, $this->formKey())->assertSessionHas('error', fn ($msg) => str_contains($msg, 'sudah tidak tersedia'));
        $this->assertSame(1, Order::count());
    }

    public function test_checkout_after_booking_for_same_product_is_rejected(): void
    {
        $product = $this->product();
        $this->book($product, $this->formKey(), User::factory()->create(['role' => 'pembeli']));

        $this->checkout($product, $this->formKey())->assertSessionHas('error');
        $this->assertSame(0, Order::count());
    }

    // ---------------------------------------------------------------- Langganan

    public function test_subscription_payment_submitted_twice_is_charged_once(): void
    {
        $key = $this->formKey();

        $this->actingAs($this->seller)->post(route('subscription.pay'), ['idempotency_key' => $key])->assertSessionHas('success');
        $expires = $this->seller->fresh()->subscription_expires_at;

        $this->actingAs($this->seller)->post(route('subscription.pay'), ['idempotency_key' => $key])
            ->assertSessionHas('success', fn ($msg) => str_contains($msg, 'Pembayaran ini sudah diproses'));

        $this->assertTrue($this->seller->fresh()->subscription_expires_at->equalTo($expires));
        $payment = SubscriptionPayment::sole();
        $this->assertSame(5000, $payment->amount);
        $this->assertSame(30, $payment->days);
        $this->assertSame('simulasi', $payment->source);
    }

    public function test_two_separate_payments_both_extend_without_lost_update(): void
    {
        $this->freezeSecond();

        $this->actingAs($this->seller)->post(route('subscription.pay'), ['idempotency_key' => $this->formKey()]);
        $this->actingAs($this->seller)->post(route('subscription.pay'), ['idempotency_key' => $this->formKey()]);

        $this->assertTrue($this->seller->fresh()->subscription_expires_at->equalTo(now()->addDays(60)));
        $this->assertSame(2, SubscriptionPayment::count());
    }

    public function test_subscription_payment_without_token_is_rejected(): void
    {
        $this->actingAs($this->seller)->post(route('subscription.pay'))
            ->assertSessionHasErrors(['idempotency_key' => FormToken::EXPIRED_MESSAGE]);

        $this->assertNull($this->seller->fresh()->subscription_expires_at);
        $this->assertSame(0, SubscriptionPayment::count());
    }

    // ---------------------------------------------------------------- Produk & kuota

    private function upload(string $key, string $title = 'Jaket Baru')
    {
        return $this->actingAs($this->seller)->post(route('product.store'), [
            'idempotency_key' => $key, 'nama_barang' => $title, 'mode_jual' => 'ecer', 'kategori' => 'Fashion',
            'harga' => 75000, 'grade' => config('thriftlo.grades')[0],
            'image' => UploadedFile::fake()->image('jaket.jpg'),
            'video_proof' => UploadedFile::fake()->create('jaket.mp4', 1024, 'video/mp4'),
        ]);
    }

    public function test_product_form_submitted_twice_creates_one_product_and_no_orphan_files(): void
    {
        Storage::fake('public');
        $key = $this->formKey();

        $this->upload($key)->assertSessionHas('success', 'Barang preloved berhasil ditayangkan!');
        $this->upload($key)->assertRedirect(route('dashboard'))->assertSessionHas('success', 'Barang ini sudah ditayangkan.');

        $this->assertSame(1, Product::count());
        $this->assertCount(1, Storage::disk('public')->files('products'));
        $this->assertCount(1, Storage::disk('public')->files('product-videos'));
    }

    public function test_double_submit_of_fifth_product_does_not_show_quota_page(): void
    {
        Storage::fake('public');
        foreach (range(1, 4) as $i) {
            $this->product("Barang {$i}");
        }
        $key = $this->formKey();

        $this->upload($key, 'Barang Kelima')->assertSessionHas('success');
        // Kiriman ulang formulir yang sama: kuota sudah penuh, tapi ini bukan barang baru
        $this->upload($key, 'Barang Kelima')->assertSessionHas('success', 'Barang ini sudah ditayangkan.');

        $this->upload($this->formKey(), 'Barang Keenam')->assertRedirect(route('subscription.show'));
        $this->assertSame(5, Product::count());
    }

    // ---------------------------------------------------------------- Migrasi (data lama)

    private function migration(string $file): object
    {
        return require database_path("migrations/{$file}.php");
    }

    public function test_booking_migration_backfills_and_cancels_duplicate_active_bookings_safely(): void
    {
        $migration = $this->migration('2026_10_17_000000_add_concurrency_guards_to_bookings_table');
        $migration->down();

        $product = $this->product();
        $other = $this->product('Barang Lain');
        $row = fn (array $attrs) => DB::table('bookings')->insertGetId([
            'product_id' => $product->id, 'user_id' => $this->buyer->id, 'qr_token' => 'TL-' . uniqid(),
            'cod_location' => 'Nagoya', 'cod_schedule' => now(), 'status_cod' => 'Pending',
            'booking_fee' => 2000, 'fee_status' => 'paid', 'created_at' => now(), 'updated_at' => now(), ...$attrs,
        ]);
        $later = $row(['paid_at' => now()->subHour()]);
        $earliest = $row(['paid_at' => now()->subDays(2), 'status_cod' => 'Confirmed']);
        $noFee = $row(['paid_at' => null, 'created_at' => now()->subMinute(), 'booking_fee' => 0, 'fee_status' => null]);
        $cancelled = $row(['status_cod' => 'Cancelled', 'paid_at' => now()->subDays(5)]);
        $single = $row(['product_id' => $other->id, 'paid_at' => now()]);

        $migration->up();
        $migration->up(); // dijalankan ulang tanpa error dan tanpa mengubah hasil

        $b = fn ($id) => DB::table('bookings')->find($id);
        $this->assertSame('Confirmed', $b($earliest)->status_cod);
        $this->assertEquals($product->id, $b($earliest)->active_product_id);
        $this->assertEquals($other->id, $b($single)->active_product_id);

        foreach ([$later, $noFee] as $dup) {
            $this->assertSame('Cancelled', $b($dup)->status_cod);
            $this->assertSame('system', $b($dup)->cancelled_by);
            $this->assertSame('dibatalkan otomatis oleh migrasi: duplikat', $b($dup)->cancel_reason);
            $this->assertNull($b($dup)->active_product_id);
        }
        $this->assertSame('refunded', $b($later)->fee_status);
        $this->assertNull($b($noFee)->fee_status);
        $this->assertNull($b($cancelled)->cancelled_by); // booking lama yang sudah batal tidak disentuh

        $this->assertSame('Dibatalkan otomatis', Booking::find($later)->statusLabel());
        $this->assertSame('dibatalkan otomatis oleh migrasi: duplikat', Booking::find($later)->cancelReasonLabel());
    }

    public function test_idempotency_migration_can_run_again(): void
    {
        $migration = $this->migration('2026_10_17_000001_add_idempotency_key_to_orders_and_products_tables');
        $migration->up();
        $migration->up();

        $this->checkout($this->product(), $this->formKey())->assertSessionHas('success');
    }

    public function test_subscription_payments_migration_backfills_existing_subscribers_once(): void
    {
        $migration = $this->migration('2026_10_17_000002_create_subscription_payments_table');
        $migration->down();

        $this->seller->forceFill(['subscription_expires_at' => now()->addDays(12)])->save();
        User::factory()->create(['role' => 'penjual']); // belum pernah berlangganan

        $migration->up();
        $migration->up();

        $payment = SubscriptionPayment::sole();
        $this->assertSame($this->seller->id, $payment->user_id);
        $this->assertSame('migrasi', $payment->source);
        $this->assertNull($payment->idempotency_key);
        $this->assertTrue($payment->period_ends_at->equalTo($this->seller->fresh()->subscription_expires_at));
    }

    public function test_subscription_payments_migration_with_no_subscribers_stays_empty(): void
    {
        $migration = $this->migration('2026_10_17_000002_create_subscription_payments_table');
        $migration->down();
        $migration->up();

        $this->assertSame(0, SubscriptionPayment::count());
    }
}
