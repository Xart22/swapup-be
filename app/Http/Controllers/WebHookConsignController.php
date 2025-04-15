<?php

namespace App\Http\Controllers;

use App\Services\SendGridServices;
use App\Services\UserConsignServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Response;
use App\Models\User;
use Carbon\Carbon;

class WebHookConsignController extends Controller
{
    protected $sendGridServices;
    protected $userConsignServices;

    public function __construct(UserConsignServices $userConsignServices, SendGridServices $sendGridServices) {
        $this->sendGridServices = $sendGridServices;
        $this->userConsignServices = $userConsignServices;
    }

    public function liveTimeData(Request $request)
    {
        $signature = $request->header('X-ConsignCloud-Signature');
        $secret = config("constants.secret_key_consign");

        $computedSignature = hash_hmac('sha256', $request->getContent(), $secret);

        if (!hash_equals($signature, $computedSignature)) {
            return response()->json(['message' => 'Invalid signature'], Response::HTTP_FORBIDDEN);
        }

        $created = $request->input('created');
        $payload = $request->input('payload');
        $topic = $request->input('topic');

        if ($topic === 'item.created') {
            Log::info("Item created: ", $payload);

            $itemCreatedAt = Carbon::parse($created)->format('Y-m-d H:i:s');

            $getitemby = $this->userConsignServices->listItemsByID(isset($payload['item_id']) ? $payload['item_id'] : '');
            $shelfid = $this->userConsignServices->shelfbyid(isset($getitemby['shelf']) ? $getitemby['shelf'] : '');

            $sku = isset($getitemby['sku']) ? $getitemby['sku'] : '';

            $itemCreated = Carbon::parse($created);

            $redisKey = "last_item_{$payload['item_id']}";

            $cachedItem = cache()->store('redis')->get($redisKey);

            if ($cachedItem) {
                $cachedCreatedAt = Carbon::parse($cachedItem['created']);
                if ((str_starts_with($sku, 'B') || str_starts_with($sku, '-B')) || $itemCreated->lessThanOrEqualTo($cachedCreatedAt)) {
                    Log::info("SKU exists or item was created earlier. Skipping process for item ID: {$payload['item_id']}.");
                }
            }

            if(isset($shelfid) || $shelfid != '')
            {
                $shelfname = isset($shelfid['name']) ? $shelfid['name'] : '';
            }

            $shelfSkuMapping = [
                'B01' => 1300, 'B02' => 1300, 'B03' => 2500, 'B04' => 1000,
                'B05' => 700,  'B06' => 700,  'B07' => 600,  'B08' => 700,
                'B09' => 600,  'B10' => 700,  'B11' => 1000, 'B12' => 700,
                'B13' => 600,  'B14' => 600,  'B15' => 600,  'B16' => 600,
                'B17' => 700,  'B18' => 600,  'B19' => 500,  'B20' => 500,
                'B21' => 600,  'B22' => 500,  'B23' => 600,  'B24' => 500,
                'B25' => 500,  'B26' => 500,  'B27' => 600,  'B28' => 500,
                'B29' => 600,  'B30' => 400,  'B31' => 600,  'B32' => 500,
                'B33' => 600,  'B34' => 400,  'B35' => 600,  'B36' => 700,
                'B37' => 600,  'B38' => 500,  'B39' => 500,  'B40' => 500,
                'B41' => 1,    'B42' => 1,    'B43' => 1,    'B44' => 1,
                'B45' => 1,    'B46' => 1,    'B47' => 1,    'B48' => 1,
                'B49' => 1,    'B50' => 1,    'B51' => 1,    'B52' => 1,
                'B53' => 1,    'B54' => 1,    'B55' => 1,    'B56' => 1,
                'B57' => 1,    'B58' => 1,    'B59' => 1,    'B60' => 1,
                'B61' => 1,    'B62' => 1,    'B63' => 1,    'B64' => 1,
                'B65' => 1,    'B66' => 1,    'B67' => 1,    'B68' => 1,
                'B69' => 1,    'B70' => 1,    'B71' => 1,    'B72' => 1,
                'B73' => 1,    'B74' => 1,    'B75' => 1,    'B76' => 1,
                'B77' => 1,    'B78' => 1,    'B79' => 1,    'B80' => 1,
                'B81' => 1,    'B82' => 1,    'B83' => 1,    'B84' => 1,
                'B85' => 1,    'B86' => 1,    'B87' => 1,    'B88' => 1,
                'B89' => 1,    'B90' => 1,    'B91' => 300,  'B92' => 300,
                'B93' => 300,  'B94' => 400,  'B95' => 1,    'B96' => 1,
                'B97' => 1,    'B98' => 1,    'B99' => 1,
            ];

            if (!empty($shelfname) && isset($shelfSkuMapping[$shelfname])) {
                $lastSkuNumber = cache()->store('redis')->get("last_sku_{$shelfname}");
                if ($lastSkuNumber === null) {
                    $newSkuNumber = isset($shelfSkuMapping[$shelfname]) ? $shelfSkuMapping[$shelfname] : '';
                    Log::info("First item on shelf {$shelfname}. Starting SKU: {$newSkuNumber}");
                } else {
                    $newSkuNumber = $lastSkuNumber + 1;
                    Log::info("Incremented SKU for shelf {$shelfname}. New SKU: {$newSkuNumber}");
                }
                $newSku = $shelfname . '-' . $newSkuNumber;
                $colorname = isset($getitemby['color']) ? '-' . $getitemby['color'] : '';
                $skuket = $newSku . $colorname;
                $updatedatasku = $this->userConsignServices->updatedItemsByID(isset($payload['item_id']) ? $payload['item_id'] : '', ["sku" => $skuket]);


                cache()->store('redis')->put($redisKey, [
                    'item_id' => $payload['item_id'],
                    'created' => $itemCreatedAt
                ]);

                cache()->store('redis')->put("last_sku_{$shelfname}", $newSkuNumber);

                Log::info('SKU generated and updated successfully: ' . $updatedatasku);
            } else {
                Log::warning("Shelf name not found in the mapping: {$shelfname}");
            }

            // if(isset($getitemby['sku']) || $getitemby != '' || $getitemby['sku'] != '')
            // {
            //     $skuname = isset($getitemby['sku']) ? '-'.$getitemby['sku'] : '';

            //     preg_match('/(\d+)/', $skuname, $matches);
            //     $numberPart = isset($matches[0]) ? intval($matches[0]) : 0;
            //     $incrementedNumber = $numberPart + 1;
            //     $newSkuNumber = str_pad($incrementedNumber, 6, '0', STR_PAD_LEFT);
            //     $newSku = preg_replace('/\d+/', $newSkuNumber, $skuname);
            // }
            // if(isset($getitemby['color'])  || $getitemby != '' || $getitemby['color'] != '')
            // {
            //     $colorname = isset($getitemby['color']) ? '-'.$getitemby['color'] : '';
            // }

            // $skuket = (isset($shelfname) && isset($newSku) && isset($colorname)) ? $shelfname.$newSku.$colorname : '';

            // $updatedatasku = $this->userConsignServices->updatedItemsByID(isset($payload['item_id']) ? $payload['item_id'] : '', ["sku" => $skuket]);

            // Log::info('Berhasil Updated SKU' . $updatedatasku);
        }

        if ($topic === 'item.sold') {
            Log::info("Item sold: ", $payload);
            $itemId = isset($payload['item_id']) ? $payload['item_id'] : '';
            $getitemby = $this->userConsignServices->listItemsByID($itemId);
            $account_id = isset($getitemby['account']) ? $getitemby['account'] : '';
            // $account_id = isset($datachecksolditem["data"][0]["item"]["supplier"]) ? $datachecksolditem["data"][0]["item"]["supplier"] : '';
            
            $user = User::where('consign_id', $account_id)->first();
            
            if(!$user)
            {
                Log::info('Data tidak ditemukan');
            }
            
            $deduplicationKey = "item_sold_{$account_id}_{$itemId}";
            
            if (cache()->store('redis')->has($deduplicationKey)) {
                Log::info("Duplicate item.sold event detected for item ID: $account_id - $itemId. Skipping processing.");
                return response()->json(['message' => 'Duplicate event detected. Skipped. Because this account after notified email'], Response::HTTP_OK);
            }
            
            cache()->store('redis')->put($deduplicationKey, true, 86400);
            
            // $tagprice=isset($getitemby['tag_price']) ? number_format($getitemby['tag_price'] / 100, 2) : 0;
            $consignResponse2 = $this->userConsignServices->listItemsbystatuschange($itemId);
            $datachecksolditem = self::getcogsbyarraystatuschanges($itemId);

            foreach ($consignResponse2['data'] as $statusChange) {
                if (in_array($statusChange['to_status'], ['sold', 'sold_on_shopify'])) {
                    $solditemprice = $datachecksolditem["data"][0]["stock"]["cost"];
                    $saleprice = isset($datachecksolditem["data"][0]["stock"]["sales"]) ? number_format($datachecksolditem["data"][0]["stock"]["sales"] / 100, 2) : 0;
                    break;
                }
                // if (in_array($statusChange['to_status'], ['sold_on_shopify'])) {
                //     $solditemprice = $datachecksolditem["data"][0]["stock"]["cost"];
                //     $saleprice = isset($datachecksolditem["data"][0]["stock"]["sales"]) ? number_format($datachecksolditem["data"][0]["stock"]["sales"] / 100, 2) : 0;
                //     break;
                // }
            }
            $formatzero = number_format($solditemprice / 100, 2);
            if($solditemprice <= 0)
            {
                Log::info("Value Cogs: $formatzero for item ID: $account_id - $itemId. Skipping processing.");
                return response()->json(['message' => "Value Cogs: $formatzero for item ID: $account_id - $itemId. Skipped. Because this account after notified email"], Response::HTTP_OK);
            }
            $cogs = number_format($solditemprice / 100, 2);
            // $tagprice = number_format($saleprice / 100, 2);

            $consignData = $this->userConsignServices->getUser($account_id);
            $balance = isset($consignData['balance']) ? number_format($consignData['balance'] / 100, 2) : 0;

            $sendMailer = self::sendEmailSold($user->email, (isset($datachecksolditem["data"][0]["item"]["title"]) ? $datachecksolditem["data"][0]["item"]["title"] : ''), $saleprice, $cogs, $user->first_name, $balance);

            if ($sendMailer->status() == 202) {
                Log::info('Send Notified Email Success');
            } else {
                dd($sendMailer);
            }
        }

        return response()->json(['message' => 'Webhook received'], Response::HTTP_OK);
    }

    protected function sendEmailSold($seller_email, $title, $price, $cogs, $first_name, $balance)
    {

        $templateMail = config("constants.sendgrid_template_7_");
        $sendGridData = [
            "from" => [
                "email" => "hello@swapup.com.au"
            ],
            "personalizations" => [
                [
                    "to" => [
                        [
                            "email" => $seller_email
                        ]
                    ],
                    "bcc" => [
                        [
                            "email" => "swapup.au@gmail.com"
                        ]
                    ],
                    "dynamic_template_data" => [
                        "product" => $title,
                        "price" => $price,
                        "credit" => $cogs,
                        "first_name" => $first_name,
                        "balance" => $balance
                    ]
                ]
            ],
            "template_id" => $templateMail
        ];

        $SendGridResponse = $this->sendGridServices->sendMailByTemplate($sendGridData);

        return $SendGridResponse;
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
            return $checkData ?? [];
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
