<?php

namespace App\Http\Requests\Bills;

use App\Concerns\BillValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBillRequest extends FormRequest
{
    use BillValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->billRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->billMessages();
    }
}
