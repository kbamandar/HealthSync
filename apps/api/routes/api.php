<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ComplianceController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\FamilyMemberController;
use App\Http\Controllers\Api\HealthRecordController;
use App\Http\Controllers\Internal\RecordFileTransferController;
use App\Http\Controllers\Api\ReminderController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SharedLinkController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VitalReadingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // AUTH (public — these are how you obtain/refresh/drop credentials)
    Route::post('auth/otp/send', [AuthController::class, 'sendOtp']);
    Route::post('auth/otp/verify', [AuthController::class, 'verifyOtp']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
});

Route::prefix('v1')->middleware('auth.jwt')->group(function () {
    // USERS
    Route::get('users/me', [UserController::class, 'me']);
    Route::put('users/me', [UserController::class, 'updateMe']);
    Route::post('users/me/photo', [UserController::class, 'uploadPhoto']);
    Route::post('devices', [UserController::class, 'registerDevice']);

    // FAMILY
    Route::get('family/members', [FamilyMemberController::class, 'index']);
    Route::post('family/members', [FamilyMemberController::class, 'store']);
    Route::put('family/members/{id}', [FamilyMemberController::class, 'update']);
    Route::delete('family/members/{id}', [FamilyMemberController::class, 'destroy']);
    Route::post('family/invite/accept', [FamilyMemberController::class, 'acceptInvite']);
    Route::post('family/invite/decline', [FamilyMemberController::class, 'declineInvite']);

    // RECORDS
    Route::get('records', [HealthRecordController::class, 'index']);
    Route::post('records', [HealthRecordController::class, 'store']);
    Route::get('records/recycle-bin', [HealthRecordController::class, 'recycleBin']);
    Route::get('records/{id}', [HealthRecordController::class, 'show']);
    Route::put('records/{id}', [HealthRecordController::class, 'update']);
    Route::delete('records/{id}', [HealthRecordController::class, 'destroy']);
    Route::post('records/{id}/upload-url', [HealthRecordController::class, 'uploadUrl']);
    Route::post('records/{id}/files', [HealthRecordController::class, 'registerFile']);
    Route::post('records/{id}/restore', [HealthRecordController::class, 'restore']);

    // VITALS
    Route::get('vitals', [VitalReadingController::class, 'index']);
    Route::post('vitals', [VitalReadingController::class, 'store']);
    Route::delete('vitals/{id}', [VitalReadingController::class, 'destroy']);

    // REMINDERS
    Route::get('reminders', [ReminderController::class, 'index']);
    Route::post('reminders', [ReminderController::class, 'store']);
    Route::put('reminders/{id}', [ReminderController::class, 'update']);
    Route::delete('reminders/{id}', [ReminderController::class, 'destroy']);

    // DOCTORS
    Route::get('doctors', [DoctorController::class, 'index']);
    Route::post('doctors', [DoctorController::class, 'store']);
    Route::put('doctors/{id}', [DoctorController::class, 'update']);
    Route::delete('doctors/{id}', [DoctorController::class, 'destroy']);

    // SHARING
    Route::post('share/record', [SharedLinkController::class, 'shareRecord']);
    Route::post('share/summary', [SharedLinkController::class, 'shareSummary']);
    Route::get('share', [SharedLinkController::class, 'index']);
    Route::delete('share/{id}', [SharedLinkController::class, 'destroy']);

    // SEARCH & TIMELINE
    Route::get('search', [SearchController::class, 'search']);
    Route::get('timeline', [SearchController::class, 'timeline']);

    // DASHBOARD
    Route::get('dashboard', [DashboardController::class, 'index']);
    Route::get('members/{id}/health-score', [DashboardController::class, 'healthScore']);

    // DATA & COMPLIANCE
    Route::post('data-export', [ComplianceController::class, 'requestDataExport']);
    Route::post('account/delete', [ComplianceController::class, 'requestAccountDeletion']);
    Route::get('audit-log', [ComplianceController::class, 'auditLog']);
});

// Public: no auth required
Route::get('public/share/{token}', [SharedLinkController::class, 'publicAccess']);

// Stand-ins for S3 presigned URLs (see RecordStorageService) — gated by the
// `signed` middleware alone, exactly as a presigned S3 URL is gated by its
// own signature and nothing else.
Route::middleware('signed')->group(function () {
    Route::put('internal/records/upload/{key}', [RecordFileTransferController::class, 'upload'])
        ->name('internal.records.upload');
    Route::get('internal/records/download/{key}', [RecordFileTransferController::class, 'download'])
        ->name('internal.records.download');
});
