<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Services\AdminConsignServices;

class SessionAdminConsign
{
    protected $consignServices;

    public function __construct(AdminConsignServices $consignServices)
    {
        $this->consignServices = $consignServices;
    }

    public function handle($request, Closure $next)
    {
        $sessionId = Cache::get('session_id');
        if (!$sessionId || $this->isSessionInvalid($sessionId)) {
            $sessionId = $this->consignServices->login();
        }

        $request->headers->set('Authorization', 'Bearer ' . $sessionId);
        return $next($request);
    }

    private function isSessionInvalid($sessionId)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $sessionId
            ])->get('https://api.consigncloud.com/api/v1/auth/me');

            return $response->status() == 401;
        } catch (\Throwable $th) {
            return true;
        }
    }
}
