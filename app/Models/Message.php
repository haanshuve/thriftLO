<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender_id',
        'receiver_id',
        'product_id',
        'message',
        'is_read',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Daftar percakapan milik user, terbaru di atas: lawan bicara, pesan terakhir, jumlah belum dibaca.
     * Hanya orang yang pernah bertukar pesan dengan user yang muncul.
     *
     * @return \Illuminate\Support\Collection<int, array{partner: User, last: Message, unread: int}>
     */
    public static function conversationsFor(User $user, ?int $limit = null)
    {
        $lastIds = static::query()
            ->selectRaw('MAX(id) as id')
            ->where(fn ($q) => $q->where('sender_id', $user->id)->orWhere('receiver_id', $user->id))
            ->groupByRaw('CASE WHEN sender_id = ? THEN receiver_id ELSE sender_id END', [$user->id])
            ->pluck('id');

        $unread = static::query()
            ->where('receiver_id', $user->id)
            ->where('is_read', false)
            ->selectRaw('sender_id, COUNT(*) as total')
            ->groupBy('sender_id')
            ->pluck('total', 'sender_id');

        return static::with(['sender', 'receiver'])
            ->whereIn('id', $lastIds)
            ->orderByDesc('id')
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get()
            ->map(function (Message $last) use ($user, $unread) {
                $partner = (int) $last->sender_id === (int) $user->id ? $last->receiver : $last->sender;

                return ['partner' => $partner, 'last' => $last, 'unread' => (int) ($unread[$partner->id] ?? 0)];
            });
    }
}
