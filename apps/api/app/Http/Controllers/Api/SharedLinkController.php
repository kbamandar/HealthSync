<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;

class SharedLinkController extends Controller
{
    public function shareRecord(Request $request)
    {
        return ApiResponse::success();
    }

    public function shareSummary(Request $request)
    {
        return ApiResponse::success();
    }

    public function index(Request $request)
    {
        return ApiResponse::success([]);
    }

    public function destroy(Request $request, string $id)
    {
        return ApiResponse::success();
    }

    public function publicAccess(Request $request, string $token)
    {
        return ApiResponse::success();
    }
}
