import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    team: { id: number; name: string; slug: string };
    store: { id: number; name: string; slug: string; status: string; timezone: string; currency: string };
};

export default function StoreSettings({ team, store }: Props) {
    const baseUrl = '/settings/teams/' + team.slug + '/stores/' + store.id;

    return (
        <>
            <Head title={'Settings — ' + store.name} />
            <div className="flex flex-col gap-8">
                <div>
                    <Button asChild variant="ghost" size="sm" className="-ml-3 mb-2">
                        <Link href={baseUrl}><ArrowLeft /> Back to store</Link>
                    </Button>
                    <Heading variant="small" title="Store settings" description={'Configure ' + store.name + '.'} />
                </div>

                <section className="max-w-3xl rounded-xl border p-5">
                    <Form action={baseUrl} method="patch" className="space-y-6">
                        {({ errors, processing }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="store-name">Store name</Label>
                                    <Input id="store-name" name="name" defaultValue={store.name} maxLength={255} required />
                                    <InputError message={errors.name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="store-slug">Store slug</Label>
                                    <Input id="store-slug" name="slug" defaultValue={store.slug} pattern="[a-z0-9]+(?:-[a-z0-9]+)*" maxLength={255} required />
                                    <p className="text-xs text-muted-foreground">Use lowercase letters, numbers, and hyphens. The slug must be unique within this Team.</p>
                                    <InputError message={errors.slug} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="store-timezone">Timezone</Label>
                                    <Input id="store-timezone" name="timezone" defaultValue={store.timezone} required />
                                    <InputError message={errors.timezone} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="store-currency">Currency</Label>
                                    <Input id="store-currency" name="currency" defaultValue={store.currency} minLength={3} maxLength={3} required />
                                    <p className="text-xs text-muted-foreground">Use a three-letter uppercase currency code, such as IDR or USD.</p>
                                    <InputError message={errors.currency} />
                                </div>
                                <div className="flex flex-wrap items-center gap-3 border-t pt-5">
                                    <Button type="submit" disabled={processing}><Save /> {processing ? 'Saving…' : 'Save settings'}</Button>
                                    <Button asChild type="button" variant="outline"><Link href={baseUrl}>Cancel</Link></Button>
                                </div>
                            </>
                        )}
                    </Form>
                </section>

                <section className="max-w-3xl rounded-xl border p-5">
                    <h2 className="font-semibold">Store status</h2>
                    <p className="mt-1 text-sm text-muted-foreground">Current status: <span className="font-medium capitalize text-foreground">{store.status}</span>.</p>
                    <p className="mt-2 text-sm text-muted-foreground">Status transitions are intentionally not editable here. Activation and suspension need a dedicated lifecycle flow that validates storefront and domain readiness.</p>
                </section>
            </div>
        </>
    );
}

StoreSettings.layout = (props: { team: { name: string; slug: string }; store: { name: string; id: number } }) => ({
    breadcrumbs: [
        { title: 'Teams', href: '/settings/teams' },
        { title: props.team.name, href: '/settings/teams/' + props.team.slug },
        { title: 'Stores', href: '/settings/teams/' + props.team.slug + '/stores' },
        { title: props.store.name, href: '/settings/teams/' + props.team.slug + '/stores/' + props.store.id },
        { title: 'Settings', href: '/settings/teams/' + props.team.slug + '/stores/' + props.store.id + '/settings' },
    ],
});
