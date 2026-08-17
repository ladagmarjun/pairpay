<?php

namespace App\Http\Requests\Expenses;

use App\Concerns\ExpenseValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    use ExpenseValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->expenseRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->expenseMessages();
    }
}
