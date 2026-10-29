<?php

namespace App\Core\Services;

use App\Core\Contracts\RepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

abstract class AbstractService
{
    public function __construct(protected RepositoryContract $repository) {}

    public function list(int $perPage = 15): Collection|LengthAwarePaginator
    {
        return $perPage > 0
            ? $this->repository->paginate($perPage)
            : $this->repository->all();
    }

    public function find(int|string $id): ?Model
    {
        return $this->repository->find($id);
    }

    public function create(array $data): Model
    {
        return $this->repository->create($data);
    }

    public function update(int|string $id, array $data): Model
    {
        return $this->repository->update($id, $data);
    }

    public function delete(int|string $id): bool
    {
        return $this->repository->delete($id);
    }
}
