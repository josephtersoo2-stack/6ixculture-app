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
 * Authenticated Customer & Agent Conversation Channel.
 * Authorized for:
 * 1. The authenticated customer who owns the conversation.
 * 2. An assigned or department-scoped support agent.
 * 3. Elevated Admin / Manager users.
 */
Broadcast::channel('support.conversation.{publicId}', function ($user, string $publicId) {
    if (!$user) {
        return false;
    }

    $conv = SupportConversation::where('public_id', $publicId)->first();
    if (!$conv) {
        return false;
    }

    // 1. Authenticated customer ownership
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
 * Guest Customer Conversation Channel.
 * Authorized ONLY when a valid guest token is provided that matches conversation.guest_session_id.
 * Wrong token, missing token, or token for another conversation is strictly rejected.
 */
Broadcast::channel('support.guest.conversation.{publicId}', function ($user, string $publicId) {
    $guestToken = request()->header('X-Guest-Token') ?: request()->input('guest_token') ?: request()->input('token');
    if (empty($guestToken) || !is_string($guestToken)) {
        return false;
    }

    $conv = SupportConversation::where('public_id', $publicId)->first();
    if (!$conv || empty($conv->guest_session_id)) {
        return false;
    }

    return hash_equals((string)$conv->guest_session_id, (string)$guestToken);
}, ['guards' => ['sanctum', 'web', null]]);

/**
 * Agent-Specific Conversation Channel (for internal staff notes & notifications).
 * Customer accounts are strictly denied.
 */
Broadcast::channel('support.agent.conversation.{publicId}', function ($user, string $publicId) {
    if (!$user) {
        return false;
    }

    $conv = SupportConversation::where('public_id', $publicId)->first();
    if (!$conv) {
        return false;
    }

    // Elevated Admin / Manager
    try {
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Admin', 'Manager'])) {
            return true;
        }
        if (method_exists($user, 'can') && ($user->can('manage-support') || $user->can('all_support'))) {
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
 * Authorized ONLY for elevated Admin / Manager users.
 * Non-elevated department-scoped agents are strictly denied (must subscribe to department-specific channels).
 */
Broadcast::channel('support.agent.queue', function ($user) {
    if (!$user) {
        return false;
    }

    try {
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Admin', 'Manager'])) {
            return true;
        }
        if (method_exists($user, 'can') && ($user->can('manage-support') || $user->can('all_support'))) {
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
    if (!$user) {
        return false;
    }

    try {
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Admin', 'Manager'])) {
            return true;
        }
        if (method_exists($user, 'can') && ($user->can('manage-support') || $user->can('all_support'))) {
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
 * Authorized for all active support staff and admins.
 */
Broadcast::channel('support.agent.presence', function ($user) {
    if (!$user) {
        return false;
    }

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
