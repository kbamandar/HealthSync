<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function sendOtp(Request $request)
    {
        return ApiResponse::success();
    }

    public function verifyOtp(Request $request)
    {
        return ApiResponse::success();
    }

    public function refresh(Request $request)
    {
        return ApiResponse::success();
    }

    public function logout(Request $request)
    {
        return ApiResponse::success();
    }
}
