import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Globe2, Settings, Store as StoreIcon } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type StoreDomain = {
    id: number;
    domain: string;
    type: string;
    status: string;
    is_primary: boolean;
    verified_at: string | null;
};

type Props = {
    team: { id: number; name: string; slug: string };
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
                            <Button asChild>
                                <Link href={'/settings/teams/' + team.slug + '/stores/' + store.id + '/settings'}><Settings /> Store settings</Link>
                            </Button>
                        ) : null}
                    </div>
                </div>

                <section className="rounded-xl border p-5">
                    <div className="mb-5 flex items-center gap-3">
                        <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-muted"><StoreIcon className="h-6 w-6 text-muted-foreground" /></div>
                        <div className="min-w-0">
                            <p className="font-semibold">{store.name}</p>
                            <p className="text-sm text-muted-foreground">/{store.slug}</p>
                        </div>
                        <Badge className="ml-auto" variant={store.status === 'active' ? 'default' : 'secondary'}>{store.status}</Badge>
                    </div>
                    <dl className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        <div><dt className="text-sm text-muted-foreground">Currency</dt><dd className="mt-1 font-medium">{store.currency}</dd></div>
                        <div><dt className="text-sm text-muted-foreground">Timezone</dt><dd className="mt-1 font-medium">{store.timezone}</dd></div>
                        <div><dt className="text-sm text-muted-foreground">Created</dt><dd className="mt-1 font-medium">{store.created_at ? new Date(store.created_at).toLocaleString() : '—'}</dd></div>
                    </dl>
                </section>

                <section className="space-y-4">
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <h2 className="font-semibold">Domains</h2>
                            <p className="text-sm text-muted-foreground">Domains connected to this storefront.</p>
                        </div>
                        <Badge variant="secondary">{store.domains.length}</Badge>
                    </div>
                    {store.domains.length ? (
                        <div className="divide-y rounded-xl border">
                            {store.domains.map((domain) => (
                                <div key={domain.id} className="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="flex items-center gap-3">
                                        <Globe2 className="h-4 w-4 text-muted-foreground" />
                                        <div>
                                            <p className="font-medium">{domain.domain}</p>
                                            <p className="text-xs text-muted-foreground">{domain.type}{domain.is_primary ? ' · Primary' : ''}</p>
                                        </div>
                                    </div>
                                    <Badge variant={domain.status === 'active' && domain.verified_at ? 'default' : 'secondary'}>{domain.verified_at ? domain.status : 'unverified'}</Badge>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="rounded-xl border border-dashed p-8 text-center">
                            <Globe2 className="mx-auto mb-3 h-7 w-7 text-muted-foreground" />
                            <p className="font-medium">No domains connected</p>
                            <p className="mt-1 text-sm text-muted-foreground">Domain management will be available as a separate step.</p>
                        </div>
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
