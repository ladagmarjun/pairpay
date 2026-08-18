import { Head } from '@inertiajs/react';
import LoanController from '@/actions/App/Http/Controllers/LoanController';
import Heading from '@/components/heading';
import { LoanForm } from '@/components/loans/loan-form';
import { index as loansIndex } from '@/routes/loans';
import type { DirectionOption, Loan } from '@/types';

export default function EditLoan({
    loan,
    directions,
}: {
    loan: Loan;
    directions: DirectionOption[];
}) {
    return (
        <>
            <Head title="Edit loan" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Edit loan"
                    description="Payments already recorded are left alone; the balance follows the new amount."
                />

                <LoanForm
                    formProps={LoanController.update.form(loan.id)}
                    loan={loan}
                    directions={directions}
                    submitLabel="Save changes"
                />
            </div>
        </>
    );
}

EditLoan.layout = {
    breadcrumbs: [
        { title: 'Loans', href: loansIndex() },
        { title: 'Edit loan', href: loansIndex() },
    ],
};
