<?php

namespace App\Concerns;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait BillValidationRules
{
    /**
     * Rules for a recurring bill. Shared by the create and update requests so
     * the two flows can never drift apart.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function billRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            // When the bill first falls due. It recurs on that day monthly
            // from then on; a month too short for the day clamps rather than
            // being rejected, so the 31st stays usable.
            'due_date' => ['required', 'date'],
            'category' => ['required', Rule::enum(ExpenseCategory::class)],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function billMessages(): array
    {
        return [
            'amount.gt' => 'Amount must be greater than zero.',
            'amount.numeric' => 'Enter a valid amount.',
            'due_date.date' => 'Enter a valid due date.',
        ];
    }
}
