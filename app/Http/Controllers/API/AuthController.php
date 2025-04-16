<?php

namespace App\Http\Controllers\API;

use App\Services\UserConsignServices;
use App\Services\SendGridServices;
use App\Services\ShopifyServices;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use App\Helpers\Helpers;
use App\Models\RequestCashout;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\RecentActivityCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx\Rels;
use App\Models\Items;
use App\Jobs\ProcessCallItems;
use Illuminate\Support\Str;

class AuthController extends BaseController
{
    private $userConsignServices;
    private $sendGridServices;
    private $shopifyServices;

    public function __construct(UserConsignServices $userConsignServices, SendGridServices $sendGridServices, ShopifyServices $shopifyServices)
    {
        $this->userConsignServices = $userConsignServices;
        $this->sendGridServices = $sendGridServices;
        $this->shopifyServices = $shopifyServices;
    }

    /**
     * Login api
     *
     * @return \Illuminate\Http\Response
     */
    public function login(Request $request): JsonResponse
    {
        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            $user = Auth::user();

            if (! $user->hasVerifiedEmail()) {
                Auth::logout();
                return response()->json(['error' => 'Please verify your email address before logging in.'], 403);
            }

            $consignId = Auth::user()->consign_id;

            // $lastProcessedDate = Cache::get("consign_items_processed_{$consignId}");

            // if ($lastProcessedDate != date('Y-m-d')) {

            ProcessCallItems::dispatch($consignId);

            //     Cache::put("consign_items_processed_{$consignId}", date('Y-m-d'), now()->addDay());
            // }

            $success['token'] =  $user->createToken('SwapUp')->plainTextToken;
            $success['name'] =  $user->name;
            $success['role'] =  $user->role;

            return Helpers::Response(200, $success);
            // return $this->sendResponse($success, 'User login successfully.');
        } else {
            return $this->sendError('Unauthorised.', ['error' => 'Unauthorised'], 401);
        }
    }

    public function getUser(): JsonResponse
    {
        $consignResponse = $this->userConsignServices->getUser(Auth::user()->consign_id);
        $consignResponse['bsb'] = Auth::user()->bsb;
        $consignResponse['account_number'] = Auth::user()->account_number;
        $consignResponse['suburb'] = Auth::user()->suburb;
        $consignResponse['address_line_1'] = Auth::user()->address_line_1;
        $consignResponse['address_line_2'] = Auth::user()->address_line_2;
        $consignResponse['city'] = Auth::user()->city;
        $consignResponse['state'] = Auth::user()->state;
        $consignResponse['postal_code'] = Auth::user()->postal_code;
        $consignResponse['phone_number'] = Auth::user()->phone_number;
        $consignResponse['first_name'] = Auth::user()->first_name;
        $consignResponse['last_name'] = Auth::user()->last_name;
        $consignResponse['email'] = Auth::user()->email;
        $consignResponse['phone_number'] = Auth::user()->phone_number;


        return response()->json(['data' => $consignResponse, 'message' => 'Get User successfully.'], 200);
    }

    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'address_line_1' => 'nullable|string',
            'address_line_2' => 'nullable|string',
            'city' => 'nullable|string',
            'company' => 'nullable|string',
            'email' => 'required|email',
            'email_notifications_enabled' => 'boolean',
            'first_name' => 'nullable|string',
            'last_name' => 'nullable|string',
            'phone_number' => 'nullable|string',
            'postal_code' => 'nullable|string',
            'bsb' => 'nullable|string',
            'account_number' => 'nullable|string',
            'state' => 'nullable|string',
            'password' => 'required',
            'c_password' => 'required|same:password',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation Error.', 'messages' => $validator->errors()], 422);
        }

        $input = $request->all();

        $shopifydata = [
            "customer" => [
                'email' => $input['email'],
                'first_name' => $input['first_name'],
                'last_name' => $input['last_name']
            ]
        ];


        // Data untuk ConsignCloud API (tanpa password)
        $consignData = [
            'address_line_1' => $input['address_line_1'],
            'address_line_2' => $input['address_line_2'] ?? null,
            'city' => $input['city'],
            'company' => $input['company'] ?? null,
            'email' => $input['email'],
            'email_notifications_enabled' => $input['email_notifications_enabled'],
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'phone_number' => $input['phone_number'],
            'postal_code' => $input['postal_code'],
            'state' => $input['state'],
        ];


        try {

            $checkDataShopify = $this->shopifyServices->searchCustomers("email:" . (isset($input["email"]) ? $input["email"] : null));


            if (isset($checkDataShopify['customers'][0]['email']) === isset($input['email'])) {
                $checkDataConsign = $this->userConsignServices->listUser((isset($input["email"]) ? $input["email"] : null));

                if (isset($checkDataConsign)) {
                    if (isset($checkDataConsign["data"][0]["email"]) === isset($input["email"])) {
                        return response()->json(['error' => 'Registration Error.', 'message' => "Consign Data is exists, please use for this other email"], 500);
                    }
                }

                $consignResponse = $this->userConsignServices->register($consignData);
            } else {

                $shopifyResponse = $this->shopifyServices->createSellerIDShopify($shopifydata);

                if (isset($shopifyResponse)) {
                    if (isset($shopifyResponse->original['status']) && $shopifyResponse->original['status'] == 'error') {
                        return response()->json(['error' => 'Registration Error.', 'message' => "Shopify Data is exists, please use for this other email"], 500);
                    }
                }

                $checkDataConsign = $this->userConsignServices->listUser((isset($input["email"]) ? $input["email"] : null));

                if (isset($checkDataConsign)) {
                    if (isset($checkDataConsign["data"][0]["email"]) === isset($input["email"])) {
                        return response()->json(['error' => 'Registration Error.', 'message' => "Consign Data is exists, please use for this other email"], 500);
                    }
                }

                $consignResponse = $this->userConsignServices->register($consignData);
            }

            $consignId = isset($consignResponse['id']) ? $consignResponse['id'] : null;
            $shopify_seller_id = (isset($checkDataShopify['customers'][0]['email']) === isset($input["email"])) ? $checkDataShopify['customers'][0]['id'] : $shopifyResponse['customer']['id'];

            $user = User::create([
                'consign_id' => $consignId,
                'shopify_seller_id' => $shopify_seller_id,
                'name' =>  $input['first_name'] . ' ' . $input['last_name'],
                'email' => $input['email'],
                'bsb' =>  $input['bsb'],
                'account_number' => $input['account_number'],
                'password' => bcrypt($input['password']),
                'phone_number' => $input['phone_number'],
                'first_name' => $input['first_name'],
                'last_name' => $input['last_name']
            ]);

            // $success['token'] =  $user->createToken('SwapUp')->plainTextToken;
            // $success['name'] =  $user->name;

            $data = [
                'id' => $user->id,
                'hash' => sha1($user->email),
                'expires' => Carbon::now()->addDay()->timestamp,
                'signature' => hash_hmac('sha256', $user->id . sha1($user->email), config('app.key'))
            ];
            $jsonData = json_encode($data);
            $base64Data = base64_encode($jsonData);
            $verificationUrl = URL::temporarySignedRoute(
                'verification.verify',
                Carbon::now()->addDay(),
                ['q' => $base64Data]
            );

            $sendMailer = $this->sendMailerConfirmRegistration((isset($input["email"]) ? $input["email"] : ""), $verificationUrl);

            if ($sendMailer->status() == 202) {
                return response()->json(['message' => 'User registered successfully. Please your check email for verify data'], 201);
            } else {
                dd($sendMailer);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Registration Error.', 'message' => $e->getMessage()], 500);
        }
    }

    public function resendMailerVerify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation Error.', 'messages' => $validator->errors()], 422);
        }

        $input = $request->all();

        $user = User::where('email', $input['email'])->first();

        $data = [
            'id' => $user->id,
            'hash' => sha1($user->email),
            'expires' => Carbon::now()->addDay()->timestamp,
            'signature' => hash_hmac('sha256', $user->id . sha1($user->email), config('app.key'))
        ];
        $jsonData = json_encode($data);
        $base64Data = base64_encode($jsonData);
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addDay(),
            ['q' => $base64Data]
        );

        $sendMailer = $this->sendMailerConfirmRegistration((isset($input["email"]) ? $input["email"] : ""), $verificationUrl);

        if ($sendMailer->status() == 202) {
            return response()->json(['message' => 'Please your check email for verify data'], 201);
        } else {
            dd($sendMailer);
        }
    }

    public function verifyAtMailer(Request $request)
    {
        $encodedParams = $request->query('q');

        if (!$encodedParams) {
            // return response()->json(['error' => 'Invalid verification link.'], 403);
            return redirect()->to(config('constants.pass_url_') . '/login?' . base64_encode(json_encode(['q' => 'Invalid verification link.', 's' => 403])));
        }

        $jsonData = base64_decode($encodedParams);
        $params = json_decode($jsonData, true);

        if (!isset($params['id'], $params['hash'], $params['expires'], $params['signature'])) {
            // return response()->json(['error' => 'Invalid verification link.'], 403);
            return redirect()->to(config('constants.pass_url_') . '/login?' . base64_encode(json_encode(['q' => 'Invalid verification link.', 's' => 403])));
        }

        $user = User::findOrFail($params['id']);

        if (! hash_equals(sha1($user->email), $params['hash'])) {
            // return response()->json(['error' => 'Email verification link is invalid.'], 403);
            return redirect()->to(config('constants.pass_url_') . '/login?' . base64_encode(json_encode(['q' => 'Email verification link is invalid.', 's' => 403])));
        }

        if (! $request->hasValidSignature()) {
            // return response()->json(['error' => 'Invalid or expired verification link.'], 403);
            return redirect()->to(config('constants.pass_url_') . '/login?' . base64_encode(json_encode(['q' => 'Invalid or expired verification link.', 's' => 403])));
        }

        $calculatedSignature = hash_hmac('sha256', $params['id'] . $params['hash'], config('app.key'));
        if (! hash_equals($calculatedSignature, $params['signature'])) {
            // return response()->json(['error' => 'Invalid signature.'], 403);
            return redirect()->to(config('constants.pass_url_') . '/login?' . base64_encode(json_encode(['q' => 'Invalid signature.', 's' => 403])));
        }

        if (Carbon::now()->timestamp > $params['expires']) {
            // return response()->json(['error' => 'Verification link has expired.'], 403);
            return redirect()->to(config('constants.pass_url_') . '/login?' . base64_encode(json_encode(['q' => 'Verification link has expired.', 's' => 403])));
        }

        // notes baseurl not same its frontend
        if ($user->hasVerifiedEmail()) {
            // return response()->json(['message' => 'Email is already verified.'], 200);
            Auth::login($user);
            return redirect()->to(config('constants.pass_url_') . '/verify?' . base64_encode(json_encode(['q' => $user->createToken('SwapUp')->plainTextToken, 's' => 200])));
            // return redirect(config('constants.pass_url_') . '/login?' . http_build_query(['q' => 'Email is already verified.']));

        }

        $user->markEmailAsVerified();
        Auth::login($user);
        return redirect()->to(config('constants.pass_url_') . '/verify?' . base64_encode(json_encode(['q' => $user->createToken('SwapUp')->plainTextToken, 's' => 200])));
        // return redirect(config('constants.pass_url_') . '/login?' . http_build_query(['q' => 'Email verified successfully.']));
        // return response()->json(['message' => 'Email verified successfully.'], 200);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = User::find(Auth::id());
        $input = $request->all();

        $suburb = $input['suburb'];
        $state = $input['state'];
        $postal_code = $input['postal_code'];
        $url = config("constants.auspost_api_url") . "/shipping/v1/address?suburb=$suburb&state=$state&postcode=$postal_code";
        // $response = Http::withBasicAuth(env('AUSPOST_API_USERNAME'), env('AUSPOST_API_PASSWORD'))->get($url);
        $response = Http::withBasicAuth(config("constants.auspost_api_username"), config("constants.auspost_api_password"))->get($url);

        if ($response->failed()) {
            return Helpers::Response(500, 'An error occurred while validating the suburb.');
        }
        if ($response->json()['found']) {

            $consignData = [
                'address_line_1' => $input['address_line_1'],
                'address_line_2' => $input['address_line_2'] ?? "",
                'city' => $suburb,
                'postal_code' =>  $postal_code,
                'state' => $state,
            ];

            $user->suburb = $input['suburb'];
            $user->address_line_1 = $input['address_line_1'];
            $user->address_line_2 = $input['address_line_2'] ??  "";
            $user->city = $input['city'] ?? $input['suburb'];
            $user->state = $state;
            $user->postal_code = $postal_code;

            if (isset($user)) {
                $consignData['company'] = $input['company'] ?? "";
                $consignData['first_name'] = $input['first_name'];
                $consignData['last_name'] = $input['last_name'];
                $consignData['phone_number'] = $input['phone_number'];
                $user->name = $input['first_name'] . ' ' . $input['last_name'];
                $user->bsb = $input['bsb'];
                $user->suburb = $input["suburb"];
                $user->account_number = $input['account_number'];
                $user->phone_number = $input['phone_number'];
                $user->first_name = $input['first_name'];
                $user->last_name = $input['last_name'];
            }
            $consignResponse = $this->userConsignServices->updateProfile($consignData);
            if (isset($consignResponse['error'])) {
                return Helpers::Response(400, $consignResponse);
            }
            $user->save();
            return Helpers::Response(201, $consignResponse);
        } else {
            return Helpers::Response(400, 'Suburb is invalid.');
        }
    }

    public function checkEditMailer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email,' . Auth::id(),
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation Error.', 'messages' => $validator->errors()], 422);
        }

        $checkDataConsign = $this->userConsignServices->listUser($request->email);

        if (isset($checkDataConsign)) {
            if (isset($checkDataConsign["data"][0])) {
                return response()->json(['error' => 'Validation Error.', 'message' => "Email address is exists"], 500);
            }
        }

        return response()->json([
            'success' => 'Email address is available.'
        ], 200);
    }

    public function generateOTPMailerSend(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation Error.', 'messages' => $validator->errors()], 422);
        }
        // Cache::forget('otp_' . $request->email);

        cache()->forget('otp_' . $request->email);

        $otp = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 5));
        $expiration = time() + (30 * 60);

        $secret = env('APP_KEY');

        $data = $request->email . $expiration . $otp;
        $signature = hash_hmac('sha256', $data, $secret);

        $queryParams = [
            'email' => $request->email,
            'expires' => $expiration,
            'otp' => $otp,
            'signature' => $signature
        ];

        $queryString = http_build_query($queryParams);
        $encodedUrl = base64_encode($queryString);

        $otpLink = "id=" . $encodedUrl;
        $sendMailer = $this->sendMailerConfirmOTP($request->email, $otp, date('i', (30 * 60)));

        if ($sendMailer->status() == 202) {

            // Cache::put('otp_' . $request->email, ['otp' => $otp, 'expires' => $expiration, 'used' => false], 30);
            cache()->put('otp_' . $request->email, ['otp' => $otp, 'expires' => $expiration, 'used' => false], 30);
            return response()->json([
                'success' => 'OTP has been generated and sent to your email.',
                'otp_link' => $otpLink,
                'otp' => $otp
            ], 200);
        } else {
            return response()->json(['error' => 'Failed to send OTP email.'], 500);
        }
    }

    public function validateOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'otp' => 'required|string|max:5',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation Error.', 'messages' => $validator->errors()], 422);
        }

        $input = $request->all();

        $user = User::find(Auth::id());

        $encodeDate = $request->query('id');
        $decodeData = base64_decode($encodeDate);

        parse_str($decodeData, $params);

        $email = $params['email'] ?? null;
        $otp = $params['otp'] ?? null;
        $expiration = $params['expires'] ?? null;
        $signature = $params['signature'] ?? null;

        if (!$email || !$otp || !$expiration || !$signature) {
            return response()->json([
                'error' => 'Invalid or missing data.'
            ], 400);
        }

        if (time() > $expiration) {
            return response()->json([
                'error' => 'OTP has expired. Please regenerate a new OTP.'
            ], 400);
        }

        $secret = env('APP_KEY');
        $data = $email . $expiration . $otp;
        $calculatedSignature = hash_hmac('sha256', $data, $secret);

        if (!hash_equals($calculatedSignature, $signature)) {
            return response()->json([
                'error' => 'Invalid OTP signature.',
                'email' => $email,
                'otp' => $otp,
                'expiration' => $expiration,
            ], 403);
        }

        // $cachedOtpData = Cache::get('otp_' . $email);
        $cachedOtpData = cache()->get('otp_' . $email);


        if (!$cachedOtpData || !isset($cachedOtpData['otp'])) {
            return response()->json(['error' => 'OTP not found in cache.'], 204);
        }

        if ($cachedOtpData['otp'] !== $input['otp']) {
            return response()->json(['error' => 'Invalid OTP.'], 400);
        }
        if (time() > $cachedOtpData['expires']) {
            return response()->json(['error' => 'OTP has expired.'], 400);
        }

        if ($cachedOtpData['used']) {
            return response()->json(['error' => 'OTP has already been used.'], 400);
        }

        // Cache::put('otp_' . $email, ['otp' => $input['otp'], 'expires' => $expiration, 'used' => true], 30);
        cache()->put('otp_' . $email, ['otp' => $input['otp'], 'expires' => $expiration, 'used' => true], 30);
        $consignResponse = $this->userConsignServices->updateProfile(['email' => $email]);
        if ($consignResponse == null) {
            $user->email = $email;
            $user->save();

            return response()->json([
                'success' => 'Success Update Email Address',
            ], 200);
        }
    }

    public function cashout(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $input = $request->all();
            $user = Auth::user()->consign_id;

            $memo = "$" . (isset($input['amount']) ? number_format($input['amount'] / 100, 2)  : 0) . " " . "cash out request to " . Auth::user()->bsb . " " . Auth::user()->account_number;
            $consignData = [
                "account" => $user,
                "amount" => -$input['amount'],
                "location" => null,
                "memo" => $memo,
            ];

            $consignTitle = [
                "title" => $memo
            ];

            $data = $this->userConsignServices->getUser($user);

            if ($data["balance"] < $input['amount']) {
                return response()->json([
                    "error" => "Validation failed cashout",
                    "message" => 'You have a balance $' . number_format((isset($data['balance']) ? $data['balance'] : 0) / 100, 2) . ' cannot cashout your balance'
                ], 500);
            }

            $consignResponse = $this->userConsignServices->cashout($consignData);

            $limitdata = [
                "limit" => 2147483648,
                "offset" => 0,
                "where" => [
                    [
                        "operator" => "eq",
                        "value" => Auth::user()->consign_id,
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

            $cashout->user_id = Auth::id();
            $cashout->cashout_amount = $input['amount'];
            $cashout->before_balance = $data['balance'];
            $cashout->after_balance = $datanew['balance'];
            $cashout->request_date = Carbon::now();
            $cashout->receipt_number = null;
            $cashout->mark_paid = null;
            $cashout->notes = 'Cash Out';

            $cashout->save();

            DB::commit();

            $sendMailer = $this->sendMailerBeforeCashout(Auth::user()->email, Auth::user()->first_name);

            if ($sendMailer->status() == 202) {
                return Helpers::Response(201, [
                    "succcess" => "Send Confirm Payment Cashout Success",
                ]);
            } else {
                dd($sendMailer);
            }
        } catch (\Exception $e) {
            DB::rollback();
            return Helpers::Response(400, $e->getMessage());
        }
    }

    public function directLinkMailer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation Error.', 'messages' => $validator->errors()], 422);
        }

        $input = $request->all();

        $usermailer = User::where('email', $input['email'])->first();

        if (!isset($usermailer)) {
            return response()->json(['error' => 'Email not found'], 500);
        }

        if (!isset($usermailer->email_verified_at)) {
            return response()->json(['error' => 'Email not verified'], 500);
        }

        $expiration = time() + (30 * 60);

        $secret = env('APP_KEY');

        $data = (isset($input['email']) ? $input['email'] : "") . ($expiration);
        $signature = hash_hmac('sha256', $data, $secret);

        $queryParams = [
            "email" => (isset($input["email"]) ? $input["email"] : ""),
            "times" => $expiration,
            'signature' => $signature
        ];

        $querystring = http_build_query($queryParams);
        $encodeurl = base64_encode($querystring);

        // $newurlgenerate = env("ENV_FORGOT_PASS_URL_", "https://dev.swapup.online") . "/forgot-password?" . "id=" . $encodeurl;

        $newurlgenerate = config("constants.pass_url_") . "/forgot-password?" . "id=" . $encodeurl;

        $sendMailer = $this->sendMailerSeller((isset($input["email"]) ? $input["email"] : ""), $newurlgenerate);

        if ($sendMailer->status() == 202) {
            return response()->json([
                "succcess" => "Send Forgot Password Success",
                "urlgenerate" => $newurlgenerate
            ]);
        } else {
            dd($sendMailer);
        }
    }

    protected function sendMailerSeller($seller_mail, $newurl)
    {
        $templateMail = config("constants.sendgrid_template2_");
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
                    "dynamic_template_data" => [
                        "url_generate" => $newurl
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

    protected function sendMailerConfirmOTP($seller_mail, $code, $timer)
    {
        $templateMail = config("constants.sendgrid_template3_");
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
                    "dynamic_template_data" => [
                        "otp" => $code,
                        "minutes" => (int)$timer
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

    protected function sendMailerBeforeCashout($seller_mail, $name)
    {
        $templateMail = config("constants.sendgrid_template_4_");
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


    protected function sendMailerConfirmRegistration($seller_mail, $url_verify)
    {
        $templateMail = config("constants.sendgrid_template_6_");
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
                    "dynamic_template_data" => [
                        "url_verify" => $url_verify
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

    protected function sendMailerSetPassword($seller_mail, $url_link)
    {
        $templateMail = config("constants.sendgrid_template_swapupkit_register_manual");
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
                    "dynamic_template_data" => [
                        "url_link" => $url_link
                    ]
                ]
            ],
            "template_id" => $templateMail
        ];

        $SendGridResponse = $this->sendGridServices->sendMailByTemplate($sendGridData);

        return $SendGridResponse;
    }

    public function updatePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required',
            'c_password' => 'required|same:password',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation Error.', 'messages' => $validator->errors()], 422);
        }

        $input = $request->all();

        $urlC = parse_url($request->query('urlgenerate'));
        parse_str($urlC['query'], $params);

        $decodeURI = base64_decode($params['id']);
        parse_str($decodeURI, $params2);

        if (time() > $params2['times']) {
            return response()->json([
                'error' => "The password reset link has expired."
            ], 412);
        }

        $secret = env('APP_KEY');
        $data = (isset($params2['email']) ? $params2['email'] : "") . (isset($params2['times']) ? $params2['times'] : "");
        $signature = (isset($params2['signature']) ? $params2['signature'] : "");

        $calculateSignature = hash_hmac('sha256', $data, $secret);

        if (!hash_equals($calculateSignature, $signature)) {
            return response()->json([
                'error' => 'Invalid signature. The password reset link is invalid'
            ], 403);
        }


        try {
            $user = User::where("email", $params2['email'])->firstOrFail();
            $user->password = bcrypt($input['password']);
            $user->save();
            return response()->json([
                "succcess" => "Please log in again to enter the swap up dashboard",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                "error" => $e->getMessage()
            ]);
        }
    }

    public function recentActivity(Request $request)
    {

        $limit = filter_var($request->limit, FILTER_VALIDATE_INT);
        $limit = ($limit !== false && $limit > 0) ? $limit : 2147483648;

        try {
            $data = [
                "limit" => $limit,
                "offset" => 0,
                "where" => [
                    [
                        "operator" => "eq",
                        "value" => Auth::user()->consign_id,
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

            $checkData = $this->userConsignServices->checkrecentactivity($data);
            $responseData = $checkData['data'] ?? [];
            $resources = RecentActivityCollection::collection(collect($responseData));

            return response()->json(["data" => $resources, "total" => count($resources)], 202);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function registerWithAdmin(Request $request): JsonResponse
    {
        $input = $request->all();

        $shopifydata = [
            "customer" => [
                'email' => $input['email'],
                'first_name' => $input['first_name'],
                'last_name' => $input['last_name']
            ]
        ];

        // Data untuk ConsignCloud API (tanpa password)
        $consignData = [
            'address_line_1' => $input['address_line_1'] ?? "",
            'address_line_2' => $input['address_line_2'] ?? "",
            'city' => $input['city'] ?? "",
            'company' => $input['company'] ?? "",
            'email' => $input['email'],
            'email_notifications_enabled' => false,
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'phone_number' => $input['phone_number'],
            'postal_code' => $input['postal_code'] ?? "",
            'state' => $input['state'] ?? "",
        ];

        try {
            $checkDataShopify = $this->shopifyServices->searchCustomers("email:" . (isset($input["email"]) ? $input["email"] : null));
            if (isset($checkDataShopify['customers'][0]['email']) === isset($input['email'])) {
                $checkDataConsign = $this->userConsignServices->listUser((isset($input["email"]) ? $input["email"] : null));

                if (isset($checkDataConsign)) {
                    if (isset($checkDataConsign["data"][0]["email"]) === isset($input["email"])) {
                        return response()->json(['error' => 'Registration Error.', 'message' => "Consign Data is exists, please use for this other email"], 500);
                    }
                }
                $consignResponse = $this->userConsignServices->register($consignData);
            } else {

                $shopifyResponse = $this->shopifyServices->createSellerIDShopify($shopifydata);
                if (isset($shopifyResponse)) {
                    if (isset($shopifyResponse->original['status']) && $shopifyResponse->original['status'] == 'error') {
                        return response()->json(['error' => 'Registration Error.', 'message' => "Shopify Data is exists, please use for this other email"], 500);
                    }
                }
                $checkDataConsign = $this->userConsignServices->listUser((isset($input["email"]) ? $input["email"] : null));
                if (isset($checkDataConsign)) {
                    if (isset($checkDataConsign["data"][0]["email"]) === isset($input["email"])) {
                        return response()->json(['error' => 'Registration Error.', 'message' => "Consign Data is exists, please use for this other email"], 500);
                    }
                }
                $consignResponse = $this->userConsignServices->register($consignData);
            }

            $consignId = isset($consignResponse['id']) ? $consignResponse['id'] : null;
            $shopify_seller_id = (isset($checkDataShopify['customers'][0]['email']) === isset($input["email"])) ? $checkDataShopify['customers'][0]['id'] : $shopifyResponse['customer']['id'];
            $randomPassword = bcrypt(Str::random(10));
            User::create([
                'consign_id' => $consignId,
                'shopify_seller_id' => $shopify_seller_id,
                'name' =>  $input['first_name'] . ' ' . $input['last_name'],
                'email' => $input['email'],
                'bsb' =>  $input['bsb'],
                'account_number' => $input['account_number'],
                'password' => $randomPassword,
                'phone_number' => $input['phone_number'],
                'first_name' => $input['first_name'],
                'last_name' => $input['last_name']
            ]);

            $sendMailer = $this->sendMailerSetPassword($input["email"]);

            if ($sendMailer->status() == 202) {
                return response()->json(['message' => 'User registered successfully. Please check email for verify data'], 201);
            } else {
                dd($sendMailer);
            }

            return response()->json(['message' => 'User registered successfully. Please check email for verify data'], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Registration Error.', 'message' => $e->getMessage()], 500);
        }
    }

    public function searchUsers(Request $request)
    {
        try {
            $query = User::where('role', '!=', 'admin');

            if ($request->has('keyword')) {
                $keyword = $request->input('keyword');
                $query->where(function ($q) use ($keyword) {
                    $q->where('first_name', 'like', '%' . $keyword . '%')
                        ->orWhere('last_name', 'like', '%' . $keyword . '%')
                        ->orWhere('email', 'like', '%' . $keyword . '%');
                });
            }
            $perPage = $request->input('perPage', 20);
            $page = $request->input('page', 1);
            $users = $query->paginate($perPage, ['*'], 'page', $page);

            return response()->json($users, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
