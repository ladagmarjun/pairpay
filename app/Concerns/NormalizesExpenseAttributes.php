<?php

namespace App\Concerns;

trait NormalizesExpenseAttributes
{
    /**
     * Tidy validated input before it reaches the database: trim the title, and
     * treat an all-whitespace note as no note at all (the mobile app cleared
     * the column in the same situation).
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function normalize(array $attributes): array
    {
        if (array_key_exists('title', $attributes)) {
            $attributes['title'] = trim((string) $attributes['title']);
        }

        $notes = trim((string) ($attributes['notes'] ?? ''));
        $attributes['notes'] = $notes === '' ? null : $notes;

        return $attributes;
    }
}
