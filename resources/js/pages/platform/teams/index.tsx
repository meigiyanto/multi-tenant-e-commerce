import { Form, Head, Link } from '@inertiajs/react';
import { ShieldCheck, Users, Store } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Team = {
    id: number;
    name: string;
    slug: string;
    status: string;
    is_personal: boolean;
    stores_count: number;
    members_count: number;
    created_at: string | null;
};

type Props = {
    teams: {
        data: Team[];
        links: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
        total: number;
    };
};

function nextStatuses(status: string): string[] {
    if (status === 'pending') return ['active', 'cancelled'];
    if (status === 'active') return ['suspended', 'cancelled'];
    if (status === 'suspended') return ['active', 'cancelled'];
    return [];
}

export default function PlatformTeamsIndex({ teams }: Props) {
    return (
        <>
            <Head title="Platform — Teams" />
            <div className="flex flex-col gap-6">
                <div className="flex items-center gap-3">
                    <div className="flex h-11 w-11 items-center justify-center rounded-lg bg-muted"><ShieldCheck className="h-5 w-5" /></div>
                    <Heading variant="small" title="Platform Teams" description="Review tenant accounts and control their lifecycle." />
                    <Badge className="ml-auto" variant="secondary">{teams.total} teams</Badge>
                </div>
                <div className="space-y-3">
                    {teams.data.map((team) => (
                        <article key={team.id} className="flex flex-col gap-4 rounded-xl border p-4 lg:flex-row lg:items-center lg:justify-between">
                            <div className="min-w-0 space-y-1">
                                <div className="flex flex-wrap items-center gap-2"><h2 className="font-semibold">{team.name}</h2><Badge variant={team.status === 'active' ? 'default' : 'secondary'}>{team.status}</Badge>{team.is_personal ? <Badge variant="outline">Personal</Badge> : null}</div>
                                <p className="text-sm text-muted-foreground">{team.slug} · Team #{team.id}</p>
                                <div className="flex flex-wrap gap-4 text-xs text-muted-foreground"><span className="inline-flex items-center gap-1"><Users className="h-3.5 w-3.5" /> {team.members_count} members</span><span className="inline-flex items-center gap-1"><Store className="h-3.5 w-3.5" /> {team.stores_count} stores</span></div>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                {nextStatuses(team.status).map((status) => (
                                    <Form key={status} action={'/platform/admin/teams/' + team.slug + '/status'} method="patch">
                                        <input type="hidden" name="status" value={status} />
                                        <Button size="sm" variant={status === 'cancelled' ? 'destructive' : 'outline'} type="submit">{status === 'active' ? 'Activate' : status === 'suspended' ? 'Suspend' : 'Cancel team'}</Button>
                                    </Form>
                                ))}
                            </div>
                        </article>
                    ))}
                    {teams.data.length === 0 ? <div className="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground">No Teams found.</div> : null}
                </div>
                <nav className="flex flex-wrap justify-center gap-2" aria-label="Pagination">
                    {teams.links.map((link, index) => (
                        link.url ? <Button key={index} asChild size="sm" variant={link.active ? 'default' : 'outline'}><Link href={link.url}>{link.label.replace(/<[^>]*>/g, "").replace(/&laquo;/g, "«").replace(/&raquo;/g, "»")}</Link></Button> : <Button key={index} size="sm" variant="outline" disabled>{link.label.replace(/&laquo;|&raquo;/g, '')}</Button>
                    ))}
                </nav>
                <p className="text-center text-xs text-muted-foreground">Page {teams.current_page} of {teams.last_page}</p>
            </div>
        </>
    );
}
