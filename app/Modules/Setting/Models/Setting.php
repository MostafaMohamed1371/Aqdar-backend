<?php

namespace App\Modules\Setting\Models;

use App\Modules\Setting\Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'value', 'group'])]
class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory;

    protected static function newFactory(): SettingFactory
    {
        return SettingFactory::new();
    }
}
