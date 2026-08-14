<?php

namespace App\Support\Policies;

use App\Models\User;
use App\Support\Models\SupportConversation;
use Illuminate\Auth\Access\HandlesAuthorization;

class SupportConversationPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the support conversation.
     */
    public function view(User $user, SupportConversation $conversation): bool
    {
        // 1. Authenticated customer ownership check
        if ($conversation->customer_id && (int)$conversation->customer_id === (int)$user->id) {
            return true;
        }

        // 2. Assigned agent check
        if ($conversation->assigned_agent_id && (int)$conversation->assigned_agent_id === (int)$user->id) {
            return true;
        }

        // 3. Admin role ID check
        if (isset($user->role_id) && (int)$user->role_id === 1) {
            return true;
        }

        // 4. Spatie role / permission check (safely handled)
        try {
            if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
                return true;
            }
            if (method_exists($user, 'can') && $user->can('customer-support')) {
                return true;
            }
        } catch (\Throwable $e) {
            // Permission not defined in testing DB
        }

        return false;
    }

    /**
     * Determine whether the user can send a message or update the conversation.
     */
    public function update(User $user, SupportConversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    /**
     * Determine whether the user can assign or reassign the conversation.
     */
    public function assign(User $user, SupportConversation $conversation): bool
    {
        if (isset($user->role_id) && (int)$user->role_id === 1) {
            return true;
        }

        try {
            if (method_exists($user, 'can') && $user->can('customer-support')) {
                return true;
            }
        } catch (\Throwable $e) {
            // Permission not defined in testing DB
        }

        return false;
    }
}
