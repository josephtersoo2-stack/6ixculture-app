<?php

namespace App\Support\Services\Customer;

use App\Models\User;
use App\Support\Models\SupportCustomerPreference;

class CustomerPreferenceService
{
    /**
     * Get defaults for support preferences.
     *
     * @return array{preferred_support_language: string, preferred_support_voice: string, preferred_support_speaking_rate: float}
     */
    public function getDefaults(): array
    {
        return [
            'preferred_support_language' => 'en',
            'preferred_support_voice' => 'nova',
            'preferred_support_speaking_rate' => 1.00,
        ];
    }

    /**
     * Get persistent support preferences for a customer, falling back to safe defaults.
     *
     * @param User|int $user
     * @return array{preferred_support_language: string, preferred_support_voice: string, preferred_support_speaking_rate: float}
     */
    public function getPreferences(User|int $user): array
    {
        $userId = $user instanceof User ? $user->id : $user;
        $defaults = $this->getDefaults();

        if (!$userId) {
            return $defaults;
        }

        $record = SupportCustomerPreference::where('user_id', $userId)->first();

        if (!$record) {
            return $defaults;
        }

        return [
            'preferred_support_language' => $record->preferred_language ?: $defaults['preferred_support_language'],
            'preferred_support_voice' => $record->preferred_voice ?: $defaults['preferred_support_voice'],
            'preferred_support_speaking_rate' => (float)($record->preferred_speaking_rate ?: $defaults['preferred_support_speaking_rate']),
        ];
    }

    /**
     * Persist or update customer support preferences.
     *
     * @param User|int $user
     * @param array $data
     * @return SupportCustomerPreference|null
     */
    public function updatePreferences(User|int $user, array $data): ?SupportCustomerPreference
    {
        $userId = $user instanceof User ? $user->id : $user;
        if (!$userId) {
            return null;
        }

        $updateData = [];

        if (!empty($data['preferred_support_language']) || !empty($data['language'])) {
            $lang = $data['preferred_support_language'] ?? $data['language'];
            if (in_array($lang, ['en', 'yo', 'ig', 'ha'])) {
                $updateData['preferred_language'] = $lang;
            }
        }

        if (!empty($data['preferred_support_voice']) || !empty($data['voice'])) {
            $voice = $data['preferred_support_voice'] ?? $data['voice'];
            $updateData['preferred_voice'] = (string)$voice;
        }

        if (isset($data['preferred_support_speaking_rate']) || isset($data['speaking_rate'])) {
            $rate = $data['preferred_support_speaking_rate'] ?? $data['speaking_rate'];
            $updateData['preferred_speaking_rate'] = max(0.5, min(2.0, (float)$rate));
        }

        if (!empty($data['metadata'])) {
            $updateData['metadata'] = $data['metadata'];
        }

        if (empty($updateData)) {
            return SupportCustomerPreference::where('user_id', $userId)->first();
        }

        return SupportCustomerPreference::updateOrCreate(
            ['user_id' => $userId],
            $updateData
        );
    }
}
