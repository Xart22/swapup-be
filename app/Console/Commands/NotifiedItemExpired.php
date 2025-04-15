<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Items;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Services\SendGridServices;

class NotifiedItemExpired extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:notified-item-expired';

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
        //
        $itemJoin = Items::where([['has_send_mailer', 0], ['status_item_expired', 1], ['is_deleted', false], ['expires', '>', '2025-03-17']])->get();

        $this->info(json_encode($itemJoin));

        if (!$itemJoin) {
            $this->info('No Items Data');
        }

        $arr = [];
        $arr2 = [];

        foreach ($itemJoin as $value) {
            array_push($arr2, $value->item_id);
            if (!in_array($value->user, $arr)) {
                array_push($arr, $value->user);
            }
        }

        $this->info(json_encode($arr));

        foreach ($arr as $value) {
            $sendMailer = self::sendMailerItemExpired($value->email, $value->first_name);

            if ($sendMailer->status() == 202) {
                $this->info('Send Notified to User for Item Success');

                foreach ($arr2 as $value) {
                    Items::where('item_id', $value)->update(['has_send_mailer' => 1]);
                }

            } else {
                $this->info($sendMailer);
            }
        }
    }

    protected function sendMailerItemExpired($seller_mail, $name)
    {
        $sendGridServices = new SendGridServices();

        $templateMail = config("constants.sendgrid_template_8_");
        $sendGridData = [
            "from" => [
                "email" => "hello@swapup.com.au"
            ],
            "personalizations" => [
                [
                    "to" => [
                        [
                            "email" => $seller_mail
                        ]
                    ],
                    "bcc" => [
                        [
                            "email" => "swapup.au@gmail.com"
                        ]
                    ],
                    "dynamic_template_data" => [
                        "first_name" => "{$name}"
                    ]
                ]
            ],
            "template_id" => $templateMail
        ];

        $SendGridResponse = $sendGridServices->sendMailByTemplate($sendGridData);

        return $SendGridResponse;
    }


}
