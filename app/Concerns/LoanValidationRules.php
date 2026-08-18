<?php

namespace App\Concerns;

use App\Enums\LoanDirection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait LoanValidationRules
{
    /**
     * Rules for a loan. Shared by the create and update requests so the two
     * flows can never drift apart.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function loanRules(): array
    {
        return [
            'counterparty' => ['required', 'string', 'max:200'],
            'direction' => ['required', Rule::enum(LoanDirection::class)],
            'principal' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'date' => ['required', 'date'],
            // The repayment plan is optional, but half a plan is not: a
            // term without a start date has no months to fall on.
            'term_months' => ['nullable', 'integer', 'between:1,600'],
            'first_due_date' => ['nullable', 'required_with:term_months', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function loanMessages(): array
    {
        return [
            'principal.gt' => 'Amount must be greater than zero.',
            'principal.numeric' => 'Enter a valid amount.',
            'counterparty.required' => 'Say who this is with.',
            'term_months.between' => 'A term is a number of months, between 1 and 600.',
            'first_due_date.required_with' => 'Say when the first installment is due.',
        ];
    }
}
