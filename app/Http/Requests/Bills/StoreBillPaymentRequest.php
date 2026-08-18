<?php

namespace App\Http\Requests\Bills;

use App\Models\Bill;
use App\Support\MonthInput;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreBillPaymentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * The amount is optional: leaving it out clears whatever is left, which is
     * the ordinary "mark this paid" case. Sending one pays part of the month.
     *
     * Either way it cannot exceed what is still owed — paying ₱900 against a
     * bill with ₱400 left is a typo, and the month has nowhere to put the
     * difference.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['nullable', 'numeric', 'gt:0', 'max:'.$this->remaining()],
            'month' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.gt' => 'Amount must be greater than zero.',
            'amount.numeric' => 'Enter a valid amount.',
            'amount.max' => 'That is more than is left to pay on this bill.',
        ];
    }

    /**
     * What is still owed on the month being paid.
     */
    private function remaining(): float
    {
        $bill = $this->route('bill');

        if (! $bill instanceof Bill) {
            return 0.0;
        }

        return $bill->remainingIn(MonthInput::fromRequest($this));
    }
}
