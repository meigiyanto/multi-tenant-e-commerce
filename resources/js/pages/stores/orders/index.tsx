import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Search } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type OrderRow = {
    id: number;
    order_number: string;
    customer_name: string;
    customer_email: string;
    status: string;
    payment_status: string;
    total: string;
    currency: string;
    items_count: number;
    placed_at: string | null;
};

type Props = {
    team: { name: string; slug: string };
    store: { id: number; name: string; currency: string };
    orders: {
        data: OrderRow[];
        links: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: { status: string; search: string };
    statuses: string[];
};

function money(amount: string, currency: string): string {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency }).format(Number(amount));
}

function variant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (['paid', 'processing', 'shipped', 'delivered'].includes(status)) return 'default';
    if (['failed', 'cancelled', 'expired'].includes(status)) return 'destructive';
    if (status === 'pending') return 'secondary';
    return 'outline';
}

export default function StoreOrdersIndex({ team, store, orders, filters, statuses }: Props) {
    const base = '/settings/teams/' + team.slug + '/stores/' + store.id;
    const statusLabel = (status: string) => status.replaceAll('_', ' ');

    return (
        <>
            <Head title={'Orders — ' + store.name} />
            <div className="flex flex-col gap-6">
                <div className="m-2">
                    <Button asChild variant="ghost" size="sm" className="mb-2">
                        <Link href={base + '/dashboard'}><ArrowLeft /> Back to dashboard</Link>
                    </Button>
                    <Heading variant="small" title="Orders" description={'Manage orders placed in ' + store.name + '.'} />
                </div>

                <form method="get" action={base + '/orders'} className="grid gap-3 rounded-xl border p-4 sm:grid-cols-[1fr_220px_auto]">
                    <label className="flex items-center gap-2 rounded-md border px-3">
                        <Search className="h-4 w-4 text-muted-foreground" />
                        <input name="search" defaultValue={filters.search} placeholder="Order number or customer" className="h-10 min-w-0 flex-1 bg-transparent text-sm outline-none" />
                    </label>
                    <select name="status" defaultValue={filters.status} className="h-10 rounded-md border bg-background px-3 text-sm">
                        <option value="">All statuses</option>
                        {statuses.map((status) => <option key={status} value={status}>{statusLabel(status)}</option>)}
                    </select>
                    <Button type="submit">Filter orders</Button>
                </form>

                <section className="space-y-3">
                    <div className="flex items-center justify-between gap-3">
                        <p className="text-sm text-muted-foreground">{orders.total.toLocaleString('id-ID')} orders</p>
                        <p className="text-sm text-muted-foreground">Page {orders.current_page} of {orders.last_page}</p>
                    </div>
                    {orders.data.length ? (
                        <div className="overflow-x-auto rounded-xl border">
                            <table className="w-full min-w-[760px] text-sm">
                                <thead className="bg-muted/50 text-left text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">Order</th>
                                        <th className="px-4 py-3 font-medium">Customer</th>
                                        <th className="px-4 py-3 font-medium">Items</th>
                                        <th className="px-4 py-3 font-medium">Total</th>
                                        <th className="px-4 py-3 font-medium">Order status</th>
                                        <th className="px-4 py-3 font-medium">Payment</th>
                                        <th className="px-4 py-3 font-medium">Details</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {orders.data.map((order) => (
                                        <tr key={order.id}>
                                            <td className="px-4 py-3">
                                                <p className="font-medium">{order.order_number}</p>
                                                <p className="text-xs text-muted-foreground">{order.placed_at ? new Date(order.placed_at).toLocaleString('id-ID') : '—'}</p>
                                            </td>
                                            <td className="px-4 py-3">
                                                <p>{order.customer_name}</p>
                                                <p className="text-xs text-muted-foreground">{order.customer_email}</p>
                                            </td>
                                            <td className="px-4 py-3 tabular-nums">{order.items_count}</td>
                                            <td className="px-4 py-3 font-medium tabular-nums">{money(order.total, order.currency)}</td>
                                            <td className="px-4 py-3"><Badge variant={variant(order.status)}>{statusLabel(order.status)}</Badge></td>
                                            <td className="px-4 py-3"><Badge variant={variant(order.payment_status)}>{statusLabel(order.payment_status)}</Badge></td>
                                            <td className="px-4 py-3"><Button asChild size="sm" variant="outline"><Link href={base + '/orders/' + order.id}>View</Link></Button></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <div className="rounded-xl border border-dashed p-10 text-center">
                            <p className="font-medium">No matching orders</p>
                            <p className="mt-1 text-sm text-muted-foreground">Try another status or search term.</p>
                        </div>
                    )}
                    {orders.links.length > 3 ? (
                        <nav aria-label="Order pagination" className="flex flex-wrap justify-center gap-2">
                            {orders.links.map((link, index) => (
                                <Button key={index} asChild size="sm" variant={link.active ? 'default' : 'outline'} disabled={!link.url}>
                                    <Link href={link.url ?? '#'} preserveScroll dangerouslySetInnerHTML={{ __html: link.label }} />
                                </Button>
                            ))}
                        </nav>
                    ) : null}
                </section>
            </div>
        </>
    );
}

StoreOrdersIndex.layout = (props: { team: { name: string; slug: string }; store: { name: string; id: number } }) => ({
    breadcrumbs: [
        { title: 'Teams', href: '/settings/teams' },
        { title: props.team.name, href: '/settings/teams/' + props.team.slug },
        { title: 'Stores', href: '/settings/teams/' + props.team.slug + '/stores' },
        { title: props.store.name, href: '/settings/teams/' + props.team.slug + '/stores/' + props.store.id },
        { title: 'Orders', href: '/settings/teams/' + props.team.slug + '/stores/' + props.store.id + '/orders' },
    ],
});
