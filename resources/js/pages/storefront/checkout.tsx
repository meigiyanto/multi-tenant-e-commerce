import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft, Lock, ShoppingBag } from 'lucide-react';
import { type FormEvent, useEffect, useState } from 'react';

type CheckoutItem = {
    id: number; name: string; variant_name: string; quantity: number;
    unit_price: string; line_total: string; image_url: string | null;
};
type Destination = {
    id: number; label: string; province_name?: string; city_name?: string;
    province?: string; city?: string;
};
type ShippingRate = {
    courier: string; courier_name: string; service: string; description: string;
    amount: number; etd: string;
};
type Props = {
    store: { name: string; currency: string };
    items: CheckoutItem[];
    subtotal: string;
    weight_grams: number;
    customer: { name: string; email: string; phone: string };
};

export default function StorefrontCheckout({ store, items, subtotal, weight_grams, customer }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        customer_name: customer.name, customer_email: customer.email, customer_phone: customer.phone,
        shipping_address: '', shipping_city: '', shipping_province: '', shipping_postal_code: '',
        shipping_destination_id: '', shipping_destination_label: '', shipping_courier: '',
        shipping_service: '', customer_note: '',
    });
    const [destinationQuery, setDestinationQuery] = useState('');
    const [destinations, setDestinations] = useState<Destination[]>([]);
    const [selectedDestination, setSelectedDestination] = useState<Destination | null>(null);
    const [rates, setRates] = useState<ShippingRate[]>([]);
    const [loadingDestinations, setLoadingDestinations] = useState(false);
    const [loadingRates, setLoadingRates] = useState(false);
    const [shippingError, setShippingError] = useState('');
    const money = (amount: string | number) => new Intl.NumberFormat('id-ID', {
        style: 'currency', currency: store.currency, maximumFractionDigits: 0,
    }).format(Number(amount));

    useEffect(() => {
        if (selectedDestination || destinationQuery.trim().length < 3) {
            setDestinations([]);
            return;
        }
        const timer = window.setTimeout(async () => {
            setLoadingDestinations(true);
            try {
                const response = await fetch('/shipping/destinations?search=' + encodeURIComponent(destinationQuery), {
                    headers: { Accept: 'application/json' },
                });
                const payload = await response.json();
                if (!response.ok) throw new Error(payload.message || 'Gagal mencari kota tujuan.');
                setDestinations(payload.data || []);
            } catch (error) {
                setShippingError(error instanceof Error ? error.message : 'Gagal mencari kota tujuan.');
            } finally {
                setLoadingDestinations(false);
            }
        }, 350);
        return () => window.clearTimeout(timer);
    }, [destinationQuery, selectedDestination]);

    useEffect(() => {
        if (!selectedDestination) {
            setRates([]);
            return;
        }
        const loadRates = async () => {
            setLoadingRates(true);
            setShippingError('');
            try {
                const response = await fetch('/shipping/quotes?destination_id=' + selectedDestination.id, {
                    headers: { Accept: 'application/json' },
                });
                const payload = await response.json();
                if (!response.ok) throw new Error(payload.message || payload.errors?.shipping?.[0] || 'Gagal menghitung ongkir.');
                setRates(payload.rates || []);
                setData('shipping_courier', '');
                setData('shipping_service', '');
                if (!payload.rates?.length) setShippingError('Tidak ada layanan pengiriman yang tersedia untuk tujuan ini.');
            } catch (error) {
                setShippingError(error instanceof Error ? error.message : 'Gagal menghitung ongkir.');
            } finally {
                setLoadingRates(false);
            }
        };
        loadRates();
    }, [selectedDestination]);

    const formError = Object.values(errors).find((error): error is string => typeof error === 'string');
    const selectedRate = rates.find((rate) => rate.courier === data.shipping_courier && rate.service === data.shipping_service);
    const total = Number(subtotal) + (selectedRate?.amount || 0);
    const selectDestination = (destination: Destination) => {
        setSelectedDestination(destination);
        setDestinationQuery(destination.label);
        setData('shipping_destination_id', String(destination.id));
        setData('shipping_destination_label', destination.label);
        setData('shipping_city', destination.city_name || destination.city || destination.label);
        setData('shipping_province', destination.province_name || destination.province || '');
        setDestinations([]);
    };
    
    const field = (name: keyof typeof data, label: string, placeholder = '', type = 'text') => (
        <label className="block">
            <span className="mb-1.5 block text-sm font-medium">{label}</span>
            <input type={type} value={data[name]} onChange={(event) => setData(name, event.target.value)} placeholder={placeholder}
                className={'h-11 w-full rounded-md border bg-white px-3 text-sm outline-none focus:border-slate-500 ' + (errors[name] ? 'border-rose-400' : 'border-black/10')} />
            {errors[name] ? <span className="mt-1 block text-xs text-rose-600">{errors[name]}</span> : null}
        </label>
    );
    const submit = (event: FormEvent) => {
        event.preventDefault();
        post('/checkout');
    };

    return <>
        <Head title={'Checkout · ' + store.name} />
        <div className="min-h-screen bg-[#faf9f6] text-slate-900">
            <header className="border-b border-black/5"><div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-5 sm:px-8">
                <a href="/" className="text-lg font-semibold tracking-tight">{store.name}</a>
                <span className="flex items-center gap-2 text-xs text-slate-500"><Lock className="h-3.5 w-3.5" /> Secure checkout</span>
            </div></header>
            <main className="mx-auto max-w-6xl px-4 py-10 sm:px-8 sm:py-14">
                <a href="/cart" className="inline-flex items-center gap-2 text-sm text-slate-500 hover:text-slate-900"><ArrowLeft className="h-4 w-4" /> Back to cart</a>
                <h1 className="mt-5 text-3xl font-semibold tracking-tight sm:text-4xl">Checkout</h1>
                <form onSubmit={submit} className="mt-8 grid gap-10 lg:grid-cols-[1fr_360px]">
                    <section className="space-y-8">
                        <div className="rounded-md border border-black/10 bg-white p-5 sm:p-7">
                            <h2 className="text-lg font-semibold">Contact information</h2>
                            <div className="mt-5 grid gap-4 sm:grid-cols-2">
                                {field('customer_name', 'Full name', 'Your full name')}
                                {field('customer_email', 'Email address', 'name@example.com', 'email')}
                                <div className="sm:col-span-2">
                                    {field('customer_phone', 'Phone / WhatsApp', '+62')}
                                </div>
                            </div>
                        </div>
                        <div className="rounded-md border border-black/10 bg-white p-5 sm:p-7">
                            <h2 className="text-lg font-semibold">Shipping address</h2>
                            <div className="mt-5 grid gap-4 sm:grid-cols-2">
                                <div className="sm:col-span-2">
                                    <label className="block">
                                        <span className="mb-1.5 block text-sm font-medium">Street address</span>
                                        <textarea value={data.shipping_address} onChange={(event) => setData('shipping_address', event.target.value)} rows={3} placeholder="Street, building, house number, RT/RW" className={'w-full rounded-md border bg-white px-3 py-2.5 text-sm outline-none focus:border-slate-500 ' + (errors.shipping_address ? 'border-rose-400' : 'border-black/10')} />{errors.shipping_address ? <span className="mt-1 block text-xs text-rose-600">{errors.shipping_address}</span> : null}
                                    </label>
                                </div>
                                <div className="relative sm:col-span-2">
                                    <label className="block">
                                        <span className="mb-1.5 block text-sm font-medium">Destination city / district</span>
                                        <input value={destinationQuery} onChange={(event) => { setDestinationQuery(event.target.value); setSelectedDestination(null); setData('shipping_destination_id', ''); setData('shipping_courier', ''); setData('shipping_service', ''); }} placeholder="Search city, district, or postal code here" className="h-11 w-full rounded-md border border-black/10 px-3 text-sm outline-none focus:border-slate-500" />
                                    </label>
                                    {loadingDestinations ? <p className="mt-1 text-xs text-slate-500">Searching destinations…</p> : null}
                                    {destinations.length > 0 ? <div className="absolute z-20 mt-1 max-h-56 w-full overflow-y-auto rounded-md border border-black/10 bg-white shadow-lg">{destinations.map((destination) => <button type="button" key={destination.id} onClick={() => selectDestination(destination)} className="block w-full border-b border-black/5 px-3 py-2.5 text-left text-sm last:border-0 hover:bg-slate-50">{destination.label}</button>)}</div> : null}
                                    {errors.shipping_destination_id ? <span className="mt-1 block text-sm text-rose-600">{errors.shipping_destination_id}</span> : null }
                                </div>
                                <div>
                                    {field('shipping_postal_code', 'Postal code')}
                                    <div className="text-sm mt-2 text-slate-500">{selectedDestination ? selectedDestination.label : 'Select a destination from search results from Destination city / district input.'}</div>
                                </div>
                            </div>
                        </div>
                        <div className="rounded-md border border-black/10 bg-white p-5 sm:p-7">
                            <div className="flex items-center justify-between gap-3"><h2 className="text-lg font-semibold">Shipping service</h2><span className="text-xs text-slate-500">{weight_grams.toLocaleString('id-ID')} g</span></div>
                            {!selectedDestination ? <p className="mt-4 text-sm text-slate-500">Select your destination to calculate available courier services.</p> : null}
                            {loadingRates ? <p className="mt-4 text-sm text-slate-500">Calculating shipping rates…</p> : null}
                            {rates.map((rate) => <label key={rate.courier + rate.service} className="mt-3 flex cursor-pointer items-center justify-between gap-4 rounded-md border border-black/10 p-3 hover:border-slate-500"><span className="flex min-w-0 items-start gap-3">
                                <input type="radio" name="shipping_service" checked={data.shipping_courier === rate.courier && data.shipping_service === rate.service} onChange={() => { setData('shipping_courier', rate.courier); setData('shipping_service', rate.service); }} className="mt-1" /><span><span className="block text-sm font-medium">{rate.courier_name} · {rate.service}</span>
                                <span className="mt-0.5 block text-xs text-slate-500">{rate.description} · Est. {rate.etd || 'N/A'}</span></span></span><span className="shrink-0 text-sm font-semibold">{money(rate.amount)}</span></label>)}
                            {shippingError ? <p className="mt-3 text-sm text-rose-600">{shippingError}</p> : null}
                            {errors.shipping_service ? <p className="mt-1 text-sm text-rose-600">{errors.shipping_service}</p> : null}
                        </div>
                        <div className="rounded-md border border-black/10 bg-white p-5 sm:p-7">
                            <label className="block"><span className="mb-1.5 block text-sm font-medium">Order note (optional)</span><textarea value={data.customer_note} onChange={(event) => setData('customer_note', event.target.value)} rows={3} placeholder="Additional delivery instructions" className="w-full rounded-md border border-black/10 bg-white px-3 py-2.5 text-sm outline-none focus:border-slate-500" /></label>
                        </div>
                    </section>
                    <aside className="h-fit rounded-md border border-black/10 bg-white p-5 sm:p-6">
                        <h2 className="text-lg font-semibold">Order summary</h2>
                        <div className="mt-5 max-h-80 space-y-4 overflow-y-auto">
                            {items.map((item) => <div key={item.id} className="flex gap-3">
                                <div className="relative h-16 w-14 shrink-0 overflow-hidden rounded bg-[#eeece7]">{item.image_url ? <img src={item.image_url} alt={item.name} className="h-full w-full object-cover" /> : <ShoppingBag className="m-auto h-5 w-5 text-slate-400" />}</div>
                                <div className="min-w-0 flex-1"><p className="text-sm font-medium">{item.name}</p><p className="mt-0.5 text-xs text-slate-500">{item.variant_name} · Qty {item.quantity}</p><p className="mt-1 text-sm font-semibold">{money(item.line_total)}</p></div>
                            </div>)}
                        </div>
                        <div className="mt-5 space-y-3 border-t border-black/10 pt-4 text-sm"><div className="flex justify-between"><span className="text-slate-500">Subtotal</span><span>{money(subtotal)}</span></div><div className="flex justify-between"><span className="text-slate-500">Shipping</span><span>{selectedRate ? money(selectedRate.amount) : '—'}</span></div></div>
                        <div className="mt-4 flex justify-between border-t border-black/10 pt-4 text-base font-semibold"><span>Total</span><span>{money(total)}</span></div>
                        {formError ? <p className="mt-3 text-sm text-rose-600">{formError}</p> : null}
                        <button type="submit" disabled={processing || !items.length || !selectedRate} className="mt-5 w-full rounded-md bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-50">{processing ? 'Placing order…' : 'Place order'}</button>
                        <p className="mt-3 text-xs leading-5 text-slate-500">Payment is securely processed by Midtrans after the order is created.</p>
                    </aside>
                </form>
            </main>
        </div>
    </>;
}
