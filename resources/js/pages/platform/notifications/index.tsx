import { Head, Link, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Item = { id: string; event: string; title: string; message: string; url: string; read_at: string | null; created_at: string };
type Props = { notifications: { data: Item[]; links: { url: string | null; label: string; active: boolean }[] }; unreadCount: number };

export default function PlatformNotificationsIndex({ notifications, unreadCount }: Props) {
    return (
        <>
            <Head title="Platform Notifications" />
            <div className="space-y-6 p-4 md:p-6">
                <div className="flex items-center justify-between gap-4">
                    <div><h1 className="text-2xl font-semibold">Notifications</h1><p className="text-sm text-muted-foreground">Important platform activity that may need your attention.</p></div>
                    <Button variant="outline" disabled={!unreadCount} onClick={() => router.post('/platform/admin/notifications/read-all')}>Mark all read</Button>
                </div>
                <Card><CardHeader><CardTitle>Activity</CardTitle></CardHeader><CardContent className="space-y-3">
                    {notifications.data.length === 0 ? <p className="py-8 text-center text-sm text-muted-foreground">You’re all caught up.</p> : notifications.data.map((item) => (
                        <Link key={item.id} href={`/platform/admin/notifications/${item.id}/read`} className={`block rounded-lg border p-4 hover:bg-muted/50 ${item.read_at ? 'opacity-70' : 'border-primary/40 bg-muted/30'}`}>
                            <div className="flex items-start justify-between gap-3"><div><p className="font-medium">{item.title}</p><p className="mt-1 text-sm text-muted-foreground">{item.message}</p><p className="mt-2 text-xs text-muted-foreground">{new Date(item.created_at).toLocaleString()}</p></div>{!item.read_at && <span className="mt-1 size-2 shrink-0 rounded-full bg-primary" />}</div>
                        </Link>
                    ))}
                </CardContent></Card>
                <div className="flex flex-wrap gap-2">{notifications.links.map((link, index) => <Button key={index} variant={link.active ? 'default' : 'outline'} size="sm" disabled={!link.url} onClick={() => link.url && router.visit(link.url)}>{link.label.replace('&laquo;', '«').replace('&raquo;', '»')}</Button>)}</div>
            </div>
        </>
    );
}
