import { Form, Head } from '@inertiajs/react';
import { MailCheck } from 'lucide-react';
import CustomerAccountLayout from '@/layouts/storefront/customer-account-layout';

type Props = { store: { name: string }; email: string; status?: string };

export default function VerifyCustomerEmail({ store, email, status }: Props) {
    return <CustomerAccountLayout title="Verify your email" storeName={store.name}>
        <Head title="Verify your email" />
        <section className="mx-auto max-w-xl rounded-lg border border-black/10 bg-white p-6 text-center sm:p-8">
            <MailCheck className="mx-auto h-10 w-10 text-slate-600" />
            <h2 className="mt-4 text-xl font-semibold">Verify your email address</h2>
            <p className="mt-2 text-sm leading-6 text-slate-600">Your account email address <strong>{email}</strong> has not been verified. Verify it to access your customer account, order history, and checkout.</p>
            {status === 'verification-link-sent' && <p role="status" className="mt-4 rounded-md bg-emerald-50 p-3 text-sm font-medium text-emerald-800">A new verification link has been sent. Please check your inbox.</p>}
            <Form action="/account/verification-notification" method="post" className="mt-6">
                {({ processing }) => <button type="submit" disabled={processing} className="inline-flex items-center justify-center rounded-md bg-slate-900 px-5 py-3 text-sm font-semibold text-white disabled:opacity-60">{processing ? 'Sending...' : 'Resend verification email'}</button>}
            </Form>
            <p className="mt-4 text-xs text-slate-500">If you cannot find the email, check your spam folder before requesting another link.</p>
        </section>
    </CustomerAccountLayout>;
}
