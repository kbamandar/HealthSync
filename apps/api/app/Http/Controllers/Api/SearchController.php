<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        return ApiResponse::success([]);
    }

    public function timeline(Request $request)
    {
        return ApiResponse::success([]);
    }
}
