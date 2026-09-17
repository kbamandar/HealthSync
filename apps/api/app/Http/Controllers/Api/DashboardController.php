<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        return ApiResponse::success();
    }

    public function healthScore(Request $request, string $memberId)
    {
        return ApiResponse::success();
    }
}
