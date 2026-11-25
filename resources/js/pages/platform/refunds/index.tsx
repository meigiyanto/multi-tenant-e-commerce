import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import OrderNumberCopy from '@/components/order-number-copy';
import { Button } from '@/components/ui/button';

type Refund = {
    id: number; status: string; reason: string; admin_notes: string | null; refund_reference: string | null;
    result_notes: string | null; created_at: string | null;
    order: { order_number: string; total: string; currency: string; status: string; payment_status: string };
    customer: { name: string; email: string };
    payment: { gateway: string; gateway_order_id: string; gateway_transaction_id: string | null; status: string; payment_type: string | null };
};
type Paginated<T> = { data: T[]; links: { url: string | null; label: string; active: boolean }[] };

export default function RefundRequests({ refundRequests }: { refundRequests: Paginated<Refund> }) {
    return <>
        <Head title="Refund requests" />
        <div className="space-y-6">
            <Heading variant="small" title="Refund requests" description="Review customer requests and record verified manual refund outcomes." />
            {refundRequests.data.map((refund) => <section key={refund.id} className="space-y-3 rounded-lg border p-5">
                <div className="flex flex-wrap justify-between gap-3"><div><div className="flex flex-wrap items-center gap-2"><h2 className="font-semibold">{refund.order.order_number}</h2><OrderNumberCopy value={refund.order.order_number} /></div><p className="text-sm text-muted-foreground">{refund.customer.name} · {refund.customer.email}</p><p className="text-sm text-muted-foreground">{refund.order.total} {refund.order.currency} · {refund.payment.gateway} · {refund.payment.payment_type ?? 'Payment method unavailable'}</p></div><span className="rounded-full bg-muted px-3 py-1 text-xs capitalize">{refund.status.replaceAll('_', ' ')}</span></div>
                <div className="text-sm"><p className="font-medium">Customer reason</p><p className="whitespace-pre-wrap text-muted-foreground">{refund.reason}</p></div>
                <p className="text-xs text-muted-foreground">Gateway order ID: {refund.payment.gateway_order_id} · Transaction ID: {refund.payment.gateway_transaction_id ?? 'Unavailable'}</p>
                {refund.status === 'requested' ? <div className="flex flex-wrap gap-2">
                    <Form action={`/platform/admin/refunds/${refund.id}`} method="patch"><input type="hidden" name="status" value="under_review" /><Button type="submit">Start review</Button></Form>
                    <Form action={`/platform/admin/refunds/${refund.id}`} method="patch" className="flex flex-wrap gap-2"><input type="hidden" name="status" value="rejected" /><input name="admin_notes" placeholder="Reason for rejection" className="rounded-md border bg-background px-3 py-2 text-sm" /><Button type="submit" variant="destructive">Reject</Button></Form>
                </div> : null}
                {refund.status === 'under_review' ? <Form action={`/platform/admin/refunds/${refund.id}`} method="patch" className="flex flex-wrap items-end gap-2"><input type="hidden" name="status" value="processing" /><label className="text-sm">Admin notes<input name="admin_notes" defaultValue={refund.admin_notes ?? ''} className="mt-1 block rounded-md border bg-background px-3 py-2" placeholder="Review notes" /></label><Button type="submit">Approve for processing</Button></Form> : null}
                {refund.status === 'processing' ? <div className="space-y-3 rounded-md bg-muted/40 p-3"><p className="text-sm">For Midtrans payment methods that support refunds, complete the refund through an enabled official Midtrans refund channel after the transaction is settled. For payment methods that Midtrans does not support for refunds, arrange the return directly to the customer. This platform page records the verified result; it does not move money by itself.</p>
                    <Form action={`/platform/admin/refunds/${refund.id}`} method="patch" className="flex flex-wrap items-end gap-2"><input type="hidden" name="status" value="refunded" /><label className="text-sm">Refund reference<input name="refund_reference" defaultValue={refund.refund_reference ?? ''} className="mt-1 block rounded-md border bg-background px-3 py-2" /></label><label className="text-sm">Payout/result details<input name="result_notes" defaultValue={refund.result_notes ?? ''} className="mt-1 block rounded-md border bg-background px-3 py-2" /></label><Button type="submit">Confirm refund completed</Button></Form>
                    <Form action={`/platform/admin/refunds/${refund.id}`} method="patch" className="flex flex-wrap gap-2"><input type="hidden" name="status" value="rejected" /><input name="admin_notes" placeholder="Reason for rejection" className="rounded-md border bg-background px-3 py-2 text-sm" /><Button type="submit" variant="destructive">Reject</Button></Form>
                </div> : null}
                {refund.status === 'refunded' || refund.status === 'rejected' ? <div className="text-sm text-muted-foreground"><p>Reference: {refund.refund_reference ?? 'Not provided'}</p><p>Result: {refund.result_notes ?? refund.admin_notes ?? 'No additional details.'}</p></div> : null}
            </section>)}
            {refundRequests.data.length === 0 ? <p className="py-10 text-center text-sm text-muted-foreground">There are no refund requests.</p> : null}
            <div className="flex flex-wrap gap-2">{refundRequests.links.map((link, index) => <a key={index} href={link.url ?? '#'} className={`rounded border px-3 py-2 text-sm ${link.active ? 'bg-muted font-semibold' : ''}`} dangerouslySetInnerHTML={{ __html: link.label }} />)}</div>
        </div>
    </>;
}
