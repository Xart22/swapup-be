<?php

namespace App\Console\Commands;

use App\Models\Logs;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Services\SendGridServices;

class GenerateLabel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-label';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info(date('Y-m-d H:i:s') . ' Generate label has been started');
        $orders = Order::where('label_generated', false)->get();

        if ($orders->count() < 0) {
            $this->info(date('Y-m-d H:i:s') . ' No orders found');
            return;
        }

        DB::beginTransaction();

        foreach ($orders as $order) {
            try {
                if ($order->variant->shipping_label == 1) {
                    $data = [
                        "order_reference" => $order->variant->title . ' ' . $order->variant->label . ' #' . $order->id,
                        "shipments" => [
                            [
                                "shipment_reference" => $order->variant->title . ' ' . $order->variant->label . ' #' . $order->id,
                                "customer_reference_1" => $order->user->consign_id,
                                "movement_type" => "RETURN",
                                "from" => [
                                    "name" => $order->user->first_name . ' ' . $order->user->last_name,
                                    "lines" => [
                                        $order->user->address_line_1,
                                        $order->user->address_line_2
                                    ],
                                    "suburb" => $order->user->suburb,
                                    "state" => $order->user->state,
                                    "postcode" => $order->user->postal_code,
                                    "phone" => $order->user->phone_number
                                ],
                                "to" => [
                                    "name" => $order->variant->title . ' ' . $order->variant->label . ' #' . $order->id,
                                    "business_name" => config("constants.auspost_sender_business_name"),
                                    "lines" => [
                                        config("constants.auspost_sender_address_line1"),
                                        config("constants.auspost_sender_address_line2"),
                                    ],
                                    "suburb" => config("constants.auspost_sender_address_suburb"),
                                    "postcode" => config("constants.auspost_sender_address_postcode"),
                                    "state" => config("constants.auspost_sender_address_state"),
                                    "phone" => config("constants.auspost_sender_address_phone")
                                ],
                                "items" => [
                                    [
                                        "product_id" => "PR",
                                        "authority_to_leave" => false,
                                        "allow_partial_delivery" => false,
                                        "safe_drop_enabled" => false
                                    ]
                                ]
                            ]
                        ]
                    ];
                    $url = config("constants.auspost_api_url") . "/shipping/v1/orders";

                    $response = Http::withBasicAuth(config("constants.auspost_api_username"), config("constants.auspost_api_password"))
                        ->withHeaders([
                            'account-number' => config("constants.auspost_account_number"),
                        ])->post($url, $data);

                    if ($response->failed()) {
                         throw new \Exception(json_encode($response->json()));
                    }

                    $responseCreate = $response->json();

                    $url = config("constants.auspost_api_url") . "/shipping/v1/labels";
                    $data = [
                        "wait_for_label_url" => true,
                        "preferences" => [
                            [
                                "type" => "PRINT",
                                "groups" => [
                                    [
                                        "group" => "Parcel Post",
                                        "layout" => "THERMAL-LABEL-A6-1PP",
                                        "branded" => true,
                                        "left_offset" => 0,
                                        "top_offset" => 0
                                    ]
                                ]
                            ]
                        ],
                        "shipments" => [
                            [
                                "shipment_id" => $responseCreate['order']['shipments'][0]['shipment_id'],
                            ]
                        ]
                    ];

                    $response = Http::withBasicAuth(config("constants.auspost_api_username"), config("constants.auspost_api_password"))
                        ->withHeaders([
                            'account-number' => config("constants.auspost_account_number"),
                        ])->post($url, $data);

                    if ($response->failed()) {
                        throw new \Exception(json_encode($response->json()));
                    }

                    $responseAusGenerateLabel = $response->json();
                    $labelUrl = $responseAusGenerateLabel['labels'][0]['url'];
                    $labelContent = file_get_contents($labelUrl);

                    if ($labelContent === false) {
                        throw new \Exception('An error occurred while getting the label content.');
                    }

                    $labelFileName = 'label_' . $responseCreate['order']['shipments'][0]['shipment_id'] . '.pdf';


                    Storage::disk('public')->put('labels/' . $labelFileName, $labelContent);

                    Shipment::create([
                        'order_id' => $order->id,
                        'aus_order_id' => $responseCreate['order']['order_id'],
                        'shipment_id' => $responseCreate['order']['shipments'][0]['shipment_id'],
                        'shipment_reference' => $responseCreate['order']['shipments'][0]['shipment_reference'],
                        'article_id' => $responseCreate['order']['shipments'][0]['items'][0]['tracking_details']['article_id'],
                        'consignment_id' => $responseCreate['order']['shipments'][0]['items'][0]['tracking_details']['consignment_id'],
                        'order_reference' => $responseCreate['order']['order_reference'],
                        'total_cost' => $responseCreate['order']['order_summary']['total_cost'],
                        'total_cost_ex_gst' => $responseCreate['order']['order_summary']['total_cost_ex_gst'],
                        'total_gst' => $responseCreate['order']['order_summary']['total_gst'],
                        'movement_type' => $responseCreate['order']['shipments'][0]['movement_type'],
                        'status' => 'To Be Sent in',
                        'label_url' => 'labels/' . $labelFileName,

                    ]);

                    $order->update([
                        'label_generated' => true
                    ]);

                    $this->sendEmailWithStripe(config("constants.app_url") . '/storage/labels/' . $labelFileName, $order->user);

                    $this->info(date('Y-m-d H:i:s') . ' Label generated for order ' . $order->id);
                    Logs::create([
                        'message' => 'Label generated for order ID: ' . $order->id,
                        'type' => 'info',
                        'user_id' => $order->user_id,
                        'order_id' => $order->id,
                        'variant_id' => $order->variant_id
                    ]);
                }
            } catch (\Exception $e) {
                $this->info(date('Y-m-d H:i:s') . ' Error: ' . $e->getMessage());
                Logs::create([
                    'message' => 'Error: ' . $e->getMessage(),
                    'type' => 'error',
                    'user_id' => $order->user_id,
                    'order_id' => $order->id,
                    'variant_id' => $order->variant_id
                ]);
                continue;
            }
        }
        DB::commit();
    }

    private function sendEmailWithStripe($url_label, $user)
    {
        $sendGrid = new SendGridServices();
        $templateMail = config("constants.sendgrid_template_swapupkit_label_after_payment");
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
                        "url_label" => $url_label,
                        "first_name" => $user->first_name,
                        "last_name" => ""
                    ]
                ]
            ],
            "template_id" => $templateMail
        ];
        $sendGrid->sendMailByTemplate($sendGridData);
    }
}
