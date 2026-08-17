<?php

namespace App\Support\Services\Multilingual;

use App\Models\AiAgent;
use App\Support\Services\AiProviderFactory;
use Dipokhalder\Settings\Facades\Settings;

class TranslationService
{
    /**
     * Translate text to target language.
     *
     * @return array{
     *     original_text: string,
     *     translated_text: string,
     *     source_language: string,
     *     target_language: string,
     *     is_machine_translated: bool
     * }
     */
    public function translate(string $text, string $targetLanguage, ?string $sourceLanguage = null): array
    {
        $text = trim($text);
        $source = $sourceLanguage ?: 'auto';
        $target = in_array($targetLanguage, ['en', 'yo', 'ig', 'ha'], true) ? $targetLanguage : 'en';

        if (empty($text) || ($sourceLanguage === $target)) {
            return [
                'original_text' => $text,
                'translated_text' => $text,
                'source_language' => $source,
                'target_language' => $target,
                'is_machine_translated' => false,
            ];
        }

        // Domain vocabulary & standard phrase fallbacks
        $dictionary = [
            'en' => [
                'where is my order' => 'Where is my order',
                'thank you' => 'Thank you for contacting 6ixCulture',
                'we are processing your return' => 'We are processing your return request.',
            ],
            'yo' => [
                'where is my order' => 'Níbo ni àṣẹ mi wà?',
                'thank you' => 'Ẹ ṣe púpọ̀ fún kíkàn sí 6ixCulture',
                'we are processing your return' => 'A ń ṣiṣẹ́ lórí ìbéèrè ìdápadà yín.',
            ],
            'ig' => [
                'where is my order' => 'Ebee ka ngwaahịa m nọ?',
                'thank you' => 'Imeela maka ịkpọtụrụ 6ixCulture',
                'we are processing your return' => 'Anyị na-ahazi arịrịọ nloghachi gị.',
            ],
            'ha' => [
                'where is my order' => 'Ina oda ta take?',
                'thank you' => 'Mun gode da tuntuɓar 6ixCulture',
                'we are processing your return' => 'Muna aiwatar da buƙatar dawo da kayanku.',
            ],
        ];

        $lower = strtolower($text);
        if (isset($dictionary[$target][$lower])) {
            return [
                'original_text' => $text,
                'translated_text' => $dictionary[$target][$lower],
                'source_language' => $source,
                'target_language' => $target,
                'is_machine_translated' => true,
            ];
        }

        // Attempt LLM translation using AI adapter if configured
        try {
            $defaultAgentId = Settings::group('site')->get('site_default_ai_agent');
            if ($defaultAgentId > 0) {
                $agent = AiAgent::find($defaultAgentId);
                if ($agent) {
                    $adapter = AiProviderFactory::make($agent);
                    $langNames = ['en' => 'English', 'yo' => 'Yorùbá', 'ig' => 'Igbo', 'ha' => 'Hausa'];
                    $targetName = $langNames[$target] ?? 'English';
                    $prompt = "You are a professional customer support translator. Translate the following text into {$targetName}. Only return the direct translation without any explanation or extra commentary:\n\n{$text}";
                    
                    $res = $adapter->generateResponse([
                        ['role' => 'user', 'content' => $prompt]
                    ], [
                        'temperature' => 0.2,
                        'max_tokens' => 500,
                    ]);

                    if (!empty($res['content'])) {
                        return [
                            'original_text' => $text,
                            'translated_text' => trim($res['content']),
                            'source_language' => $source,
                            'target_language' => $target,
                            'is_machine_translated' => true,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {}

        // Fallback: return original if translation service is unavailable
        return [
            'original_text' => $text,
            'translated_text' => $text,
            'source_language' => $source,
            'target_language' => $target,
            'is_machine_translated' => false,
        ];
    }
}
