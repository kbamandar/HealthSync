<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;

class DoctorController extends Controller
{
    public function index(Request $request)
    {
        return ApiResponse::success([]);
    }

    public function store(Request $request)
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
}
