import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Bell } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Item = { id: string; event: string; title: string; message: string; read_at: string | null; created_at: string };
type Props = {
    team: { name: string; slug: string };
    store: { id: number; name: string };
    notifications: { data: Item[]; links: { url: string | null; label: string; active: boolean }[] };
    unreadCount: number;
};

export default function StoreNotificationsIndex({ team, store, notifications, unreadCount }: Props) {
    const base = '/settings/teams/' + team.slug + '/stores/' + store.id;

    return (
        <>
            <Head title={'Notifications — ' + store.name} />
            <div className="flex flex-col gap-6">
                <div className="m-2">
                    <Button asChild variant="outline" size="sm" className="mb-2">
                        <Link href={base + '/dashboard'}><ArrowLeft /> Go back</Link>
                    </Button>
                    <div className="flex items-center justify-between gap-3">
                        <Heading variant="small" title="Notifications" description={'Important activity for ' + store.name + '.'} />
                        <Button variant="outline" disabled={!unreadCount} onClick={() => router.post(base + '/notifications/read-all')}>Mark all read</Button>
                    </div>
                </div>
                <Card>
                    <CardHeader><CardTitle>Activity</CardTitle></CardHeader>
                    <CardContent className="space-y-3">
                        {notifications.data.length === 0 ? (
                            <div className="py-10 text-center"><Bell className="mx-auto mb-3 h-8 w-8 text-muted-foreground" /><p className="font-medium">You’re all caught up.</p><p className="mt-1 text-sm text-muted-foreground">New paid orders and low-stock alerts will appear here.</p></div>
                        ) : notifications.data.map((item) => (
                            <Link key={item.id} href={base + '/notifications/' + item.id + '/read'} className={'block rounded-lg border p-4 hover:bg-muted/50 ' + (item.read_at ? 'opacity-70' : 'border-primary/40 bg-muted/30')}>
                                <div className="flex items-start justify-between gap-3">
                                    <div><p className="font-medium">{item.title}</p><p className="mt-1 text-sm text-muted-foreground">{item.message}</p><p className="mt-2 text-xs text-muted-foreground">{new Date(item.created_at).toLocaleString('en-US')}</p></div>
                                    {!item.read_at && <span className="mt-1 size-2 shrink-0 rounded-full bg-primary" />}
                                </div>
                            </Link>
                        ))}
                    </CardContent>
                </Card>
                <div className="flex flex-wrap justify-center gap-2">{notifications.links.map((link, index) => <Button key={index} size="sm" variant={link.active ? 'default' : 'outline'} disabled={!link.url} onClick={() => link.url && router.visit(link.url)}>{link.label.replace('&laquo;', '«').replace('&raquo;', '»')}</Button>)}</div>
            </div>
        </>
    );
}

StoreNotificationsIndex.layout = (props: { team: { name: string; slug: string }; store: { name: string; id: number } }) => ({
    breadcrumbs: [
        { title: 'Teams', href: '/settings/teams' },
        { title: props.team.name, href: '/settings/teams/' + props.team.slug },
        { title: 'Stores', href: '/settings/teams/' + props.team.slug + '/stores' },
        { title: props.store.name, href: '/settings/teams/' + props.team.slug + '/stores/' + props.store.id },
        { title: 'Notifications', href: '/settings/teams/' + props.team.slug + '/stores/' + props.store.id + '/notifications' },
    ],
});
