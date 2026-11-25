import { Head, router } from '@inertiajs/react';
import { useEffect } from 'react';
import { CheckCircle2, Clock3, Package, Truck } from 'lucide-react';
import OrderNumberCopy from '@/components/order-number-copy';

type HistoryEntry = { status: string; note: string | null; created_at: string | null };
type Props = {
    store: { name: string; currency: string };
    order: {
        order_number: string;
        status: string;
        payment_status: string;
        placed_at: string | null;
        shipped_at: string | null;
        delivered_at: string | null;
        shipping_courier: string | null;
        shipping_service: string | null;
        tracking_number: string | null;
        tracking_url: string | null;
        destination: string | null;
        history: HistoryEntry[];
    };
};

const labels: Record<string, string> = {
    pending: 'Order placed',
    processing: 'Processing',
    shipped: 'Shipped',
    delivered: 'Delivered',
    cancelled: 'Cancelled',
};

export default function OrderTracking({ store, order }: Props) {
    useEffect(() => {
        const interval = window.setInterval(() => router.reload({ only: ['order'] }), 30000);
        return () => window.clearInterval(interval);
    }, []);

    const date = (value: string | null) => value ? new Date(value).toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' }) : 'Pending';
    return (
        <>
            <Head title={'Track ' + order.order_number + ' | ' + store.name} />
            <main className="min-h-screen bg-[#faf9f6] px-4 py-10 text-slate-900 sm:py-16">
                <div className="mx-auto max-w-2xl">
                    <a href="/" className="text-lg font-semibold">{store.name}</a>
                    <section className="mt-8 rounded-xl border border-black/10 bg-white p-6 sm:p-8">
                        <div className="flex items-center gap-3">
                            <Package className="h-7 w-7" />
                            <div><p className="text-xs uppercase tracking-widest text-slate-500">Order tracking</p><h1 className="text-2xl font-semibold">Track your order</h1></div>
                        </div>
                        <div className="mt-6 flex flex-wrap items-center gap-3 rounded-lg bg-slate-50 p-4">
                            <div className="min-w-0 flex-1"><p className="text-xs text-slate-500">Order number</p><p className="break-all font-semibold">{order.order_number}</p></div>
                            <OrderNumberCopy value={order.order_number} />
                        </div>
                        <div className="mt-5 grid grid-cols-2 gap-4 text-sm">
                            <div><p className="text-slate-500">Order status</p><p className="mt-1 font-semibold capitalize">{order.status}</p></div>
                            <div><p className="text-slate-500">Payment status</p><p className="mt-1 font-semibold capitalize">{order.payment_status}</p></div>
                        </div>
                        <div className="mt-8">
                            <h2 className="font-semibold">Delivery details</h2>
                            <div className="mt-3 space-y-2 rounded-lg border p-4 text-sm">
                                <p>{[order.shipping_courier, order.shipping_service].filter(Boolean).join(' · ') || 'Courier not assigned yet'}</p>
                                <div><p className="text-slate-500">Tracking number</p><p className="font-semibold">{order.tracking_number ?? 'Not available yet'}</p></div>
                                {order.tracking_url ? <a href={order.tracking_url} target="_blank" rel="noreferrer" className="inline-flex font-medium text-blue-700 underline">Open courier tracking</a> : null}
                                {order.destination ? <p className="border-t pt-2 text-slate-500">Destination: {order.destination}</p> : null}
                            </div>
                        </div>
                        <div className="mt-8">
                            <h2 className="font-semibold">Order timeline</h2>
                            <ol className="mt-4 space-y-5">
                                {order.history.map((entry, index) => (
                                    <li key={index} className="flex gap-3">
                                        <span className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100">{entry.status === 'delivered' ? <CheckCircle2 className="h-4 w-4" /> : entry.status === 'shipped' ? <Truck className="h-4 w-4" /> : <Clock3 className="h-4 w-4" />}</span>
                                        <div className="min-w-0 flex-1 border-b pb-4">
                                            <p className="font-medium">{labels[entry.status] ?? entry.status.replaceAll('_', ' ')}</p>
                                            <p className="mt-1 text-xs text-slate-500">{date(entry.created_at)}</p>
                                            {entry.note ? <p className="mt-2 text-sm text-slate-600">{entry.note}</p> : null}
                                        </div>
                                    </li>
                                ))}
                            </ol>
                        </div>
                        <p className="mt-6 text-xs leading-5 text-slate-500">Tracking information is updated by the store. Courier tracking details are provided by the courier when available.</p>
                    </section>
                </div>
            </main>
        </>
    );
}
