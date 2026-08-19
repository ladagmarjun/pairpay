import { Link } from '@inertiajs/react';
import {
    ChartColumn,
    HandCoins,
    Landmark,
    LayoutGrid,
    ReceiptText,
    TrendingUp,
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
import { dashboard, reports, shared } from '@/routes';
import { index as billsIndex } from '@/routes/bills';
import { index as expensesIndex } from '@/routes/expenses';
import { index as incomeIndex } from '@/routes/income';
import { index as loansIndex } from '@/routes/loans';
import { create as payDay } from '@/routes/payments';
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
        title: 'Income',
        href: incomeIndex(),
        icon: TrendingUp,
    },
    {
        title: 'Expenses',
        href: expensesIndex(),
        icon: Wallet,
    },
    {
        title: 'Bills',
        href: billsIndex(),
        icon: ReceiptText,
    },
    {
        title: 'Loans',
        href: loansIndex(),
        icon: Landmark,
    },
    {
        title: 'Pay',
        href: payDay(),
        icon: HandCoins,
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
