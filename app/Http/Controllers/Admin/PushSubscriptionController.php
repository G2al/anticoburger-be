<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePushSubscriptionRequest;
use App\Models\PushSubscription;
use App\Services\Notifications\AdminPushNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Lets the admin panel (session authenticated) register and remove the push
 * subscription of the current device, and send itself a test notification.
 */
class PushSubscriptionController extends Controller
{
    public function store(StorePushSubscriptionRequest $request): JsonResponse
    {
        $endpoint = $request->validated('endpoint');

        $subscription = PushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => PushSubscription::hashEndpoint($endpoint)],
            [
                'user_id' => $request->user()->id,
                'endpoint' => $endpoint,
                'public_key' => $request->validated('keys.p256dh'),
                'auth_token' => $request->validated('keys.auth'),
                'content_encoding' => $request->validated('content_encoding') ?? 'aes128gcm',
                'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            ],
        );

        return response()->json(['data' => ['subscribed' => true]], $subscription->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request): Response
    {
        $endpoint = (string) $request->validate(['endpoint' => ['required', 'string', 'max:2048']])['endpoint'];

        $request->user()->pushSubscriptions()
            ->where('endpoint_hash', PushSubscription::hashEndpoint($endpoint))
            ->delete();

        return response()->noContent();
    }

    public function test(Request $request, AdminPushNotifier $notifier): JsonResponse
    {
        if (! $notifier->isConfigured()) {
            return response()->json(['message' => 'Le chiavi VAPID non sono configurate sul server.'], 503);
        }

        return response()->json(['data' => ['sent' => $notifier->sendTest($request->user())]]);
    }
}
