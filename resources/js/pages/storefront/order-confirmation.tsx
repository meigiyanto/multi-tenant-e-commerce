import { Head } from '@inertiajs/react';
import { CheckCircle2, Package, ShoppingBag } from 'lucide-react';

type OrderItem = { product_name: string; variant_name: string; quantity: number; line_total: string };
type Props = {
    store: { name: string; currency: string };
    order: {
        order_number: string; status: string; payment_status: string; customer_name: string;
        customer_email: string; total: string; currency: string; items: OrderItem[];
    };
};

export default function StorefrontOrderConfirmation({ store, order }: Props) {
    const money = (amount: string | number) => new Intl.NumberFormat('id-ID', {
        style: 'currency', currency: order.currency || store.currency, maximumFractionDigits: 0,
    }).format(Number(amount));
    return <>
        <Head title={'Order ' + order.order_number + ' · ' + store.name} />
        <div className="min-h-screen bg-[#faf9f6] text-slate-900">
            <header className="border-b border-black/5"><div className="mx-auto max-w-3xl px-4 py-5 sm:px-8"><a href="/" className="text-lg font-semibold tracking-tight">{store.name}</a></div></header>
            <main className="mx-auto max-w-3xl px-4 py-12 sm:px-8 sm:py-16">
                <div className="rounded-md border border-black/10 bg-white p-6 text-center sm:p-10">
                    <CheckCircle2 className="mx-auto h-12 w-12 text-emerald-600" />
                    <p className="mt-5 text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Order received</p>
                    <h1 className="mt-2 text-3xl font-semibold tracking-tight">Thank you, {order.customer_name}.</h1>
                    <p className="mt-3 text-sm leading-6 text-slate-600">Your order has been created. A confirmation summary is shown below.</p>
                    <div className="mt-7 rounded-md bg-[#faf9f6] p-4"><p className="text-xs text-slate-500">Order number</p><p className="mt-1 text-lg font-semibold tracking-wide">{order.order_number}</p></div>
                    <div className="mt-7 grid grid-cols-2 gap-3 text-left text-sm"><div><p className="text-xs text-slate-500">Order status</p><p className="mt-1 font-medium capitalize">{order.status}</p></div><div><p className="text-xs text-slate-500">Payment status</p><p className="mt-1 font-medium capitalize">{order.payment_status}</p></div></div>
                    <div className="mt-7 border-t border-black/10 pt-6 text-left"><h2 className="font-semibold">Order items</h2><div className="mt-4 space-y-4">{order.items.map((item, index) => <div key={index} className="flex items-start justify-between gap-4 text-sm"><div><p className="font-medium">{item.product_name}</p><p className="mt-1 text-xs text-slate-500">{item.variant_name} · Qty {item.quantity}</p></div><p className="shrink-0 font-semibold">{money(item.line_total)}</p></div>)}</div><div className="mt-5 flex justify-between border-t border-black/10 pt-4 font-semibold"><span>Total</span><span>{money(order.total)}</span></div></div>
                    <p className="mt-6 text-sm text-slate-500">Order updates will be sent to {order.customer_email}.</p>
                    <a href="/" className="mt-7 inline-flex items-center justify-center gap-2 rounded-md bg-slate-900 px-5 py-3 text-sm font-semibold text-white"><ShoppingBag className="h-4 w-4" /> Continue shopping</a>
                </div>
            </main>
        </div>
    </>;
}
