import { Form } from '@inertiajs/react';
import CustomerAccountLayout from '@/layouts/storefront/customer-account-layout';

type ProfileData = { name: string; email: string; phone: string | null; shipping_address: string | null; shipping_city: string | null; shipping_province: string | null; shipping_postal_code: string | null };
type Props = { store: { name: string }; profile: ProfileData };

export default function CustomerProfile({ store, profile }: Props) {
    const fields = [
        { name: 'phone', label: 'Phone / WhatsApp', value: profile.phone ?? '', placeholder: '+62...' },
        { name: 'shipping_address', label: 'Street address', value: profile.shipping_address ?? '', placeholder: 'Street, building, house number' },
        { name: 'shipping_city', label: 'City / Regency', value: profile.shipping_city ?? '', placeholder: 'City or regency' },
        { name: 'shipping_province', label: 'Province', value: profile.shipping_province ?? '', placeholder: 'Province' },
        { name: 'shipping_postal_code', label: 'Postal code', value: profile.shipping_postal_code ?? '', placeholder: 'Postal code' },
    ];
    const inputClass = 'h-11 w-full rounded-md border border-black/15 px-3 text-sm outline-none focus:border-slate-500';
    return <CustomerAccountLayout title="My profile" storeName={store.name}>
        <Form action="/account/profile" method="patch" options={{ preserveScroll: true }} className="space-y-5 rounded-lg border border-black/10 bg-white p-5 sm:p-7">
            {({ processing, errors }) => <>
                <div><label htmlFor="name" className="mb-1.5 block text-sm font-medium">Full name</label><input id="name" name="name" defaultValue={profile.name} required autoComplete="name" className={inputClass} />{errors.name ? <p className="mt-1 text-xs text-rose-600">{errors.name}</p> : null}</div>
                <div><label htmlFor="email" className="mb-1.5 block text-sm font-medium">Email address</label><input id="email" name="email" type="email" defaultValue={profile.email} required autoComplete="email" className={inputClass} />{errors.email ? <p className="mt-1 text-xs text-rose-600">{errors.email}</p> : null}</div>
                <div className="border-t border-black/5 pt-5"><h2 className="font-semibold">Default shipping details</h2><p className="mt-1 text-sm text-slate-500">These details are used to prefill your checkout information.</p></div>
                {fields.map((field) => <div key={field.name}><label htmlFor={field.name} className="mb-1.5 block text-sm font-medium">{field.label}</label><input id={field.name} name={field.name} defaultValue={field.value} placeholder={field.placeholder} className={inputClass} />{errors[field.name as keyof typeof errors] ? <p className="mt-1 text-xs text-rose-600">{errors[field.name as keyof typeof errors]}</p> : null}</div>)}
                <div className="border-t border-black/5 pt-5"><h2 className="font-semibold">Change password</h2><p className="mt-1 text-sm text-slate-500">Leave these fields blank if you do not want to change your password.</p></div>
                <div><label htmlFor="current_password" className="mb-1.5 block text-sm font-medium">Current password</label><input id="current_password" name="current_password" type="password" autoComplete="current-password" className={inputClass} />{errors.current_password ? <p className="mt-1 text-xs text-rose-600">{errors.current_password}</p> : null}</div>
                <div><label htmlFor="password" className="mb-1.5 block text-sm font-medium">New password</label><input id="password" name="password" type="password" autoComplete="new-password" className={inputClass} />{errors.password ? <p className="mt-1 text-xs text-rose-600">{errors.password}</p> : null}</div>
                <div><label htmlFor="password_confirmation" className="mb-1.5 block text-sm font-medium">Confirm new password</label><input id="password_confirmation" name="password_confirmation" type="password" autoComplete="new-password" className={inputClass} /></div>
                <button type="submit" disabled={processing} className="rounded-md bg-slate-900 px-5 py-3 text-sm font-semibold text-white disabled:opacity-50">{processing ? 'Saving...' : 'Save changes'}</button>
            </>}
        </Form>
    </CustomerAccountLayout>;
}
