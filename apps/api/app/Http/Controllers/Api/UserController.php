<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function me(Request $request)
    {
        return ApiResponse::success();
    }

    public function updateMe(Request $request)
    {
        return ApiResponse::success();
    }

    public function uploadPhoto(Request $request)
    {
        return ApiResponse::success();
    }

    public function registerDevice(Request $request)
    {
        return ApiResponse::success();
    }
}
