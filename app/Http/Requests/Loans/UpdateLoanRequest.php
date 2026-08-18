<?php

namespace App\Http\Requests\Loans;

use App\Concerns\LoanValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLoanRequest extends FormRequest
{
    use LoanValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->loanRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->loanMessages();
    }
}
