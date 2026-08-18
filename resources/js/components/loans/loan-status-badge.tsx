import { cn } from '@/lib/utils';
import type { LoanStatusValue } from '@/types';

const BADGES: Record<LoanStatusValue, { label: string; className: string }> = {
    open: { label: 'Open', className: 'bg-muted text-muted-foreground' },
    partial: {
        label: 'Part paid',
        className: 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
    },
    settled: {
        label: 'Settled',
        className: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400',
    },
};

/**
 * How far through a loan is. The server derives this from the payments, so the
 * badge cannot disagree with the balance next to it.
 */
export function LoanStatusBadge({ status }: { status: LoanStatusValue }) {
    const badge = BADGES[status];

    return (
        <span
            className={cn(
                'shrink-0 rounded-full px-2 py-0.5 text-xs font-medium',
                badge.className,
            )}
        >
            {badge.label}
        </span>
    );
}
