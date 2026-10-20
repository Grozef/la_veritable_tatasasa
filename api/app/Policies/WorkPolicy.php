<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Work;

/**
 * Compte unique : aujourd'hui cette policy dit la meme chose que le middleware
 * `auth:sanctum` qui protege deja les routes atelier. Elle existe comme point
 * d'accroche — le jour ou un second compte apparait (assistant, galerie), la regle
 * se pose ici et nulle part ailleurs. Sans elle, la condition finirait dispersee
 * dans les controllers.
 */
class WorkPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Work $work): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Work $work): bool
    {
        return true;
    }

    public function delete(User $user, Work $work): bool
    {
        return true;
    }
}
