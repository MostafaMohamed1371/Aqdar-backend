<?php

namespace App\Modules\Setting\Repositories;

use App\Core\Repositories\AbstractRepository;
use App\Modules\Setting\Models\Setting;
use Illuminate\Database\Eloquent\Collection;

class SettingRepository extends AbstractRepository
{
    public function __construct(Setting $model)
    {
        parent::__construct($model);
    }

    public function findByKey(string $key): ?Setting
    {
        return $this->model->newQuery()->where('key', $key)->first();
    }

    /** @return Collection<int, Setting> */
    public function allOrdered(): Collection
    {
        return $this->model->newQuery()->orderBy('group')->orderBy('key')->get();
    }
}
