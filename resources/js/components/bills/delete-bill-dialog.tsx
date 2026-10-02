import { Form } from '@inertiajs/react';
import BillController from '@/actions/App/Http/Controllers/BillController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { Bill } from '@/types';

/**
 * Confirmation before a destructive delete, matching `DeleteExpenseDialog`.
 * The payments this bill already produced are expenses in their own right and
 * survive it, which is worth saying out loud here.
 */
export function DeleteBillDialog({
    bill,
    onClose,
}: {
    bill: Bill | null;
    onClose: () => void;
}) {
    return (
        <Dialog
            open={bill !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete bill?</DialogTitle>

                    <DialogDescription>
                        “{bill?.name}” will be removed from this month. The
                        expenses you already recorded for it are kept.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        Cancel
                    </Button>

                    {bill && (
                        <Form
                            {...BillController.destroy.form(bill.id)}
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
