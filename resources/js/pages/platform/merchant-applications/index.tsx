import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';

type Application = { id: number; business_name: string; business_description: string | null; status: string; review_notes: string | null; created_at: string | null; user: { id: number; name: string; email: string }; reviewer: { id: number; name: string } | null };
type Paginated<T> = { data: T[]; links: { url: string | null; label: string; active: boolean }[] };

export default function MerchantApplications({ applications }: { applications: Paginated<Application> }) {
    return <>
        <Head title="Merchant applications" />
        <div className="space-y-6">
            <Heading variant="small" title="Merchant applications" description="Review and approve users who want to create merchant workspaces." />
            <div className="space-y-4">
                {applications.data.map((application) => <section key={application.id} className="space-y-3 rounded-lg border p-5">
                    <div className="flex flex-wrap items-start justify-between gap-3"><div><h2 className="font-semibold">{application.business_name}</h2><p className="text-sm text-muted-foreground">{application.user.name} · {application.user.email}</p></div><span className="rounded-full bg-muted px-3 py-1 text-xs font-medium capitalize">{application.status}</span></div>
                    <p className="whitespace-pre-wrap text-sm">{application.business_description || 'No business description provided.'}</p>
                    {application.status === 'pending' ? <div className="space-y-3">
                        <Form action={`/platform/admin/merchant-applications/${application.id}`} method="patch" className="space-y-2">
                            <input type="hidden" name="status" value="approved" />
                            <textarea name="review_notes" defaultValue={application.review_notes ?? ''} rows={2} placeholder="Optional approval notes" className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm" />
                            <Button type="submit">Approve application</Button>
                        </Form>
                        <Form action={`/platform/admin/merchant-applications/${application.id}`} method="patch" className="space-y-2">
                            <input type="hidden" name="status" value="rejected" />
                            <textarea name="review_notes" rows={2} placeholder="Reason for rejection (optional)" className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm" />
                            <Button type="submit" variant="destructive">Reject application</Button>
                        </Form>
                    </div> : <p className="text-xs text-muted-foreground">Reviewed by {application.reviewer?.name ?? 'Platform'}.</p>}
                </section>)}
                {applications.data.length === 0 ? <p className="py-10 text-center text-sm text-muted-foreground">There are no merchant applications yet.</p> : null}
            </div>
        </div>
    </>;
}
