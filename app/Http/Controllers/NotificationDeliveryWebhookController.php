<?php

namespace App\Http\Controllers;

use App\Models\NotificationDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class NotificationDeliveryWebhookController extends Controller
{
    public function __invoke(Request $request, string $uuid): JsonResponse
    {
        $secret = (string) config('services.notification_delivery_webhook.secret');
        $signature = (string) $request->header('X-Delivery-Signature');

        abort_if($secret === '' || $signature === '', 401);
        $expected = hash_hmac('sha256', $request->getContent(), $secret);
        abort_unless(hash_equals($expected, $signature), 401);

        $validated = $request->validate([
            'status' => ['required', 'in:delivered,read,failed'],
            'provider_message_id' => ['nullable', 'string', 'max:255'],
            'occurred_at' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ]);

        $delivery = NotificationDelivery::query()->where('uuid', $uuid)->firstOrFail();
        $status = $validated['status'];
        $allowed = match ($delivery->status) {
            'sent' => ['delivered', 'read', 'failed'],
            'delivered' => ['read'],
            default => [],
        };

        if (! in_array($status, $allowed, true)) {
            if ($delivery->status === $status) {
                return response()->json(['success' => true, 'duplicate' => true]);
            }

            Log::warning('Notification delivery webhook transition rejected', [
                'notification_delivery_id' => $delivery->id,
                'current_status' => $delivery->status,
                'incoming_status' => $status,
            ]);

            return response()->json(['success' => false, 'message' => 'Invalid delivery status transition.'], 409);
        }

        $occurredAt = isset($validated['occurred_at']) ? Carbon::parse($validated['occurred_at']) : now();
        $updates = [
            'status' => $status,
            'provider_message_id' => $validated['provider_message_id'] ?? $delivery->provider_message_id,
            'metadata' => array_merge((array) $delivery->metadata, (array) ($validated['metadata'] ?? [])),
        ];
        $updates[$status.'_at'] = $occurredAt;

        if ($status === 'failed') {
            $updates['last_error'] = data_get($validated, 'metadata.reason', 'Delivery provider reported failure.');
        }

        $delivery->update($updates);

        return response()->json(['success' => true]);
    }
}
