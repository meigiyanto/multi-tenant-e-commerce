import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, Globe2, Plus, Store as StoreIcon } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type StoreRecord = {
    id: number;
    name: string;
    slug: string;
    status: string;
    timezone: string;
    currency: string;
    domains_count: number;
    created_at: string | null;
};

type Props = {
    team: { id: number; name: string; slug: string };
    canManageStores: boolean;
    stores: StoreRecord[];
};

export default function StoresIndex({ team, canManageStores, stores }: Props) {
    return (
        <>
            <Head title={`Stores — ${team.name}`} />
            <div className="flex flex-col space-y-6 gap-8">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <Button asChild variant="ghost" size="sm" className="-ml-3 mb-2">
                            <Link href={`/settings/teams/${team.slug}`}><ArrowLeft /> Back to team</Link>
                        </Button>
                        <Heading variant="small" title="Stores" description={`Manage storefronts owned by ${team.name}.`} />
                    </div>
                    <Badge variant="secondary">{stores.length} {stores.length === 1 ? 'store' : 'stores'}</Badge>
                </div>

                {canManageStores ? (
                    <section className="space-y-4 rounded-xl border p-5">
                        <div className="flex items-center gap-2">
                            <Plus className="h-5 w-5" />
                            <h2 className="font-semibold">Create a store</h2>
                        </div>
                        <p className="text-sm text-muted-foreground">Create a storefront workspace. It starts as a draft and can be connected to a domain in a later step.</p>
                        <Form action={`/settings/teams/${team.slug}/stores`} method="post" className="grid gap-4 md:grid-cols-2">
                            {({ errors, processing }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="store-name">Store name</Label>
                                        <Input id="store-name" name="name" placeholder="My Online Store" required maxLength={255} />
                                        <InputError message={errors.name} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="store-slug">Store slug</Label>
                                        <Input id="store-slug" name="slug" placeholder="my-online-store" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" required maxLength={255} />
                                        <InputError message={errors.slug} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="store-timezone">Timezone</Label>
                                        <Input id="store-timezone" name="timezone" defaultValue="Asia/Jakarta" required />
                                        <InputError message={errors.timezone} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="store-currency">Currency</Label>
                                        <Input id="store-currency" name="currency" defaultValue="IDR" maxLength={3} minLength={3} required />
                                        <InputError message={errors.currency} />
                                    </div>
                                    <div className="md:col-span-2">
                                        <Button type="submit" disabled={processing}><Plus /> {processing ? 'Creating…' : 'Create store'}</Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </section>
                ) : null}

                <section className="space-y-4">
                    <h2 className="font-semibold">Your stores</h2>
                    {stores.length > 0 ? (
                        <div className="space-y-3">
                            {stores.map((store) => (
                                <article key={store.id} className="flex flex-col gap-3 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="flex items-center gap-3">
                                        <div className="flex h-11 w-11 items-center justify-center rounded-lg bg-muted"><StoreIcon className="h-5 w-5 text-muted-foreground" /></div>
                                        <div>
                                            <h3 className="font-medium">{store.name}</h3>
                                            <p className="text-sm text-muted-foreground">/{store.slug} · {store.timezone} · {store.currency}</p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <Badge variant={store.status === 'active' ? 'default' : 'secondary'}>{store.status}</Badge>
                                        <span className="flex items-center gap-1 text-sm text-muted-foreground"><Globe2 className="h-4 w-4" /> {store.domains_count} domains</span>
                                    </div>
                                </article>
                            ))}
                        </div>
                    ) : (
                        <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed p-10 text-center">
                            <StoreIcon className="h-8 w-8 text-muted-foreground" />
                            <div>
                                <p className="font-medium">No stores yet</p>
                                <p className="text-sm text-muted-foreground">Create your first store to start building its storefront.</p>
                            </div>
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

StoresIndex.layout = (props: { team: { name: string; slug: string } }) => ({
    breadcrumbs: [
        { title: 'Teams', href: '/settings/teams' },
        { title: props.team.name, href: `/settings/teams/${props.team.slug}` },
        { title: 'Stores', href: `/settings/teams/${props.team.slug}/stores` },
    ],
});
