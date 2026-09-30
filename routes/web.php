<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\RequestController;

// Halaman Publik / Katalog Utama
Route::get('/', [ProductController::class, 'index'])->name('home');

// Middleware Autentikasi Umum (Buyer & Seller)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [ProductController::class, 'sellerDashboard'])->name('dashboard');
    Route::post('/seller/product/store', [ProductController::class, 'store'])->name('product.store');
    Route::delete('/seller/product/{id}', [ProductController::class, 'destroy'])->name('product.destroy');

    // Booking & Secure COD Smart QR Code
    Route::post('/product/{id}/book', [ProductController::class, 'bookProduct'])->name('product.book');
    Route::post('/seller/verify-qr', [ProductController::class, 'verifyQrCode'])->name('booking.verify');

    // Tiket Saya & Review Booking
    Route::get('/my-bookings', function() {
        $bookings = \App\Models\Booking::with(['product.user'])->where('user_id', Auth::id())->latest()->get();
        return view('my-bookings', compact('bookings'));
    })->name('bookings.index');

    Route::post('/booking/{id}/review', [ReviewController::class, 'store'])->name('review.store');

    // Chat & Negosiasi Harga (Open Price)
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::post('/chat/send', [ChatController::class, 'store'])->name('chat.send');

    // Fitur One to Buy / Request Barang
    Route::get('/requests', [RequestController::class, 'index'])->name('requests.index');
    Route::post('/requests', [RequestController::class, 'store'])->name('requests.store');

    // Profil Breeze
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Panel Admin Verifikasi Penjual
Route::middleware(['auth'])->group(function () {
    Route::get('/admin/sellers', [AdminController::class, 'index'])->name('admin.sellers');
    Route::post('/admin/seller/{id}/verify', [AdminController::class, 'verifySeller'])->name('admin.verifySeller');
    Route::post('/admin/seller/{id}/reject', [AdminController::class, 'rejectSeller'])->name('admin.rejectSeller');
});

// Rute Autentikasi Bawaan Laravel Breeze
require __DIR__.'/auth.php';
