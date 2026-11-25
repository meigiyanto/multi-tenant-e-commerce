import { Link } from '@inertiajs/react';
import { ArrowRight, CheckCircle2, ClipboardList, Package, UserRound } from 'lucide-react';
import CustomerAccountLayout from '@/layouts/storefront/customer-account-layout';

type Order = { order_number: string; store_name: string; status: string; payment_status: string; total: string; currency: string; placed_at: string | null; items_count: number };
type Props = { store: { name: string; currency: string }; summary: { orders_count: number; profile_complete: boolean }; latest_orders: Order[] };

export default function CustomerAccount({ store, summary, latest_orders }: Props) {
    const money = (amount: string, currency: string) => new Intl.NumberFormat('en-US', { style: 'currency', currency, maximumFractionDigits: 0 }).format(Number(amount));
    return <CustomerAccountLayout title="Overview" storeName={store.name}>
        <div className="grid gap-4 sm:grid-cols-2">
            <Link href="/account/orders" className="rounded-lg border border-black/10 bg-white p-5 transition hover:border-black/25"><div className="flex items-center justify-between"><span className="text-sm text-slate-500">Orders placed</span><ClipboardList className="h-5 w-5 text-slate-500" /></div><p className="mt-3 text-3xl font-semibold">{summary.orders_count}</p><span className="mt-2 inline-flex items-center gap-1 text-sm font-medium">View order history <ArrowRight className="h-4 w-4" /></span></Link>
            <Link href="/account/profile" className="rounded-lg border border-black/10 bg-white p-5 transition hover:border-black/25"><div className="flex items-center justify-between"><span className="text-sm text-slate-500">Account profile</span><UserRound className="h-5 w-5 text-slate-500" /></div><p className="mt-3 flex items-center gap-2 text-lg font-semibold">{summary.profile_complete ? 'Profile complete' : 'Complete your profile'}<CheckCircle2 className={'h-5 w-5 ' + (summary.profile_complete ? 'text-emerald-600' : 'text-slate-300')} /></p><span className="mt-2 inline-flex items-center gap-1 text-sm font-medium">Manage profile <ArrowRight className="h-4 w-4" /></span></Link>
        </div>
        <section className="mt-8 rounded-lg border border-black/10 bg-white p-5 sm:p-6">
            <div className="flex items-center justify-between gap-3"><div><h3 className="font-semibold">Recent orders</h3><p className="mt-1 text-sm text-slate-500">Your latest purchases from all stores.</p></div><Link href="/account/orders" className="text-sm font-semibold underline underline-offset-4">View all</Link></div>
            {latest_orders.length ? <div className="mt-5 divide-y divide-black/5">{latest_orders.map((order) => <div key={order.order_number} className="flex flex-wrap items-center justify-between gap-3 py-4 first:pt-0 last:pb-0"><div><p className="font-medium">{order.order_number}</p><p className="mt-1 text-xs text-slate-500">From {order.store_name} · {order.placed_at ? new Date(order.placed_at).toLocaleDateString('en-US') : 'Date unavailable'} · {order.items_count} {order.items_count === 1 ? 'item' : 'items'}</p></div><div className="text-right"><p className="font-semibold">{money(order.total, order.currency)}</p><p className="mt-1 text-xs capitalize text-slate-500">{order.status.replaceAll('_', ' ')} · {order.payment_status.replaceAll('_', ' ')}</p></div></div>)}</div> : <div className="py-10 text-center"><Package className="mx-auto h-8 w-8 text-slate-400" /><p className="mt-3 font-medium">No orders yet</p><p className="mt-1 text-sm text-slate-500">Your purchases from all stores will appear here.</p><Link href="/" className="mt-4 inline-flex text-sm font-semibold underline underline-offset-4">Start shopping</Link></div>}
        </section>
    </CustomerAccountLayout>;
}
