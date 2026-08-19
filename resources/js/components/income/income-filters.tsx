import { Link, router } from '@inertiajs/react';
import { FilterX, Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { index as incomeIndex } from '@/routes/income';
import type { IncomeCategoryOption, IncomeFilters } from '@/types';

/**
 * Search box, category chips, and a date range — the expense filter bar, over
 * the income list. The filter lives in the query string rather than component
 * state, so a filtered list is linkable and survives a refresh.
 *
 * Search is debounced and issues a partial reload; the chips and dates are
 * plain links.
 */
export function IncomeFilterBar({
    filters,
    categories,
}: {
    filters: IncomeFilters;
    categories: IncomeCategoryOption[];
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const isFirstRender = useRef(true);

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        const timeout = setTimeout(() => {
            router.get(
                incomeIndex.url({
                    mergeQuery: { search: search || null, page: null },
                }),
                {},
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    only: ['income', 'filters'],
                },
            );
        }, 300);

        return () => clearTimeout(timeout);
    }, [search]);

    return (
        <div className="space-y-3">
            <div className="flex flex-wrap items-center gap-2">
                <div className="relative min-w-56 flex-1">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />

                    <Input
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Search income"
                        aria-label="Search income"
                        className="pl-9"
                    />
                </div>

                <div className="flex items-center gap-2">
                    <Label
                        htmlFor="start_date"
                        className="text-sm text-muted-foreground"
                    >
                        From
                    </Label>

                    <Input
                        id="start_date"
                        type="date"
                        className="w-40"
                        defaultValue={filters.start_date ?? ''}
                        onChange={(event) =>
                            router.get(
                                incomeIndex.url({
                                    mergeQuery: {
                                        start_date: event.target.value || null,
                                        page: null,
                                    },
                                }),
                                {},
                                { preserveState: true, preserveScroll: true },
                            )
                        }
                    />

                    <Label
                        htmlFor="end_date"
                        className="text-sm text-muted-foreground"
                    >
                        To
                    </Label>

                    <Input
                        id="end_date"
                        type="date"
                        className="w-40"
                        defaultValue={filters.end_date ?? ''}
                        onChange={(event) =>
                            router.get(
                                incomeIndex.url({
                                    mergeQuery: {
                                        end_date: event.target.value || null,
                                        page: null,
                                    },
                                }),
                                {},
                                { preserveState: true, preserveScroll: true },
                            )
                        }
                    />
                </div>

                {filters.is_active && (
                    <Button asChild variant="ghost" size="sm">
                        <Link
                            href={incomeIndex({
                                mergeQuery: {
                                    search: null,
                                    category: null,
                                    start_date: null,
                                    end_date: null,
                                    page: null,
                                },
                            })}
                            preserveScroll
                            onClick={() => setSearch('')}
                        >
                            <FilterX className="size-4" />
                            Clear filters
                        </Link>
                    </Button>
                )}
            </div>

            <div className="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1">
                {categories.map((category) => {
                    const selected = filters.category === category.value;

                    return (
                        <Link
                            key={category.value}
                            href={incomeIndex({
                                mergeQuery: {
                                    category: selected ? null : category.value,
                                    page: null,
                                },
                            })}
                            preserveScroll
                            className={cn(
                                'inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm transition-colors',
                                selected
                                    ? 'border-transparent text-white'
                                    : 'hover:bg-muted',
                            )}
                            style={
                                selected
                                    ? { backgroundColor: category.color }
                                    : undefined
                            }
                        >
                            {category.label}
                            {selected && <X className="size-3.5" />}
                        </Link>
                    );
                })}
            </div>
        </div>
    );
}
