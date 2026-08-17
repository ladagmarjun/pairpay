import { usePage } from '@inertiajs/react';
import type { Auth } from '@/types';

/**
 * The signed-in user's currency code, for formatting money. Set in profile
 * settings; new accounts default to USD.
 */
export function useCurrency(): string {
    const { auth } = usePage<{ auth: Auth }>().props;

    return auth?.user?.currency_code ?? 'USD';
}
