<?php

namespace App\Http\Controllers\API;

use App\Helpers\Helpers;
use App\Models\Order;
use App\Models\User;
use App\Services\SendGridServices;
use App\Services\UserConsignServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class AusPostController extends BaseController
{
    private $userConsignServices;
    private $sendGridServices;

    public function __construct(UserConsignServices $userConsignServices, SendGridServices $sendGridServices)
    {
        $this->userConsignServices = $userConsignServices;
        $this->sendGridServices = $sendGridServices;
    }

    public function validateSuburb(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'suburb' => 'required|string',
                'state' => 'required|string',
                'postal_code' => 'required|string',
                'phone_number' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => 'Validation Error.', 'messages' => $validator->errors()], 422);
            }

            $suburb = $request->suburb;
            $state = $request->state;
            $postcode = $request->postal_code;
            $url = config("constants.auspost_api_url") . "/shipping/v1/address?suburb=$suburb&state=$state&postcode=$postcode";
            // $response = Http::withBasicAuth(env('AUSPOST_API_USERNAME'), env('AUSPOST_API_PASSWORD'))->get($url);
            $response = Http::withBasicAuth(config("constants.auspost_api_username"), config("constants.auspost_api_password"))->get($url);

            if ($response->failed()) {
                return Helpers::Response(500, 'An error occurred while validating the suburb.');
            }

            if ($response->json()['found']) {
                $data = [
                    'state' => $state,
                    'postal_code' => $postcode,
                    'address_line_1' => $request->address_line_1,
                    'address_line_2' => $request->address_line_2,
                    'city' => $request->city,
                    'phone_number' => $request->phone_number,
                ];
                $this->userConsignServices->updateProfile($data);
                $user = User::find(Auth::user()->id);
                $user->suburb = $suburb;
                $user->state = $state;
                $user->postal_code = $postcode;
                $user->address_line_1 = $request->address_line_1;
                $user->address_line_2 = $request->address_line_2;
                $user->phone_number = $request->phone_number;
                $user->city = $request->city;

                $user->save();

                return Helpers::Response(200, 'Suburb is valid.');
            } else {
                return Helpers::Response(400, 'Suburb is invalid.');
            }
        } catch (\Throwable $th) {
            return Helpers::Response(500, $th->getMessage());
        }
    }


    public function createLabel($order_id)
    {
        try {
            $order = Order::find($order_id);

            if (!$order) {
                return Helpers::Response(404, 'Order not found');
            }
            // $url = config("constants.auspost_api_url"). "/shipping/v1/labels";
            $url = config("constants.auspost_api_url") . "/shipping/v1/labels";
            $data = [
                "wait_for_label_url" => true,
                "preferences" => [
                    [
                        "type" => "PRINT",
                        "groups" => [
                            [
                                "group" => "Parcel Post",
                                "layout" => "A4-1pp",
                                "branded" => true,
                                "left_offset" => 0,
                                "top_offset" => 0
                            ],
                            [
                                "group" => "Express Post",
                                "layout" => "A4-1pp",
                                "branded" => false,
                                "left_offset" => 0,
                                "top_offset" => 0
                            ],
                            [
                                "group" => "StarTrack",
                                "layout" => "A4-1pp",
                                "branded" => false,
                                "left_offset" => 0,
                                "top_offset" => 0
                            ]
                        ]
                    ]
                ],
                "shipments" => [
                    [
                        "shipment_id" => $order->shipment->shipment_id,
                    ]
                ]
            ];

            // $response = Http::withBasicAuth(env('AUSPOST_API_USERNAME'), env('AUSPOST_API_PASSWORD'))
            $response = Http::withBasicAuth(config("constants.auspost_api_username"), config("constants.auspost_api_password"))
                ->withHeaders([
                    // 'account-number' => env('AUSPOST_ACCOUNT_NUMBER'),
                    'account-number' => config("constants.auspost_account_number"),
                ])->post($url, $data);
            if ($response->failed()) {
                return Helpers::Response(500, 'An error occurred while creating the label.');
            }

            $responseAus = $response->json();

            $order->shipment->update([
                'label_url' => $responseAus['labels'][0]['url'],
            ]);


            return Helpers::Response(200, $order->shipment->label_url);
        } catch (\Throwable $th) {
            return Helpers::Response(500, $th->getMessage());
        }
    }
}
