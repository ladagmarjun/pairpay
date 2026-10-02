import { Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

/**
 * The signed-out shell: a branded panel beside the form.
 *
 * The panel is deliberately the same deep teal in both themes. It is a brand
 * surface rather than a piece of UI — inverting it would leave the app with two
 * different front doors, and the form half already answers to the theme.
 *
 * Everything in the panel is decorative, so it is hidden below `lg` rather than
 * stacked: on a phone it would push the form itself below the fold, and the
 * form is the only thing anyone came here to use.
 */
export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props;

    return (
        <div className="relative grid min-h-svh lg:grid-cols-[1.1fr_1fr]">
            <aside
                aria-hidden="true"
                className="relative hidden flex-col justify-between overflow-hidden bg-[oklch(0.28_0.06_183)] p-10 text-white lg:flex xl:p-14"
            >
                {/* Two soft pools of light rather than a flat fill, so the
                    panel has some depth behind the copy without needing an
                    image the deploy would have to ship. */}
                <div
                    className="pointer-events-none absolute inset-0 opacity-70"
                    style={{
                        background:
                            'radial-gradient(70% 60% at 15% 0%, oklch(0.45 0.10 183) 0%, transparent 60%), radial-gradient(60% 55% at 95% 100%, oklch(0.38 0.09 183) 0%, transparent 65%)',
                    }}
                />

                <div className="relative z-10 flex items-center gap-2.5 text-lg font-semibold tracking-tight">
                    <AppLogoIcon className="size-8 fill-current" />
                    {name}
                </div>

                <div className="relative z-10 max-w-md">
                    <h2 className="text-3xl font-semibold tracking-tight text-balance xl:text-4xl">
                        Every peso, accounted for.
                    </h2>

                    <p className="mt-4 text-base/relaxed text-white/70">
                        Track what you spend, keep the monthly bills from
                        creeping up on you, and always know who owes whom.
                    </p>

                    <ul className="mt-10 space-y-4">
                        {HIGHLIGHTS.map((highlight) => (
                            <li
                                key={highlight.label}
                                className="flex items-center gap-3.5"
                            >
                                {/* The same semantic colors the app uses for
                                    these figures once you are inside, so the
                                    panel is a preview rather than decoration. */}
                                <span
                                    className="size-2.5 shrink-0 rounded-full"
                                    style={{ backgroundColor: highlight.color }}
                                />
                                <span className="text-sm text-white/85">
                                    <span className="font-medium text-white">
                                        {highlight.label}
                                    </span>{' '}
                                    {highlight.detail}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>

                <p className="relative z-10 text-xs text-white/45">
                    Your records stay yours. {name} never shares them.
                </p>
            </aside>

            <main className="flex flex-col items-center justify-center bg-background px-6 py-10 sm:px-10">
                <div className="w-full max-w-sm">
                    <Link
                        href={home()}
                        className="mb-8 flex items-center justify-center gap-2 text-lg font-semibold tracking-tight lg:hidden"
                    >
                        <AppLogoIcon className="size-7 fill-current text-primary" />
                        {name}
                    </Link>

                    <div className="mb-8 flex flex-col gap-2 text-center">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {title}
                        </h1>
                        <p className="text-sm text-balance text-muted-foreground">
                            {description}
                        </p>
                    </div>

                    {children}
                </div>
            </main>
        </div>
    );
}

/**
 * What the app is for, in the order a new account meets it. The colors are the
 * `--expense` / `--owed` / `--lent` tokens, hard-coded to their dark-theme
 * values because the panel does not follow the theme.
 */
const HIGHLIGHTS = [
    {
        label: 'Expenses',
        detail: 'sorted by category, summed by month.',
        color: 'oklch(0.68 0.19 27)',
    },
    {
        label: 'Bills',
        detail: 'month by month, with what is still owed.',
        color: 'oklch(0.75 0.16 62)',
    },
    {
        label: 'Loans',
        detail: 'lent or borrowed, down to the last installment.',
        color: 'oklch(0.68 0.15 257)',
    },
];
