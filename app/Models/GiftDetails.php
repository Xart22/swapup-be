<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GiftDetails extends Model
{
    use HasFactory;

    protected $table = 'gift_details';

    protected $fillable = [
        'id_gift_card',
        'request_date',
        'user_id',
        'amount_used',
        'percentage_amount_used',
        'gift_total_amount',
        'before_balance',
        'after_balance',
        'gen_code'
    ];

    public $timestamps = false;

}
