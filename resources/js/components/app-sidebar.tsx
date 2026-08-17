import { Link } from '@inertiajs/react';
import {
    ChartColumn,
    Landmark,
    LayoutGrid,
    ReceiptText,
    Users,
    Wallet,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
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
import { bills, dashboard, loans, reports, shared } from '@/routes';
import { index as expensesIndex } from '@/routes/expenses';
import type { NavItem } from '@/types';

/**
 * The app's top-level destinations — the same set the mobile app exposes
 * through its bottom navigation, with the later-phase modules kept visible so
 * the shape of the product is clear.
 */
const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Expenses',
        href: expensesIndex(),
        icon: Wallet,
    },
    {
        title: 'Bills',
        href: bills(),
        icon: ReceiptText,
    },
    {
        title: 'Loans',
        href: loans(),
        icon: Landmark,
    },
    {
        title: 'Shared',
        href: shared(),
        icon: Users,
    },
    {
        title: 'Reports',
        href: reports(),
        icon: ChartColumn,
    },
];

export function AppSidebar() {
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
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
