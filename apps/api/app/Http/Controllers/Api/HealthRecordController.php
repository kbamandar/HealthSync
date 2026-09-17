<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;

class HealthRecordController extends Controller
{
    public function index(Request $request)
    {
        return ApiResponse::success([]);
    }

    public function store(Request $request)
    {
        return ApiResponse::success();
    }

    public function show(Request $request, string $id)
    {
        return ApiResponse::success();
    }

    public function update(Request $request, string $id)
    {
        return ApiResponse::success();
    }

    public function destroy(Request $request, string $id)
    {
        return ApiResponse::success();
    }

    public function uploadUrl(Request $request, string $id)
    {
        return ApiResponse::success();
    }

    public function registerFile(Request $request, string $id)
    {
        return ApiResponse::success();
    }

    public function recycleBin(Request $request)
    {
        return ApiResponse::success([]);
    }

    public function restore(Request $request, string $id)
    {
        return ApiResponse::success();
    }
}
