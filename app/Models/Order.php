<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = [
        'created_at' => 'date:Y-m-d H:i:s',
        'updated_at' => 'date:Y-m-d H:i:s',
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function variant()
    {
        return $this->belongsTo(Variants::class);
    }

    public function shipment()
    {
        return $this->hasOne(Shipment::class);
    }
}
