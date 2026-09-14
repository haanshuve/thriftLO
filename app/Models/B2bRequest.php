<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class B2bRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'detail_kebutuhan',
        'budget_range',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
