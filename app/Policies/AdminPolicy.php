<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

abstract class AdminPolicy
{
    protected function allowed(User $user): bool
    {
        return $user->is_admin && $user->status === 'active';
    }

    public function viewAny(User $user): bool
    {
        return $this->allowed($user);
    }

    public function view(User $user, Model $model): bool
    {
        return $this->allowed($user);
    }

    public function create(User $user): bool
    {
        return $this->allowed($user);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->allowed($user);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->allowed($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->allowed($user);
    }

    public function restore(User $user, Model $model): bool
    {
        return $this->allowed($user);
    }

    public function restoreAny(User $user): bool
    {
        return $this->allowed($user);
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return $this->allowed($user);
    }
}
