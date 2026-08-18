<?php

namespace App\Concerns;

trait NormalizesBillAttributes
{
    /**
     * Tidy validated input before it reaches the database, the same way
     * {@see NormalizesExpenseAttributes} does for expenses: trim the name, and
     * treat an all-whitespace note as no note at all.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function normalize(array $attributes): array
    {
        if (array_key_exists('name', $attributes)) {
            $attributes['name'] = trim((string) $attributes['name']);
        }

        $notes = trim((string) ($attributes['notes'] ?? ''));
        $attributes['notes'] = $notes === '' ? null : $notes;

        return $attributes;
    }
}
