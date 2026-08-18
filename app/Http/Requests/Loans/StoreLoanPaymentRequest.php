<?php

namespace App\Http\Requests\Loans;

use App\Models\Loan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreLoanPaymentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * A payment cannot exceed what is left to pay. That is a rule about the
     * loan rather than the field, so the ceiling is read off the route's loan
     * instead of being hard-coded — paying ₱600 against ₱500 outstanding is a
     * typo, not a payment.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        $outstanding = $this->loan()->outstanding();

        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:'.$outstanding],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
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
            'amount.max' => 'That is more than is left to pay on this loan.',
        ];
    }

    private function loan(): Loan
    {
        $loan = $this->route('loan');

        return $loan instanceof Loan ? $loan : new Loan;
    }
}
