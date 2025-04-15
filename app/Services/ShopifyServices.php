<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ShopifyServices
{
    private $baseUrl;
    protected $token;

    public function __construct()
    {

        $this->baseUrl = config("constants.shopify_base");
        $this->token = config("constants.shopify_access_token");
    }

    public function listCard()
    {
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->token,
        ])->get("{$this->baseUrl}/gift_cards.json", [
            "status" => "enabled"
        ]);

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Get List Gift Card Failed');
    }

    public function searchCard($query)
    {
        $url = "{$this->baseUrl}/gift_cards/search.json?" . http_build_query(["query" => $query, "order" => "default id DESC"]);

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->token,
        ])->get($url);

        if ($response->successful()) {
            return $response->json();
        }
        throw new \Exception('Get Search for Gift Card Failed');
    }

    public function createCard($payload)
    {

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->token,
                'Content-Type' => 'application/json'
            ])->post("{$this->baseUrl}/gift_cards.json", $payload);

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

    public function searchCustomers($email)
    {
        $url = "{$this->baseUrl}/customers/search.json?" . http_build_query(["query" => $email]);

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->token,
        ])->get($url);

        if ($response->successful()) {
            return $response->json();
        }
        throw new \Exception('Get Search for Customers Failed');
    }

    public function createSellerIDShopify($payload)
    {

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->token,
                'Content-Type' => 'application/json'
            ])->post("{$this->baseUrl}/customers.json", $payload);

            if ($response->successful()) {
                return $response->json();
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
