import { Form } from '@inertiajs/react';
import IncomeController from '@/actions/App/Http/Controllers/IncomeController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { Income } from '@/types';

/**
 * Confirmation before a destructive delete — deleting an income entry is not
 * undoable, so it never happens on a single click.
 */
export function DeleteIncomeDialog({
    income,
    onClose,
}: {
    income: Income | null;
    onClose: () => void;
}) {
    return (
        <Dialog
            open={income !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete income?</DialogTitle>

                    <DialogDescription>
                        “{income?.source}” will be permanently removed.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        Cancel
                    </Button>

                    {income && (
                        <Form
                            {...IncomeController.destroy.form(income.id)}
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
