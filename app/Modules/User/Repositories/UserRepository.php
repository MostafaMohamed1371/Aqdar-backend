<?php

namespace App\Modules\User\Repositories;

use App\Core\Repositories\AbstractRepository;
use App\Modules\User\Models\User;

class UserRepository extends AbstractRepository
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->model->newQuery()->where('email', $email)->first();
    }

    public function findByPhone(string $phone): ?User
    {
        return $this->model->newQuery()->where('phone', $phone)->first();
    }
}
