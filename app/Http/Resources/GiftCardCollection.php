<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Helpers\Helpers;
use Illuminate\Http\Response;

class GiftCardCollection extends JsonResource
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request)
    {
        return
            [
                "request_date" => $this->request_date,
                "seller_name" => $this->name,
                "first_name" => $this->first_name,
                "last_name" => $this->last_name,
                "email" => $this->email,
                "amount_used" => number_format($this->amount_used / 100, 2) ?? 0,
                "percentage_amount_used" => number_format($this->percentage_amount_used / 100, 2) ?? 0,
                "total_gift_card_amount" => number_format($this->gift_total_amount / 100, 2) ?? 0,
                "balance_before" => number_format($this->before_balance / 100, 2) ?? 0,
                "after_balance" => number_format($this->after_balance / 100, 2) ?? 0,
                "gift_code" => Helpers::maskString5characters($this->gen_code)
            ];
    }
}
