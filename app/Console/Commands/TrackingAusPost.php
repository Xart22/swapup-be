<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Shipment;
use App\Models\SwapUpKit;
use App\Services\SendGridServices;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TrackingAusPost extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:tracking-aus-post';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get tracking information from AusPost';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info(date('Y-m-d H:i:s') . ' Tracking information from AusPost has been started');


        $shipments = Shipment::where('status', '!=', 'Delivered')
            ->where('status', '!=', 'Processed')->where('created_at', '>', '2024-10-02')
            ->get();

        $chunks = $shipments->pluck('article_id')->chunk(10);

        foreach ($chunks as $chunk) {
            $tracking_ids = $chunk->implode(',');

            $this->info('Tracking IDs: ' . $tracking_ids);
            $url = config("constants.auspost_api_url") . "/shipping/v1/track";

            $response = Http::withBasicAuth(config('constants.auspost_api_username'), config('constants.auspost_api_password'))
                ->withHeaders([
                    'account-number' => config('constants.auspost_account_number')
                ])->get($url, [
                    'tracking_ids' => $tracking_ids
                ]);

            if ($response->failed()) {
                $this->info('Failed to get tracking information from AusPost for tracking IDs: ' . $tracking_ids);
                continue;
            }

            $responseCreate = $response->json();


            $toBeSentIn = ["created", "sealed", "in transit", "initiated", "unsuccessful pickup", "none"];
            $inTransit = ["in transit", "awaiting collection", "possible delay", "article damaged", "cancelled", "held by courier", "cannot be delivered", "track items for detailed delivery information"];


            foreach ($responseCreate['tracking_results'] as $shipment) {
                $shipmentData = Shipment::where('article_id', $shipment['tracking_id'])->first();
                if (in_array(strtolower($shipment['status']), $toBeSentIn)) {
                    $this->info('Tracking ID: ' . $shipment['tracking_id'] . ' is ' . $shipment['status']);
                    $shipmentData->status = 'To Be Sent In';
                    $shipmentData->save();

                    $order = Order::find($shipmentData->order_id);
                    $order->status = 'To Be Sent In';
                    $order->save();
                }

                if (in_array(strtolower($shipment['status']), $inTransit)) {
                    $this->info('Tracking ID: ' . $shipment['tracking_id'] . ' is ' . $shipment['status']);
                    $shipmentData->status = 'In Transit';
                    $shipmentData->save();

                    $order = Order::find($shipmentData->order_id);
                    $order->status = 'In Transit';
                    $order->save();
                }

                if (strtolower($shipment['status']) == 'delivered') {
                    $this->info('Tracking ID: ' . $shipment['tracking_id'] . ' is ' . $shipment['status']);
                    $shipmentData->status = 'Delivered';
                    $shipmentData->save();

                    $order = Order::find($shipmentData->order_id);
                    $order->status = 'Delivered';
                    $order->save();

                    $this->sendEmailDelivered($order);
                }
            }
        }

        $this->info(date('Y-m-d H:i:s') . ' Tracking information from AusPost has been updated');
    }
    private function sendEmailDelivered($order)
    {
        $sendGrid = new SendGridServices();

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
                        "url" => config("constants.app_url"),
                        "order_id" => $order->id
                    ]
                ]
            ],
            "template_id" => $templateMail
        ];

        $sendGrid->sendMailByTemplate($sendGridData);
    }
}
