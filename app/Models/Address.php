<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id', 'type', 'first_name', 'last_name', 'company',
        'address_1', 'address_2', 'city', 'postcode', 'country', 'state',
        'email', 'phone', 'is_processed', 'process_status'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
