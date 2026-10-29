<?php

namespace App\Providers;

use App\Payments\BookingFeeGateway;
use App\Payments\MidtransGateway;
use App\Payments\SimulationGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Biaya booking COD: simulasi (default) atau Midtrans, lihat config/payment.php
        $this->app->bind(BookingFeeGateway::class, fn () => config('payment.driver') === 'midtrans'
            ? new MidtransGateway()
            : new SimulationGateway());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
