<?php

namespace App\View\Components;

use App\Models\Message;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;
use Illuminate\View\View;

// Layout bergaya marketplace: header + search, chip kategori (opsional), bottom nav mobile
class MarketLayout extends Component
{
    public function __construct(
        public ?string $title = null,
        public bool $showCategories = false,
    ) {
    }

    public function render(): View
    {
        $user = Auth::user();

        return view('layouts.market', [
            'categories'     => config('thriftlo.categories'),
            'activeKategori' => request('kategori', 'all'),
            'unreadCount'    => $user ? Message::where('receiver_id', $user->id)->where('is_read', false)->count() : 0,
            'transaksiUrl'   => $user
                ? ($user->role === 'penjual' ? route('dashboard') : route('bookings.index'))
                : route('login'),
        ]);
    }
}
