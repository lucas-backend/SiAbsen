import { Link, usePage } from '@inertiajs/react';
import { BookOpen, ClipboardCheck, FolderGit2, LayoutGrid } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import attendance from '@/routes/attendance';
import { dashboard } from '@/routes';
import type { Auth, NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const reviewNavItem: NavItem = {
    title: 'Persetujuan Izin',
    href: attendance.requests.review.page(),
    icon: ClipboardCheck,
};

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/lucas-backend/SiAbsen',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://github.com/lucas-backend/SiAbsen#readme',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { auth } = usePage<{ auth?: Auth }>().props;
    const role = auth?.user?.role;
    const items =
        role === 'TEACHER' || role === 'ADMIN'
            ? [...mainNavItems, reviewNavItem]
            : mainNavItems;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={items} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
