import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, ClipboardList, LayoutDashboard, LogOut, Store, UserRound } from 'lucide-react';
import type { ReactNode } from 'react';

type Props = { children: ReactNode; title: string; storeName: string };

const navigation = [
    { label: 'Overview', href: '/account', icon: LayoutDashboard, exact: true },
    { label: 'My orders', href: '/account/orders', icon: ClipboardList, exact: false },
    { label: 'My profile', href: '/account/profile', icon: UserRound, exact: false },
];

export default function CustomerAccountLayout({ children, title, storeName }: Props) {
    const page = usePage();
    const currentPath = page.url.split('?')[0];

    return <>
        <Head title={title + ' - ' + storeName} />
        <div className="min-h-screen bg-[#f7f7f5] text-slate-900">
            <header className="border-b border-black/10 bg-white">
                <div className="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-8">
                    <Link href="/" className="flex min-w-0 items-center gap-2 font-semibold"><Store className="h-5 w-5 shrink-0" /><span className="truncate">{storeName}</span></Link>
                    <div className="flex items-center gap-3">
                        <Link href="/" className="hidden items-center gap-2 text-sm text-slate-600 hover:text-slate-950 sm:flex"><ArrowLeft className="h-4 w-4" /> Back to store</Link>
                        <button type="button" onClick={() => router.post('/logout')} className="inline-flex items-center gap-2 rounded-md border border-black/10 px-3 py-2 text-sm font-medium hover:bg-slate-50"><LogOut className="h-4 w-4" /><span className="hidden sm:inline">Log out</span></button>
                    </div>
                </div>
            </header>
            <div className="mx-auto grid max-w-7xl gap-8 px-4 py-8 sm:px-8 lg:grid-cols-[240px_minmax(0,1fr)] lg:py-10">
                <aside>
                    <div className="mb-5"><p className="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Customer account</p><h1 className="mt-1 text-xl font-semibold">My account</h1></div>
                    <nav aria-label="Customer account navigation" className="flex gap-2 overflow-x-auto pb-2 lg:flex-col lg:overflow-visible">
                        {navigation.map((item) => {
                            const active = item.exact ? currentPath === item.href : currentPath.startsWith(item.href);
                            const Icon = item.icon;
                            return <Link key={item.href} href={item.href} aria-current={active ? 'page' : undefined} className={'inline-flex shrink-0 items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium transition ' + (active ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-white hover:text-slate-950')}><Icon className="h-4 w-4" />{item.label}</Link>;
                        })}
                    </nav>
                </aside>
                <main className="min-w-0">
                    <div className="mb-6"><p className="text-sm text-slate-500">{storeName} / Customer account</p><h2 className="mt-1 text-2xl font-semibold tracking-tight">{title}</h2></div>
                    {children}
                </main>
            </div>
        </div>
    </>;
}
