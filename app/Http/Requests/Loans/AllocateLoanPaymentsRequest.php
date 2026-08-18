<?php

namespace App\Http\Requests\Loans;

use App\Enums\LoanDirection;
use App\Models\Loan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AllocateLoanPaymentsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * `Rule::exists` is scoped to the signed-in user's *borrowed* loans, so
     * both an id belonging to somebody else and one for money you lent out
     * fail here rather than reaching the action. Paying a lump sum is money
     * going out; a repayment coming back is recorded on its own loan.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['required', 'date'],
            'loans' => ['required', 'array', 'min:1'],
            'loans.*' => [
                'integer',
                Rule::exists('loans', 'id')
                    ->where('user_id', $this->user()->id)
                    ->where('direction', LoanDirection::Borrowed->value),
            ],
        ];
    }

    /**
     * The lump sum cannot exceed what the chosen loans still owe between them.
     * Checked here rather than as a `max:` rule because the ceiling depends on
     * which loans were ticked.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $owed = round(
                    $this->selectedLoans()->sum(fn (Loan $loan): float => $loan->outstanding()),
                    2,
                );

                if ((float) $this->validated('amount') > $owed) {
                    $validator->errors()->add(
                        'amount',
                        'That is more than the loans you picked still owe.',
                    );
                }
            },
        ];
    }

    /**
     * The loans being paid, with their payment totals already summed so
     * working out each balance costs no further queries.
     *
     * @return Collection<int, Loan>
     */
    public function selectedLoans(): Collection
    {
        return $this->user()
            ->loans()
            ->whereIn('id', (array) $this->input('loans', []))
            ->withSum('payments', 'amount')
            ->get();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.gt' => 'Amount must be greater than zero.',
            'amount.numeric' => 'Enter a valid amount.',
            'loans.required' => 'Pick at least one loan to pay.',
        ];
    }
}
