<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'ip_address',
        'user_agent',
        'logged_at',
    ];

    protected $dates = ['logged_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
