import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { ArrowLeft, Check, Minus, Plus, ShoppingBag, LoaderCircle } from 'lucide-react';

type Variant = { id: number; name: string; options: Record<string, string>; price: string; stock: number | null };
type Product = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    category: string | null;
    price: string;
    compare_at_price: string | null;
    images: { url: string; alt_text: string; is_primary: boolean }[];
    variants: Variant[];
};
type RelatedProduct = { id: number; name: string; slug: string; price: string; image_url: string | null; in_stock: boolean };
type Props = {
    store: { name: string; currency: string; primary_color: string; accent_color: string };
    product: Product;
    related_products: RelatedProduct[];
    cart_count: number;
};

export default function StorefrontProduct({ store, product, related_products, cart_count }: Props) {
    const availableVariant = product.variants.find((variant) => variant.stock === null || variant.stock > 0);
    const [variantId, setVariantId] = useState<number | null>(availableVariant?.id ?? product.variants[0]?.id ?? null);
    const [quantity, setQuantity] = useState(1);
    const [imageIndex, setImageIndex] = useState(Math.max(0, product.images.findIndex((image) => image.is_primary)));
    const [adding, setAdding] = useState(false);
    const [cartCount, setCartCount] = useState(cart_count);
    useEffect(() => setCartCount(cart_count), [cart_count]);
    const selected = product.variants.find((variant) => variant.id === variantId);
    const money = (amount: string | number) => new Intl.NumberFormat('id-ID', {
        style: 'currency', currency: store.currency, maximumFractionDigits: 0,
    }).format(Number(amount));
    const availableStock = selected?.stock ?? null;
    const canAdd = Boolean(selected && (availableStock === null || availableStock > 0) && quantity <= (availableStock ?? 99));

    const addToCart = () => {
        if (!selected || !canAdd) return;
        setAdding(true);
        router.post('/cart/items', { product_variant_id: selected.id, quantity }, {
            preserveScroll: true,
            onSuccess: () => {},
            onFinish: () => setAdding(false),
        });
    };

    return <>
        <Head title={product.name + ' · ' + store.name}>
            <meta name="description" content={product.description?.slice(0, 155) || product.name + ' from ' + store.name} />
        </Head>
        <div className="min-h-screen bg-[#faf9f6] text-slate-900">
            <header className="border-b border-black/5">
                <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-5 sm:px-8">
                    <a href="/" className="text-lg font-semibold tracking-tight" style={{ color: store.accent_color }}>{store.name}</a>
                    <a href="/cart" aria-label={`Shopping cart, ${cartCount} items`} className="relative flex h-10 w-10 items-center justify-center rounded-md border border-black/10"><ShoppingBag className="h-5 w-5" />{cartCount > 0 ? <span className="absolute -right-2 -top-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-slate-900 px-1 text-[10px] font-semibold text-white">{cartCount}</span> : null}</a>
                </div>
            </header>
            <main className="mx-auto max-w-7xl px-4 py-8 sm:px-8 sm:py-12">
                <a href="/" className="inline-flex items-center gap-2 text-sm text-slate-500 hover:text-slate-900"><ArrowLeft className="h-4 w-4" /> Back to collection</a>
                <div className="mt-7 grid gap-8 lg:grid-cols-2 lg:gap-14">
                    <section className={'grid grid-cols-1 gap-3 ' + (product.images.length > 1 ? 'sm:grid-cols-[76px_minmax(0,1fr)]' : 'sm:grid-cols-1')}>
                        {product.images.length > 1 ? <div className="order-2 flex gap-2 overflow-x-auto sm:order-1 sm:flex-col">{product.images.map((image, index) => <button key={image.url} type="button" onClick={() => setImageIndex(index)} aria-label={'View product image ' + (index + 1)} className={'h-16 w-16 shrink-0 overflow-hidden rounded-md border ' + (imageIndex === index ? 'border-slate-900' : 'border-black/10')}><img src={image.url} alt="" className="h-full w-full object-cover" /></button>)}</div> : null}
                        <div className="order-1 aspect-[4/5] overflow-hidden rounded-md bg-[#eeece7] sm:order-2">{product.images[imageIndex] ? <img src={product.images[imageIndex].url} alt={product.images[imageIndex].alt_text} className="h-full w-full object-cover" /> : <div className="flex h-full items-center justify-center text-slate-400"><ShoppingBag className="h-12 w-12" /></div>}</div>
                    </section>
                    <section className="lg:py-6">
                        {product.category ? <p className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{product.category}</p> : null}
                        <h1 className="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">{product.name}</h1>
                        <div className="mt-5 flex flex-wrap items-baseline gap-3"><p className="text-xl font-semibold" style={{ color: store.accent_color }}>{money(selected?.price ?? product.price)}</p>{product.compare_at_price && Number(product.compare_at_price) > Number(selected?.price ?? product.price) ? <p className="text-sm text-slate-400 line-through">{money(product.compare_at_price)}</p> : null}</div>
                        <div className="my-7 border-t border-black/10" />
                        <p className="whitespace-pre-line text-sm leading-7 text-slate-600">{product.description || 'A considered piece, selected for your everyday collection.'}</p>
                        {product.variants.length ? <div className="mt-8"><div className="mb-3 flex items-center justify-between"><h2 className="text-sm font-semibold">Choose your option</h2>{selected ? <span className="text-xs text-slate-500">{selected.name}</span> : null}</div><div className="flex flex-wrap gap-2">{product.variants.map((variant) => <button key={variant.id} type="button" disabled={variant.stock !== null && variant.stock < 1} onClick={() => { setVariantId(variant.id); setQuantity(1); }} className={'rounded-md border px-4 py-2.5 text-sm transition disabled:cursor-not-allowed disabled:opacity-35 ' + (variantId === variant.id ? 'border-slate-900 bg-slate-900 text-white' : 'border-black/15 bg-white text-slate-700 hover:border-black/40')}>{variant.name || Object.values(variant.options).join(' / ')}{variantId === variant.id ? <Check className="ml-2 inline h-3.5 w-3.5" /> : null}</button>)}</div></div> : <p className="mt-7 text-sm text-rose-600">This product is not available for purchase yet.</p>}
                        {selected ? <div className="mt-5 flex items-center justify-between text-xs text-slate-500"><span>{selected.stock === null ? 'Available' : selected.stock > 0 ? selected.stock + ' in stock' : 'Out of stock'}</span>{Object.keys(selected.options).length ? <span>{Object.entries(selected.options).map(([key, value]) => key + ': ' + value).join(' · ')}</span> : null}</div> : null}
                        <div className="mt-8 flex gap-3">
                            <div className="flex h-12 shrink-0 items-center rounded-md border border-black/10 bg-white"><button type="button" aria-label="Decrease quantity" disabled={quantity <= 1} onClick={() => setQuantity((value) => Math.max(1, value - 1))} className="flex h-12 w-10 items-center justify-center disabled:opacity-30"><Minus className="h-4 w-4" /></button><span className="w-8 text-center text-sm tabular-nums">{quantity}</span><button type="button" aria-label="Increase quantity" disabled={quantity >= Math.min(99, availableStock ?? 99)} onClick={() => setQuantity((value) => Math.min(99, availableStock ?? 99, value + 1))} className="flex h-12 w-10 items-center justify-center disabled:opacity-30"><Plus className="h-4 w-4" /></button></div>
                            <button type="button" disabled={!canAdd || adding} onClick={addToCart} className="flex min-w-0 flex-1 items-center justify-center gap-2 rounded-md px-5 py-3 text-sm font-semibold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40" style={{ backgroundColor: store.primary_color }}>{adding ? <><LoaderCircle className="h-4 w-4 animate-spin" /> Memproses...</> : 'Tambah ke keranjang'}</button>
                        </div>
                        <p className="mt-4 text-xs leading-5 text-slate-500">Secure checkout and delivery options will be available in the next storefront stage.</p>
                    </section>
                </div>
                {related_products.length ? <section className="mt-20 border-t border-black/10 pt-10"><h2 className="text-2xl font-semibold tracking-tight">You may also like</h2><div className="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4 sm:gap-6">{related_products.map((item) => <a key={item.id} href={'/products/' + item.slug} className="group min-w-0"><div className="aspect-[4/5] overflow-hidden rounded-md bg-[#eeece7]">{item.image_url ? <img src={item.image_url} alt={item.name} loading="lazy" className="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]" /> : null}</div><h3 className="mt-3 text-sm font-medium">{item.name}</h3><p className="mt-1 text-sm font-semibold">{money(item.price)}</p></a>)}</div></section> : null}
            </main>
            <footer className="border-t border-black/10 px-4 py-6 text-center text-xs text-slate-500">© {new Date().getFullYear()} {store.name}. All rights reserved.</footer>
        </div>
    </>;
}
