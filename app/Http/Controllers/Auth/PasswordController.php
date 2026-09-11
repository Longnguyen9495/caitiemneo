<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\SessionRevoker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    public function __construct(private SessionRevoker $sessionRevoker) {}

    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Đổi mật khẩu thường là phản ứng khi nghi ngờ bị lộ, nên mọi phiên
        // khác phải bị cắt ngay; giữ lại đúng phiên đang thao tác.
        $this->sessionRevoker->revokeFor($request->user(), $request->session()->getId());

        return back()->with('status', 'password-updated');
    }
}
