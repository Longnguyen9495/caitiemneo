<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('view', $request->user());

        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(30);

        return view('admin.notifications.index', [
            'notifications' => $notifications,
        ]);
    }
}
