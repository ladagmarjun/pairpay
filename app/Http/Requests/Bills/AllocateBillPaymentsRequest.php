<?php

namespace App\Http\Requests\Bills;

use App\Models\Bill;
use App\Support\MonthInput;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AllocateBillPaymentsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * `Rule::exists` is scoped to the signed-in user's bills, so an id
     * belonging to somebody else fails here rather than reaching the action.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'bills' => ['required', 'array', 'min:1'],
            'bills.*' => [
                'integer',
                Rule::exists('bills', 'id')->where('user_id', $this->user()->id),
            ],
            'month' => ['nullable', 'string'],
        ];
    }

    /**
     * The lump sum cannot exceed what the chosen bills still owe between them.
     * Checked here rather than as a `max:` rule because the ceiling depends on
     * which bills were ticked.
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
                    $this->selectedBills()->sum(
                        fn (Bill $bill): float => $bill->remaining()
                    ),
                    2,
                );

                if ((float) $this->validated('amount') > $owed) {
                    $validator->errors()->add(
                        'amount',
                        'That is more than the bills you picked still owe.',
                    );
                }
            },
        ];
    }

    /**
     * The bills being paid, with their payments already loaded so
     * working out what each still owes costs no further queries.
     *
     * @return Collection<int, Bill>
     */
    public function selectedBills(): Collection
    {
        $month = MonthInput::fromRequest($this);

        return $this->user()
            ->bills()
            ->whereIn('id', (array) $this->input('bills', []))
            // Only this month's bills: an id posted for another month's bill
            // is dropped rather than paid.
            ->dueIn($month)
            ->with('payments')
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
            'bills.required' => 'Pick at least one bill to pay.',
        ];
    }
}
