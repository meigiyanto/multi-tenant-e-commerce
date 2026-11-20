import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, Globe2, Settings, Store as StoreIcon } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type StoreDomain = {
    id: number;
    domain: string;
    type: string;
    status: string;
    is_primary: boolean;
    verification_token: string | null;
    verified_at: string | null;
};

type Props = {
    team: { id: number; name: string; slug: string; status: string };
    store: {
        id: number;
        name: string;
        slug: string;
        status: string;
        timezone: string;
        currency: string;
        created_at: string | null;
        updated_at: string | null;
        domains: StoreDomain[];
    };
    canManageStore: boolean;
};

export default function StoreShow({ team, store, canManageStore }: Props) {
    const base = '/settings/teams/' + team.slug + '/stores/' + store.id;
    const storeNextStatus = store.status === 'draft' ? 'active' : store.status === 'active' ? 'suspended' : store.status === 'suspended' ? 'active' : null;

    return (
        <>
            <Head title={store.name + ' — ' + team.name} />
            <div className="flex flex-col gap-8">
                <div>
                    <Button asChild variant="ghost" size="sm" className="-ml-3 mb-2">
                        <Link href={'/settings/teams/' + team.slug + '/stores'}><ArrowLeft /> Back to stores</Link>
                    </Button>
                    <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                        <Heading variant="small" title={store.name} description={'Store details for ' + team.name + '.'} />
                        {canManageStore ? (
                            <Button asChild><Link href={base + '/settings'}><Settings /> Store settings</Link></Button>
                        ) : null}
                    </div>
                </div>

                <section className="rounded-xl border p-5">
                    <div className="mb-5 flex items-center gap-3">
                        <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-muted text-muted-foreground"><StoreIcon className="h-6 w-6" /></div>
                        <div className="min-w-0"><p className="font-semibold">{store.name}</p><p className="text-sm text-muted-foreground">/{store.slug}</p></div>
                        <Badge className="ml-auto" variant={store.status === 'active' ? 'default' : 'secondary'}>{store.status}</Badge>
                    </div>
                    <dl className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        <div><dt className="text-sm text-muted-foreground">Currency</dt><dd className="mt-1 font-medium">{store.currency}</dd></div>
                        <div><dt className="text-sm text-muted-foreground">Timezone</dt><dd className="mt-1 font-medium">{store.timezone}</dd></div>
                        <div><dt className="text-sm text-muted-foreground">Created</dt><dd className="mt-1 font-medium">{store.created_at ? new Date(store.created_at).toLocaleString() : '—'}</dd></div>
                    </dl>
                    {canManageStore && storeNextStatus ? (
                        <Form action={base + '/status'} method="patch" className="mt-5">
                            <input type="hidden" name="status" value={storeNextStatus} />
                            <Button size="sm" variant="outline" type="submit">{storeNextStatus === 'active' ? 'Activate store' : 'Suspend store'}</Button>
                        </Form>
                    ) : null}
                </section>

                <section className="space-y-4">
                    <div className="flex items-center justify-between gap-3">
                        <div><h2 className="font-semibold">Custom domains</h2><p className="text-sm text-muted-foreground">Verify domain ownership with a DNS TXT record before activation.</p></div>
                        <Badge variant="secondary">{store.domains.length}</Badge>
                    </div>
                    {canManageStore ? (
                        <Form action={base + '/domains'} method="post" className="grid gap-3 rounded-xl border p-4 sm:grid-cols-[1fr_auto]">
                            {({ errors, processing }) => (
                                <>
                                    <div className="grid gap-2"><Label htmlFor="custom-domain">Domain name</Label><Input id="custom-domain" name="domain" placeholder="shop.example.com" required maxLength={253} /><InputError message={errors.domain} /></div>
                                    <div className="flex items-end"><Button type="submit" disabled={processing}><Globe2 /> Add domain</Button></div>
                                </>
                            )}
                        </Form>
                    ) : null}
                    {store.domains.length ? (
                        <div className="divide-y rounded-xl border">
                            {store.domains.map((domain) => (
                                <div key={domain.id} className="space-y-3 p-4">
                                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                        <div className="flex items-center gap-3"><Globe2 className="h-4 w-4 text-muted-foreground" /><div><p className="font-medium">{domain.domain}</p><p className="text-xs text-muted-foreground">{domain.type}{domain.is_primary ? ' · Primary' : ''}</p></div></div>
                                        <Badge variant={domain.status === 'active' ? 'default' : 'secondary'}>{domain.status}</Badge>
                                    </div>
                                    {canManageStore && domain.status === 'pending' ? (
                                        <Form action={base + '/domains/' + domain.id + '/verification'} method="post"><Button size="sm" variant="outline" type="submit">Generate DNS challenge</Button></Form>
                                    ) : null}
                                    {canManageStore && domain.status === 'verifying' && domain.verification_token ? (
                                        <div className="space-y-2 rounded-lg bg-muted/50 p-3">
                                            <p className="text-sm font-medium">Add this DNS TXT record at your DNS provider</p>
                                            <dl className="grid gap-2 text-sm sm:grid-cols-2">
                                                <div><dt className="text-muted-foreground">Host / Name</dt><dd className="break-all font-mono">_store-verification.{domain.domain}</dd></div>
                                                <div><dt className="text-muted-foreground">TXT value</dt><dd className="break-all font-mono">{domain.verification_token}</dd></div>
                                            </dl>
                                            <Form action={base + '/domains/' + domain.id + '/verification/check'} method="post"><Button size="sm" type="submit">Check DNS record</Button></Form>
                                        </div>
                                    ) : null}
                                    {canManageStore && domain.status === 'verified' ? (
                                        <Form action={base + '/domains/' + domain.id + '/status'} method="patch">
                                            <input type="hidden" name="status" value="active" />
                                            <Button size="sm" type="submit">Activate domain</Button>
                                        </Form>
                                    ) : null}
                                    {canManageStore && domain.status === 'active' ? (
                                        <Form action={base + '/domains/' + domain.id + '/status'} method="patch">
                                            <input type="hidden" name="status" value="disabled" />
                                            <Button size="sm" variant="outline" type="submit">Disable domain</Button>
                                        </Form>
                                    ) : null}
                                    {domain.verified_at ? <p className="text-xs text-muted-foreground">Verified {new Date(domain.verified_at).toLocaleString()}</p> : null}
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="rounded-xl border border-dashed p-8 text-center"><Globe2 className="mx-auto mb-3 h-7 w-7 text-muted-foreground" /><p className="font-medium">No domains connected</p><p className="mt-1 text-sm text-muted-foreground">Add a custom domain to begin ownership verification.</p></div>
                    )}
                </section>
            </div>
        </>
    );
}

StoreShow.layout = (props: { team: { name: string; slug: string }; store: { name: string; id: number } }) => ({
    breadcrumbs: [
        { title: 'Teams', href: '/settings/teams' },
        { title: props.team.name, href: '/settings/teams/' + props.team.slug },
        { title: 'Stores', href: '/settings/teams/' + props.team.slug + '/stores' },
        { title: props.store.name, href: '/settings/teams/' + props.team.slug + '/stores/' + props.store.id },
    ],
});
