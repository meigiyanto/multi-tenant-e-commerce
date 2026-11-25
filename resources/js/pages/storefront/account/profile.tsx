import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, UserRound } from 'lucide-react';

type ProfileData = { name: string; email: string; phone: string | null; shipping_address: string | null; shipping_city: string | null; shipping_province: string | null; shipping_postal_code: string | null };
type Props = { profile: ProfileData; flash?: { success?: string } };

export default function CustomerProfile({ profile, flash }: Props) {
    const fields = [
        { name: 'phone', label: 'Phone / WhatsApp', value: profile.phone ?? '', placeholder: '+62...' },
        { name: 'shipping_address', label: 'Street address', value: profile.shipping_address ?? '', placeholder: 'Street, building, house number' },
        { name: 'shipping_city', label: 'City / Regency', value: profile.shipping_city ?? '', placeholder: 'City or regency' },
        { name: 'shipping_province', label: 'Province', value: profile.shipping_province ?? '', placeholder: 'Province' },
        { name: 'shipping_postal_code', label: 'Postal code', value: profile.shipping_postal_code ?? '', placeholder: 'Postal code' },
    ];
    return <>
        <Head title="My profile" />
        <main className="min-h-screen bg-[#faf9f6] px-4 py-8 text-slate-900 sm:px-8">
            <div className="mx-auto max-w-2xl">
                <Link href="/" className="inline-flex items-center gap-2 text-sm text-slate-600 hover:text-slate-950"><ArrowLeft className="h-4 w-4" /> Back to store</Link>
                <div className="mt-8 flex items-center gap-3"><UserRound className="h-7 w-7" /><div><p className="text-xs uppercase tracking-widest text-slate-500">Customer account</p><h1 className="text-3xl font-semibold">My profile</h1></div></div>
                {flash?.success ? <p className="mt-6 rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{flash.success}</p> : null}
                <Form action="/account/profile" method="patch" options={{ preserveScroll: true }} className="mt-8 space-y-5 rounded-lg border border-black/10 bg-white p-5 sm:p-7">
                    {({ processing, errors }) => <>
                        <div><label htmlFor="name" className="mb-1.5 block text-sm font-medium">Full name</label><input id="name" name="name" defaultValue={profile.name} required autoComplete="name" className="h-11 w-full rounded-md border border-black/15 px-3 text-sm outline-none focus:border-slate-500" />{errors.name ? <p className="mt-1 text-xs text-rose-600">{errors.name}</p> : null}</div>
                        <div><label htmlFor="email" className="mb-1.5 block text-sm font-medium">Email address</label><input id="email" name="email" type="email" defaultValue={profile.email} required autoComplete="email" className="h-11 w-full rounded-md border border-black/15 px-3 text-sm outline-none focus:border-slate-500" />{errors.email ? <p className="mt-1 text-xs text-rose-600">{errors.email}</p> : null}</div>
                        <div className="border-t border-black/5 pt-5"><h2 className="font-semibold">Default shipping details</h2><p className="mt-1 text-sm text-slate-500">These details are used to prefill your checkout information.</p></div>
                        {fields.map((field) => <div key={field.name}><label htmlFor={field.name} className="mb-1.5 block text-sm font-medium">{field.label}</label><input id={field.name} name={field.name} defaultValue={field.value} placeholder={field.placeholder} className="h-11 w-full rounded-md border border-black/15 px-3 text-sm outline-none focus:border-slate-500" />{errors[field.name as keyof typeof errors] ? <p className="mt-1 text-xs text-rose-600">{errors[field.name as keyof typeof errors]}</p> : null}</div>)}
                        <button type="submit" disabled={processing} className="rounded-md bg-slate-900 px-5 py-3 text-sm font-semibold text-white disabled:opacity-50">{processing ? 'Saving...' : 'Save profile'}</button>
                    </>}
                </Form>
            </div>
        </main>
    </>;
}
