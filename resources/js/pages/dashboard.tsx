import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { DynamicIcon } from '@/components/dynamic-icon';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/hooks/use-initials';
import { dashboard, reports, shared } from '@/routes';
import { index as billsIndex } from '@/routes/bills';
import { index as expensesIndex } from '@/routes/expenses';
import { index as loansIndex } from '@/routes/loans';
import type { Auth } from '@/types';

/**
 * Landing screen for the authenticated app. Mirrors the mobile app's dashboard
 * placeholder: it confirms who is signed in and lays out the roadmap, while the
 * charts-and-insights dashboard is still ahead (Phase 7).
 */
const MODULES = [
    {
        title: 'Expenses',
        description:
            'Track what you spend, filter it, and watch the month add up.',
        href: expensesIndex(),
        icon: 'wallet',
        available: true,
    },
    {
        title: 'Bills',
        description: 'Fixed monthly expenses, and what is still to pay.',
        href: billsIndex(),
        icon: 'receipt-text',
        available: true,
    },
    {
        title: 'Loans',
        description:
            'What you owe, what you lent, and the payments against each.',
        href: loansIndex(),
        icon: 'landmark',
        available: true,
    },
    {
        title: 'Shared',
        description: 'Split expenses with a partner, housemates, or family.',
        href: shared(),
        icon: 'users',
        available: false,
    },
    {
        title: 'Reports',
        description: 'Spending trends and category insights over time.',
        href: reports(),
        icon: 'chart-column',
        available: false,
    },
];

export default function Dashboard() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const getInitials = useInitials();

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-center gap-4">
                    <span className="flex size-14 shrink-0 items-center justify-center rounded-full bg-primary-container text-lg font-semibold text-primary-container-foreground">
                        {getInitials(auth.user.name)}
                    </span>

                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">
                            Welcome, {auth.user.name}
                        </h1>

                        <p className="text-sm text-muted-foreground">
                            Here is what PairPay can do so far — the rest is on
                            the way.
                        </p>
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {MODULES.map((module) => (
                        <div
                            key={module.title}
                            className="flex flex-col gap-3 rounded-xl border p-5"
                        >
                            <div className="flex items-center gap-3">
                                <DynamicIcon
                                    name={module.icon}
                                    className="size-5 text-primary"
                                />

                                <h2 className="flex-1 font-medium">
                                    {module.title}
                                </h2>

                                {!module.available && (
                                    <span className="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                                        Soon
                                    </span>
                                )}
                            </div>

                            <p className="flex-1 text-sm text-muted-foreground">
                                {module.description}
                            </p>

                            <Button
                                asChild
                                variant={module.available ? 'default' : 'ghost'}
                                size="sm"
                                className="self-start"
                            >
                                <Link href={module.href}>
                                    {module.available ? 'Open' : 'Preview'}
                                    <ArrowRight className="size-4" />
                                </Link>
                            </Button>
                        </div>
                    ))}
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
