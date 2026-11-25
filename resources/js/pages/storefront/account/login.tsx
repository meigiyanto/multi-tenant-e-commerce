import { Form, Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';

type Props = { store: { name: string }; status?: string };

export default function CustomerLogin({ store, status }: Props) {
    return (
        <>
            <Head title={`Customer login - ${store.name}`} />
            <div className="mx-auto w-full max-w-md space-y-6 p-6">
                <h1 className="text-2xl font-semibold">Customer login</h1>
                {status === 'customer-password-reset' && <p role="status" className="text-sm">Your password has been reset. You can now log in.</p>}
                <p className="text-sm text-muted-foreground">{store.name}</p>
                <Form action="/account/login" method="post" className="space-y-4">
                    {({ errors, processing }) => (
                        <>
                            <div className="space-y-2"><Label htmlFor="email">Email</Label><Input id="email" name="email" type="email" required autoComplete="email" /><InputError message={errors.email} /></div>
                            <div className="space-y-2"><Label htmlFor="password">Password</Label><Input id="password" name="password" type="password" required autoComplete="current-password" /><InputError message={errors.password} /></div>
                            <Button type="submit" disabled={processing} className="w-full">Log in</Button>
                        </>
                    )}
                </Form>
                <p className="text-sm"><Link className="underline" href="/account/forgot-password">Lupa password?</Link></p>
                <p className="text-sm">Belum punya akun? <Link className="underline" href="/account/register">Daftar</Link></p>
            </div>
        </>
    );
}
