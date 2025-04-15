<?php

namespace App\Jobs;

use App\Services\UserConsignServices;
use Carbon\Carbon;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Items;
use Illuminate\Support\Facades\Log;

class ProcessCallItems implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use Batchable;

    protected $consignId;
    protected $userConsignServices;


    /**
     * Create a new job instance.
     */
    public function __construct($consignId)
    {
        $this->consignId = $consignId;
        $this->userConsignServices = new UserConsignServices();
    }

    /**
     * Execute the job.
     */
    public function handle()
    {

        if ($this->batch()?->cancelled()) {
            return;
        }


        try {
            Log::channel('queue_get_items')->info("Running ProcessCallItems for consignId: " . $this->consignId);
            $consignR = self::getlistitemscount($this->consignId);
            $consignResponse = $this->userConsignServices->listItems($this->consignId, isset($consignR["total"]) ? $consignR["total"] : 10);
            Log::channel('queue_get_items')->info("ProcessCallItems for consignId: " . json_encode($consignResponse['data']) . " completed");

            foreach ($consignResponse['data'] as $item) {

                $item['expected_credit'] = self::calculateExpectedCredit($item['tag_price']);
                $itemId = $item['id'];
                $shelfid = $item['shelf'];

                // $consignResponse2 = $this->userConsignServices->listItemsbystatuschange($itemId);
                $consignResponse3 = $this->userConsignServices->shelfbyid($shelfid);
                $consignResponse4 = $this->userConsignServices->locationbyid($consignResponse3['location']);

                $datachecksolditem = self::getcogsbyarraystatuschanges($itemId);
                Log::channel('queue_get_items')->info("DataCheckSoldItem for consignId: " . json_encode($datachecksolditem['data']) . " completed");

                $item['status_item'] = null;
                $item['sold_item_date'] = null;

                $status = $datachecksolditem['data'][0]['to_status'] ?? $datachecksolditem['data'][0]['reason'];
                if (isset($status)) {
                    $item['status_item'] = (isset($status) ? $status : 'inactive');
                    if ($status === "sold") {
                        $item['status_item'] = 'sold';
                        $item['sold_item_date'] = Carbon::parse($datachecksolditem['data'][0]['created'])->format('Y-m-d');
                        // $datachecksolditem = self::getcogsbyarraystatuschanges($itemId);
                        $solditemprice = $datachecksolditem["data"][0]["stock"]["cost"];
                        $saleprice = (int)$datachecksolditem["data"][0]["stock"]["sales"];
                        $item['cogs'] = number_format($solditemprice / 100, 2);
                        $item['tag_price'] = $saleprice;
                    } if ($status === "sold_on_shopify") {
                        $item['status_item'] = 'sold_on_shopify';
                        $item['sold_item_date'] = Carbon::parse($datachecksolditem['data'][0]['created'])->format('Y-m-d');
                        // $datachecksolditem = self::getcogsbyarraystatuschanges($itemId);
                        $solditemprice = $datachecksolditem["data"][0]["stock"]["cost"];
                        $saleprice = (int)$datachecksolditem["data"][0]["stock"]["sales"];
                        $item['cogs'] = number_format($solditemprice / 100, 2);
                        $item['tag_price'] = $saleprice;
                    }
                    $item['status_item_expired'] = !(
                        $item['status_item'] == 'sold' ||
                        $item['status_item'] == 'active' ||
                        $item['status_item'] == 'sold_on_shopify' ||
                        (isset($datachecksolditem['data'][0]['from_status']) && $datachecksolditem['data'][0]['from_status'] == 'third_party'))
                    || Carbon::now()->format('Y-m-d') >= Carbon::parse($item['expires'])->format('Y-m-d');
                }
                if (isset($consignResponse3['location']) && isset($consignResponse4['name'])) {
                    if ($item['shelf'] == $shelfid) {
                        $item['location_name'] = $consignResponse4['name'];
                    }
                }

                if(( $status === "sold" && $item['sold_item_date'] >= Carbon::parse($item['expires'])->format('Y-m-d') || $item['sold_item_date'] >= Carbon::parse($item['expires'])->format('Y-m-d') && $status === "sold") || ($status === "sold_on_shopify" && $item['sold_item_date'] >= Carbon::parse($item['expires'])->format('Y-m-d') || $item['sold_item_date'] >= Carbon::parse($item['expires'])->format('Y-m-d') && $status === "sold_on_shopify"))
                {
                    $item['status_item_expired'] = true;
                }

                $item['status_item_expired'] = $item['expires'] ? Carbon::now()->format('Y-m-d') >= Carbon::parse($item['expires'])->format('Y-m-d') : false;

                $valueDeleted = $datachecksolditem["data"][0]["item"]["deleted"];
                Log::channel('queue_get_items')->info("DataCheckSoldItem for consignId: deleted: " . $valueDeleted . " completed");
                $item['is_deleted'] = isset($valueDeleted) ? 1 : 0;

                $checkData = Items::where('item_id', $itemId)->first();
                $data = [
                    'shopify_product_id' => $item['shopify_product_id'],
                    'account' => $item['account'],
                    'title' => $item['title'],
                    'description' => $item['description'],
                    'tag_price' => $item['tag_price'],
                    'expected_credit' => $item['expected_credit'],
                    'status_item' => $item['status_item'],
                    'expires' => $item['expires'],
                    'schedule_start' => $item['schedule_start'],
                    'sold_item_date' => $item['sold_item_date'],
                    'cogs' => $item['cogs'] ?? null,
                    'status_item_expired' => $item['status_item_expired'],
                    'location_name' => $item['location_name'],
                    'created' => Carbon::parse($item['created'])->format('Y-m-d H:i:s'),
                    'is_deleted' => $item['is_deleted']
                ];

                if ($checkData) {
                    $checkData->update($data);
                } else {
                    $data['item_id'] = $itemId;
                    Items::create($data);
                }
            }

            Log::channel('queue_get_items')->info("Transaction insert table for consignId: " . $this->consignId . " completed");
            Log::channel('queue_get_items')->info("Close ProcessCallItems for consignId: " . $this->consignId);
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
        }
    }

    protected function getcogsbyarraystatuschanges($itemid)
    {
        try {
            $data = [
                "limit" => 2147483648,
                "offset" => 0,
                "where" => [
                    [
                        "operator" => "eq",
                        "value" => "{$itemid}",
                        "field" => "item"
                    ]
                ],
                "order_by" => [
                    [
                        "field" => "created",
                        "order" => "DESC"
                    ]
                ],
                "select" => [
                    "created",
                    "event.entity_type",
                    "event.entity_id",
                    "item.*",
                    "reason",
                    "from_status",
                    "to_status",
                    "stock.quantity",
                    "stock.cost",
                    "stock.sales",
                    "id"
                ]
            ];

            $checkData = $this->userConsignServices->checkstatuschange($data);
            Log::channel('resource_api')->info("Checkstatuschanges for consignId: " . json_encode($checkData) . " completed");
            return $checkData ?? [];
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    protected function getlistitemscount($consign_id)
    {
        try {
            $data = [
                "limit" => 2147483648,
                "offset" => 0,
                "where" => [
                    [
                        "operator" => "eq",
                        "value" => null,
                        "field" => "deleted"
                    ],
                    [
                        "operator" => "eq",
                        "value" => "{$consign_id}",
                        "field" => "supplier"
                    ]
                ],
                "order_by" => [
                    [
                        "field" => "created",
                        "order" => "DESC"
                    ]
                ],
                "select" => ["sku", "title", "quantity", "status", "tag_price", "cost_per", "split", "created", "expires", "id"],
                "account_id" => "{$consign_id}"
            ];

            $checkData = $this->userConsignServices->newlistItems($data);
            Log::channel('resource_api_2')->info("Checkstatuschanges for consignId: " . json_encode($checkData) . " completed");
            return $checkData ?? [];
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    protected function calculateExpectedCredit($tagPrice)
    {
        $tax = 1 / 11;
        $rounded = number_format($tagPrice / 100, 2);
        $gst = number_format(($rounded * $tax), 2);
        $splitTagPrice = $rounded - $gst;

        $arrayPercentage = [3000, 4999, 5000, 9999, 10000, 19999];

        if ($splitTagPrice < number_format($arrayPercentage[0] / 100, 2)) {
            $percentage = 0.15;
        } elseif ($splitTagPrice >= number_format($arrayPercentage[0] / 100, 2) && $splitTagPrice <= number_format($arrayPercentage[1] / 100, 2)) {
            $percentage = 0.25;
        } elseif ($splitTagPrice >= number_format($arrayPercentage[2] / 100, 2) && $splitTagPrice <= number_format($arrayPercentage[3] / 100, 2)) {
            $percentage = 0.35;
        } elseif ($splitTagPrice >= number_format($arrayPercentage[4] / 100, 2) && $splitTagPrice <= number_format($arrayPercentage[5] / 100, 2)) {
            $percentage = 0.50;
        } else {
            $percentage = 0.60;
        }

        $expectedPayout = number_format(($splitTagPrice * $percentage), 2);

        return $expectedPayout;
    }
}
