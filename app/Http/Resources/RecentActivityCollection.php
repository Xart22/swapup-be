<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;
use Illuminate\Http\Response;

class RecentActivityCollection extends JsonResource
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request)
    {
        $pattern = '/\$\d+(\.\d{2})?/';
        // $whitespace_pattern = '/\s/';

        $title = $this['title'];
        if (stripos($title, 'gift card') !== false || stripos($title, 'cash out') !== false) {
            $title = preg_replace($pattern, '', $title);
            $title = ucfirst(strtolower(trim($title)));
        }

        // ucfirst(strtolower(preg_replace($whitespace_pattern, '', preg_replace($pattern, '', $this['title']), 1)))

        return [
            "keterangan" => $title,
            "amount_balance" => number_format($this['delta'] / 100, 2),
            "request_activity_date" => Carbon::parse($this['created'])->format('Y-m-d h:i:s')
        ];
    }
}
