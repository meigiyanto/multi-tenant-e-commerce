import { Link, usePage } from '@inertiajs/react';
import { Bell, BookOpen, FolderGit2, LayoutGrid, ReceiptText, ShieldCheck } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { TeamSwitcher } from '@/components/team-switcher';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const page = usePage();
    const { auth, currentTeam, teams, platformNotifications } = page.props;
    const dashboardUrl = currentTeam ? dashboard(currentTeam.slug) : '/';

    const mainNavItems: NavItem[] = [
        ...(currentTeam ? [{ title: 'Dashboard', href: dashboardUrl, icon: LayoutGrid }] : []),
        ...(auth.isPlatformAdmin ? [{
            title: 'Platform Teams',
            href: '/platform/admin/teams',
            icon: ShieldCheck,
        }, {
            title: 'Notifications' + (platformNotifications?.unread_count ? ` (${platformNotifications.unread_count})` : ''),
            href: '/platform/admin/notifications',
            icon: Bell,
        }, {
            title: 'Refund Requests',
            href: '/platform/admin/refunds',
            icon: ReceiptText,
        }] : []),
    ];

    const footerNavItems: NavItem[] = [
        {
            title: 'Repository',
            href: 'https://github.com/laravel/react-starter-kit',
            icon: FolderGit2,
        },
        {
            title: 'Documentation',
            href: 'https://laravel.com/docs/starter-kits#react',
            icon: BookOpen,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" className="group-data-[collapsible=icon]:size-12! group-data-[collapsible=icon]:p-2!" asChild>
                            <Link href={dashboardUrl} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                {auth.user && teams.length > 0 ? (
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <TeamSwitcher />
                        </SidebarMenuItem>
                    </SidebarMenu>
                ) : null}
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
