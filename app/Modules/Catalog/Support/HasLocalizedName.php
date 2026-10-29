<?php

namespace App\Modules\Catalog\Support;

trait HasLocalizedName
{
    public function localizedName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        if ($locale === 'en') {
            return $this->name_en ?: $this->name_ar ?: (string) ($this->name ?? '');
        }

        return $this->name_ar ?: $this->name_en ?: (string) ($this->name ?? '');
    }

    public function localizedTitle(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        if ($locale === 'en') {
            return $this->title_en ?: $this->title_ar ?: '';
        }

        return $this->title_ar ?: $this->title_en ?: '';
    }

    public function localizedBody(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        if ($locale === 'en') {
            return $this->body_en ?: $this->body_ar ?: '';
        }

        return $this->body_ar ?: $this->body_en ?: '';
    }
}
