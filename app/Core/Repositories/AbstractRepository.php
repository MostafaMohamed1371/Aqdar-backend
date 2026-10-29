<?php

namespace App\Core\Repositories;

use App\Core\Contracts\RepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

abstract class AbstractRepository implements RepositoryContract
{
    public function __construct(protected Model $model) {}

    public function all(): Collection
    {
        return $this->model->newQuery()->get();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->newQuery()->paginate($perPage);
    }

    public function find(int|string $id): ?Model
    {
        return $this->model->newQuery()->find($id);
    }

    public function create(array $data): Model
    {
        return $this->model->newQuery()->create($data);
    }

    public function update(int|string $id, array $data): Model
    {
        $record = $this->find($id);

        if ($record === null) {
            abort(404);
        }

        $record->update($data);

        return $record->refresh();
    }

    public function delete(int|string $id): bool
    {
        $record = $this->find($id);

        if ($record === null) {
            return false;
        }

        return (bool) $record->delete();
    }
}
