<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use DB;

/**
 *
 */
class Helpers
{
    private static function setMessage($status)
    {
        switch ($status) {
            case 200:
                return "OK - The request was successful";
                break;
            case 201:
                return "Created - The request was successful and a resource was created.";
                break;
            case 204:
                return "No Content - The request was successful but there is no representation to return (i.e. the response is empty)";
                break;
            case 400:
                return "Bad Request - The request could not be understood or was missing required parameters";
                break;
            case 401:
                return "Unauthorized - Authentication failed or user doesn't have permissions for requested operation";
                break;
            case 403:
                return "Forbidden - Access denied";
                break;
            case 404:
                return "Not Found - Resource was not found";
                break;
            case 405:
                return "Method Not Allowed - Requested method is not supported for resource";
                break;
            case 422:
                return "Unprocessable Entity - Unable to process the contained instructions";
                break;
            case 500:
                return "Internal Server Error - Something went wrong within database proccess. Please report to the administrator";
                break;
            default:
                return "Unknown condition";
                break;
        };
    }
    public static function Response($status, $data)
    {
        $error = ($status == 200 || $status == 201 || $status == 204) ? 'false' : 'true';
        $message = static::setMessage($status);
        return response()->json(['error' => $error, 'code' => $status, 'message' => $message, 'data' => $data], $status);
    }

    public static function maskString($input)
    {
        $lastFour = substr($input, -4);
        $length = strlen($input);
        $maskedPart = str_repeat('*', $length - 4);
        return $maskedPart . $lastFour;
    }

    public static function maskString5characters($input)
    {
        $lastFour = substr($input, -4);
        return "**" . $lastFour;
    }
}
