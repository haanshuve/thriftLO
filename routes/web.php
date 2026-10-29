<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\BookingCancellationController;
use App\Http\Controllers\BookingPaymentController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\MidtransNotificationController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\SubscriptionController;

// Halaman Publik / Katalog Utama
Route::get('/', [ProductController::class, 'index'])->name('home');
Route::get('/product/{product}', [ProductController::class, 'show'])->name('product.show');
Route::get('/merek', [BrandController::class, 'index'])->name('brands.index');
Route::get('/merek/{brand}', [BrandController::class, 'show'])->name('brands.show');

// Webhook notifikasi Midtrans (biaya booking COD). Publik dan tanpa CSRF, diverifikasi lewat signature_key.
Route::post('/payments/midtrans/notification', MidtransNotificationController::class)
    ->middleware('throttle:120,1')
    ->name('payments.midtrans.notification');

// Middleware Autentikasi Umum (Buyer & Seller)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [ProductController::class, 'sellerDashboard'])->name('dashboard');
    Route::post('/seller/product/store', [ProductController::class, 'store'])->name('product.store');
    Route::delete('/seller/product/{id}', [ProductController::class, 'destroy'])->name('product.destroy');
    Route::get('/seller/product/{id}/edit', [ProductController::class, 'edit'])->name('product.edit');
    Route::put('/seller/product/{id}', [ProductController::class, 'update'])->name('product.update');

    // Pengiriman (penjual di luar Batam)
    Route::post('/product/{product}/checkout', [OrderController::class, 'store'])->name('order.store');
    Route::patch('/seller/order/{order}/ship', [OrderController::class, 'ship'])->name('order.ship');
    Route::patch('/order/{order}/confirm', [OrderController::class, 'confirm'])->name('order.confirm');

    // Langganan penjual (Rp5.000/bulan untuk produk unlimited)
    Route::get('/seller/langganan', [SubscriptionController::class, 'show'])->name('subscription.show');
    Route::post('/seller/langganan/bayar', [SubscriptionController::class, 'pay'])->name('subscription.pay');

    // Booking & Secure COD Smart QR Code
    Route::post('/product/{id}/book', [ProductController::class, 'bookProduct'])->name('product.book');
    Route::post('/seller/verify-qr', [ProductController::class, 'verifyQrCode'])->name('booking.verify');

    // Bayar biaya booking lewat QRIS Midtrans (PAYMENT_DRIVER=midtrans)
    Route::get('/booking/{booking}/bayar', [BookingPaymentController::class, 'show'])->name('booking.pay');
    Route::get('/booking/{booking}/bayar/status', [BookingPaymentController::class, 'status'])->name('booking.payStatus');

    // Pembatalan booking COD oleh pembeli atau penjual: biaya booking selalu menjadi milik platform
    Route::patch('/booking/{booking}/cancel', [BookingCancellationController::class, 'cancelByBuyer'])->name('booking.cancel');
    Route::patch('/seller/booking/{booking}/cancel', [BookingCancellationController::class, 'cancelBySeller'])->name('booking.sellerCancel');

    // Tiket Saya & Review Booking
    Route::get('/my-bookings', function() {
        \App\Models\Order::releaseOverdue();
        \App\Models\Booking::releaseExpiredPayments();
        $bookings =\App\Models\Booking::with(['product.user', 'review'])->where('user_id', Auth::id())->latest()->get();
        $orders = \App\Models\Order::with('product.user')->where('user_id', Auth::id())->latest()->get();
        return view('my-bookings', compact('bookings', 'orders'));
    })->name('bookings.index');

    Route::post('/booking/{id}/review', [ReviewController::class, 'store'])->name('review.store');

    // Chat: tanya kondisi barang & koordinasi COD (harga fixed, tanpa nego)
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::post('/chat/send', [ChatController::class, 'store'])->name('chat.send');

    // Fitur One to Buy / Request Barang
    Route::get('/requests', [RequestController::class, 'index'])->name('requests.index');
    Route::post('/requests', [RequestController::class, 'store'])->name('requests.store');
    Route::patch('/requests/{productRequest}/fulfill', [RequestController::class, 'fulfill'])->name('requests.fulfill');

    // Profil Breeze
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
    Route::delete('/profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');
});

// Panel Admin (layout terpisah dari marketplace). Semua rute di sini khusus role admin.
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

    // Verifikasi penjual (KYC)
    Route::get('/sellers', [AdminController::class, 'index'])->name('sellers');
    Route::get('/seller/{id}/ktp', [AdminController::class, 'showKtp'])->name('sellerKtp');
    Route::get('/seller/{id}/selfie', [AdminController::class, 'showSelfie'])->name('sellerSelfie');
    Route::post('/seller/{id}/verify', [AdminController::class, 'verifySeller'])->name('verifySeller');
    Route::post('/seller/{id}/reject', [AdminController::class, 'rejectSeller'])->name('rejectSeller');

    Route::get('/transaksi', [Admin\TransactionController::class, 'index'])->name('transactions');

    Route::get('/pengguna', [Admin\UserController::class, 'index'])->name('users');
    Route::patch('/pengguna/{user}/nonaktifkan', [Admin\UserController::class, 'deactivate'])->name('users.deactivate');
    Route::patch('/pengguna/{user}/aktifkan', [Admin\UserController::class, 'activate'])->name('users.activate');

    Route::get('/produk', [Admin\ProductController::class, 'index'])->name('products');
    Route::patch('/produk/{product}/turunkan', [Admin\ProductController::class, 'takeDown'])->name('products.takeDown');
    Route::patch('/produk/{product}/tayangkan', [Admin\ProductController::class, 'restore'])->name('products.restore');

    Route::get('/kategori', [Admin\CategoryController::class, 'index'])->name('categories');
    Route::post('/kategori', [Admin\CategoryController::class, 'store'])->name('categories.store');
    Route::put('/kategori/{category}', [Admin\CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/kategori/{category}', [Admin\CategoryController::class, 'destroy'])->name('categories.destroy');

    Route::get('/merek', [Admin\BrandController::class, 'index'])->name('brands');
    Route::post('/merek', [Admin\BrandController::class, 'store'])->name('brands.store');
    Route::put('/merek/{brand:id}', [Admin\BrandController::class, 'update'])->name('brands.update');
    Route::patch('/merek/{brand:id}/status', [Admin\BrandController::class, 'toggle'])->name('brands.toggle');
    Route::delete('/merek/{brand:id}', [Admin\BrandController::class, 'destroy'])->name('brands.destroy');

    Route::get('/laporan', [Admin\ReportController::class, 'index'])->name('reports');
});

// Rute Autentikasi Bawaan Laravel Breeze
require __DIR__.'/auth.php';
