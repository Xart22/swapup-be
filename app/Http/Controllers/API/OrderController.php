<?php

namespace App\Http\Controllers\API;

use App\Helpers\Helpers;
use App\Http\Controllers\Controller;
use App\Models\EmailOrderStatus;
use App\Models\Logs;
use App\Models\Order;
use App\Models\SwapUpKit;
use App\Models\User;
use App\Models\Variants;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\SendGridServices;
use App\Services\UserConsignServices;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{

    private $sendGridServices;
    private $userConsignServices;

    public function __construct(UserConsignServices $userConsignServices, SendGridServices $sendGridServices)
    {
        $this->userConsignServices = $userConsignServices;
        $this->sendGridServices = $sendGridServices;
    }

    public function payWithCredit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'variant_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return Helpers::response(400, $validator->errors());
        }
        try {

            DB::beginTransaction();
            $variant = Variants::find($request->variant_id);
            $swapUpKit = SwapUpKit::first();
            $data = $this->userConsignServices->getUser(Auth::user()->consign_id);

            if ($data['balance'] < $variant->price * 100) {
                return Helpers::Response(400, 'Insufficient credit balance.');
            }

            if ($variant->available_stock === 0) {
                return Helpers::Response(400, 'Variant is out of stock.');
            }
            // kurangin credit balance
            $memo = $swapUpKit->title . "-" . $variant->label . " Ordered";

            $consignData = [
                "account" => Auth::user()->consign_id,
                "amount" => -$variant->price * 100,
                "location" => null,
                "memo" => $memo,
            ];

            $consignTitle = [
                "title" => $memo
            ];

            $consignResponse = $this->userConsignServices->cashout($consignData);

            $limitdata = [
                "limit" => 2147483648,
                "offset" => 0,
                "where" => [
                    [
                        "operator" => "eq",
                        "value" => Auth::user()->consign_id,
                        "field" => "contact"
                    ]
                ],
                "order_by" => [
                    [
                        "field" => "created",
                        "order" => "DESC"
                    ]
                ],
                "select" => ["created", "event.entity_id", "event.entity_type", "event.id", "item.*", "title", "extra", "reason", "deleted", "invoice", "payouts.*", "bonus", "location.*", "delta", "balance", "id"]
            ];

            $checkData = $this->userConsignServices->checkrecentactivity($limitdata);
            $responseData = $checkData['data'] ?? [];

            $idrowbalance = $responseData[0]["id"];

            $this->userConsignServices->updatedatabalance($idrowbalance, $consignTitle);





            if (isset($consignResponse['error'])) {
                DB::rollBack();
                return Helpers::Response(500, 'An error occurred while processing the payment.');
            }

            $order = Order::create([
                'user_id' => Auth::id(),
                'variant_id' => $request->variant_id,
                'payment_intent_id' => 'Payment with Credit Balance',
                'client_secret' => 'Payment with Credit Balance',
                'status' => 'To Be Sent in',
                'stripe_status' => "",
                'label_generated' => $variant->shipping_label == 0 ? true : false
            ]);

            $variant->available_stock -= 1;
            $variant->save();

            if ($variant->shipping_label == 0) {
                $this->sendEmailWithoutStripe($order);
            }

            if ($variant->gift_card_amount != 0) {
                $data = [
                    "account" => Auth::user()->consign_id,
                    "balance" => $variant->gift_card_amount * 100,
                    "barcode" => (string)$order->id,
                ];
                $this->userConsignServices->createGiftCard($data);
            }

            Logs::create([
                'type' => 'Info',
                'message' => 'Payment succeeded for Order ID: ' . $order->id . ' ' . $variant->title . ' - ' . 'Variant ID : ' . $variant->id . ' - Payment Intent ID :Pay with credit  Shipping Label Required : ' . ($variant->shipping_label == 0 ? 'No' : 'Yes'),
                'user_id' => Auth::id(),
                'order_id' => $order->id,
                'variant_id' => $variant->id
            ]);
            DB::commit();
            return Helpers::Response(200, 'Payment succeeded.');
        } catch (\Throwable $th) {
            DB::rollBack();
            Logs::create([
                'type' => 'Error',
                'message' => $th->getMessage(),
                'user_id' => Auth::id(),
                'variant_id' => $variant->id
            ]);
            return Helpers::Response(500, $th->getMessage());
        }
    }


    public function addNewOrderManual(Request $request)
    {
        try {

            DB::beginTransaction();
            $variant = Variants::find($request->variant_id);
            $user = User::where('id', $request->user_id)->first();

            $order = Order::create([
                'user_id' => $user->id,
                'variant_id' => $request->variant_id,
                'payment_intent_id' => 'N/A',
                'client_secret' => 'N/A',
                'status' => 'To Be Sent in',
                'stripe_status' => "",
                'label_generated' => $variant->shipping_label == 0 ? true : false
            ]);

            if ($variant->shipping_label == 0) {
                $this->sendEmailOrderManual($order->id, $user);
            }
            if ($variant->gift_card_amount != 0) {
                $data = [
                    "account" => $user->consign_id,
                    "balance" => $variant->gift_card_amount * 100,
                    "barcode" => (string)$order->id,
                ];

                $this->userConsignServices->createGiftCard($data);
            }

            Logs::create([
                'type' => 'Info',
                'message' => 'Payment succeeded for Order ID: ' . $order->id . ' ' . $variant->title . ' - ' . 'Variant ID : ' . $variant->id . ' - Payment Intent ID :Pay with credit  Shipping Label Required : ' . ($variant->shipping_label == 0 ? 'No' : 'Yes'),
                'user_id' => $user->id,
                'order_id' => $order->id,
                'variant_id' => $variant->id
            ]);
            DB::commit();
            return Helpers::Response(200, 'Payment succeeded.');
        } catch (\Throwable $th) {
            DB::rollBack();
            Logs::create([
                'type' => 'Error',
                'message' => $th->getMessage(),
                'user_id' => $user->id,
                'variant_id' => $variant->id
            ]);
            return Helpers::Response(500, $th->getMessage());
        }
    }



    public function getOrderByUser(Request $request)
    {
        $order = Order::where([
            ['user_id', Auth::id()],
            ['stripe_status', '!=', 'requires_payment_method']
        ])->with('shipment', 'variant', 'user')->orderBy('created_at', 'desc')->get();

        $kit = SwapUpKit::first();

        // $filteredOrders = $order->filter(function ($item) {
        //     return $item->shipment !== null && $item->shipment->status !== 'Delivered';
        // });

        // if ($filteredOrders->count() > 0) {
        //     $tracking_id = $filteredOrders->pluck('shipment.article_id')->implode(',');
        //     $url = config("constants.auspost_api_url"). "/shipping/v1/track";
        //     $response = Http::withBasicAuth(env('AUSPOST_API_USERNAME'), env('AUSPOST_API_PASSWORD'))
        //         ->withHeaders([
        //             'account-number' => env('AUSPOST_ACCOUNT_NUMBER'),
        //         ])->get($url, [
        //             'tracking_ids' => $tracking_id
        //         ]);

        //     if ($response->failed()) {
        //         return Helpers::Response(500, 'An error occurred while tracking the shipment.');
        //     }

        //     $responseCreate = $response->json();

        //     $toBeSentIn = ["created", "sealed", "in transit", "initiated", "unsuccessful pickup", "none"];
        //     $inTransit = ["in transit", "awaiting collection", "possible delay", "article damaged", "cancelled", "held by courier", "cannot be delivered", "track items for detailed delivery information"];


        //     $filteredOrders->each(function ($order) use ($responseCreate, $toBeSentIn, $inTransit) {
        //         $shipment = $order->shipment;
        //         $articleId = $shipment->article_id;

        //         $trackingResult = collect($responseCreate['tracking_results'])->firstWhere('tracking_id', $articleId);
        //         if ($trackingResult) {
        //             $responseStatusShipment = strtolower($trackingResult['status']);

        //             if (in_array($responseStatusShipment, $toBeSentIn)) {
        //                 $shipment->status = 'To Be Sent in';
        //             } elseif (in_array($responseStatusShipment, $inTransit)) {
        //                 $shipment->status = 'In Transit';
        //             } else {
        //                 $shipment->status = 'Unknown Status';
        //             }
        //             $shipment->save();
        //         }
        //     });
        // }

        return Helpers::response(200, ['orders' => $order, 'kit' => $kit]);
    }

    public function getAllOrder(Request $request)
    {

        $order = Order::where('stripe_status', '!=', 'requires_payment_method')->orderBy('id', 'desc')->with('shipment', 'variant', 'user')->get();

        $kit = SwapUpKit::first();

        // $filteredOrders = $order->filter(function ($item) {
        //     return $item->shipment !== null && $item->shipment->status !== 'Delivered';
        // });

        // if ($filteredOrders->count() > 0) {
        //     $tracking_id = $filteredOrders->pluck('shipment.article_id')->implode(',');
        //     $url = config("constants.auspost_api_url"). "/shipping/v1/track";
        //     $response = Http::withBasicAuth(env('AUSPOST_API_USERNAME'), env('AUSPOST_API_PASSWORD'))
        //         ->withHeaders([
        //             'account-number' => env('AUSPOST_ACCOUNT_NUMBER'),
        //         ])->get($url, [
        //             'tracking_ids' => $tracking_id
        //         ]);

        //     if ($response->failed()) {
        //         return Helpers::Response(500, 'An error occurred while tracking the shipment.');
        //     }

        //     $responseCreate = $response->json();

        //     $toBeSentIn = ["created", "sealed", "in transit", "initiated", "unsuccessful pickup", "none"];
        //     $inTransit = ["in transit", "awaiting collection", "possible delay", "article damaged", "cancelled", "held by courier", "cannot be delivered", "track items for detailed delivery information"];


        //     $filteredOrders->each(function ($order) use ($responseCreate, $toBeSentIn, $inTransit) {
        //         $shipment = $order->shipment;
        //         $articleId = $shipment->article_id;

        //         $trackingResult = collect($responseCreate['tracking_results'])->firstWhere('tracking_id', $articleId);
        //         if ($trackingResult) {
        //             $responseStatusShipment = strtolower($trackingResult['status']);

        //             if (in_array($responseStatusShipment, $toBeSentIn)) {
        //                 $shipment->status = 'To Be Sent in';
        //             } elseif (in_array($responseStatusShipment, $inTransit)) {
        //                 $shipment->status = 'In Transit';
        //             } else if ($responseStatusShipment === 'delivered') {
        //                 $this->sendEmailDelivered($order);
        //                 $shipment->status = 'Delivered';
        //             } else {
        //                 $shipment->status = 'Unknown Status';
        //             }
        //             $shipment->save();
        //         }
        //     });
        // }

        return Helpers::response(200, ['orders' => $order, 'kit' => $kit]);
    }
    public function getOrderByDate(Request $request)
    {

        $order = Order::where([
            ['created_at', '>=', Carbon::parse($request->start_date)->startOfDay()],
            ['created_at', '<=', Carbon::parse($request->end_date)->endOfDay()],
            ['stripe_status', '!=', 'requires_payment_method']
        ])->with('shipment', 'variant', 'user')->orderBy('created_at', 'desc')->get();

        $kit = SwapUpKit::first();

        // $filteredOrders = $order->filter(function ($item) {
        //     return $item->shipment !== null && $item->shipment->status !== 'Delivered';
        // });

        // if ($filteredOrders->count() > 0) {
        //     $tracking_id = $filteredOrders->pluck('shipment.article_id')->implode(',');
        //     $url = config("constants.auspost_api_url"). "/shipping/v1/track";
        //     $response = Http::withBasicAuth(env('AUSPOST_API_USERNAME'), env('AUSPOST_API_PASSWORD'))
        //         ->withHeaders([
        //             'account-number' => env('AUSPOST_ACCOUNT_NUMBER'),
        //         ])->get($url, [
        //             'tracking_ids' => $tracking_id
        //         ]);

        //     if ($response->failed()) {
        //         return Helpers::Response(500, 'An error occurred while tracking the shipment.');
        //     }

        //     $responseCreate = $response->json();

        //     $toBeSentIn = ["created", "sealed", "in transit", "initiated", "unsuccessful pickup", "none"];
        //     $inTransit = ["in transit", "awaiting collection", "possible delay", "article damaged", "cancelled", "held by courier", "cannot be delivered", "track items for detailed delivery information"];


        //     $filteredOrders->each(function ($order) use ($responseCreate, $toBeSentIn, $inTransit) {
        //         $shipment = $order->shipment;
        //         $articleId = $shipment->article_id;

        //         $trackingResult = collect($responseCreate['tracking_results'])->firstWhere('tracking_id', $articleId);
        //         if ($trackingResult) {
        //             $responseStatusShipment = strtolower($trackingResult['status']);

        //             if (in_array($responseStatusShipment, $toBeSentIn)) {
        //                 $shipment->status = 'To Be Sent in';
        //             } elseif (in_array($responseStatusShipment, $inTransit)) {
        //                 $shipment->status = 'In Transit';
        //             } else if ($responseStatusShipment === 'delivered') {
        //                 $this->sendEmailDelivered($order);
        //                 $shipment->status = 'Delivered';
        //             } else {
        //                 $shipment->status = 'Unknown Status';
        //             }
        //             $shipment->save();
        //         }
        //     });
        // }

        return Helpers::response(200, ['orders' => $order, 'kit' => $kit]);
    }

    public function updateOrderStatus(Request $request)
    {
        try {
            $order = Order::find($request->id);

            if ($order->shipment === null) {
                $order->status = $request->status;
            } else {
                $order->shipment->update([
                    'status' => $request->status,
                ]);
                $order->status = $request->status;
            }

            if ($request->status === 'Cancelled') {
                $this->sendEmailCanceled($order);
                $order->reason_cancel = $request->reason_cancel;
            }

            if ($request->status === 'Processed') {
                EmailOrderStatus::create([
                    'order_id' => $order->id,
                    'is_sent' => false
                ]);
            }
            if ($request->status === 'Delivered') {
                $this->sendEmailDelivered($order);
            }
            $order->save();

            return Helpers::response(200, 'Order status updated successfully.');
        } catch (\Throwable $th) {

            return Helpers::response(500, $th->getMessage());
        }
    }



    private function sendEmailCanceled($order)
    {
        $templateMail = config("constants.sendgrid_template_swapupkit_cancelled");
        $sendGridData = [
            "from" => [
                "email" => "hello@swapup.com.au"
            ],
            "personalizations" => [
                [
                    "to" => [
                        [
                            "email" => $order->user->email
                        ]
                    ],
                    "bcc" => [
                        [
                            "email" => "swapup.au@gmail.com"
                        ]
                    ],
                    "dynamic_template_data" => [
                        "order_id" => $order->id,
                        "first_name" => $order->user->first_name,
                    ]
                ]
            ],
            "template_id" => $templateMail
        ];
        $this->sendGridServices->sendMailByTemplate($sendGridData);
    }

    private function sendEmailDelivered($order)
    {

        // $templateMail = env("SENDRID_TEMPLATE_SWAUPKIT_LABEL_AFTER_PAYMENT");
        $templateMail = config("constants.sendgrid_template_swapupkit_label_after_delivered");
        $sendGridData = [
            "from" => [
                "email" => "hello@swapup.com.au"
            ],
            "personalizations" => [
                [
                    "to" => [
                        [
                            "email" => $order->user->email
                        ]
                    ],
                    "bcc" => [
                        [
                            "email" => "swapup.au@gmail.com"
                        ]
                    ],
                    "dynamic_template_data" => [
                        "url" => config("constants.pass_url_"),
                        "order_id" => $order->id
                    ]
                ]
            ],
            "template_id" => $templateMail
        ];
        $this->sendGridServices->sendMailByTemplate($sendGridData);
    }

    private function sendEmailWithoutStripe($order)
    {

        // $templateMail = env("SENDRID_TEMPLATE_SWAUPKIT_LABEL_DROP_OFF");
        $templateMail = config("constants.sendgrid_template_swapupkit_lable_drop_off");
        $sendGridData = [
            "from" => [
                "email" => "hello@swapup.com.au"
            ],
            "personalizations" => [
                [
                    "to" => [
                        [
                            "email" => Auth::user()->email
                        ]
                    ],
                    "bcc" => [
                        [
                            "email" => "swapup.au@gmail.com"
                        ]
                    ],
                    "dynamic_template_data" => [
                        "url_maps" => config("constants.url_maps"),
                        "first_name" => Auth::user()->first_name,
                        "order_id" => $order->id
                    ]
                ]
            ],
            "template_id" => $templateMail
        ];
        $this->sendGridServices->sendMailByTemplate($sendGridData);
    }

    private function sendEmailOrderManual($order_id, $user)
    {

        // $templateMail = env("SENDRID_TEMPLATE_SWAUPKIT_LABEL_DROP_OFF");
        $templateMail = config("constants.sendgrid_template_swapupkit_lable_drop_off");
        $sendGridData = [
            "from" => [
                "email" => "hello@swapup.com.au"
            ],
            "personalizations" => [
                [
                    "to" => [
                        [
                            "email" => $user->email
                        ]
                    ],
                    "bcc" => [
                        [
                            "email" => "swapup.au@gmail.com"
                        ]
                    ],
                    "dynamic_template_data" => [
                        "url_maps" => config("constants.url_maps"),
                        "first_name" => $user->first_name,
                        "order_id" => $order_id
                    ]
                ]
            ],
            "template_id" => $templateMail
        ];
        $this->sendGridServices->sendMailByTemplate($sendGridData);
    }
}
