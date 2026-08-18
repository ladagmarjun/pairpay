import { Form } from '@inertiajs/react';
import LoanController from '@/actions/App/Http/Controllers/LoanController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { Loan } from '@/types';

/**
 * Confirmation before a destructive delete. Unlike a bill, a loan takes its
 * payments with it — they are not expenses and exist only as its history — so
 * the dialog says so.
 */
export function DeleteLoanDialog({
    loan,
    onClose,
}: {
    loan: Loan | null;
    onClose: () => void;
}) {
    return (
        <Dialog
            open={loan !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete loan?</DialogTitle>

                    <DialogDescription>
                        The loan with “{loan?.counterparty}” and every payment
                        recorded against it will be permanently removed.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        Cancel
                    </Button>

                    {loan && (
                        <Form
                            {...LoanController.destroy.form(loan.id)}
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
