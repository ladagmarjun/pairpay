<?php

namespace App\Http\Requests\Income;

use App\Concerns\IncomeValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreIncomeRequest extends FormRequest
{
    use IncomeValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->incomeRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->incomeMessages();
    }
}
