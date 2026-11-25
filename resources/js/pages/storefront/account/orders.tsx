import { Link } from '@inertiajs/react';
import { ClipboardList, Package } from 'lucide-react';\nimport CustomerAccountLayout from '@/layouts/storefront/customer-account-layout';

type Order = { order_number: string; status: string; payment_status: string; items_count: number; total: string; currency: string; placed_at: string | null; tracking_url: string };
type Props = { store: { name: string; currency: string }; orders: Order[] };

export default function CustomerOrders({ store, orders }: Props) {
    const money = (amount: string, currency: string) => new Intl.NumberFormat('en-US', { style: 'currency', currency, maximumFractionDigits: 0 }).format(Number(amount));
    return <>
        <Head title={`My orders - ${store.name}`} />
        <main className="min-h-screen bg-[#faf9f6] px-4 py-8 text-slate-900 sm:px-8">
            <div className="mx-auto max-w-4xl">
                <Link href="/" className="inline-flex items-center gap-2 text-sm text-slate-600 hover:text-slate-950"><ArrowLeft className="h-4 w-4" /> Back to store</Link>
                <div className="mt-8 flex items-center gap-3"><ClipboardList className="h-7 w-7" /><div><p className="text-xs uppercase tracking-widest text-slate-500">{store.name}</p><h1 className="text-3xl font-semibold">My orders</h1></div></div>
                {orders.length ? <div className="mt-8 space-y-4">{orders.map((order) => <article key={order.order_number} className="rounded-lg border border-black/10 bg-white p-5">
                    <div className="flex flex-wrap items-start justify-between gap-3"><div><p className="text-xs text-slate-500">Order number</p><p className="mt-1 font-semibold">{order.order_number}</p><p className="mt-1 text-xs text-slate-500">{order.placed_at ? new Date(order.placed_at).toLocaleString('en-US') : 'Date unavailable'}</p></div><div className="text-right"><p className="font-semibold">{money(order.total, order.currency)}</p><p className="mt-1 text-xs capitalize text-slate-500">{order.payment_status.replaceAll('_', ' ')}</p></div></div>
                    <div className="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-black/5 pt-4"><div className="flex items-center gap-2 text-sm text-slate-600"><Package className="h-4 w-4" />{order.items_count} {order.items_count === 1 ? 'item' : 'items'} · <span className="capitalize">{order.status.replaceAll('_', ' ')}</span></div><a href={order.tracking_url} className="text-sm font-semibold underline underline-offset-4">Track order</a></div>
                </article>)}</div> : <div className="mt-8 rounded-lg border border-dashed border-black/15 bg-white px-6 py-16 text-center"><Package className="mx-auto h-9 w-9 text-slate-400" /><h2 className="mt-3 font-semibold">No orders yet</h2><p className="mt-1 text-sm text-slate-500">Your orders from this store will appear here.</p><Link href="/" className="mt-5 inline-flex rounded-md bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white">Start shopping</Link></div>}
            </div>
        </main>
    </>;
}
