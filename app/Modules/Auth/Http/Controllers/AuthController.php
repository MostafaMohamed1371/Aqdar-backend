<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Auth\Http\Requests\ForgotPasswordRequest;
use App\Modules\Auth\Http\Requests\LoginRequest;
use App\Modules\Auth\Http\Requests\RegisterDeviceRequest;
use App\Modules\Auth\Http\Requests\RegisterRequest;
use App\Modules\Auth\Http\Requests\ResetPasswordRequest;
use App\Modules\Auth\Http\Requests\SendOtpRequest;
use App\Modules\Auth\Http\Requests\UpdateMeRequest;
use App\Modules\Auth\Http\Requests\VerifyOtpRequest;
use App\Modules\Auth\Http\Resources\AuthResource;
use App\Modules\Auth\Services\AuthService;
use App\Modules\User\Http\Resources\UserResource;
use App\Modules\User\Models\User;
use App\Modules\User\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends BaseController
{
    public function __construct(
        private AuthService $authService,
        private UserService $userService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $auth = $this->authService->register($request->validated());

        return (new AuthResource($auth))
            ->response()
            ->setStatusCode(201);
    }

    public function login(LoginRequest $request): AuthResource
    {
        $auth = $this->authService->login(
            $request->validated('email'),
            $request->validated('password'),
        );

        return new AuthResource($auth);
    }

    public function sendOtp(SendOtpRequest $request): JsonResponse
    {
        $result = $this->authService->sendOtp(
            $request->validated('phone'),
            $request->safe()->only(['consent', 'consent_privacy', 'consent_terms', 'locale']),
        );

        return response()->json([
            'message' => __('aqdar.auth.otp_sent'),
            'resend_after' => $result['resend_after'],
            'consent_required' => $result['consent_required'],
        ]);
    }

    public function verifyOtp(VerifyOtpRequest $request): AuthResource
    {
        $auth = $this->authService->verifyOtp(
            $request->validated('phone'),
            $request->validated('code'),
            $request->safe()->only(['consent', 'consent_privacy', 'consent_terms', 'locale']),
        );

        return new AuthResource($auth);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authService->logout($user);

        return response()->json([
            'message' => __('structer.logged_out'),
        ])->header('Content-Language', app()->getLocale());
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function updateMe(UpdateMeRequest $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        return new UserResource($this->userService->updateProfile($user, $request->validated()));
    }

    public function registerDevice(RegisterDeviceRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $device = $this->userService->registerDevice($user, $request->validated());

        return response()->json([
            'data' => [
                'id' => $device->id,
                'device_uuid' => $device->device_uuid,
                'platform' => $device->platform,
            ],
        ], 201);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $message = $this->authService->forgotPassword($request->validated('email'));

        return response()->json(['message' => $message]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $message = $this->authService->resetPassword($request->validated());

        return response()->json(['message' => $message]);
    }
}
