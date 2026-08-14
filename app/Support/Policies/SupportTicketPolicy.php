<?php

namespace App\Support\Policies;

use App\Models\User;
use App\Support\Models\SupportTicket;
use Illuminate\Auth\Access\HandlesAuthorization;

class SupportTicketPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the support ticket.
     */
    public function view(User $user, SupportTicket $ticket): bool
    {
        // 1. Authenticated customer ownership check
        if ($ticket->customer_id && (int)$ticket->customer_id === (int)$user->id) {
            return true;
        }

        // 2. Assigned agent check
        if ($ticket->assigned_agent_id && (int)$ticket->assigned_agent_id === (int)$user->id) {
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
     * Determine whether the user can update the support ticket.
     */
    public function update(User $user, SupportTicket $ticket): bool
    {
        return $this->view($user, $ticket);
    }
}
