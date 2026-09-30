<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    // Hanya user dengan role admin yang boleh mengakses panel admin
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== 'admin') {
            abort(403, 'Halaman ini khusus Admin thriftLO.');
        }

        return $next($request);
    }
}
