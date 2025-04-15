<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class UserConsignServices
{
    private $baseUrl;
    private $baseUrl2;


    public function __construct()
    {
        $this->baseUrl = 'https://api.consigncloud.com/api/v1';
        $this->baseUrl2 = 'https://api.consigncloud.com';
    }

    public function register($data)
    {

        try {
            $sessionId = Cache::get('session_id');

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $sessionId,
            ])->post("{$this->baseUrl}/accounts", $data);

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
        // throw new \Exception('Registration failed');
    }

    public function updateProfile($data)
    {
        try {
            $sessionId = Cache::get('session_id');
            $consign_id = Auth::user()->consign_id;

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $sessionId,
            ])->patch("{$this->baseUrl}/accounts/{$consign_id}", $data);
            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    public function getUser($data)
    {
        $sessionId = Cache::get('session_id');

        // Mengirim request registrasi dengan token Bearer
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $sessionId,
        ])->get("{$this->baseUrl}/accounts/{$data}");

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Get My User failed');
    }

    public function listUser($data)
    {
        $sessionId = Cache::get('session_id');

        // Mengirim request registrasi dengan token Bearer
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $sessionId,
        ])->get("{$this->baseUrl}/accounts", [
            "email" => $data
        ]);

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Get List User failed');
    }

    public function listItems($consign_id, $limit)
    {
        $sessionId = Cache::get('session_id');
        // Mengirim request registrasi dengan token Bearer
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $sessionId,
        ])->get("{$this->baseUrl}/items?account={$consign_id}&limit={$limit}");

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Get List Items Failed');
    }

    public function listItemsByID($item_id)
    {
        $sessionId = Cache::get('session_id');
        // Mengirim request registrasi dengan token Bearer
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $sessionId,
        ])->get("{$this->baseUrl}/items/{$item_id}");

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Get List Items By ID Failed');
    }

    public function newlistItems($data)
    {
        try {
            $sessionId = Cache::get('session_id');
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $sessionId,
                'Content-Type' => 'application/json'
            ])->post("{$this->baseUrl2}/search/item?count=true", $data);

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



    public function listItemsbystatuschange($item)
    {
        try {
            $sessionId = Cache::get('session_id');
            // $consign_id = Auth::user()->consign_id;
            // Mengirim request registrasi dengan token Bearer
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $sessionId,
            ])->get("{$this->baseUrl}/item-status-changes?item={$item}");

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

    public function shelfbyid($shelfid)
    {
        try {
            $sessionId = Cache::get('session_id');
            // $consign_id = Auth::user()->consign_id;
            // Mengirim request registrasi dengan token Bearer
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $sessionId,
            ])->get("{$this->baseUrl}/shelves/{$shelfid}");

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

    public function locationbyid($locationid)
    {
        try {
            $sessionId = Cache::get('session_id');
            // $consign_id = Auth::user()->consign_id;
            // Mengirim request registrasi dengan token Bearer
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $sessionId,
            ])->get("{$this->baseUrl}/locations/{$locationid}");

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

    public function cashout($data)
    {
        $sessionId = Cache::get('session_id');

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $sessionId,
        ])->post("{$this->baseUrl}/balance-entries", $data);

        if ($response->successful()) {
            return $response;
        }

        throw new \Exception('Cash Out Failed');
    }

    public function checkrecentactivity($data)
    {
        try {
            $sessionId = Cache::get('session_id');
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $sessionId,
                'Content-Type' => 'application/json'
            ])->post("{$this->baseUrl2}/search/balance_entry?count=true", $data);

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

    public function checkstatuschange($data)
    {
        try {
            $sessionId = Cache::get('session_id');
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $sessionId,
                'Content-Type' => 'application/json'
            ])->post("{$this->baseUrl2}/search/status_change?count=true", $data);

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

    public function newcashout($userid, $data)
    {
        try {
            $sessionId = Cache::get('session_id');
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $sessionId,
                'Content-Type' => 'application/json'
            ])->post("{$this->baseUrl2}/contact/{$userid}/change_balance", $data);

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

    public function updatedatabalance($id, $data)
    {
        try {
            $sessionId = Cache::get('session_id');
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $sessionId,
                'Content-Type' => 'application/json'
            ])->put("{$this->baseUrl2}/balance_entry/{$id}", $data);

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

    public function updatedItemsByID($item_id, $payload)
    {
        try {
            $sessionId = Cache::get('session_id');
            // Mengirim request registrasi dengan token Bearer
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $sessionId,
            ])->patch("{$this->baseUrl}/items/{$item_id}", $payload);

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


    public function createGiftCard($data)
    {
        try {
            $sessionId = Cache::get('session_id');
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $sessionId,
            ])->post("{$this->baseUrl}/gift-cards", $data);
            dd($response->json());
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
