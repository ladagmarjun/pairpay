/** A labelled rule dividing social sign-in from the email/password form. */
export function OrSeparator({ label = 'or' }: { label?: string }) {
    return (
        <div className="flex items-center gap-3">
            <span className="h-px flex-1 bg-border" />
            <span className="text-xs text-muted-foreground uppercase">
                {label}
            </span>
            <span className="h-px flex-1 bg-border" />
        </div>
    );
}
