<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payments\PaymentService;
use App\Support\Stores\StoreContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function start(string $storeSubdomain, string $orderNumber, StoreContext $context): RedirectResponse
    {
        $store = $context->store();
        abort_if($store === null, 404);

        $order = Order::query()
            ->where('store_id', $store->id)
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        $transaction = $this->payments->start($order);

        return redirect()->away($transaction->redirect_url);
    }

    public function notification(Request $request): JsonResponse
    {
        $context = [
            'order_id' => $request->input('order_id'),
            'transaction_id' => $request->input('transaction_id'),
            'transaction_status' => $request->input('transaction_status'),
            'payment_type' => $request->input('payment_type'),
            'status_code' => $request->input('status_code'),
            'gross_amount' => $request->input('gross_amount'),
        ];

        Log::info('Midtrans notification received', $context);

        try {
            $this->payments->handleNotification($request->all(), 'midtrans');
        } catch (ModelNotFoundException $exception) {
            Log::warning('Midtrans notification references an unknown payment transaction', [
                ...$context,
                'exception' => $exception::class,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Transaksi pembayaran belum ditemukan. Midtrans dapat mencoba kembali.',
            ], 503);
        } catch (RuntimeException $exception) {
            Log::warning('Midtrans notification rejected', [
                ...$context,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => $exception->getMessage()], 400);
        } catch (Throwable $exception) {
            Log::error('Midtrans notification processing failed', [
                ...$context,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        Log::info('Midtrans notification processed', $context);

        return response()->json(['success' => true]);
    }
}
