import { Form } from '@inertiajs/react';
import ExpenseController from '@/actions/App/Http/Controllers/ExpenseController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { Expense } from '@/types';

/**
 * Confirmation before a destructive delete — deleting an expense is not
 * undoable, so it never happens on a single click.
 */
export function DeleteExpenseDialog({
    expense,
    onClose,
}: {
    expense: Expense | null;
    onClose: () => void;
}) {
    return (
        <Dialog
            open={expense !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete expense?</DialogTitle>

                    <DialogDescription>
                        “{expense?.title}” will be permanently removed.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        Cancel
                    </Button>

                    {expense && (
                        <Form
                            {...ExpenseController.destroy.form(expense.id)}
                            options={{ preserveScroll: true }}
                            onSuccess={onClose}
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    Delete
                                </Button>
                            )}
                        </Form>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
