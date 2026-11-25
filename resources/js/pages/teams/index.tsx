import { Head, Link } from '@inertiajs/react';
import { Eye, LogOut, Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import CreateTeamModal from '@/components/create-team-modal';
import Heading from '@/components/heading';
import LeaveTeamModal from '@/components/leave-team-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { edit, index } from '@/routes/teams';
import type { Team } from '@/types';

type Props = { teams: Team[]; canCreateTeam: boolean; merchantApplicationStatus: string | null };

export default function TeamsIndex({ teams, canCreateTeam, merchantApplicationStatus }: Props) {
    const [leaveTeamDialogOpen, setLeaveTeamDialogOpen] = useState(false);
    const [teamLeaving, setTeamLeaving] = useState<Team | null>(null);
    const openLeaveTeamDialog = (team: Team) => { setTeamLeaving(team); setLeaveTeamDialogOpen(true); };

    return <>
        <Head title="Merchant workspaces" />
        <h1 className="sr-only">Merchant workspaces</h1>
        <div className="flex flex-col space-y-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <Heading variant="small" title="Merchant workspaces" description="Manage the businesses and stores you own or collaborate with." />
                {canCreateTeam ? <CreateTeamModal><Button data-test="teams-new-team-button"><Plus /> New workspace</Button></CreateTeamModal> : <Button asChild variant="outline"><Link href="/merchant/application">{merchantApplicationStatus === 'pending' ? 'View merchant application' : merchantApplicationStatus === 'rejected' ? 'Resubmit merchant application' : 'Become a merchant'}</Link></Button>}
            </div>
            {!canCreateTeam ? <p className="rounded-lg border bg-muted/30 p-4 text-sm text-muted-foreground">{merchantApplicationStatus === 'pending' ? 'Your application is awaiting review. Workspace creation will be enabled after approval.' : merchantApplicationStatus === 'rejected' ? 'Your application was not approved. Review the notes and submit an updated application.' : 'A verified account and an approved merchant application are required before creating a workspace.'}</p> : null}
            <div className="space-y-3">
                {teams.map((team) => {
                    const canLeaveTeam = !team.isPersonal && team.role !== 'owner';
                    return <div key={team.id} data-test="team-row" className="flex items-center justify-between gap-4 rounded-lg border p-4">
                        <div><div className="flex items-center gap-2"><span className="font-medium">{team.name}</span>{team.isPersonal ? <Badge variant="secondary">Personal</Badge> : null}</div><span className="text-sm text-muted-foreground">{team.roleLabel}</span></div>
                        <TooltipProvider><div className="flex items-center gap-2">
                            {canLeaveTeam ? <Tooltip><TooltipTrigger asChild><Button variant="ghost" size="sm" data-test="team-leave-button" onClick={() => openLeaveTeamDialog(team)}><LogOut className="h-4 w-4" /></Button></TooltipTrigger><TooltipContent><p>Leave workspace</p></TooltipContent></Tooltip> : null}
                            <Tooltip><TooltipTrigger asChild><Button variant="ghost" size="sm" data-test="team-edit-button" asChild><Link href={edit(team.slug)}>{team.role === 'member' ? <Eye className="h-4 w-4" /> : <Pencil className="h-4 w-4" />}</Link></Button></TooltipTrigger><TooltipContent><p>{team.role === 'member' ? 'View workspace' : 'Manage workspace'}</p></TooltipContent></Tooltip>
                        </div></TooltipProvider>
                    </div>;
                })}
                {teams.length === 0 ? <p className="py-8 text-center text-muted-foreground">You do not belong to any merchant workspaces yet.</p> : null}
            </div>
        </div>
        <LeaveTeamModal team={teamLeaving} open={leaveTeamDialogOpen} onOpenChange={setLeaveTeamDialogOpen} />
    </>;
}

TeamsIndex.layout = { breadcrumbs: [{ title: 'Merchant workspaces', href: index() }] };
