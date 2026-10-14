<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class LogoutDeactivatedUser
{
    // Akun yang dinonaktifkan admin saat masih login langsung dikeluarkan di request berikutnya
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isDeactivated()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', User::DEACTIVATED_MESSAGE);
        }

        return $next($request);
    }
}
