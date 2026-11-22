import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ClipboardList, CreditCard, Package, ShoppingBag } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Props = {
    team: { id: number; name: string; slug: string };
    store: { id: number; name: string; slug: string; currency: string };
    stats: {
        total_orders: number;
        pending_orders: number;
        paid_orders: number;
        paid_revenue: string;
        active_products: number;
    };
    recentOrders: {
        id: number;
        order_number: string;
        customer_name: string;
        status: string;
        payment_status: string;
        total: string;
        currency: string;
        items_count: number;
        placed_at: string | null;
    }[];
};

function money(amount: string, currency: string): string {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency }).format(Number(amount));
}

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'paid' || status === 'processing' || status === 'active') return 'default';
    if (status === 'failed' || status === 'cancelled' || status === 'expired') return 'destructive';
    if (status === 'pending') return 'secondary';
    return 'outline';
}

export default function StoreDashboard({ team, store, stats, recentOrders }: Props) {
    const base = '/settings/teams/' + team.slug + '/stores/' + store.id;
    const cards = [
        { label: 'Total orders', value: stats.total_orders.toLocaleString('id-ID'), icon: ShoppingBag },
        { label: 'Awaiting payment', value: stats.pending_orders.toLocaleString('id-ID'), icon: ClipboardList },
        { label: 'Paid orders', value: stats.paid_orders.toLocaleString('id-ID'), icon: CreditCard },
        { label: 'Active products', value: stats.active_products.toLocaleString('id-ID'), icon: Package },
    ];

    return (
        <>
            <Head title={'Dashboard — ' + store.name} />
            <div className="flex flex-col gap-6">
                <div className="m-2">
                    <Button asChild variant="ghost" size="sm" className="mb-2">
                        <Link href={base}><ArrowLeft /> Back to store</Link>
                    </Button>
                    <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                        <Heading variant="small" title={store.name} description={'Store dashboard for ' + team.name + '.'} />
                        <Button asChild variant="outline"><Link href={base + '/catalog'}><Package /> Product catalog</Link></Button>
                    </div>
                </div>

                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {cards.map((card) => {
                        const Icon = card.icon;
                        return (
                            <article key={card.label} className="rounded-xl border p-4">
                                <div className="flex items-center justify-between gap-3">
                                    <p className="text-sm text-muted-foreground">{card.label}</p>
                                    <Icon className="h-4 w-4 text-muted-foreground" />
                                </div>
                                <p className="mt-3 text-2xl font-semibold tabular-nums">{card.value}</p>
                            </article>
                        );
                    })}
                </section>

                <section className="rounded-xl border p-4 sm:p-5">
                    <div className="flex items-center gap-2">
                        <CreditCard className="h-5 w-5 text-muted-foreground" />
                        <h2 className="font-semibold">Paid revenue</h2>
                    </div>
                    <p className="mt-3 text-2xl font-semibold tabular-nums">{money(stats.paid_revenue, store.currency)}</p>
                    <p className="mt-1 text-sm text-muted-foreground">Total value of orders whose payment status is paid.</p>
                </section>

                <section className="space-y-3">
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <h2 className="font-semibold">Recent orders</h2>
                            <p className="text-sm text-muted-foreground">The latest orders placed in this store.</p>
                        </div>
                        <Badge variant="secondary">{recentOrders.length} shown</Badge>
                    </div>
                    {recentOrders.length > 0 ? (
                        <div className="overflow-x-auto rounded-xl border">
                            <table className="w-full min-w-[720px] text-sm">
                                <thead className="bg-muted/50 text-left text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">Order</th>
                                        <th className="px-4 py-3 font-medium">Customer</th>
                                        <th className="px-4 py-3 font-medium">Items</th>
                                        <th className="px-4 py-3 font-medium">Total</th>
                                        <th className="px-4 py-3 font-medium">Order status</th>
                                        <th className="px-4 py-3 font-medium">Payment</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {recentOrders.map((order) => (
                                        <tr key={order.id}>
                                            <td className="px-4 py-3">
                                                <p className="font-medium">{order.order_number}</p>
                                                <p className="text-xs text-muted-foreground">{order.placed_at ? new Date(order.placed_at).toLocaleString('id-ID') : '—'}</p>
                                            </td>
                                            <td className="px-4 py-3">{order.customer_name}</td>
                                            <td className="px-4 py-3 tabular-nums">{order.items_count}</td>
                                            <td className="px-4 py-3 font-medium tabular-nums">{money(order.total, order.currency)}</td>
                                            <td className="px-4 py-3"><Badge variant={statusVariant(order.status)}>{order.status}</Badge></td>
                                            <td className="px-4 py-3"><Badge variant={statusVariant(order.payment_status)}>{order.payment_status}</Badge></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <div className="rounded-xl border border-dashed p-10 text-center">
                            <ShoppingBag className="mx-auto mb-3 h-8 w-8 text-muted-foreground" />
                            <p className="font-medium">No orders yet</p>
                            <p className="mt-1 text-sm text-muted-foreground">Orders placed through this storefront will appear here.</p>
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

StoreDashboard.layout = (props: { team: { name: string; slug: string }; store: { name: string; id: number } }) => ({
    breadcrumbs: [
        { title: 'Teams', href: '/settings/teams' },
        { title: props.team.name, href: '/settings/teams/' + props.team.slug },
        { title: 'Stores', href: '/settings/teams/' + props.team.slug + '/stores' },
        { title: props.store.name, href: '/settings/teams/' + props.team.slug + '/stores/' + props.store.id },
        { title: 'Dashboard', href: '/settings/teams/' + props.team.slug + '/stores/' + props.store.id + '/dashboard' },
    ],
});
