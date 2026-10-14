<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public const STATUSES = ['semua' => 'Semua status', 'aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'];

    public function index(Request $request)
    {
        $role = array_key_exists($request->query('role'), User::ROLE_LABELS) ? $request->query('role') : 'semua';
        $status = array_key_exists($request->query('status'), self::STATUSES) ? $request->query('status') : 'semua';
        $search = trim((string) $request->query('q', ''));

        $users = User::query()
            ->when($role !== 'semua', fn ($q) => $q->where('role', $role))
            ->when($status === 'aktif', fn ($q) => $q->whereNull('deactivated_at'))
            ->when($status === 'nonaktif', fn ($q) => $q->whereNotNull('deactivated_at'))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('nama_toko', 'like', "%{$search}%")))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $roleCounts = User::selectRaw('role, COUNT(*) as total')->groupBy('role')->pluck('total', 'role');

        return view('admin.users', [
            'users'      => $users,
            'role'       => $role,
            'status'     => $status,
            'search'     => $search,
            'roleCounts' => $roleCounts,
            'statuses'   => self::STATUSES,
        ]);
    }

    public function deactivate(Request $request, User $user)
    {
        // Admin tidak bisa menonaktifkan akunnya sendiri supaya tidak terkunci dari panel
        if ($user->is($request->user())) {
            return back()->with('error', 'Kamu tidak bisa menonaktifkan akunmu sendiri.');
        }

        if (!$user->isDeactivated()) {
            $user->forceFill(['deactivated_at' => now()])->save();
        }

        return back()->with('success', "Akun {$user->name} dinonaktifkan. Pengguna ini tidak bisa masuk sampai diaktifkan lagi.");
    }

    public function activate(User $user)
    {
        $user->forceFill(['deactivated_at' => null])->save();

        return back()->with('success', "Akun {$user->name} aktif kembali dan bisa masuk seperti biasa.");
    }
}
