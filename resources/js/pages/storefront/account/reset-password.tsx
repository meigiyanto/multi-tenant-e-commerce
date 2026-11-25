import { Form, Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';

type Props = { store: { name: string }; email: string; token: string };

export default function ResetCustomerPassword({ store, email, token }: Props) {
    return (
        <>
            <Head title={`Reset password - ${store.name}`} />
            <div className="mx-auto w-full max-w-md space-y-6 p-6">
                <h1 className="text-2xl font-semibold">Choose a new password</h1>
                <Form action="/account/reset-password" method="post" className="space-y-4">
                    {({ errors, processing }) => <>
                        <input type="hidden" name="token" value={token} />
                        <div className="space-y-2"><Label htmlFor="email">Email</Label><Input id="email" name="email" type="email" defaultValue={email} required autoComplete="email" /><InputError message={errors.email} /></div>
                        <div className="space-y-2"><Label htmlFor="password">New password</Label><Input id="password" name="password" type="password" required autoComplete="new-password" /><InputError message={errors.password} /></div>
                        <div className="space-y-2"><Label htmlFor="password_confirmation">Confirm password</Label><Input id="password_confirmation" name="password_confirmation" type="password" required autoComplete="new-password" /></div>
                        <Button type="submit" disabled={processing} className="w-full">Reset password</Button>
                    </>}
                </Form>
                <Link className="text-sm underline" href="/account/login">Back to login</Link>
            </div>
        </>
    );
}
