<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Store a push notification token or send a push notification.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'platform' => 'required|string|in:ios,android',
        ]);

        $user = $request->user();
        $platform = $request->input('platform');

        // Store or update the push token on the user
        $user->updatePushToken(
            $request->input('token'),
            $platform,
        );

        return response()->json([
            'success' => true,
            'message' => 'Push token registered.',
            'data' => null,
        ]);
    }

    /**
     * Send a push notification to a user.
     */
    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer',
            'title' => 'required|string',
            'body' => 'required|string',
            'data' => 'nullable|array',
        ]);

        $targetUser = User::query()->find($request->input('user_id'));

        if (! $targetUser) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
                'data' => null,
            ], 404);
        }

        $targetUser->sendPushNotification(
            $request->input('title'),
            $request->input('body'),
            $request->input('data', []),
        );

        return response()->json([
            'success' => true,
            'message' => 'Push notification sent.',
            'data' => null,
        ]);
    }
}
