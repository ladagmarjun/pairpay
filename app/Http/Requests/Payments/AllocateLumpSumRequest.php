<?php

namespace App\Http\Requests\Payments;

use App\Enums\LoanDirection;
use App\Models\Bill;
use App\Models\Loan;
use App\Support\MonthInput;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AllocateLumpSumRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Both lists may be empty on their own — paying only bills or only loans
     * is ordinary — but not both at once, which the `after` hook catches.
     *
     * Each `exists` is scoped to the signed-in user, and loans additionally to
     * money *borrowed*: this screen is money going out, so a loan you made
     * cannot be on the receiving end of it.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['required', 'date'],
            'month' => ['nullable', 'string'],
            'bills' => ['array'],
            'bills.*' => [
                'integer',
                Rule::exists('bills', 'id')->where('user_id', $this->user()->id),
            ],
            'loans' => ['array'],
            'loans.*' => [
                'integer',
                Rule::exists('loans', 'id')
                    ->where('user_id', $this->user()->id)
                    ->where('direction', LoanDirection::Borrowed->value),
            ],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->selectedBills()->isEmpty() && $this->selectedLoans()->isEmpty()) {
                    $validator->errors()->add('bills', 'Pick at least one thing to pay.');

                    return;
                }

                $month = MonthInput::fromRequest($this);

                // What is due *now*, matching what the screen adds up and what
                // `AllocateLumpSum` will actually hand out. Measuring against
                // whole loan balances here would accept money the allocation
                // then has nowhere to put.
                $due = round(
                    $this->selectedBills()->sum(fn (Bill $bill): float => $bill->remainingIn($month))
                    + $this->selectedLoans()->sum(fn (Loan $loan): float => $loan->amountDueNow()),
                    2,
                );

                if ((float) $this->validated('amount') > $due) {
                    $validator->errors()->add(
                        'amount',
                        'That is more than the things you picked have due now.',
                    );
                }
            },
        ];
    }

    /**
     * @return Collection<int, Bill>
     */
    public function selectedBills(): Collection
    {
        $month = MonthInput::fromRequest($this);

        return $this->user()
            ->bills()
            ->whereIn('id', (array) $this->input('bills', []))
            // A bill that has not started owes this month nothing, so an id
            // posted for one is dropped rather than paid.
            ->startedBy($month)
            ->with(['payments' => fn ($query) => $query->forMonth($month)])
            ->get();
    }

    /**
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
        ];
    }
}
