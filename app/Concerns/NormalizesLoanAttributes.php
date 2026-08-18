<?php

namespace App\Concerns;

trait NormalizesLoanAttributes
{
    /**
     * Tidy validated input before it reaches the database, as
     * {@see NormalizesExpenseAttributes} does: trim the name, and treat an
     * all-whitespace note as no note at all.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function normalize(array $attributes): array
    {
        if (array_key_exists('counterparty', $attributes)) {
            $attributes['counterparty'] = trim((string) $attributes['counterparty']);
        }

        $notes = trim((string) ($attributes['notes'] ?? ''));
        $attributes['notes'] = $notes === '' ? null : $notes;

        return $attributes;
    }
}
