<?php

namespace App\Http\Controllers;

use App\Models\NotificationDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class FonnteDeliveryWebhookController extends Controller
{
    public function __invoke(Request $request, string $secret): JsonResponse
    {
        $configuredSecret = (string) config('services.fonnte.webhook_secret');
        abort_if($configuredSecret === '' || ! hash_equals($configuredSecret, $secret), 401);

        $validated = $request->validate([
            'id' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'stateid' => ['nullable', 'string', 'max:255'],
            'device' => ['nullable', 'string', 'max:255'],
            'timestamp' => ['nullable'],
        ]);

        $delivery = NotificationDelivery::query()
            ->where('provider', 'fonnte')
            ->where('channel', 'whatsapp')
            ->where('provider_message_id', $validated['id'])
            ->first();

        if ($delivery === null) {
            return response()->json(['success' => true, 'ignored' => true]);
        }

        $state = strtolower((string) ($validated['state'] ?? ''));
        $providerStatus = strtolower((string) ($validated['status'] ?? ''));
        $combined = $state.' '.$providerStatus;
        $nextStatus = match (true) {
            str_contains($combined, 'read') => 'read',
            str_contains($combined, 'deliver') => 'delivered',
            str_contains($combined, 'fail'), str_contains($combined, 'error'), str_contains($combined, 'invalid'), str_contains($combined, 'expired') => 'failed',
            default => null,
        };

        $metadata = array_merge((array) $delivery->metadata, [
            'fonnte_webhook' => $validated,
            'fonnte_stateid' => $validated['stateid'] ?? null,
        ]);

        if ($nextStatus === null || in_array($delivery->status, ['read', 'failed'], true)) {
            $delivery->update(['metadata' => $metadata]);

            return response()->json(['success' => true, 'updated' => false]);
        }

        $allowed = match ($delivery->status) {
            'sent' => ['delivered', 'read', 'failed'],
            'delivered' => ['read', 'failed'],
            default => [],
        };

        if (in_array($nextStatus, $allowed, true)) {
            $updates = ['status' => $nextStatus, 'metadata' => $metadata];
            $updates[$nextStatus.'_at'] = isset($validated['timestamp'])
                ? Carbon::parse($validated['timestamp'])
                : now();
            if ($nextStatus === 'failed') {
                $updates['last_error'] = 'Fonnte reported message delivery failure.';
            }
            $delivery->update($updates);
        } else {
            $delivery->update(['metadata' => $metadata]);
        }

        return response()->json(['success' => true]);
    }
}
