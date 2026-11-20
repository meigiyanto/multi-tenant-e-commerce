import { Head } from '@inertiajs/react';
import { Mail, MapPin, Package, Phone, Store } from 'lucide-react';

type NavigationItem = { label: string; url: string };
type StoreProduct = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    category: string | null;
    price: string;
    compare_at_price: string | null;
    image_url: string | null;
    in_stock: boolean;
    variants: { id: number; name: string; options: Record<string, string>; price: string; stock: number | null }[];
};
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
    products: StoreProduct[];
    domain: string | null;
};

export default function StorefrontIndex({ store, products, domain }: Props) {
    const formatPrice = (price: string | number) => new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: store.currency,
        maximumFractionDigits: 2,
    }).format(Number(price));

    return (
        <>
            <Head title={store.name} />
            <div className="min-h-screen bg-white text-slate-900">
                <header className="border-b border-slate-200 bg-white">
                    <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-5 py-4 sm:px-8">
                        <a href="/" className="flex min-w-0 items-center gap-3">
                            {store.logo_url ? <img src={store.logo_url} alt={store.name + ' logo'} className="h-11 w-11 rounded-md object-contain" /> : <span className="flex h-11 w-11 items-center justify-center rounded-md text-white" style={{ backgroundColor: store.primary_color }}><Store className="h-6 w-6" /></span>}
                            <span className="truncate text-xl font-bold" style={{ color: store.accent_color }}>{store.name}</span>
                        </a>
                        {store.navigation.length > 0 ? <nav aria-label="Main navigation" className="flex flex-wrap items-center gap-x-6 gap-y-2">{store.navigation.map((item, index) => <a key={index} href={item.url} className="text-sm font-medium text-slate-600 transition hover:text-slate-950">{item.label}</a>)}</nav> : null}
                    </div>
                </header>
                <main>
                    {store.banner_url ? <section className="relative"><img src={store.banner_url} alt={store.name + ' banner'} className="h-56 w-full object-cover sm:h-80 lg:h-[28rem]" /><div className="absolute inset-0 bg-black/25" /><div className="absolute inset-0 mx-auto flex max-w-7xl items-end px-5 pb-8 sm:px-8 sm:pb-12"><div className="max-w-2xl text-white drop-shadow"><h1 className="text-3xl font-bold sm:text-5xl">{store.name}</h1>{store.description ? <p className="mt-3 max-w-xl text-sm text-white/90 sm:text-base">{store.description}</p> : null}</div></div></section> : <section className="mx-auto max-w-7xl px-5 py-16 sm:px-8 sm:py-24"><p className="text-xs font-semibold uppercase tracking-[0.2em]" style={{ color: store.primary_color }}>Welcome to our store</p><h1 className="mt-3 max-w-3xl text-4xl font-bold tracking-tight sm:text-6xl" style={{ color: store.accent_color }}>{store.name}</h1>{store.description ? <p className="mt-5 max-w-2xl text-lg leading-8 text-slate-600">{store.description}</p> : <p className="mt-5 max-w-2xl text-lg leading-8 text-slate-600">Explore our latest products below.</p>}</section>}
                    <section className="mx-auto max-w-7xl px-5 pb-16 sm:px-8">
                        <div className="mb-6 flex items-end justify-between gap-4"><div><p className="text-xs font-semibold uppercase tracking-[0.18em]" style={{ color: store.primary_color }}>Our collection</p><h2 className="mt-2 text-2xl font-bold sm:text-3xl" style={{ color: store.accent_color }}>Products</h2></div><span className="text-sm text-slate-500">{products.length} products</span></div>
                        {products.length ? <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">{products.map((product) => <article key={product.id} className="overflow-hidden rounded-md border border-slate-200 bg-white">
                            <div className="aspect-[4/3] bg-slate-100">{product.image_url ? <img src={product.image_url} alt={product.name} className="h-full w-full object-cover" /> : <div className="flex h-full items-center justify-center text-slate-400"><Package className="h-10 w-10" /></div>}</div>
                            <div className="space-y-3 p-4"><div>{product.category ? <p className="text-xs font-medium uppercase tracking-wide text-slate-500">{product.category}</p> : null}<h3 className="mt-1 font-semibold text-slate-900">{product.name}</h3>{product.description ? <p className="line-clamp-2 text-sm text-slate-600">{product.description}</p> : null}</div>
                                <div className="flex items-center gap-2"><p className="font-semibold" style={{ color: store.primary_color }}>{formatPrice(product.price)}</p>{product.compare_at_price && Number(product.compare_at_price) > Number(product.price) ? <p className="text-xs text-slate-400 line-through">{formatPrice(product.compare_at_price)}</p> : null}</div>
                                {product.variants.length ? <div className="space-y-1 border-t border-slate-100 pt-3">{product.variants.slice(0, 3).map((variant) => <div key={variant.id} className="flex justify-between gap-2 text-xs text-slate-500"><span>{variant.name} {Object.values(variant.options).filter(Boolean).join(' / ')}</span><span className="shrink-0">{formatPrice(variant.price)}{variant.stock === null ? '' : ' · ' + (variant.stock > 0 ? variant.stock + ' available' : 'Out of stock')}</span>}</div>)}</div> : null}
                                <p className={'text-xs font-medium ' + (product.in_stock ? 'text-emerald-700' : 'text-red-600')}>{product.in_stock ? 'In stock' : 'Out of stock'}</p>
                            </div>
                        </article>)}</div> : <div className="rounded-md border border-dashed border-slate-300 px-6 py-14 text-center"><Package className="mx-auto mb-3 h-8 w-8 text-slate-400" /><h3 className="font-semibold">No products published yet</h3><p className="mt-1 text-sm text-slate-500">Please check back later for new products.</p></div>}
                    </section>
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
