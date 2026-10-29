<?php

namespace App\Core\Validation;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ValidationRules
{
    /** @return array<int, mixed> */
    public static function name(bool $required = true): array
    {
        $rules = [
            'string',
            'min:2',
            'max:255',
            'regex:/^[\pL\s\-\'\.]+$/u',
        ];

        return $required
            ? array_merge(['required'], $rules)
            : array_merge(['sometimes'], $rules);
    }

    /** @return array<int, mixed> */
    public static function email(bool $required = true, mixed $unique = null): array
    {
        $rules = [
            'string',
            'email:rfc,filter',
            'max:255',
        ];

        if ($unique !== null) {
            $rules[] = $unique;
        }

        return $required
            ? array_merge(['required'], $rules)
            : array_merge(['sometimes'], $rules);
    }

    /** @return array<int, mixed> */
    public static function loginEmail(): array
    {
        return ['required', 'string', 'email:rfc', 'max:255'];
    }

    /** @return array<int, mixed> */
    public static function loginPassword(): array
    {
        return ['required', 'string', 'max:128'];
    }

    /** @return array<int, mixed> */
    public static function password(bool $required = true, bool $confirmed = false): array
    {
        $rules = [
            'string',
            Password::min(8)
                ->letters()
                ->numbers(),
            'max:128',
        ];

        if ($confirmed) {
            $rules[] = 'confirmed';
        }

        return $required
            ? array_merge(['required'], $rules)
            : array_merge(['sometimes'], $rules);
    }

    /** @return array<int, mixed> */
    public static function text(bool $required = true, int $min = 1, int $max = 255): array
    {
        $rules = ['string', "min:{$min}", "max:{$max}"];

        return $required
            ? array_merge(['required'], $rules)
            : array_merge(['sometimes'], $rules);
    }

    /** @return array<int, mixed> */
    public static function number(bool $required = true, int|float|null $min = null, int|float|null $max = null): array
    {
        $rules = ['numeric'];

        if ($min !== null) {
            $rules[] = "min:{$min}";
        }

        if ($max !== null) {
            $rules[] = "max:{$max}";
        }

        return $required
            ? array_merge(['required'], $rules)
            : array_merge(['sometimes'], $rules);
    }

    /** @return array<int, mixed> */
    public static function integer(bool $required = true, ?int $min = null, ?int $max = null): array
    {
        $rules = ['integer'];

        if ($min !== null) {
            $rules[] = "min:{$min}";
        }

        if ($max !== null) {
            $rules[] = "max:{$max}";
        }

        return $required
            ? array_merge(['required'], $rules)
            : array_merge(['sometimes'], $rules);
    }

    public static function uniqueEmail(?int $ignoreUserId = null): mixed
    {
        $rule = Rule::unique('users', 'email');

        if ($ignoreUserId !== null) {
            $rule->ignore($ignoreUserId);
        }

        return $rule;
    }

    public static function uniquePhone(?int $ignoreUserId = null): mixed
    {
        $rule = Rule::unique('users', 'phone');

        if ($ignoreUserId !== null) {
            $rule->ignore($ignoreUserId);
        }

        return $rule;
    }
}
