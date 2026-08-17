<?php

namespace App\Concerns;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ExpenseValidationRules
{
    /**
     * Get the validation rules used to validate expenses. Shared by the create
     * and update requests so the two flows can never drift apart.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function expenseRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'category' => ['required', Rule::enum(ExpenseCategory::class)],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function expenseMessages(): array
    {
        return [
            'amount.gt' => 'Amount must be greater than zero.',
            'amount.numeric' => 'Enter a valid amount.',
        ];
    }
}
