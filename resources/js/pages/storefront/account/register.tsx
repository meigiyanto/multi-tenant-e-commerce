import { Form, Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';

type Props = { store: { name: string } };

export default function CustomerRegister({ store }: Props) {
    return (
        <>
            <Head title={`Create customer account - ${store.name}`} />
            <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
                <div className="w-full max-w-sm">
                    <h1 className="text-2xl font-semibold">Create customer account</h1>
                    <p className="text-sm text-muted-foreground">{store.name}</p>
                    <Form action="/account/register" method="post" className="space-y-4">
                        {({ errors, processing }) => (
                            <>
                                <div className="space-y-2"><Label htmlFor="name">Name</Label><Input id="name" name="name" required autoComplete="name" /><InputError message={errors.name} /></div>
                                <div className="space-y-2"><Label htmlFor="email">Email</Label><Input id="email" name="email" type="email" required autoComplete="email" /><InputError message={errors.email} /></div>
                                <div className="space-y-2"><Label htmlFor="password">Password</Label><Input id="password" name="password" type="password" required autoComplete="new-password" /><InputError message={errors.password} /></div>
                                <div className="space-y-2"><Label htmlFor="password_confirmation">Confirm password</Label><Input id="password_confirmation" name="password_confirmation" type="password" required autoComplete="new-password" /><InputError message={errors.password_confirmation} /></div>
                                <Button type="submit" disabled={processing} className="w-full">Create account</Button>
                            </>
                        )}
                    </Form>
                    <p className="text-sm mt-3">Sudah punya akun? <Link className="underline" href="/account/login">Login</Link></p>
                </div>
            </div>
        </>
    );
}
