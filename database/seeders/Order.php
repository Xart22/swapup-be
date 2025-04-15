<?php

namespace Database\Seeders;

use App\Models\Variants;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class Order extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $orderData = file_get_contents(base_path('database/data/order.json'));
        $dataOrder = json_decode($orderData, true);
        $userData = file_get_contents(base_path('database/data/user-new.json'));
        $dataUser = json_decode($userData, true);
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        foreach ($dataUser as $user) {
            // find order by email
            $order = collect($dataOrder)->where('Email', $user['Email'])->first();
            $userId = DB::table('users')->where('email', $user['Email'])->first();
            if ($order) {
                $id = DB::table('orders')->insertGetId([
                    'id' => (int) $order['id'],
                    'user_id' => $userId->id,
                    'variant_id' => 1,
                    'payment_intent_id' => $order['payment_intent_id'],
                    'client_secret' => $order['client_secret'],
                    'stripe_status' => $order['stripe_status'] ?: "",
                    'status' => $order['status'],
                    'payment_method' => $order['payment_method'] ?: null,
                ]);
                DB::table('shipments')->insert([
                    'order_id' => $id,
                    'aus_order_id' => $order['aus_order_id'],
                    'shipment_id' => $order['shipment_id'],
                    'article_id' => $order['article_id'],
                    'consignment_id' => $order['consignment_id'],
                    'shipment_reference' => $order['shipment_reference'],
                    'order_reference' => $order['order_reference'],
                    'total_cost' => $order['total_cost'],
                    'total_cost_ex_gst' => $order['total_cost_ex_gst'],
                    'total_gst' => $order['total_gst'],
                    'movement_type' => $order['movement_type'],
                    'status' => $order['status'],
                    'label_url' => 'labels/' . $order['label_url'] . ".pdf",
                ]);
            } else {
                Log::info('Order not found for email: ' . $user['Email']);
            }
        }


        // foreach ($data as $order) {
        //     //get user id
        //     $user = DB::table('users')->where('email', $order['Email'])->first();

        //     if ($user) {
        //         $id = DB::table('orders')->insertGetId([
        //             'id' => (int) $order['id'],
        //             'user_id' => $user->id,
        //             'variant_id' => 1,
        //             'payment_intent_id' => $order['payment_intent_id'],
        //             'client_secret' => $order['client_secret'],
        //             'stripe_status' => $order['stripe_status'] ?: "",
        //             'status' => $order['status'],
        //             'payment_method' => $order['payment_method'] ?: null,
        //         ]);
        //         DB::table('shipments')->insert([
        //             'order_id' => $id,
        //             'aus_order_id' => $order['aus_order_id'],
        //             'shipment_id' => $order['shipment_id'],
        //             'article_id' => $order['article_id'],
        //             'consignment_id' => $order['consignment_id'],
        //             'shipment_reference' => $order['shipment_reference'],
        //             'order_reference' => $order['order_reference'],
        //             'total_cost' => $order['total_cost'],
        //             'total_cost_ex_gst' => $order['total_cost_ex_gst'],
        //             'total_gst' => $order['total_gst'],
        //             'movement_type' => $order['movement_type'],
        //             'status' => $order['status'],
        //             'label_url' => 'labels/' . $order['label_url'],
        //         ]);
        //     } else {
        //         Log::info('User not found for email: ' . $order['Email']);
        //     }
        // }
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
