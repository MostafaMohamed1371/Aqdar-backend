<?php

namespace App\Core\Enums;

enum FormFieldType: string
{
    case Text = 'text';
    case Number = 'number';
    case Select = 'select';
    case Boolean = 'boolean';

    public function isChoice(): bool
    {
        return $this === self::Select;
    }
}
