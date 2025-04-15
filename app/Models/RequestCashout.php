<?php

namespace App\Models;

use App\Helpers\Helpers;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\SendGridServices;

class RequestCashout extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'cashout_amount',
        'before_balance',
        'after_balance',
        'request_date',
        'receipt_number',
        'mark_paid',
        'notes'
    ];

    public static function listCashout($request)
    {
        // $perPage = $request->perPage ?? 10;
        // $sortField = $request->sortField ?? 'created_at';
        // $sortDirection = $request->sortDirection ?? 'desc';

        // $cashout = DB::table('request_cashouts as rc')
        //     ->join('users as u', 'rc.user_id', '=', 'u.id')
        //     ->select('u.name',
        //             'u.email',
        //             'u.bsb',
        //             'u.account_number',
        //             'rc.cashout_amount',
        //             'rc.request_date',
        //             'rc.receipt_number',
        //             'rc.mark_paid',
        //             'rc.notes')
        //     ->orderBy($sortField, $sortDirection)
        //     ->paginate($perPage);

        // return response()->json($cashout, 200);

        $sortField = $request->sortField ?? 'created_at';
        $sortDirection = $request->sortDirection ?? 'desc';

        $validSortFields = ['created_at', 'name', 'email', 'bsb', 'account_number', 'cashout_amount', 'request_date', 'receipt_number', 'mark_paid', 'notes'];
        if (!in_array($sortField, $validSortFields)) {
            $sortField = 'created_at';
        }

        $searchTerm = $request->search ?? null;
        $dateRange = $request->date_range ?? null;

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

        $query = DB::table('request_cashouts as rc')->where('rc.notes', 'Cash Out')
            ->join('users as u', 'rc.user_id', '=', 'u.id')
            ->where(function ($query) use ($searchTerm) {
                $query->where('u.name', 'like', "%{$searchTerm}%")
                    ->orWhere('u.email', 'like', "%{$searchTerm}%")
                    ->orWhere('u.first_name', 'like', "%{$searchTerm}%")
                    ->orWhere('u.last_name', 'like', "%{$searchTerm}%");
            });

        if ($startDate && $endDate) {
            $query->whereBetween('rc.request_date', [$startDate, $endDate]);
        }

        $cashout = $query->select(
            'rc.id',
            'u.name',
            'u.first_name',
            'u.last_name',
            'u.email',
            'u.bsb',
            'u.account_number',
            'rc.cashout_amount',
            'rc.request_date',
            'rc.receipt_number',
            'rc.mark_paid',
            'rc.notes',
            'rc.created_at'
        )
            ->orderBy($sortField, $sortDirection)
            ->orderBy('rc.id', $sortDirection)
            ->get();

        return response()->json($cashout, 200);
    }

    public static function updateCashouts($request)
    {
        DB::beginTransaction();
        try {

            $ids = $request->input('ids');

            if (empty($ids) || !is_array($ids)) {
                return Helpers::Response(400, 'ID tidak valid');
            }
            $receiptNumber = $request->input('receipt_number');

            if (is_null($receiptNumber)) {
                return Helpers::Response(400, 'Data receipt_number dan mark_paid diperlukan');
            }
            RequestCashout::whereIn('id', $ids)
                ->update([
                    'receipt_number' => $receiptNumber,
                    'mark_paid' => Carbon::now()
                ]);

            $updatedCashouts = RequestCashout::whereIn('request_cashouts.id', $ids)
                ->join('users', 'users.id', '=', 'request_cashouts.user_id')
                ->select(
                    'users.email as seller_mail',
                    'users.first_name',
                    'request_cashouts.cashout_amount',
                    'request_cashouts.request_date',
                    'request_cashouts.receipt_number'
                )->get();

            foreach ($updatedCashouts as $cashout) {
                $sendMailerResponse = self::sendMailerAfterCashout(
                    $cashout->seller_mail,
                    $cashout->first_name,
                    number_format($cashout->cashout_amount / 100, 2),
                    Carbon::parse($cashout->request_date)->format('d M Y h:i:s'),
                    $cashout->receipt_number
                );

                if ($sendMailerResponse->status() != 202) {
                    DB::rollback();
                    return Helpers::Response(400, 'Failed to send email');
                }
            }

            DB::commit();

            return Helpers::Response(201, "Data Berhasil di Perbarui");
        } catch (\Exception $ex) {
            DB::rollback();
            $responseData = $ex->getMessage();
            return Helpers::Response(400, $responseData);
        }
    }

    protected static function sendMailerAfterCashout($seller_mail, $name, $cashout_amount, $request_date, $receipt_number)
    {

        $sendgrid = new SendGridServices();

        $templateMail = config("constants.sendgrid_template_5_");
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
                        "first_name" => "{$name}",
                        "cashout_amount" => "{$cashout_amount}",
                        "request_date" => "{$request_date}",
                        "receipt_number" => "{$receipt_number}"
                    ]
                ]
            ],
            "template_id" => $templateMail
        ];

        $SendGridResponse = $sendgrid->sendMailByTemplate($sendGridData);

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
