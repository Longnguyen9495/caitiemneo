<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('view', $request->user());

        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(30);

        return view('admin.notifications.index', [
            'notifications' => $notifications,
        ]);
    }

    public function show(Request $request, string $notification): RedirectResponse
    {
        $this->authorize('view', $request->user());

        $record = $request->user()
            ->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        if ($record->read_at === null) {
            $record->markAsRead();
        }

        return redirect()->to($record->data['url'] ?? route('admin.notifications.index'));
    }
}
