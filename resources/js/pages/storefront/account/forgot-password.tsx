import { Form, Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';

type Props = { store: { name: string }; status?: string };

export default function ForgotCustomerPassword({ store, status }: Props) {
    return (
        <>
            <Head title={`Forgot password - ${store.name}`} />
            <div className="mx-auto w-full max-w-md space-y-6 p-6">
                <h1 className="text-2xl font-semibold">Reset customer password</h1>
                <p className="text-sm text-muted-foreground">Enter the email address registered with {store.name}.</p>
                {status === 'customer-password-reset-link-sent' && <p role="status" className="text-sm">If an account exists for that email, a reset link has been sent.</p>}
                <Form action="/account/forgot-password" method="post" className="space-y-4">
                    {({ errors, processing }) => <>
                        <div className="space-y-2"><Label htmlFor="email">Email</Label><Input id="email" name="email" type="email" required autoComplete="email" /><InputError message={errors.email} /></div>
                        <Button type="submit" disabled={processing} className="w-full">Send reset link</Button>
                    </>}
                </Form>
                <Link className="text-sm underline" href="/account/login">Back to login</Link>
            </div>
        </>
    );
}
