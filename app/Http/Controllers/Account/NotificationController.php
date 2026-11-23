<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return view('notifications.index', ['notifications' => $request->user()->notifications()->paginate(20)]);
    }

    /** Marks one notification read and opens its link (only links on this site are followed). */
    public function open(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;

        return redirect($url && str_starts_with($url, url('/')) ? $url : route('notifications.index'));
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }
}
