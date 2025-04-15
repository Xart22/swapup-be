<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class AdminConsignServices
{
    private $baseUrl;
    private $email;
    private $password;

    public function __construct()
    {
        $this->baseUrl = 'https://api.consigncloud.com/api/v1';

        // TODO MOVE TO ENV
        // $this->email = 'habibie@harakirimail.com';
        // $this->password = 'Fajarwisnu99';
        // $this->email = 'swapup.au@gmail.com';
        // $this->password = 'anakkugator31514';
        $this->email = 'infodigyta@gmail.com';
        $this->password = '123123123!@#aA';
    }

    public function login()
    {
        $response = Http::post("{$this->baseUrl}/auth/login/organization", [
            'email' => $this->email,
            'password' => $this->password,
        ]);

        $sessionId = $response->json()['session_id'] ?? $response->json()['login_token'];
        // $tokenId = $response->json()['login_token'];

        // Cache::put('token_consign', $tokenId, 3600);
        Cache::put('session_id', $sessionId, 3600); // Menyimpan session_id selama 1 jam

        return $sessionId;
    }

    public function getSessionId()
    {
        return Cache::get('session_id', function () {
            return $this->login();
        });
    }
}
