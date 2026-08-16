<?php

use App\Models\User;
use App\Support\Models\SupportAgentProfile;
use App\Support\Models\SupportConversation;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Channel authorization callbacks for the 6ixCulture AI Support platform.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Shared Customer & Agent Conversation Channel.
 * Authorized for:
 * 1. The authenticated customer who owns the conversation.
 * 2. An assigned or department-scoped support agent.
 * 3. Elevated Admin / Manager users.
 */
Broadcast::channel('support.conversation.{publicId}', function ($user, string $publicId) {
    $conv = SupportConversation::where('public_id', $publicId)->first();
    if (!$conv) {
        return false;
    }

    // 1. Customer ownership
    if ($conv->customer_id && (int)$conv->customer_id === (int)$user->id) {
        return true;
    }

    // 2. Elevated Admin / Manager
    try {
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Admin', 'Manager'])) {
            return true;
        }
        if (method_exists($user, 'can') && ($user->can('manage-support') || $user->can('all_support'))) {
            return true;
        }
    } catch (\Throwable $e) {}

    // 3. Assigned Agent
    if ($conv->assigned_agent_id && (int)$conv->assigned_agent_id === (int)$user->id) {
        return true;
    }

    // 4. Department-scoped Agent Profile check
    $profile = SupportAgentProfile::where('user_id', $user->id)->first();
    if ($profile && $conv->department_id) {
        return $profile->departments()->where('support_departments.id', $conv->department_id)->exists();
    }

    return false;
});

/**
 * Agent-Specific Conversation Channel (for internal notes & staff notifications).
 * Customer accounts are strictly denied.
 */
Broadcast::channel('support.agent.conversation.{publicId}', function ($user, string $publicId) {
    $conv = SupportConversation::where('public_id', $publicId)->first();
    if (!$conv) {
        return false;
    }

    // Elevated Admin / Manager
    try {
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Admin', 'Manager'])) {
            return true;
        }
    } catch (\Throwable $e) {}

    // Assigned Agent
    if ($conv->assigned_agent_id && (int)$conv->assigned_agent_id === (int)$user->id) {
        return true;
    }

    // Department Agent Profile check
    $profile = SupportAgentProfile::where('user_id', $user->id)->first();
    if ($profile && $conv->department_id) {
        return $profile->departments()->where('support_departments.id', $conv->department_id)->exists();
    }

    return false;
});

/**
 * Global Support Queue Channel.
 * Authorized for all active support agents and staff.
 */
Broadcast::channel('support.agent.queue', function ($user) {
    $hasProfile = SupportAgentProfile::where('user_id', $user->id)->exists();
    if ($hasProfile) {
        return true;
    }

    try {
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Admin', 'Manager', 'Stuff', 'Support Agent'])) {
            return true;
        }
    } catch (\Throwable $e) {}

    return false;
});

/**
 * Department-Specific Queue Channel.
 * Authorized for agents belonging to this department or elevated admins.
 */
Broadcast::channel('support.agent.department.{departmentId}', function ($user, $departmentId) {
    try {
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Admin', 'Manager'])) {
            return true;
        }
    } catch (\Throwable $e) {}

    $profile = SupportAgentProfile::where('user_id', $user->id)->first();
    if ($profile) {
        return $profile->departments()->where('support_departments.id', (int)$departmentId)->exists();
    }

    return false;
});

/**
 * Agent Presence Channel.
 * Authorized for all active support staff.
 */
Broadcast::channel('support.agent.presence', function ($user) {
    $hasProfile = SupportAgentProfile::where('user_id', $user->id)->exists();
    if ($hasProfile) {
        return true;
    }

    try {
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Admin', 'Manager', 'Stuff', 'Support Agent'])) {
            return true;
        }
    } catch (\Throwable $e) {}

    return false;
});
