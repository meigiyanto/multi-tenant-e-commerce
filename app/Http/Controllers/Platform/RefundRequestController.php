<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\RefundRequest;
use App\Notifications\Storefront\RefundRequestResult;
use App\Services\Notifications\PlatformNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RefundRequestController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('platform/refunds/index', [
            'refundRequests' => RefundRequest::query()
                ->with(['order:id,order_number,total,currency,payment_status,status', 'customer:id,name,email', 'paymentTransaction:id,gateway,gateway_order_id,gateway_transaction_id,status,metadata', 'reviewer:id,name'])
                ->latest()
                ->paginate(20)
                ->through(fn (RefundRequest $refund): array => [
                    'id' => $refund->id,
                    'status' => $refund->status,
                    'reason' => $refund->reason,
                    'admin_notes' => $refund->admin_notes,
                    'refund_reference' => $refund->refund_reference,
                    'result_notes' => $refund->result_notes,
                    'created_at' => $refund->created_at?->toISOString(),
                    'order' => $refund->order->only(['order_number', 'total', 'currency', 'status', 'payment_status']),
                    'customer' => $refund->customer->only(['name', 'email']),
                    'payment' => [
                        'gateway' => $refund->paymentTransaction->gateway,
                        'gateway_order_id' => $refund->paymentTransaction->gateway_order_id,
                        'gateway_transaction_id' => $refund->paymentTransaction->gateway_transaction_id,
                        'status' => $refund->paymentTransaction->status,
                        'payment_type' => data_get($refund->paymentTransaction->metadata, 'last_notification.payment_type') ?? data_get($refund->paymentTransaction->metadata, 'last_status_response.payment_type'),
                    ],
                    'reviewer' => $refund->reviewer?->only(['name']),
                ]),
        ]);
    }

    public function update(Request $request, RefundRequest $refundRequest): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['under_review', 'processing', 'refunded', 'rejected'])],
            'admin_notes' => ['nullable', 'string', 'max:3000'],
            'refund_reference' => ['nullable', 'string', 'max:120'],
            'result_notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $allowed = match ($refundRequest->status) {
            'requested' => ['under_review', 'rejected'],
            'under_review' => ['processing', 'rejected'],
            'processing' => ['refunded', 'rejected'],
            default => [],
        };

        if (! in_array($validated['status'], $allowed, true)) {
            throw ValidationException::withMessages(['status' => 'This refund status transition is not allowed.']);
        }

        if ($validated['status'] === 'processing' && $refundRequest->paymentTransaction->gateway === 'midtrans') {
            $metadata = (array) ($refundRequest->paymentTransaction->metadata ?? []);
            $paymentType = data_get($metadata, 'last_notification.payment_type') ?? data_get($metadata, 'last_status_response.payment_type');
            $gatewayStatus = data_get($metadata, 'last_notification.transaction_status') ?? data_get($metadata, 'last_status_response.transaction_status');
            $midtransRefundMethods = ['credit_card', 'gopay', 'qris', 'shopeepay', 'dana', 'ovo', 'kredivo', 'akulaku'];

            if ($paymentType === null || (in_array($paymentType, $midtransRefundMethods, true) && $gatewayStatus !== 'settlement')) {
                throw ValidationException::withMessages([
                    'status' => 'Midtrans refunds for this payment method require a settled transaction. A captured or pending transaction must follow the applicable cancellation procedure instead.',
                ]);
            }
        }

        if ($validated['status'] === 'refunded' && blank($validated['refund_reference'] ?? null) && blank($validated['result_notes'] ?? null)) {
            throw ValidationException::withMessages(['refund_reference' => 'Record a refund reference or explain the manual payout before marking this request as refunded.']);
        }

        $refundRequest->update([
            'status' => $validated['status'],
            'admin_notes' => $validated['admin_notes'] ?? $refundRequest->admin_notes,
            'refund_reference' => $validated['refund_reference'] ?? $refundRequest->refund_reference,
            'result_notes' => $validated['result_notes'] ?? $refundRequest->result_notes,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => $refundRequest->reviewed_at ?? now(),
            'resolved_at' => in_array($validated['status'], ['refunded', 'rejected'], true) ? now() : null,
        ]);

        if (in_array($refundRequest->status, ['refunded', 'rejected'], true)) {
            $refundRequest->load(['customer', 'order']);
            $refundRequest->customer->notify(new RefundRequestResult($refundRequest));
            app(PlatformNotificationService::class)->notifyAdmins([
                'event' => $refundRequest->status === 'refunded' ? 'refund_completed' : 'refund_rejected',
                'title' => $refundRequest->status === 'refunded' ? 'Refund completed' : 'Refund rejected',
                'message' => 'Refund for order '.$refundRequest->order->order_number.' was '.$refundRequest->status.'.',
                'url' => route('platform.admin.refunds.index'),
                'subject_id' => $refundRequest->id,
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Refund request updated.']);

        return to_route('platform.admin.refunds.index');
    }
}
