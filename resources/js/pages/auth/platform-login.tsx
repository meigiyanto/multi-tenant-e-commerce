import { Form, Head } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function PlatformLogin({ status, canResetPassword }: Props) {
    return (
        <>
            <Head title="Platform administrator login" />
            <div className="mb-2 rounded-xl border border-primary/20 bg-primary/5 p-5">
                <div className="mb-3 flex items-center gap-3">
                    <div className="flex size-10 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                        <ShieldCheck className="size-5" />
                    </div>
                    <div>
                        <p className="font-semibold">Platform Control Center</p>
                        <p className="text-xs text-muted-foreground">Authorized platform administrators only</p>
                    </div>
                </div>
                <p className="text-sm text-muted-foreground">Sign in with your platform administrator account to manage stores, teams, and platform operations.</p>
            </div>

            <Form {...store.form()} resetOnSuccess={['password']} className="flex flex-col gap-6">
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="email">Administrator email</Label>
                            <Input id="email" type="email" name="email" required autoFocus autoComplete="username" placeholder="admin@example.com" />
                            <InputError message={errors.email} />
                        </div>
                        <div className="grid gap-2">
                            <div className="flex items-center">
                                <Label htmlFor="password">Password</Label>
                                {canResetPassword && <TextLink href={request()} className="ml-auto text-sm">Forgot password?</TextLink>}
                            </div>
                            <PasswordInput id="password" name="password" required autoComplete="current-password" placeholder="Password" />
                            <InputError message={errors.password} />
                        </div>
                        <div className="flex items-center gap-3">
                            <Checkbox id="remember" name="remember" />
                            <Label htmlFor="remember">Remember me</Label>
                        </div>
                        <Button type="submit" className="w-full" disabled={processing} data-test="platform-login-button">
                            {processing && <Spinner />}
                            Sign in to platform
                        </Button>
                        {status && <p className="text-center text-sm font-medium text-green-600">{status}</p>}
                    </>
                )}
            </Form>

            <p className="text-center text-sm text-muted-foreground">
                Not a platform administrator? <TextLink href={login()}>Customer and store login</TextLink>
            </p>
        </>
    );
}

PlatformLogin.layout = {
    title: 'Platform administrator sign in',
    description: 'Secure access for platform management',
};
