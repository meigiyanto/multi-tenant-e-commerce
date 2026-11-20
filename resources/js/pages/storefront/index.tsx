import { Head } from '@inertiajs/react';
import { Store } from 'lucide-react';

type Props = {
    store: { name: string; slug: string; currency: string };
    domain: string | null;
};

export default function StorefrontIndex({ store, domain }: Props) {
    return (
        <>
            <Head title={store.name} />
            <main className="flex min-h-screen flex-col items-center justify-center bg-background px-6 py-16 text-center">
                <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                    <Store className="h-8 w-8" />
                </div>
                <p className="mt-6 text-sm font-medium uppercase tracking-widest text-muted-foreground">Online store</p>
                <h1 className="mt-3 text-4xl font-bold tracking-tight">{store.name}</h1>
                <p className="mt-3 max-w-lg text-muted-foreground">
                    This storefront is active. Products and shopping features can be configured from the store dashboard.
                </p>
                {domain ? <p className="mt-6 rounded-full border px-4 py-2 text-sm text-muted-foreground">{domain}</p> : null}
            </main>
        </>
    );
}
