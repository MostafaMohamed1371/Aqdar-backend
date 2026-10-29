<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Auth\Http\Requests\ForgotPasswordRequest;
use App\Modules\Auth\Http\Requests\LoginRequest;
use App\Modules\Auth\Http\Requests\ResetPasswordRequest;
use App\Modules\Auth\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuthWebController extends BaseController
{
    public function __construct(private AuthService $authService) {}

    public function login(LoginRequest $request): RedirectResponse
    {
        $this->authService->webLogin(
            $request->validated('email'),
            $request->validated('password'),
            $request->boolean('remember'),
        );

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->authService->webLogout();

        return redirect()->route('dashboard');
    }

    public function showForgotPassword(): View
    {
        return view('auth::forgot-password');
    }

    public function sendResetLink(ForgotPasswordRequest $request): RedirectResponse
    {
        $this->authService->forgotPassword($request->validated('email'));

        return back()->with('status', __('structer.password_reset_sent'));
    }

    public function showResetPassword(Request $request, string $token): View
    {
        return view('auth::reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): RedirectResponse
    {
        $this->authService->resetPassword($request->validated());

        return redirect()->route('dashboard')->with('status', __('structer.password_reset_success'));
    }
}
