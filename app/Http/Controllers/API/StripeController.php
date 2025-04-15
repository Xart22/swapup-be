<?php

namespace App\Http\Controllers\API;

use App\Helpers\Helpers;
use App\Http\Controllers\Controller;
use App\Models\Logs;
use App\Models\Order;
use App\Models\SwapUpKit;
use App\Models\Variants;
use App\Services\SendGridServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Services\UserConsignServices;

class StripeController extends Controller
{
    private $sendGridServices;
    private $userConsignServices;

    public function __construct(SendGridServices $sendGridServices, UserConsignServices $userConsignServices)
    {
        $this->sendGridServices = $sendGridServices;
        $this->userConsignServices = $userConsignServices;
    }

    public function createPaymentIntent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'variant_id' => 'required|integer',
        ]);



        if ($validator->fails()) {
            return response()->json(['error' => 'Validation Error.', 'messages' => $validator->errors()], 422);
        }

        $stripe = new \Stripe\StripeClient(
            config("constants.stripe_secret_key")
        );

        $variant = Variants::find($request->variant_id);
        $swapUpKit = SwapUpKit::first();

        $paymentIntent = $stripe->paymentIntents->create([
            'amount' => $variant->price * 100,
            'currency' => 'aud',
            'automatic_payment_methods' => ['enabled' => false],
            'payment_method_types' => ['card', 'afterpay_clearpay'],
            'description' => 'Payment for ' . $swapUpKit->title . ' - ' . $variant->label,
            'metadata' => [
                'variant_id' => $request->variant_id,
                'variant_label' => $variant->label,
            ],
            "shipping" => [
                "name" =>  config("constants.auspost_sender_name"),
                "address" => [
                    "line1" => config("constants.auspost_sender_address_line1"),
                    "city" => config("constants.auspost_sender_address_suburb"),
                    "state" => config("constants.auspost_sender_address_state"),
                    "postal_code" => config("constants.auspost_sender_address_postcode"),
                    "country" => "AU"
                ]
            ]

        ]);

        return Helpers::Response(200, $paymentIntent->client_secret);
    }


    public function retrievePayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_intent_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation Error.', 'messages' => $validator->errors()], 422);
        }

        try {

            $checkOrder = Order::where('payment_intent_id', $request->payment_intent_id)->first();

            if ($checkOrder) {
                return Helpers::Response(200, 'Payment already processed.');
            }

            $stripe = new \Stripe\StripeClient(
                config("constants.stripe_secret_key")
            );

            $paymentIntent = $stripe->paymentIntents->retrieve(
                $request->payment_intent_id,
                []
            );

            if ($paymentIntent->status === 'succeeded') {

                $variant = Variants::find($paymentIntent->metadata->variant_id);
                $variant->update([
                    'available_stock' => $variant->available_stock - 1,
                ]);

                $id = Order::create([
                    'user_id' => Auth::id(),
                    'variant_id' => $variant->id,
                    'payment_intent_id' => $request->payment_intent_id,
                    'client_secret' => $paymentIntent->client_secret,
                    'status' =>  'To Be Sent in',
                    'stripe_status' => $paymentIntent->status,
                    'label_generated' => $variant->shipping_label == 0 ? true : false
                ])->id;

                if ($variant->gift_card_amount != 0) {
                    $data = [
                        "account" => Auth::user()->consign_id,
                        "balance" => $variant->gift_card_amount * 100,
                        "barcode" => (string)$id,
                    ];
                    $this->userConsignServices->createGiftCard($data);
                }

                if ($variant->shipping_label == 0) {
                    $this->sendEmailWithoutStripe($id);
                }
                Logs::create([
                    'type' => 'Info',
                    'message' => 'Payment succeeded for Order ID: ' . $id . ' ' . $variant->title . ' - ' . 'Variant ID : ' . $variant->id . ' - Payment Intent ID : ' . $request->payment_intent_id . ' Shipping Label Required : ' . ($variant->shipping_label == 0 ? 'No' : 'Yes'),
                    'user_id' => Auth::id(),
                    'order_id' => $id,
                    'variant_id' => $variant->id
                ]);
                return Helpers::Response(200, $paymentIntent->status);
            }

            return Helpers::Response(200, $paymentIntent->status);
        } catch (\Throwable $th) {
            return response()->json(['error' => 'An error occurred while retrieving the payment.', 'messages' => $th->getMessage()], 500);
        }
    }


    private function sendEmailWithoutStripe($id)
    {

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
                        "order_id" => $id
                    ]
                ]
            ],
            "template_id" => $templateMail
        ];
        $this->sendGridServices->sendMailByTemplate($sendGridData);
    }
}
