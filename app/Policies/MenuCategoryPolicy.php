<?php

namespace App\Policies;

use App\Models\MenuCategory;
use App\Models\User;

class MenuCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, MenuCategory $category): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, MenuCategory $category): bool
    {
        return $user->isAdmin();
    }

    public function reorder(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Categories that still contain products cannot be deleted.
     */
    public function delete(User $user, MenuCategory $category): bool
    {
        return $user->isAdmin() && ! $category->items()->exists();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
