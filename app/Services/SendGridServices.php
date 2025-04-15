<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SendGridServices
{
    private $baseUrl;
    protected $token;

    public function __construct()
    {
        $this->baseUrl = config("constants.sendgrid_base");
        $this->token = config("constants.sendgrid_api_key");
    }

    public function sendMailByTemplate($data)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/json'
            ])->post("{$this->baseUrl}/v3/mail/send", $data);

            if ($response->successful()) {
                return response()->json([
                    'status' => 'success',
                    'data' => $response->json()
                ], $response->status());
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => $response->body()
                ], $response->status());
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
