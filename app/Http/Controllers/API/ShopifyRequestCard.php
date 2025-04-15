<?php

namespace App\Http\Controllers\API;

use App\Services\ShopifyServices;
use App\Services\UserConsignServices;
use App\Services\SendGridServices;
use App\Http\Controllers\API\BaseController as BaseController;
use App\Helpers\Helpers;
use App\Http\Resources\GiftCardCollection;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Models\RequestCashout;
use App\Models\GiftDetails;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use \SendGrid\Mail\Mail;
use Illuminate\Support\Facades\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Models\User;

class ShopifyRequestCard extends BaseController
{
    private $userConsignServices;
    private $sendGridServices;
    private $shopifyServices;
    public function __construct(ShopifyServices $shopifyService, UserConsignServices $userConsignServices, SendGridServices $sendGridServices)
    {
        $this->shopifyServices = $shopifyService;
        $this->userConsignServices = $userConsignServices;
        $this->sendGridServices = $sendGridServices;
    }

    public function getListCard(): JsonResponse
    {
        try {
            $dataResponse = $this->shopifyServices->listCard();
            return Helpers::Response(200, $dataResponse);
        } catch (Exception $e) {
            return Helpers::Response(500, $e->getMessage());
        }
    }

    public function searchForListGiftCard(Request $request)
    {
        try {
            $searchTerm = $request->input('search');
            $dateRange = $request->input('date_range');

            if ($dateRange) {
                $dates = explode(',', $dateRange);
                if (count($dates) === 2) {
                    $startDate = date('Y-m-d', strtotime(trim($dates[0])));
                    $endDate = date('Y-m-d', strtotime(trim($dates[1])));
                } else {
                    return response()->json(['error' => 'Invalid date range format'], 400);
                }
            } else {
                $startDate = null;
                $endDate = null;
            }

            $query = GiftDetails::join('users', 'gift_details.user_id', '=', 'users.consign_id')
                ->where(function ($query) use ($searchTerm) {
                    $query->where('users.name', 'like', "%{$searchTerm}%")
                        ->orWhere('users.email', 'like', "%{$searchTerm}%")
                        ->orWhere('users.first_name', 'like', "%{$searchTerm}%")
                        ->orWhere('users.last_name', 'like', "%{$searchTerm}%");
                });


            if ($startDate && $endDate) {
                $query->whereBetween('gift_details.request_date', [$startDate, $endDate]);
            }

            $giftDetails = $query->select('*')->orderBy('gift_details.request_date', 'desc')->get();
            return response(["data" => GiftCardCollection::collection($giftDetails)], 202);
        } catch (\Exception $e) {
            return response()->json([
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function exportForListGiftCard(Request $request)
    {
        try {
            $searchTerm = $request->input('search');
            $dateRange = $request->input('date_range');

            if ($dateRange) {
                $dates = explode(',', $dateRange);
                if (count($dates) === 2) {
                    $startDate = date('Y-m-d', strtotime(trim($dates[0])));
                    $endDate = date('Y-m-d', strtotime(trim($dates[1])));
                } else {
                    return response()->json(['error' => 'Invalid date range format'], 400);
                }
            } else {
                $startDate = null;
                $endDate = null;
            }

            $query = GiftDetails::join('users', 'gift_details.user_id', '=', 'users.consign_id')
                ->where(function ($query) use ($searchTerm) {
                    $query->where('users.name', 'like', "%{$searchTerm}%")
                        ->orWhere('users.email', 'like', "%{$searchTerm}%")
                        ->orWhere('users.first_name', 'like', "%{$searchTerm}%")
                        ->orWhere('users.last_name', 'like', "%{$searchTerm}%");
                });


            if ($startDate && $endDate) {
                $query->whereBetween('gift_details.request_date', [$startDate, $endDate]);
            }

            $giftDetails = $query->select('*')->orderBy('gift_details.request_date', 'desc')->get();

            $giftCardData = GiftCardCollection::collection($giftDetails);

            // // Create new Spreadsheet
            // $spreadsheet = new Spreadsheet();
            // $sheet = $spreadsheet->getActiveSheet();

            // // Set column headers
            // $sheet->setCellValue('A1', 'Customer Email');
            // $sheet->setCellValue('B1', 'First Name');
            // $sheet->setCellValue('C1', 'Last Name');
            // $sheet->setCellValue('D1', 'Credit Used');
            // $sheet->setCellValue('E1', 'Gift Card Bonus');
            // $sheet->setCellValue('F1', 'Gift Card Amount');
            // $sheet->setCellValue('G1', 'Gift Card Created (timestamp)');
            // $sheet->setCellValue('H1', 'Gift Card Code Ending (4 chars)');

            // $numberFormat = '#,##0.00';

            // // Populate the data into the sheet
            // $row = 2; // Starting row for data
            // foreach ($giftCardData as $gift) {
            //     $sheet->setCellValue('A' . $row, $gift->email);
            //     $sheet->setCellValue('B' . $row, $gift->first_name);
            //     $sheet->setCellValue('C' . $row, $gift->last_name);
            //     $sheet->setCellValue('D' . $row, number_format($gift->amount_used / 100, 2) ?? 0);
            //     $sheet->setCellValue('E' . $row, number_format($gift->percentage_amount_used / 100, 2) ?? 0);
            //     $sheet->setCellValue('F' . $row, number_format($gift->gift_total_amount / 100, 2) ?? 0);
            //     $sheet->setCellValue('G' . $row, Carbon::parse($gift->request_date)->format('Y-m-d H:i:s'));
            //     $sheet->setCellValue('H' . $row, Helpers::maskString5characters($gift->gen_code));

            //     $sheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode($numberFormat);
            //     $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode($numberFormat);
            //     $sheet->getStyle('F' . $row)->getNumberFormat()->setFormatCode($numberFormat);

            //     $row++;
            // }
            // // // Save the file to a temporary path
            // // $tempFilePath = tempnam(sys_get_temp_dir(), 'GiftCardExport');
            // // $writer = new Xlsx($spreadsheet);
            // // $writer->save($tempFilePath);

            // // // Return a response that forces a download
            // // return Response::download($tempFilePath, 'gift_card_export_' . now()->format('Y_m_d_H_i_s') . '.xlsx')
            // //     ->deleteFileAfterSend(true);

            // // Save the file to a temporary path
            // $tempFilePath = tempnam(sys_get_temp_dir(), 'GiftCardExport');
            // $writer = new Xlsx($spreadsheet);
            // $writer->save($tempFilePath);

            // // Stream the file to the browser
            // $response = Response::stream(function () use ($tempFilePath) {
            //     readfile($tempFilePath);
            //     // Delete the file after sending it to the client
            //     unlink($tempFilePath);
            // }, 200, [
            //     'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            //     'Content-Disposition' => 'attachment; filename="request_gift_card_export_' . now()->format('Y_m_d_H_i_s') . '.xlsx"',
            //     'Cache-Control' => 'max-age=0',
            // ]);

            // return $response;

            // Create new Spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set column headers
            $headers = ['Customer Email', 'First Name', 'Last Name', 'Credit Used', 'Gift Card Bonus', 'Gift Card Amount', 'Gift Card Created (timestamp)', 'Gift Card Code Ending (4 chars)'];
            $columns = range('A', 'H');

            foreach ($headers as $index => $header) {
                $sheet->setCellValue($columns[$index] . '1', $header);
                $sheet->getStyle($columns[$index] . '1')->getFont()->setBold(true); // Bold headers
            }

            // Auto-size columns
            foreach ($columns as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            // Freeze the first row (headers)
            $sheet->freezePane('A2');

            // Apply filters to headers
            $sheet->setAutoFilter($sheet->calculateWorksheetDimension());

            $numberFormat = '$#,##0.00';

            // Populate the data into the sheet
            $row = 2; // Starting row for data
            foreach ($giftCardData as $gift) {
                $sheet->setCellValue('A' . $row, $gift->email);
                $sheet->setCellValue('B' . $row, $gift->first_name);
                $sheet->setCellValue('C' . $row, $gift->last_name);
                $sheet->setCellValue('D' . $row, number_format($gift->amount_used / 100, 2) ?? 0);
                $sheet->setCellValue('E' . $row, number_format($gift->percentage_amount_used / 100, 2) ?? 0);
                $sheet->setCellValue('F' . $row, number_format($gift->gift_total_amount / 100, 2) ?? 0);
                $sheet->setCellValue('G' . $row, Carbon::parse($gift->request_date)->format('Y-m-d H:i:s'));
                $sheet->setCellValue('H' . $row, Helpers::maskString5characters($gift->gen_code));

                $sheet->getStyle('D' . $row)->getNumberFormat()->setFormatCode($numberFormat);
                $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode($numberFormat);
                $sheet->getStyle('F' . $row)->getNumberFormat()->setFormatCode($numberFormat);

                $row++;
            }

            // Save the file to a temporary path
            $tempFilePath = tempnam(sys_get_temp_dir(), 'GiftCardExport');
            $writer = new Xlsx($spreadsheet);
            $writer->save($tempFilePath);

            // Stream the file to the browser
            $response = Response::stream(function () use ($tempFilePath) {
                readfile($tempFilePath);
                unlink($tempFilePath); // Delete the file after sending it to the client
            }, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="request_gift_card_export_' . now()->format('Y_m_d_H_i_s') . '.xlsx"',
                'Cache-Control' => 'max-age=0',
            ]);

            return $response;
        } catch (\Exception $e) {
            return response()->json([
                "error" => $e->getMessage()
            ], 500);
        }
    }

    // public function searchForListGiftCard(Request $request): JsonResponse
    // {
    //     $validator = Validator::make($request->all(), [
    //         'created_at' => 'date|date_format:Y-m-d',
    //         'updated_at' => 'date|date_format:Y-m-d',
    //         'disabled_at' => 'date|date_format:Y-m-d',
    //         'balance' => 'numeric|between:0,99.99',
    //         'initial_value' => 'numeric|between:0,99.99',
    //         'amount_spent' => 'numeric|between:0,99.99',
    //         'email' => 'email',
    //         'last_characters' => 'max_digits:4'
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json(['error' => 'Validation Error.', 'messages' => $validator->errors()], 422);
    //     }

    //     $arrayparams = [
    //         "created_at" => $request->input('created_at'),
    //         "updated_at" => $request->input('updated_at'),
    //         "disabled_at" => $request->input('disabled_at'),
    //         "balance" => $request->input('balance'),
    //         "initial_value" => $request->input('initial_value'),
    //         "amount_spent" => $request->input('amount_spent'),
    //         "email" => $request->input('email'),
    //         "last_characters" => $request->input('last_characters')
    //     ];

    //     // Construct query string
    //     $query = '';

    //     foreach ($arrayparams as $key => $value) {
    //         if (!is_null($value) && $value !== '') {
    //             $query .= $key . ':' . $value . ' ';
    //         }
    //     }
    //     $query = trim($query);

    //     try {
    //         $dataResponse = $this->shopifyServices->searchCard($query);

    //         $giftCards = $dataResponse['gift_cards']; // assuming response structure has 'gift_cards' key
    //         // Filtering logic based on user input
    //         $filteredResults = array_filter($giftCards, function ($item) use ($request) {
    //             $match = true;

    //             // Filter by created_at (compare only the date part)
    //             if ($request->filled('created_at')) {
    //                 $created_at = Carbon::parse($item['created_at'])->format('Y-m-d');
    //                 $requestCreatedAt = Carbon::parse($request->input('created_at'))->format('Y-m-d');
    //                 $match = $match && ($created_at === $requestCreatedAt);
    //             }
    //             // Filter by updated_at (compare only the date part)
    //             if ($request->filled('updated_at')) {
    //                 $updated_at = Carbon::parse($item['updated_at'])->format('Y-m-d');
    //                 $requestUpdatedAt = Carbon::parse($request->input('updated_at'))->format('Y-m-d');
    //                 $match = $match && ($updated_at === $requestUpdatedAt);
    //             }
    //             // Filter by disabled_at (compare only the date part)
    //             if ($request->filled('disabled_at')) {
    //                 $disabled_at = Carbon::parse($item['disabled_at'])->format('Y-m-d');
    //                 $requestDisabledAt = Carbon::parse($request->input('disabled_at'))->format('Y-m-d');
    //                 $match = $match && ($disabled_at === $requestDisabledAt);
    //             }
    //             // Filter by balance (numeric comparison)
    //             if ($request->filled('balance')) {
    //                 $balance = number_format(floatval($item['balance']), 2);
    //                 $requestedBalance = number_format(floatval($request->input('balance')), 2);
    //                 $match = $match && ($balance === $requestedBalance);
    //             }
    //             // Filter by initial_value (numeric comparison)
    //             if ($request->filled('initial_value')) {
    //                 $initial_value = number_format(floatval($item['initial_value']), 2);
    //                 $requestedInitialValue = number_format(floatval($request->input('initial_value')), 2);
    //                 $match = $match && ($initial_value === $requestedInitialValue);
    //             }
    //             // Filter by amount_spent (numeric comparison)
    //             if ($request->filled('amount_spent')) {
    //                 $amount_spent_value = number_format(floatval($item['amount_spent']), 2);
    //                 $requestedAmountSpentValue = number_format(floatval($request->input('amount_spent')), 2);
    //                 $match = $match && ($amount_spent_value === $requestedAmountSpentValue);
    //             }
    //             // Filter by email (exact match)
    //             if ($request->filled('email')) {
    //                 $match = $match && ($item['email'] === $request->input('email'));
    //             }
    //             // Filter by last_characters (exact match)
    //             if ($request->filled('last_characters')) {
    //                 $match = $match && ($item['last_characters'] === $request->input('last_characters'));
    //             }

    //             return $match;
    //         });

    //         dd(array_values($filteredResults));

    //         // return Helpers::Response(200, array_values($filteredResults));
    //     } catch (Exception $e) {
    //         return Helpers::Response(500, $e->getMessage());
    //     }
    // }

    public function requestCreateGiftCard(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|nullable|integer|min:1|max_digits:12'
        ]);

        $user = Auth::user()->consign_id;

        $datauser = User::where('consign_id', $user)->first();

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation Error.', 'messages' => $validator->errors()], 422);
        }

        $data = $this->userConsignServices->getUser($user);

        $input = $request->all();

        if ($data['balance'] < $input['amount']) {
            return response()->json([
                "error" => "Validation failed cashout",
                "message" => 'You have a balance $' . (isset($data['balance']) ? number_format($data['balance'] / 100, 2) : 0) . ' cannot cashout your balance'
            ], 500);
        }

        DB::beginTransaction();

        try {

            $amountBalanceBonus = (isset($input["amount"]) ? $input["amount"] : 0) + ((isset($input['amount']) ? $input['amount'] : 0) * (20 / 100));
            $totalAmountBalanceBonus = (isset($data['balance']) ? $data['balance'] : 0) - $amountBalanceBonus;

            $shopifyData = [
                "gift_card" => [
                    "initial_value" => (string) number_format($amountBalanceBonus / 100, 2),
                    "customer_id" => Auth::user()->shopify_seller_id,
                    "currency" => "AUD",
                ]
            ];

            $responseShopify = $this->shopifyServices->createCard($shopifyData);

            // adjust amount

            $memo = "$" . (isset($input["amount"]) ? number_format($input["amount"] / 100, 2) : 0) . " " . "gift card request ending " . Helpers::maskString5characters((isset($responseShopify->original["data"]['gift_card']['code']) ? $responseShopify->original["data"]['gift_card']['code'] : null));

            $consignData = [
                "account" => $user,
                "amount" => -$input['amount'],
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
                        "value" => $user,
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

            $consignResponse2 = $this->userConsignServices->updatedatabalance($idrowbalance, $consignTitle);

            $datanew = $this->userConsignServices->getUser($user);

            $cashout = new RequestCashout();
            $giftCards = new GiftDetails();

            $cashout->user_id = Auth::id();
            $cashout->cashout_amount = (isset($input["amount"]) ? $input["amount"] : 0);
            $cashout->before_balance = $data['balance'];
            $cashout->after_balance = $datanew['balance'];
            $cashout->request_date = Carbon::now();
            $cashout->receipt_number = null;
            $cashout->mark_paid = null;
            $cashout->notes = 'Gift Card';

            $giftCards->id_gift_card = (isset($responseShopify->original["data"]['gift_card']['id']) ? $responseShopify->original["data"]['gift_card']['id'] : null);
            $giftCards->request_date = Carbon::now()->format('Y-m-d h:i:s');
            $giftCards->user_id = Auth::user()->consign_id;
            $giftCards->amount_used = (isset($input["amount"]) ? $input["amount"] : 0);
            $giftCards->percentage_amount_used = ((isset($input['amount']) ? $input['amount'] : 0) * (20 / 100));
            $giftCards->gift_total_amount = $amountBalanceBonus;
            $giftCards->before_balance = $data["balance"];
            $giftCards->after_balance = $datanew["balance"];
            $giftCards->gen_code = (isset($responseShopify->original["data"]['gift_card']['code']) ? $responseShopify->original["data"]['gift_card']['code'] : null);

            $cashout->save();
            $giftCards->save();

            DB::commit();

            if ($responseShopify->status() == 201) {
                // $sendMailer = $this->sendMailerSeller(Auth::user()->email, (isset($datauser['first_name']) ? $datauser['first_name'] : ''), (isset($responseShopify->original["data"]['gift_card']['code']) ? $responseShopify->original["data"]['gift_card']['code'] : null), (isset($datanew["balance"]) ? $datanew["balance"] : 0), (isset($input["amount"]) ? $input["amount"] : 0), $amountBalanceBonus);
                $sendMailer = $this->sendMailerSeller(Auth::user()->email, (isset($datauser['first_name']) ? $datauser['first_name'] : ''), (isset($responseShopify->original["data"]['gift_card']['code']) ? $responseShopify->original["data"]['gift_card']['code'] : null), (isset($input["amount"]) ? $input["amount"] : 0), (isset($input["amount"]) ? $input["amount"] : 0), $amountBalanceBonus);
                if ($sendMailer->status() == 202) {
                    return response()->json([
                        "succcess" => "Send Gift Card Success",
                    ], 202);
                } else {
                    $error = json_decode($sendMailer->original["message"]->body(), true);
                    return response()->json([
                        "error" => $error["errors"][0]["message"]
                    ], 400);
                }
            }
        } catch (\Exception $e) {
            DB::rollback();
            return Helpers::Response(400, $e->getMessage());
        }
    }

    protected function sendMailerSeller($seller_mail, $name, $code, $balance, $amount, $amountbalanceBonus)
    {
        // $templateMail = env("SENDGRID_TEMPLATE_", "d-f545fe80107b48c68d730c7ded01a0ef");
        $templateMail = config("constants.sendgrid_template");
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
                        "amount_used" => "$" . number_format($amount / 100, 2),
                        "remaining_balance" => "$" . number_format($balance / 100, 2),
                        "total_amount_used" => "$" . number_format($amountbalanceBonus / 100, 2),
                        "seller_name" => $name,
                        "code" => $code
                    ]
                ]
            ],
            "template_id" => $templateMail
        ];

        $SendGridResponse = $this->sendGridServices->sendMailByTemplate($sendGridData);

        return $SendGridResponse;


        //this use library but you can

        // $email = new Mail();
        // $email->setFrom(
        //     'hello@swapup.com.au',
        //     $name
        // );
        // $email->setSubject('Kirim Gift Card dari Shopify');
        // $email->addTo(
        //     $seller_mail,
        //     $name
        // );
        // $email->addContent(
        //     "text/html",
        //     "<strong>Gift Card Code : $code </strong>
        //      <strong>Amount Gift : $amountBalanceBonus </strong>
        //     "
        // );
        // $sendgrid = new \SendGrid(getenv('SENDGRID_API_KEY'));
        // try {
        //     $response = $sendgrid->send($email);
        //     return Helpers::Response(200, $response);
        // } catch (Exception $e) {
        //     return Helpers::Response(400, $e->getMessage());
        // }
    }
}
