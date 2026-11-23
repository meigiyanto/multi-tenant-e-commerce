import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, Package, Truck } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Props = {
    team: { name: string; slug: string };
    store: { id: number; name: string; currency: string };
    order: {
        id: number;
        order_number: string;
        status: string;
        payment_status: string;
        customer: { name: string; email: string; phone: string };
        shipping: {
            address: string;
            city: string;
            province: string;
            postal_code: string;
            destination_label: string | null;
            provider: string | null;
            courier: string | null;
            service: string | null;
        };
        customer_note: string | null;
        currency: string;
        subtotal: string;
        shipping_amount: string;
        discount_amount: string;
        total: string;
        placed_at: string | null;
        items: { id: number; product_name: string; variant_name: string; sku: string; quantity: number; unit_price: string; line_total: string }[];
        payments: { id: number; gateway: string; status: string; amount: string; currency: string; created_at: string | null }[];
        history: { id: number; from_status: string | null; to_status: string; note: string | null; actor: string | null; created_at: string | null }[];
        next_status: string | null;
    };
};

function money(amount: string, currency: string): string {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency }).format(Number(amount));
}

function title(value: string): string {
    return value.replaceAll('_', ' ');
}

export default function StoreOrderShow({ team, store, order }: Props) {
    const base = '/settings/teams/' + team.slug + '/stores/' + store.id;
    const address = [order.shipping.address, order.shipping.city, order.shipping.province, order.shipping.postal_code].filter(Boolean).join(', ');

    return (
        <>
            <Head title={order.order_number + ' — ' + store.name} />
            <div className="flex flex-col gap-6">
                <div className="m-2">
                    <Button asChild variant="ghost" size="sm" className="mb-2"><Link href={base + '/orders'}><ArrowLeft /> Back to orders</Link></Button>
                    <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                        <Heading variant="small" title={order.order_number} description={order.placed_at ? 'Placed ' + new Date(order.placed_at).toLocaleString('id-ID') : 'Order details'} />
                        <div className="flex gap-2">
                            <Badge>{title(order.status)}</Badge>
                            <Badge variant="secondary">Payment: {title(order.payment_status)}</Badge>
                        </div>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-[minmax(0,1.6fr)_minmax(280px,1fr)]">
                    <div className="space-y-4">
                        <section className="rounded-xl border">
                            <div className="border-b p-4"><h2 className="font-semibold">Items</h2></div>
                            <div className="divide-y">
                                {order.items.map((item) => (
                                    <div key={item.id} className="flex flex-wrap items-center justify-between gap-3 p-4">
                                        <div>
                                            <p className="font-medium">{item.product_name}</p>
                                            <p className="text-sm text-muted-foreground">{item.variant_name} · SKU {item.sku}</p>
                                            <p className="text-xs text-muted-foreground">{item.quantity} × {money(item.unit_price, order.currency)}</p>
                                        </div>
                                        <p className="font-medium tabular-nums">{money(item.line_total, order.currency)}</p>
                                    </div>
                                ))}
                            </div>
                            <dl className="space-y-2 border-t p-4 text-sm">
                                <div className="flex justify-between gap-3"><dt className="text-muted-foreground">Subtotal</dt><dd>{money(order.subtotal, order.currency)}</dd></div>
                                <div className="flex justify-between gap-3"><dt className="text-muted-foreground">Shipping</dt><dd>{money(order.shipping_amount, order.currency)}</dd></div>
                                <div className="flex justify-between gap-3"><dt className="text-muted-foreground">Discount</dt><dd>−{money(order.discount_amount, order.currency)}</dd></div>
                                <div className="flex justify-between gap-3 border-t pt-3 font-semibold"><dt>Total</dt><dd>{money(order.total, order.currency)}</dd></div>
                            </dl>
                        </section>

                        <section className="rounded-xl border p-4">
                            <h2 className="mb-3 font-semibold">Order status history</h2>
                            {order.history.length ? (
                                <ol className="space-y-4">
                                    {order.history.map((entry) => (
                                        <li key={entry.id} className="border-l-2 pl-4">
                                            <p className="font-medium">{entry.from_status ? title(entry.from_status) + ' → ' : ''}{title(entry.to_status)}</p>
                                            <p className="text-xs text-muted-foreground">{entry.actor ?? 'System'} · {entry.created_at ? new Date(entry.created_at).toLocaleString('id-ID') : '—'}</p>
                                            {entry.note ? <p className="mt-1 text-sm">{entry.note}</p> : null}
                                        </li>
                                    ))}
                                </ol>
                            ) : <p className="text-sm text-muted-foreground">No status history recorded.</p>}
                        </section>
                    </div>

                    <aside className="space-y-4">
                        {order.next_status ? (
                            <section className="rounded-xl border p-4">
                                <div className="mb-3 flex items-center gap-2"><Truck className="h-4 w-4 text-muted-foreground" /><h2 className="font-semibold">Fulfillment</h2></div>
                                <p className="mb-3 text-sm text-muted-foreground">Payment is confirmed. Update the order when this fulfillment step is complete.</p>
                                <Form action={base + '/orders/' + order.id + '/status'} method="patch" className="space-y-3">
                                    <input type="hidden" name="status" value={order.next_status} />
                                    <label className="block text-sm font-medium" htmlFor="status-note">Note (optional)</label>
                                    <textarea id="status-note" name="note" rows={3} maxLength={1000} placeholder="Add fulfillment or delivery details" className="w-full rounded-md border bg-background p-3 text-sm" />
                                    <Button type="submit" className="w-full">Mark as {title(order.next_status)}</Button>
                                </Form>
                            </section>
                        ) : null}

                        <section className="rounded-xl border p-4">
                            <h2 className="mb-3 font-semibold">Customer</h2>
                            <p className="font-medium">{order.customer.name}</p>
                            <p className="text-sm text-muted-foreground">{order.customer.email}</p>
                            <p className="text-sm text-muted-foreground">{order.customer.phone}</p>
                            {order.customer_note ? <p className="mt-3 border-t pt-3 text-sm">Note: {order.customer_note}</p> : null}
                        </section>

                        <section className="rounded-xl border p-4">
                            <h2 className="mb-3 font-semibold">Shipping</h2>
                            <p className="text-sm">{address}</p>
                            {order.shipping.destination_label ? <p className="mt-1 text-sm text-muted-foreground">{order.shipping.destination_label}</p> : null}
                            <p className="mt-2 text-sm text-muted-foreground">{[order.shipping.provider, order.shipping.courier, order.shipping.service].filter(Boolean).join(' · ') || 'Shipping service not recorded'}</p>
                        </section>

                        <section className="rounded-xl border p-4">
                            <h2 className="mb-3 font-semibold">Payments</h2>
                            {order.payments.length ? <div className="space-y-3">{order.payments.map((payment) => (
                                <div key={payment.id} className="border-t pt-3 first:border-0 first:pt-0">
                                    <p className="font-medium">{payment.gateway} · {title(payment.status)}</p>
                                    <p className="text-sm text-muted-foreground">{money(payment.amount, payment.currency)}</p>
                                    <p className="text-xs text-muted-foreground">{payment.created_at ? new Date(payment.created_at).toLocaleString('id-ID') : '—'}</p>
                                </div>
                            ))}</div> : <p className="text-sm text-muted-foreground">No payment transaction recorded.</p>}
                        </section>
                    </aside>
                </div>
            </div>
        </>
    );
}

StoreOrderShow.layout = (props: { team: { name: string; slug: string }; store: { name: string; id: number }; order: { order_number: string; id: number } }) => ({
    breadcrumbs: [
        { title: 'Teams', href: '/settings/teams' },
        { title: props.team.name, href: '/settings/teams/' + props.team.slug },
        { title: 'Stores', href: '/settings/teams/' + props.team.slug + '/stores' },
        { title: props.store.name, href: '/settings/teams/' + props.team.slug + '/stores/' + props.store.id },
        { title: 'Orders', href: '/settings/teams/' + props.team.slug + '/stores/' + props.store.id + '/orders' },
        { title: props.order.order_number, href: '/settings/teams/' + props.team.slug + '/stores/' + props.store.id + '/orders/' + props.order.id },
    ],
});
