import { Head } from '@inertiajs/react';
import { DynamicIcon } from '@/components/dynamic-icon';

/**
 * Placeholder for destinations that arrive in a later phase. Keeps the
 * navigation structure complete while modules are built incrementally — the
 * web counterpart of the mobile app's `ComingSoon` tabs. Its title, icon, and
 * phase come straight from the route definition in `routes/web.php`.
 */
export default function ComingSoon({
    title,
    icon,
    phase,
}: {
    title: string;
    icon: string;
    phase?: string;
}) {
    return (
        <>
            <Head title={title} />

            <div className="flex flex-1 items-center justify-center p-8">
                <div className="flex max-w-md flex-col items-center text-center">
                    <DynamicIcon
                        name={icon}
                        className="size-16 text-muted-foreground/60"
                        strokeWidth={1.5}
                    />

                    <h1 className="mt-4 text-xl font-semibold">
                        {title} coming soon
                    </h1>

                    {phase && (
                        <p className="mt-1 text-sm text-muted-foreground">
                            {phase}
                        </p>
                    )}
                </div>
            </div>
        </>
    );
}
