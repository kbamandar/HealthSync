<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function me(Request $request)
    {
        return ApiResponse::success($this->serialize($request->user()));
    }

    public function updateMe(Request $request)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'date_of_birth' => ['sometimes', 'date'],
            'gender' => ['sometimes', 'string', 'max:20'],
            'blood_group' => ['sometimes', 'string', 'max:10'],
            'profile_photo' => ['sometimes', 'string', 'max:255'],
            'emergency_contact_name' => ['sometimes', 'string', 'max:255'],
            'emergency_contact_mobile' => ['sometimes', 'string', 'max:15'],
        ]);

        $user = $request->user();
        $user->update($data);

        return ApiResponse::success($this->serialize($user));
    }

    public function uploadPhoto(Request $request)
    {
        $request->validate([
            'mime_type' => ['required', 'in:image/jpeg,image/png'],
        ]);

        $extension = $request->mime_type === 'image/png' ? 'png' : 'jpg';
        $key = 'profile-photos/'.Str::uuid().'.'.$extension;

        $upload = Storage::disk('s3')->temporaryUploadUrl(
            $key,
            now()->addMinutes(15),
            ['ContentType' => $request->mime_type],
        );

        return ApiResponse::success([
            'upload_url' => $upload['url'],
            'headers' => $upload['headers'],
            's3_key' => $key,
        ]);
    }

    public function registerDevice(Request $request)
    {
        return ApiResponse::success();
    }

    private function serialize($user): array
    {
        return [
            'id' => $user->id,
            'mobile' => $user->mobile,
            'email' => $user->email,
            'name' => $user->name,
            'date_of_birth' => $user->date_of_birth?->toDateString(),
            'gender' => $user->gender,
            'blood_group' => $user->blood_group,
            'profile_photo' => $user->profile_photo,
            'emergency_contact_name' => $user->emergency_contact_name,
            'emergency_contact_mobile' => $user->emergency_contact_mobile,
            'profile_complete' => $user->name !== null,
        ];
    }
}
