# Payment and shipping setup

## Extensible integration architecture

Payment drivers implement `App\Contracts\Payments\PaymentGateway` and are registered in `config/payments.php`. Enable a driver through `PAYMENT_GATEWAYS_ENABLED` and select the default with `PAYMENT_GATEWAY`. To add another gateway, install its optional Composer package (if needed), implement the contract, register the class in `drivers`, and enable it. To disable or uninstall a gateway, remove it from the enabled list and remove its driver registration before removing its package. Order and payment transaction records remain provider-neutral.

Shipping drivers implement `App\Contracts\Shipping\ShippingProvider` and are selected through `SHIPPING_PROVIDER` and `config/shipping.php`. The shipping provider can be changed independently from the payment gateway.

## Midtrans

Midtrans is the default payment gateway. Configure these server-side environment variables:

```dotenv
PAYMENT_GATEWAY=midtrans
PAYMENT_GATEWAYS_ENABLED=midtrans
MIDTRANS_SERVER_KEY=your-server-key
MIDTRANS_CLIENT_KEY=your-client-key
MIDTRANS_SIGNATURE_SECRET_KEY=your-signature-secret-key
MIDTRANS_IS_PRODUCTION=false
```

Use Sandbox credentials while developing. Set `MIDTRANS_IS_PRODUCTION=true` only after the merchant account is approved for production. Never expose Server Key or Signature Secret Key to frontend code. In this first implementation, environment credentials are platform-wide and shared by all Stores. If each merchant must settle payments into their own Midtrans account, add encrypted per-Store credentials and a Store payment-settings UI in a later iteration; the gateway contract and registry are already separated from checkout.

Configure the Midtrans Payment Notification URL to:

```
https://your-app-domain.com/payments/midtrans/notification
```

The notification handler validates `signature_key` using the separate Signature Secret Key, checks the notified amount against the stored transaction, and processes each transaction idempotently. A successful payment deducts the reserved inventory and moves the order to processing. Failed or expired payments cancel the order and release its inventory reservation. The Midtrans dashboard must point to a publicly reachable HTTPS URL.

## RajaOngkir

RajaOngkir is the default shipping-rate provider. Configure:

```dotenv
SHIPPING_PROVIDER=rajaongkir
RAJAONGKIR_API_KEY=your-api-key
RAJAONGKIR_BASE_URL=https://rajaongkir.komerce.id/api/v1
RAJAONGKIR_COURIERS=jne,sicepat,jnt,pos,tiki
```

Each Store must have an origin destination ID in its `settings` JSON. Search for the merchant's origin using RajaOngkir's domestic destination endpoint, then save the returned ID:

```php
$store = App\Models\Store::findOrFail($storeId);
$settings = $store->settings ?? [];
data_set($settings, 'shipping.origin_destination_id', 12345);
$store->update(['settings' => $settings]);
```

Replace `12345` with the real subdistrict destination ID returned by the API. Store origin is deliberately tenant-specific; do not use a single global origin for every Store.

Product weight is stored in grams in `products.weight_grams` and defaults to 1000 grams for existing products. Update product weights to improve quote accuracy. Checkout calculates total package weight from product weight multiplied by quantity, asks RajaOngkir for available services, and recalculates the selected courier/service on the server before creating the order. The final destination, courier, service, weight, and shipping amount are snapshotted on the order.

RajaOngkir Shipping Cost API uses live rates; its dashboard Sandbox/Live switch does not make shipping-cost requests sandboxed. The adapter requests each configured courier separately and caches matching quotes for 90 seconds, so choose the courier list with the account's API quota in mind. Confirm API quota and pricing with the RajaOngkir account before enabling it for a high-traffic Store.
