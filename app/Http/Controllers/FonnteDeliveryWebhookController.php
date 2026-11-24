<?php

namespace App\Http\Controllers;

use App\Models\NotificationDelivery;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

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

        $result = DB::transaction(function () use ($validated): array {
            $delivery = NotificationDelivery::query()
                ->where('provider', 'fonnte')
                ->where('channel', 'whatsapp')
                ->where('provider_message_id', $validated['id'])
                ->lockForUpdate()
                ->first();

            if ($delivery === null) {
                return ['found' => false, 'updated' => false];
            }

            $nextStatus = $this->mapStatus(
                (string) ($validated['status'] ?? ''),
                (string) ($validated['state'] ?? ''),
            );
            $metadata = array_merge((array) $delivery->metadata, [
                'fonnte_webhook' => $validated,
                'fonnte_stateid' => $validated['stateid'] ?? null,
                'fonnte_last_callback_at' => now()->toIso8601String(),
            ]);

            $updates = ['metadata' => $metadata];
            $allowed = match ($delivery->status) {
                'sent' => ['delivered', 'read', 'failed'],
                'delivered' => ['read'],
                default => [],
            };

            if ($nextStatus !== null && in_array($nextStatus, $allowed, true)) {
                $updates['status'] = $nextStatus;
                $updates[$nextStatus.'_at'] = $this->occurredAt($validated['timestamp'] ?? null);

                if ($nextStatus === 'failed') {
                    $updates['last_error'] = 'Fonnte reported message delivery failure.';
                }
            }

            $delivery->update($updates);

            return ['found' => true, 'updated' => isset($updates['status'])];
        });

        return response()->json([
            'success' => true,
            'ignored' => ! $result['found'],
            'updated' => $result['updated'],
        ]);
    }

    private function mapStatus(string $status, string $state): ?string
    {
        $state = strtolower(trim($state));
        $status = strtolower(trim($status));

        if ($state === 'read' || $status === 'read') {
            return 'read';
        }

        if (in_array($state, ['delivered', 'delivery_success'], true)
            || in_array($status, ['delivered', 'delivery_success'], true)) {
            return 'delivered';
        }

        $failureStatuses = ['failed', 'failure', 'invalid', 'expired', 'url unreachable', 'error'];

        if (in_array($state, [...$failureStatuses, '0'], true) || in_array($status, $failureStatuses, true)) {
            return 'failed';
        }

        // Fonnte's "Sent" status means its server processed the request, not
        // that WhatsApp delivered it. Numeric state "0" is not a delivery proof.
        return null;
    }

    private function occurredAt(mixed $timestamp): CarbonInterface
    {
        if ($timestamp === null || (is_string($timestamp) && trim($timestamp) === '')) {
            return now();
        }

        try {
            return is_numeric($timestamp)
                ? Carbon::createFromTimestamp((int) $timestamp)
                : (is_string($timestamp) ? Carbon::parse($timestamp) : now());
        } catch (Throwable) {
            return now();
        }
    }
}
