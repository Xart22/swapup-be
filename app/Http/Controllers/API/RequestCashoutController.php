<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\RequestCashout;
use App\Services\UserConsignServices;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Response;

class RequestCashoutController extends Controller
{
    private $userConsignServices;

    public function __construct(UserConsignServices $userConsignServices)
    {
        $this->userConsignServices = $userConsignServices;
    }

    public function listCashout(Request $request)
    {
        return RequestCashout::listCashout($request);
    }

    public function updateCashout(Request $request)
    {
        return RequestCashout::updateCashouts($request);
    }

    public function exportcashout(Request $request)
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

            $query = RequestCashout::join('users', 'request_cashouts.user_id', '=', 'users.id')
                ->where(function ($query) use ($searchTerm) {
                    $query->where('users.name', 'like', "%{$searchTerm}%")
                        ->orWhere('users.email', 'like', "%{$searchTerm}%")
                        ->orWhere('users.first_name', 'like', "%{$searchTerm}%")
                        ->orWhere('users.last_name', 'like', "%{$searchTerm}%");
                });


            if ($startDate && $endDate) {
                $query->whereBetween('request_cashouts.request_date', [$startDate, $endDate]);
            }

            $requestCashoutDetails = $query->select('*')->orderBy('request_cashouts.request_date', 'desc')->get();

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
            $headers = ['Email', 'First Name', 'Last Name', 'BSB', 'Account Number', 'Cashout Amount', 'Cashout Request Timestamp', 'Account Balance before cashout', 'Account Balance after cashout'];
            $columns = range('A', 'I');

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
            foreach ($requestCashoutDetails as $value) {

                $sheet->setCellValue('A' . $row, $value->email);
                $sheet->setCellValue('B' . $row, $value->first_name);
                $sheet->setCellValue('C' . $row, $value->last_name);
                $sheet->setCellValue('D' . $row, $value->bsb);
                $sheet->setCellValue('E' . $row, $value->account_number);
                $sheet->setCellValue('F' . $row, number_format($value->cashout_amount / 100, 2) ?? 0);
                $sheet->setCellValue('G' . $row, Carbon::parse($value->request_date)->format('Y-m-d H:i:s'));
                $sheet->setCellValue('H' . $row, number_format($value->before_balance / 100, 2) ?? 0);
                $sheet->setCellValue('I' . $row, number_format($value->after_balance / 100, 2) ?? 0);

                $sheet->getStyle('F' . $row)->getNumberFormat()->setFormatCode($numberFormat);
                $sheet->getStyle('H' . $row)->getNumberFormat()->setFormatCode($numberFormat);
                $sheet->getStyle('I' . $row)->getNumberFormat()->setFormatCode($numberFormat);

                $row++;
            }
            // Save the file to a temporary path
            $tempFilePath = tempnam(sys_get_temp_dir(), 'CashoutExport');
            $writer = new Xlsx($spreadsheet);
            $writer->save($tempFilePath);

            // Stream the file to the browser
            $response = Response::stream(function () use ($tempFilePath) {
                readfile($tempFilePath);
                unlink($tempFilePath); // Delete the file after sending it to the client
            }, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="request_cashout_export_' . now()->format('Y_m_d_H_i_s') . '.xlsx"',
                'Cache-Control' => 'max-age=0',
            ]);

            return $response;
        } catch (\Exception $e) {
            return response()->json([
                "error" => $e->getMessage()
            ], 500);
        }
    }

    // protected function checkdatacashoutfirst($user_consignid)
    // {
    //     try {
    //         $data = [
    //             "limit" => 1000000,
    //             "offset" => 0,
    //             "where" => [
    //                 [
    //                     "operator" => "eq",
    //                     "value" => $user_consignid,
    //                     "field" => "contact"
    //                 ]
    //             ],
    //             "order_by" => [
    //                 [
    //                     "field" => "created",
    //                     "order" => "DESC"
    //                 ]
    //             ],
    //             "select" => ["created", "event.entity_id", "event.entity_type", "event.id", "item.*", "title", "extra", "reason", "deleted", "invoice", "payouts.*", "bonus", "location.*", "delta", "balance", "id"]
    //         ];

    //         $checkData = $this->userConsignServices->checkrecentactivity($data);
    //         // $responseData = $checkData['data'] ?? [];
    //         // $resources = RecentActivityCollection::collection(collect($responseData));
    //         return $checkData;
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }
}
