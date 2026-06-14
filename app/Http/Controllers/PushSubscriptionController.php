<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Stores/removes the browser's Web Push subscription for the signed-in user. The keypair is
 * the standard PushSubscription shape produced by the service worker's PushManager.
 */
class PushSubscriptionController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'endpoint'  => 'required|string',
            'keys.p256dh' => 'required|string',
            'keys.auth'   => 'required|string',
        ]);

        $request->user()->updatePushSubscription(
            $data['endpoint'],
            $data['keys']['p256dh'],
            $data['keys']['auth'],
        );

        return response()->noContent();
    }

    public function destroy(Request $request)
    {
        $endpoint = $request->validate(['endpoint' => 'required|string'])['endpoint'];
        $request->user()->deletePushSubscription($endpoint);

        return response()->noContent();
    }
}
