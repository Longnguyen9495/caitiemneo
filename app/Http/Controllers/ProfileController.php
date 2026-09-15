<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\Auth\SessionRevoker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(private SessionRevoker $sessionRevoker) {}

    /** Kết thúc mọi phiên khác của chính tài khoản đang đăng nhập. */
    public function revokeOtherSessions(Request $request): RedirectResponse
    {
        $ended = $this->sessionRevoker->revokeFor($request->user(), $request->session()->getId());

        return Redirect::route('profile.edit')->with(
            'status',
            $ended > 0
                ? "Đã kết thúc {$ended} phiên đăng nhập khác."
                : 'Không có phiên nào khác đang mở.',
        );
    }

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            // Người dùng tự thấy được các phiên đang mở của mình: một thiết bị
            // lạ là thứ nên phát hiện được ngay, không phải sau khi đã muộn.
            'sessions' => $this->sessionRevoker->listFor($request->user(), $request->session()->getId()),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->safe()->except('avatar');

        if ($request->hasFile('avatar')) {
            $newAvatarPath = $request->file('avatar')->store('avatars', 'public');
            $oldAvatarPath = $user->avatar_path;
            $data['avatar_path'] = $newAvatarPath;
        }

        $user->fill($data);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if (isset($oldAvatarPath)) {
            Storage::disk('public')->delete($oldAvatarPath);
        }

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // Xóa hẳn một tài khoản đã đụng vào tiền sẽ để lại mọi hóa đơn và mọi
        // khoản thu chi của họ trỏ về hư không — sổ sách vẫn khớp nhưng câu
        // "ai đã làm việc này" mất câu trả lời cho toàn bộ lịch sử đó.
        // Vô hiệu hóa mới là công cụ đúng: mất quyền ngay, dấu vết còn nguyên.
        if ($user->hasFinancialHistory()) {
            return back()->withErrors([
                'password' => 'Tài khoản này đã có lịch sử tài chính nên không thể xóa. Hãy nhờ chủ tiệm vô hiệu hóa tài khoản để giữ lại dấu vết đối soát.',
            ], 'userDeletion');
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
