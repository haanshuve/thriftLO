<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    // Menampilkan Halaman Pusat Percakapan
    public function index(Request $request)
    {
        $userId = Auth::id();

        // Ambil daftar user yang pernah bertukar pesan
        $contacts = User::where('id', '!=', $userId)->get();

        $activeContact = null;
        $messages = collect();
        $selectedProduct = null;

        if ($request->has('user_id')) {
            $activeContact = User::findOrFail($request->user_id);

            if ($request->has('product_id')) {
                $selectedProduct = Product::find($request->product_id);
            }

            // Ambil riwayat pesan antara user login dan kontak aktif
            $messages = Message::where(function ($q) use ($userId, $activeContact) {
                $q->where('sender_id', $userId)->where('receiver_id', $activeContact->id);
            })->orWhere(function ($q) use ($userId, $activeContact) {
                $q->where('sender_id', $activeContact->id)->where('receiver_id', $userId);
            })->orderBy('created_at', 'asc')->get();

            // Tandai pesan sebagai dibaca
            Message::where('sender_id', $activeContact->id)
                ->where('receiver_id', $userId)
                ->update(['is_read' => true]);
        }

        return view('chat.index', compact('contacts', 'activeContact', 'messages', 'selectedProduct'));
    }

    // Mengirim Pesan Baru
    public function store(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'message'     => 'required|string|max:1000',
            'product_id'  => 'nullable|exists:products,id',
        ]);

        Message::create([
            'sender_id'   => Auth::id(),
            'receiver_id' => $request->receiver_id,
            'product_id'  => $request->product_id,
            'message'     => $request->message,
        ]);

        return redirect()->route('chat.index', [
            'user_id'    => $request->receiver_id,
            'product_id' => $request->product_id
        ])->with('success', 'Pesan terkirim!');
    }
}
