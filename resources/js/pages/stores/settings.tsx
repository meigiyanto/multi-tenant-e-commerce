import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type NavigationItem = { label: string; url: string };
type StorefrontSettings = {
    description: string;
    logo_url: string | null;
    banner_url: string | null;
    primary_color: string;
    accent_color: string;
    navigation: NavigationItem[];
    contact: { email: string; phone: string; address: string };
};
type Props = {
    team: { id: number; name: string; slug: string };
    store: { id: number; name: string; slug: string; status: string; timezone: string; currency: string; storefront: StorefrontSettings };
};

export default function StoreSettings({ team, store }: Props) {
    const baseUrl = '/settings/teams/' + team.slug + '/stores/' + store.id;
    const navigation = Array.from({ length: 5 }, (_, index) => store.storefront.navigation[index] ?? { label: '', url: '' });

    return (
        <>
            <Head title={'Storefront settings — ' + store.name} />
            <div className="flex flex-col m-2 gap-8">
                <div className="m-2">
                    <Button asChild variant="ghost" size="sm" className="ml-0 mb-2">
                        <Link href={baseUrl}><ArrowLeft /> Back to store</Link>
                    </Button>
                    <Heading variant="small" title="Storefront configuration" description={'Customize the public appearance and contact details of ' + store.name + '.'} />
                </div>
                <Form action={baseUrl} method="patch" className="max-w-4xl space-y-8">
                    {({ errors, processing }) => (
                        <>
                            <section className="space-y-5 rounded-xl border p-5">
                                <div><h2 className="font-semibold">Store identity</h2><p className="text-sm text-muted-foreground">The name is shown in the storefront header and browser title.</p></div>
                                <div className="grid gap-2"><Label htmlFor="store-name">Store name</Label><Input id="store-name" name="name" defaultValue={store.name} maxLength={255} required /><InputError message={errors.name} /></div>
                                <div className="grid gap-2"><Label htmlFor="store-slug">Store slug</Label><Input id="store-slug" name="slug" defaultValue={store.slug} pattern="[a-z0-9]+(?:-[a-z0-9]+)*" maxLength={255} required /><p className="text-xs text-muted-foreground">Lowercase letters, numbers, and hyphens. Unique within this Team.</p><InputError message={errors.slug} /></div>
                                <div className="grid gap-2"><Label htmlFor="description">Store description</Label><textarea id="description" name="description" defaultValue={store.storefront.description} maxLength={1000} rows={3} className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring" placeholder="Tell customers what your store offers." /><InputError message={errors.description} /></div>
                            </section>
                            <section className="space-y-5 rounded-xl border p-5">
                                <div><h2 className="font-semibold">Brand assets</h2><p className="text-sm text-muted-foreground">Upload a logo and a wide banner. Images are stored in the public disk.</p></div>
                                <div className="grid gap-5 sm:grid-cols-2">
                                    <div className="space-y-3"><Label htmlFor="logo">Store logo</Label>{store.storefront.logo_url ? <img src={store.storefront.logo_url} alt="Current store logo" className="h-20 w-20 rounded-lg border object-contain p-2" /> : <div className="flex h-20 w-20 items-center justify-center rounded-lg border border-dashed text-xs text-muted-foreground">No logo</div>}<Input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp" /><p className="text-xs text-muted-foreground">PNG, JPG, or WebP; maximum 5 MB.</p><InputError message={errors.logo} />{store.storefront.logo_url ? <label className="flex items-center gap-2 text-sm"><input type="checkbox" name="remove_logo" value="1" /> Remove current logo</label> : null}</div>
                                    <div className="space-y-3"><Label htmlFor="banner">Store banner</Label>{store.storefront.banner_url ? <img src={store.storefront.banner_url} alt="Current store banner" className="h-20 w-full rounded-lg border object-cover" /> : <div className="flex h-20 items-center justify-center rounded-lg border border-dashed text-xs text-muted-foreground">No banner</div>}<Input id="banner" name="banner" type="file" accept="image/png,image/jpeg,image/webp" /><p className="text-xs text-muted-foreground">PNG, JPG, or WebP; maximum 10 MB.</p><InputError message={errors.banner} />{store.storefront.banner_url ? <label className="flex items-center gap-2 text-sm"><input type="checkbox" name="remove_banner" value="1" /> Remove current banner</label> : null}</div>
                                </div>
                            </section>
                            <section className="space-y-5 rounded-xl border p-5">
                                <div><h2 className="font-semibold">Theme colors</h2><p className="text-sm text-muted-foreground">Choose the main brand color and the supporting accent color.</p></div>
                                <div className="grid gap-5 sm:grid-cols-2">
                                    <div className="grid gap-2"><Label htmlFor="primary-color">Primary color</Label><div className="flex items-center gap-3"><Input id="primary-color" name="primary_color" type="color" defaultValue={store.storefront.primary_color} className="h-10 w-16 p-1" /><span className="text-sm text-muted-foreground">{store.storefront.primary_color}</span></div><InputError message={errors.primary_color} /></div>
                                    <div className="grid gap-2"><Label htmlFor="accent-color">Accent color</Label><div className="flex items-center gap-3"><Input id="accent-color" name="accent_color" type="color" defaultValue={store.storefront.accent_color} className="h-10 w-16 p-1" /><span className="text-sm text-muted-foreground">{store.storefront.accent_color}</span></div><InputError message={errors.accent_color} /></div>
                                </div>
                            </section>
                            <section className="space-y-5 rounded-xl border p-5">
                                <div><h2 className="font-semibold">Navigation</h2><p className="text-sm text-muted-foreground">Add up to five links. Use a relative path such as /about or a full HTTPS URL.</p></div>
                                {navigation.map((item, index) => (
                                    <div key={index} className="grid gap-3 sm:grid-cols-2">
                                        <div className="grid gap-2"><Label htmlFor={'nav-label-' + index}>Link {index + 1} label</Label><Input id={'nav-label-' + index} name={'navigation[' + index + '][label]'} defaultValue={item.label} maxLength={40} placeholder="About us" /><InputError message={(errors as Record<string, string>)['navigation.' + index + '.label']} /></div>
                                        <div className="grid gap-2"><Label htmlFor={'nav-url-' + index}>Link URL</Label><Input id={'nav-url-' + index} name={'navigation[' + index + '][url]'} defaultValue={item.url} maxLength={255} placeholder="/about" /><InputError message={(errors as Record<string, string>)['navigation.' + index + '.url']} /></div>
                                    </div>
                                ))}
                            </section>
                            <section className="space-y-5 rounded-xl border p-5">
                                <div><h2 className="font-semibold">Contact information</h2><p className="text-sm text-muted-foreground">Displayed in the public storefront footer.</p></div>
                                <div className="grid gap-2"><Label htmlFor="contact-email">Contact email</Label><Input id="contact-email" name="contact[email]" type="email" defaultValue={store.storefront.contact.email} maxLength={255} placeholder="hello@example.com" /><InputError message={(errors as Record<string, string>)['contact.email']} /></div>
                                <div className="grid gap-2"><Label htmlFor="contact-phone">Phone / WhatsApp</Label><Input id="contact-phone" name="contact[phone]" defaultValue={store.storefront.contact.phone} maxLength={40} placeholder="+62 812 3456 7890" /><InputError message={(errors as Record<string, string>)['contact.phone']} /></div>
                                <div className="grid gap-2"><Label htmlFor="contact-address">Business address</Label><textarea id="contact-address" name="contact[address]" defaultValue={store.storefront.contact.address} maxLength={500} rows={2} className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring" placeholder="Street, city, province" /><InputError message={(errors as Record<string, string>)['contact.address']} /></div>
                            </section>
                            <section className="space-y-5 rounded-xl border p-5">
                                <div><h2 className="font-semibold">Regional settings</h2><p className="text-sm text-muted-foreground">Used for store operations and currency display.</p></div>
                                <div className="grid gap-5 sm:grid-cols-2"><div className="grid gap-2"><Label htmlFor="store-timezone">Timezone</Label><Input id="store-timezone" name="timezone" defaultValue={store.timezone} required /><InputError message={errors.timezone} /></div><div className="grid gap-2"><Label htmlFor="store-currency">Currency</Label><Input id="store-currency" name="currency" defaultValue={store.currency} minLength={3} maxLength={3} required /><p className="text-xs text-muted-foreground">Three-letter currency code, such as IDR or USD.</p><InputError message={errors.currency} /></div></div>
                            </section>
                            <div className="flex flex-wrap items-center gap-3"><Button type="submit" disabled={processing}><Save /> {processing ? 'Saving…' : 'Save storefront configuration'}</Button><Button asChild type="button" variant="outline"><Link href={baseUrl}>Cancel</Link></Button></div>
                        </>
                    )}
                </Form>
                <section className="max-w-4xl rounded-xl border p-5"><h2 className="font-semibold">Store status</h2><p className="mt-1 text-sm text-muted-foreground">Current status: <span className="font-medium capitalize text-foreground">{store.status}</span>.</p><p className="mt-2 text-sm text-muted-foreground">Activation and suspension remain controlled by the Store lifecycle flow.</p></section>
            </div>
        </>
    );
}

StoreSettings.layout = (props: { team: { name: string; slug: string }; store: { name: string; id: number } }) => ({
    breadcrumbs: [
        { title: 'Teams', href: '/settings/teams' },
        { title: props.team.name, href: '/settings/teams/' + props.team.slug },
        { title: 'Stores', href: '/settings/teams/' + props.team.slug + '/stores' },
        { title: props.store.name, href: '/settings/teams/' + props.team.slug + '/stores/' + props.store.id },
        { title: 'Storefront', href: '/settings/teams/' + props.team.slug + '/stores/' + props.store.id + '/settings' },
    ],
});
