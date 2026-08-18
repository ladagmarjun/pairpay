import { Head } from '@inertiajs/react';
import LoanController from '@/actions/App/Http/Controllers/LoanController';
import Heading from '@/components/heading';
import { LoanForm } from '@/components/loans/loan-form';
import { create as createLoan, index as loansIndex } from '@/routes/loans';
import type { DirectionOption } from '@/types';

export default function CreateLoan({
    directions,
}: {
    directions: DirectionOption[];
}) {
    return (
        <>
            <Head title="Add loan" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Add loan"
                    description="Money you borrowed, or money you lent out."
                />

                <LoanForm
                    formProps={LoanController.store.form()}
                    directions={directions}
                    submitLabel="Add loan"
                />
            </div>
        </>
    );
}

CreateLoan.layout = {
    breadcrumbs: [
        { title: 'Loans', href: loansIndex() },
        { title: 'Add loan', href: createLoan() },
    ],
};
