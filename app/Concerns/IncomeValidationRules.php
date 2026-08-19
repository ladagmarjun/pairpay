<?php

namespace App\Concerns;

use App\Enums\IncomeCategory;
use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait IncomeValidationRules
{
    /**
     * Get the validation rules used to validate income. Shared by the create
     * and update requests so the two flows can never drift apart.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function incomeRules(): array
    {
        return [
            'source' => ['required', 'string', 'max:200'],
            'category' => ['required', Rule::enum(IncomeCategory::class)],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function incomeMessages(): array
    {
        return [
            'amount.gt' => 'Amount must be greater than zero.',
            'amount.numeric' => 'Enter a valid amount.',
            'source.required' => 'Say where this came from.',
        ];
    }
}
