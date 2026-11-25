import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Application = { business_name: string; business_description: string | null; status: string; review_notes: string | null; reviewed_at: string | null } | null;

export default function MerchantApplication({ application, canApply }: { application: Application; canApply: boolean }) {
    return <>
        <Head title="Become a merchant" />
        <div className="mx-auto max-w-3xl space-y-6">
            <Heading variant="small" title="Become a merchant" description="Apply to create a merchant workspace and launch your own storefront." />
            {application ? <section className="space-y-2 rounded-lg border p-5">
                <p className="text-sm text-muted-foreground">Application status</p>
                <p className="text-lg font-semibold capitalize">{application.status}</p>
                <p className="font-medium">{application.business_name}</p>
                {application.review_notes ? <p className="text-sm text-muted-foreground">Review notes: {application.review_notes}</p> : null}
                {application.status === 'approved' ? <p className="text-sm">Your application is approved. You can now create a merchant workspace from the Teams page.</p> : null}
                {application.status === 'pending' ? <p className="text-sm text-muted-foreground">Your application is waiting for platform review. You cannot create a merchant workspace until it is approved.</p> : null}
            </section> : null}
            {canApply ? <Form action="/merchant/application" method="post" className="space-y-5 rounded-lg border p-5">
                {({ errors, processing }) => <>
                    <div className="grid gap-2"><Label htmlFor="business_name">Business name</Label><Input id="business_name" name="business_name" defaultValue={application?.business_name ?? ''} required /><InputError message={errors.business_name} /></div>
                    <div className="grid gap-2"><Label htmlFor="business_description">Business description</Label><textarea id="business_description" name="business_description" defaultValue={application?.business_description ?? ''} rows={5} placeholder="Describe the products or services you plan to sell." className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm" /><InputError message={errors.business_description} /></div>
                    <p className="text-sm text-muted-foreground">After approval, you can create a workspace, invite team members, and create stores such as Nuvora, BelanjaKu, or Velora.</p>
                    <Button disabled={processing}>{processing ? 'Submitting...' : application ? 'Resubmit application' : 'Submit application'}</Button>
                </>}
            </Form> : null}
        </div>
    </>;
}
