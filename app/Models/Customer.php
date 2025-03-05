<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'live_id', 'email', 'first_name', 'last_name', 'role', 'username',
        'is_paying_customer', 'avatar_url', 'is_processed', 'process_status'
    ];

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }
}
