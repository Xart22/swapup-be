<?php

namespace App\Console\Commands;

use App\Models\EmailOrderStatus;
use App\Services\SendGridServices;
use Illuminate\Console\Command;

class EmailProcessed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:email-processed';

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

        $this->info('Sending email to user for processed order');
        $data = EmailOrderStatus::where('is_sent', false)->with('order')->get();

        $this->info('Data: ' . json_encode($data));

        foreach ($data as $emailOrderStatus) {
            $emailOrderStatus->is_sent = true;
            $emailOrderStatus->save();
            $this->sendEmailProcessed($emailOrderStatus->order);
        }

        $this->info('Email sent to user for processed order');
    }

    private function sendEmailProcessed($order)
    {
        $sendGrid = new SendGridServices();
        $templateMail = config("constants.sendgrid_template_swapupkit_label_processed");
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
                        "order_id" => $order->id,
                        "first_name" => $order->user->first_name
                    ]
                ]
            ],
            "template_id" => $templateMail
        ];
        $sendGrid->sendMailByTemplate($sendGridData);
    }
}
