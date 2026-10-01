<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Product;
use App\Models\User;
use App\Support\ContactInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ChatController extends Controller
{
    // Menampilkan Halaman Pusat Percakapan
    public function index(Request $request)
    {
        $userId = Auth::id();

        // Hanya orang yang pernah bertukar pesan, terbaru di atas
        $conversations = Message::conversationsFor(Auth::user());

        $activeContact = null;
        $messages = collect();
        $selectedProduct = null;

        if ($request->filled('user_id')) {
            if ((int) $request->user_id === $userId) {
                return redirect()->route('chat.index');
            }

            $activeContact = User::findOrFail($request->user_id);

            // Produk hanya dilampirkan kalau milik salah satu pihak dalam percakapan
            if ($request->filled('product_id')) {
                $selectedProduct = Product::whereKey($request->product_id)
                    ->whereIn('user_id', [$userId, $activeContact->id])
                    ->first();
            }

            // Ambil riwayat pesan antara user login dan kontak aktif
            $messages = Message::with('product')
                ->where(function ($q) use ($userId, $activeContact) {
                    $q->where('sender_id', $userId)->where('receiver_id', $activeContact->id);
                })->orWhere(function ($q) use ($userId, $activeContact) {
                    $q->where('sender_id', $activeContact->id)->where('receiver_id', $userId);
                })->orderBy('created_at', 'asc')->orderBy('id', 'asc')->get();

            // Tandai pesan sebagai dibaca
            Message::where('sender_id', $activeContact->id)
                ->where('receiver_id', $userId)
                ->where('is_read', false)
                ->update(['is_read' => true]);

            // Kontak baru (mis. dari tombol Chat Penjual) tetap muncul di daftar
            if (!$conversations->contains(fn ($c) => $c['partner']->id === $activeContact->id)) {
                $conversations->prepend(['partner' => $activeContact, 'last' => null, 'unread' => 0]);
            } else {
                $conversations = $conversations->map(fn ($c) => $c['partner']->id === $activeContact->id ? [...$c, 'unread' => 0] : $c);
            }
        }

        return view('chat.index', compact('conversations', 'activeContact', 'messages', 'selectedProduct'));
    }

    // Mengirim Pesan Baru
    public function store(Request $request)
    {
        $request->validate([
            'receiver_id' => ['required', 'exists:users,id', Rule::notIn([Auth::id()])],
            'message'     => ['required', 'string', 'max:1000', function ($attribute, $value, $fail) {
                // Transaksi harus tetap di dalam platform: tolak nomor HP/WA dan ajakan pindah aplikasi
                if (is_string($value) && ContactInfo::contains($value)) {
                    $fail(ContactInfo::WARNING);
                }
            }],
            'product_id'  => 'nullable|exists:products,id',
        ], [
            'receiver_id.not_in' => 'Kamu tidak bisa mengirim pesan ke diri sendiri.',
            'message.required'   => 'Pesan tidak boleh kosong.',
            'message.max'        => 'Pesan maksimal 1000 karakter.',
        ]);

        Message::create([
            'sender_id'   => Auth::id(),
            'receiver_id' => $request->receiver_id,
            'product_id'  => $request->product_id,
            'message'     => $request->message,
        ]);

        return redirect()->route('chat.index', array_filter([
            'user_id'    => $request->receiver_id,
            'product_id' => $request->product_id,
        ]));
    }
}
