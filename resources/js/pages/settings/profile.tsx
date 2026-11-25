import { Form, Head, usePage, Link } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';
import type { Auth } from '@/types';
import { send } from '@/routes/verification';

type ProfileData = { name: string; email: string; phone: string | null; shipping_address: string | null; shipping_city: string | null; shipping_province: string | null; shipping_postal_code: string | null };
type PageProps = { auth: Auth };

export default function Profile({ mustVerifyEmail, status, profile }: { mustVerifyEmail: boolean; status?: string; profile: ProfileData }) {
    const { auth } = usePage<PageProps>().props;
    const fields = [
        { name: 'phone', label: 'Phone / WhatsApp', value: profile.phone ?? '', placeholder: '+62...' },
        { name: 'shipping_address', label: 'Street address', value: profile.shipping_address ?? '', placeholder: 'Street, building, house number, RT/RW' },
        { name: 'shipping_city', label: 'City / Regency', value: profile.shipping_city ?? '', placeholder: 'City or regency' },
        { name: 'shipping_province', label: 'Province', value: profile.shipping_province ?? '', placeholder: 'Province' },
        { name: 'shipping_postal_code', label: 'Postal code', value: profile.shipping_postal_code ?? '', placeholder: 'Postal code' },
    ];

    return <>
        <Head title="Profile settings" />
        <h1 className="sr-only">Profile settings</h1>
        <div className="space-y-6">
            <Heading variant="small" title="Profile" description="Update your contact information and default shipping address" />
            <Form {...ProfileController.update.form()} options={{ preserveScroll: true }} className="space-y-6">
                {({ processing, errors }) => <>
                    <div className="grid gap-2"><Label htmlFor="name">Name</Label><Input id="name" className="mt-1 block w-full" defaultValue={profile.name} name="name" required autoComplete="name" placeholder="Full name" /><InputError className="mt-2" message={errors.name} /></div>
                    <div className="grid gap-2"><Label htmlFor="email">Email address</Label><Input id="email" type="email" className="mt-1 block w-full" defaultValue={profile.email} name="email" required autoComplete="username" placeholder="Email address" /><InputError className="mt-2" message={errors.email} /></div>
                    {mustVerifyEmail && auth.user.email_verified_at === null ? <div><p className="-mt-4 text-sm text-muted-foreground">Your email address is unverified. <Link href={send()} as="button" className="text-foreground underline">Click here to re-send the verification email.</Link></p>{status === 'verification-link-sent' ? <div className="mt-2 text-sm font-medium text-green-600">A new verification link has been sent to your email address.</div> : null}</div> : null}
                    <div className="border-t pt-5"><Heading variant="small" title="Default shipping details" description="These details are automatically used during checkout." /></div>
                    {fields.map((field) => <div className="grid gap-2" key={field.name}><Label htmlFor={field.name}>{field.label}</Label><Input id={field.name} name={field.name} defaultValue={field.value} placeholder={field.placeholder} /><InputError className="mt-2" message={errors[field.name as keyof typeof errors]} /></div>)}
                    <div className="flex items-center gap-4"><Button disabled={processing} data-test="update-profile-button">Save</Button></div>
                </>}
            </Form>
        </div>
        <DeleteUser />
    </>;
}

Profile.layout = { breadcrumbs: [{ title: 'Profile settings', href: edit() }] };
