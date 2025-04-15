<?php

namespace App\Http\Controllers\API;

use App\Helpers\Helpers;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessCallItems;
use App\Models\Items;
use App\Services\UserConsignServices;
use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ItemsController extends Controller
{
    public function listItems(Request $request)
    {
        try {
            $consignId = Auth::user()->consign_id;

            $items = Items::where('account', $consignId)->where('is_deleted', 0)->select(['*', 'item_id as consign_product_id'])
                ->get()
                ->makeHidden(['id', 'item_id'])
                ->map(function ($item) {
                    $item->status_item_expired = $item->status_item_expired == 0 ? false : true;
                    return $item;
                });

            return response()->json([
                'data' => $items,
                'message' => 'Items fetched successfully and processing recorded.'
            ], 200);
        } catch (\Exception $e) {
            $responseData = ['message' => $e->getMessage()];
            return Helpers::Response(500, $responseData);
        }
    }

    public function getByIdItems(Request $request)
    {
        try {
            $consignId = $request->query("id");
            $items = Items::where('account', $consignId)->where('is_deleted', 0)->select(['*', 'item_id as consign_product_id'])
                ->get()
                ->makeHidden(['id', 'item_id'])
                ->map(function ($item) {
                    $item->status_item_expired = $item->status_item_expired == 0 ? false : true;
                    if (empty($item->expires)) {
                        $startDate = new DateTime($item->schedule_start);
                        $startDate->modify('+1 month');
                        $item->expires = $startDate->format('Y-m-d');
                    }

                    return $item;
                });

            $categorizedItems = [
                'sellingNow' => [],
                'sold' => [],
                'expired' => []
            ];

            foreach ($items as $item) {
                $expiresDate = new DateTime($item->expires);

                if ($item->status_item === "active" && !$item->status_item_expired) {
                    $categorizedItems['sellingNow'][] = $item;
                } elseif (
                    in_array($item->status_item, ["sold", "sold_on_shopify"]) &&
                    (new DateTime($item->sold_item_date)) <= $expiresDate
                ) {
                    $categorizedItems['sold'][] = $item;
                } else {
                    $categorizedItems['expired'][] = $item;
                }
            }

            return response()->json([
                'data' => $categorizedItems,
                'count' => [
                    'all' => count($items),
                    'active' => count($categorizedItems['sellingNow']),
                    'inactive' => $items->where('status_item', 'inactive')->where('status_item_expired', false)->count(),
                    'sold' => count($categorizedItems['sold']),
                    'expires' => count($categorizedItems['expired'])
                ],
                'message' => 'Items fetched successfully'
            ], 200);
        } catch (\Exception $e) {
            $responseData = ['message' => $e->getMessage()];
            return Helpers::Response(500, $responseData);
        }
    }

    public function getManualItems(Request $request)
    {
        try {
            $consignId = $request->query("id");
            if (empty($consignId)){
                Helpers::Response(500, "ConsignID account this empty");
            }
            $dataItems = ProcessCallItems::dispatch($consignId);

            if(isset($dataItems))
            {
                return response()->json([
                    'data' => $dataItems,
                    'message' => 'Items fetched on consign cloud please processing recorded after 3 minutes'
                ], 200);
            }
        } catch (\Exception $e) {
            $responseData = ['message' => $e->getMessage()];
            return Helpers::Response(500, $responseData);
        }
    }

    public function resetItems(Request $request)
    {
        try {
            $consignId = $request->query("id");
            if (empty($consignId)) Helpers::Response(500, "ConsignID account this empty");

            $deleteItems = Items::where('account', $consignId)->delete();

            return Helpers::Response(200, [
                "message" => "Items reset successfully",
                "deleted_count" => $deleteItems
            ]);

        } catch (\Exception $e) {
            $responseData = ['message' => $e->getMessage()];
            return Helpers::Response(500, $responseData);
        }
    }
}
