<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;

class ComplianceController extends Controller
{
    public function requestDataExport(Request $request)
    {
        return ApiResponse::success();
    }

    public function requestAccountDeletion(Request $request)
    {
        return ApiResponse::success();
    }

    public function auditLog(Request $request)
    {
        return ApiResponse::success([]);
    }
}
