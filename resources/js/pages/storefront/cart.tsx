import { Head, router } from '@inertiajs/react';
import { ArrowLeft, Minus, Plus, ShoppingBag, Trash2 } from 'lucide-react';

type CartItem = {
    id: number;
    name: string;
    slug: string;
    image_url: string | null;
    variant_name: string;
    options: Record<string, string>;
    quantity: number;
    unit_price: string;
    line_total: string;
    stock: number | null;
};
type Props = {
    store: { name: string; currency: string };
    items: CartItem[];
    item_count: number;
    subtotal: string;
};

export default function StorefrontCart({ store, items, item_count, subtotal }: Props) {
    const money = (amount: string | number) => new Intl.NumberFormat('id-ID', {
        style: 'currency', currency: store.currency, maximumFractionDigits: 0,
    }).format(Number(amount));

    const updateQuantity = (item: CartItem, quantity: number) => {
        if (quantity < 1 || (item.stock !== null && quantity > item.stock)) return;
        router.patch('/cart/items/' + item.id, { quantity }, { preserveScroll: true });
    };

    const removeItem = (item: CartItem) => {
        router.delete('/cart/items/' + item.id, { preserveScroll: true });
    };

    return <>
        <Head title={`Shopping cart · ${store.name}`} />
        <div className="min-h-screen bg-[#faf9f6] text-slate-900">
            <header className="border-b border-black/5">
                <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-5 sm:px-8">
                    <a href="/" className="text-lg font-semibold tracking-tight">{store.name}</a>
                    <a href="/" className="text-sm text-slate-600 hover:text-slate-950">Continue shopping</a>
                </div>
            </header>
            <main className="mx-auto max-w-6xl px-4 py-10 sm:px-8 sm:py-14">
                <div className="flex items-end justify-between border-b border-black/10 pb-6">
                    <div><p className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Your selection</p><h1 className="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">Shopping cart</h1></div>
                    <p className="text-sm text-slate-500">{item_count} {item_count === 1 ? 'item' : 'items'}</p>
                </div>
                {items.length ? <div className="grid gap-10 py-8 lg:grid-cols-[1fr_320px]">
                    <div className="divide-y divide-black/10">
                        {items.map((item) => <article key={item.id} className="grid grid-cols-[88px_1fr] gap-4 py-6 first:pt-0 sm:grid-cols-[112px_1fr_auto] sm:gap-6">
                            <div className="aspect-[4/5] overflow-hidden rounded-md bg-[#eeece7]">{item.image_url ? <img src={item.image_url} alt={item.name} className="h-full w-full object-cover" /> : <div className="flex h-full items-center justify-center text-slate-400"><ShoppingBag className="h-7 w-7" /></div>}</div>
                            <div className="min-w-0">
                                <h2 className="font-medium">{item.name}</h2>
                                <p className="mt-1 text-sm text-slate-500">{item.variant_name}</p>
                                {Object.keys(item.options).length ? <p className="mt-1 text-xs text-slate-500">{Object.entries(item.options).map(([key, value]) => key + ': ' + value).join(' · ')}</p> : null}
                                <p className="mt-3 text-sm font-semibold">{money(item.unit_price)}</p>
                                <div className="mt-4 flex items-center gap-3">
                                    <div className="flex h-9 items-center rounded-md border border-black/10 bg-white">
                                        <button type="button" aria-label="Decrease quantity" disabled={item.quantity <= 1} onClick={() => updateQuantity(item, item.quantity - 1)} className="flex h-9 w-9 items-center justify-center disabled:opacity-30"><Minus className="h-3.5 w-3.5" /></button>
                                        <span className="min-w-8 text-center text-sm tabular-nums">{item.quantity}</span>
                                        <button type="button" aria-label="Increase quantity" disabled={item.stock !== null && item.quantity >= item.stock} onClick={() => updateQuantity(item, item.quantity + 1)} className="flex h-9 w-9 items-center justify-center disabled:opacity-30"><Plus className="h-3.5 w-3.5" /></button>
                                    </div>
                                    <button type="button" onClick={() => removeItem(item)} className="flex items-center gap-1.5 text-xs text-slate-500 hover:text-rose-600"><Trash2 className="h-3.5 w-3.5" /> Remove</button>
                                </div>
                                {item.stock !== null && item.stock < item.quantity ? <p className="mt-2 text-xs text-rose-600">Only {item.stock} available. Update the quantity to continue.</p> : null}
                            </div>
                            <p className="col-span-2 text-right text-sm font-semibold sm:col-span-1">{money(item.line_total)}</p>
                        </article>)}
                    </div>
                    <aside className="h-fit rounded-md border border-black/10 bg-white p-6">
                        <h2 className="text-lg font-semibold">Order summary</h2>
                        <div className="mt-5 flex justify-between border-b border-black/10 pb-4 text-sm"><span className="text-slate-500">Subtotal</span><span className="font-semibold">{money(subtotal)}</span></div>
                        <p className="mt-4 text-xs leading-5 text-slate-500">Shipping and payment will be calculated during checkout.</p>
                        <a href="/checkout" className="mt-6 block w-full rounded-md bg-slate-900 px-5 py-3 text-center text-sm font-semibold text-white transition hover:bg-slate-700">Continue to checkout</a>
                        <a href="/" className="mt-4 flex items-center justify-center gap-2 text-sm text-slate-600 hover:text-slate-950"><ArrowLeft className="h-4 w-4" /> Continue shopping</a>
                    </aside>
                </div> : <div className="py-20 text-center">
                    <ShoppingBag className="mx-auto h-10 w-10 text-slate-400" />
                    <h2 className="mt-5 text-xl font-semibold">Your cart is empty</h2>
                    <p className="mt-2 text-sm text-slate-500">Explore the collection and add something you love.</p>
                    <a href="/" className="mt-6 inline-flex rounded-md bg-slate-900 px-5 py-3 text-sm font-semibold text-white">Explore collection</a>
                </div>}
            </main>
        </div>
    </>;
}
