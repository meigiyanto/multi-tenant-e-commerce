import { Head } from '@inertiajs/react';
import type { CSSProperties } from 'react';
import { Mail, MapPin, Phone, Store } from 'lucide-react';

type NavigationItem = { label: string; url: string };
type Props = {
    store: {
        name: string;
        slug: string;
        currency: string;
        description: string;
        logo_url: string | null;
        banner_url: string | null;
        primary_color: string;
        accent_color: string;
        navigation: NavigationItem[];
        contact: { email: string; phone: string; address: string };
    };
    domain: string | null;
};

export default function StorefrontIndex({ store, domain }: Props) {
    return (
        <>
            <Head title={store.name} />
            <div className="min-h-screen bg-white text-slate-900" style={{ '--store-primary': store.primary_color, '--store-accent': store.accent_color } as CSSProperties}>
                <header className="border-b border-slate-200 bg-white">
                    <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-5 py-4 sm:px-8">
                        <a href="/" className="flex min-w-0 items-center gap-3">
                            {store.logo_url ? <img src={store.logo_url} alt={store.name + ' logo'} className="h-11 w-11 rounded-lg object-contain" /> : <span className="flex h-11 w-11 items-center justify-center rounded-lg text-white" style={{ backgroundColor: store.primary_color }}><Store className="h-6 w-6" /></span>}
                            <span className="truncate text-xl font-bold" style={{ color: store.accent_color }}>{store.name}</span>
                        </a>
                        {store.navigation.length > 0 ? <nav aria-label="Main navigation" className="flex flex-wrap items-center gap-x-6 gap-y-2">{store.navigation.map((item, index) => <a key={index} href={item.url} className="text-sm font-medium text-slate-600 transition hover:text-slate-950">{item.label}</a>)}</nav> : null}
                    </div>
                </header>
                <main>
                    {store.banner_url ? <section className="relative"><img src={store.banner_url} alt={store.name + ' banner'} className="h-56 w-full object-cover sm:h-80 lg:h-[28rem]" /><div className="absolute inset-0 bg-black/25" /><div className="absolute inset-0 mx-auto flex max-w-7xl items-end px-5 pb-8 sm:px-8 sm:pb-12"><div className="max-w-2xl text-white drop-shadow"><h1 className="text-3xl font-bold sm:text-5xl">{store.name}</h1>{store.description ? <p className="mt-3 max-w-xl text-sm text-white/90 sm:text-base">{store.description}</p> : null}</div></div></section> : <section className="mx-auto max-w-7xl px-5 py-16 sm:px-8 sm:py-24"><p className="text-xs font-semibold uppercase tracking-[0.2em]" style={{ color: store.primary_color }}>Welcome to our store</p><h1 className="mt-3 max-w-3xl text-4xl font-bold tracking-tight sm:text-6xl" style={{ color: store.accent_color }}>{store.name}</h1>{store.description ? <p className="mt-5 max-w-2xl text-lg leading-8 text-slate-600">{store.description}</p> : <p className="mt-5 max-w-2xl text-lg leading-8 text-slate-600">Welcome. We are preparing our online catalog. Please check back soon.</p>}<div className="mt-8 inline-flex rounded-lg px-5 py-3 text-sm font-semibold text-white" style={{ backgroundColor: store.primary_color }}>More products coming soon</div></section>}
                    {!store.banner_url && store.navigation.length > 0 ? <div className="mx-auto max-w-7xl px-5 pb-12 sm:px-8"><div className="h-px bg-slate-200" /></div> : null}
                </main>
                <footer className="border-t border-slate-200 bg-slate-50">
                    <div className="mx-auto grid max-w-7xl gap-8 px-5 py-10 sm:grid-cols-2 sm:px-8">
                        <div><h2 className="font-semibold" style={{ color: store.accent_color }}>{store.name}</h2>{store.description ? <p className="mt-2 max-w-md text-sm leading-6 text-slate-600">{store.description}</p> : null}{domain ? <p className="mt-3 text-xs text-slate-500">{domain}</p> : null}</div>
                        <div className="space-y-3 text-sm text-slate-600 sm:justify-self-end">{store.contact.email ? <a href={'mailto:' + store.contact.email} className="flex items-center gap-2"><Mail className="h-4 w-4" />{store.contact.email}</a> : null}{store.contact.phone ? <a href={'tel:' + store.contact.phone} className="flex items-center gap-2"><Phone className="h-4 w-4" />{store.contact.phone}</a> : null}{store.contact.address ? <p className="flex items-start gap-2"><MapPin className="mt-0.5 h-4 w-4 shrink-0" /><span>{store.contact.address}</span></p> : null}</div>
                    </div>
                    <div className="border-t border-slate-200 px-5 py-4 text-center text-xs text-slate-500">© {new Date().getFullYear()} {store.name}. All rights reserved.</div>
                </footer>
            </div>
        </>
    );
}
