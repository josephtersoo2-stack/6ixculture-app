<?php

namespace App\Support\Services\Multilingual;

class LanguageDetectionService
{
    protected const SUPPORTED_LANGUAGES = ['en', 'yo', 'ig', 'ha'];
    protected const LOW_CONFIDENCE_THRESHOLD = 0.65;

    // Distinctive vocabulary and orthography markers for Nigerian languages
    protected array $markers = [
        'yo' => [
            'keywords' => ['bawo', 'e nle', 'kilo', 'se', 'jowo', 'mo fe', 'aṣọ', 'eko', 'odabo', 'kosi', 'dupe', 'lori', 'fun', 'emi', 'awa', 'iwo', 'won', 'pelu', 'nibo', 'nigba', 'owo', 'ise', 'ore', 'oloun', 'ranti', 'dada'],
            'diacritics' => ['ẹ', 'ọ', 'ṣ', 'á', 'à', 'é', 'è', 'í', 'ì', 'ó', 'ò', 'ú', 'ù', 'ń', 'ǹ'],
        ],
        'ig' => [
            'keywords' => ['kedu', 'ndewo', 'biko', 'achorom', 'ego', 'gini', 'imeela', 'nwanne', 'onye', 'aha', 'ndi', 'anyi', 'gi', 'ya', 'ha', 'na-eme', 'ogologo', 'nri', 'mara', 'mma', 'daalu', 'olee', 'ebe'],
            'diacritics' => ['ị', 'ọ', 'ụ', 'ṅ', 'á', 'à', 'é', 'è', 'í', 'ì', 'ó', 'ò', 'ú', 'ù'],
        ],
        'ha' => [
            'keywords' => ['sannu', 'ina kwana', 'yaya', 'don Allah', 'nawa ne', 'kudi', 'kayan', 'nagode', 'lafiya', 'gida', 'zo', 'je', 'ina', 'wane', 'wace', 'kuma', 'sosai', 'yanzu', 'barka', 'sai anjima', 'shiga'],
            'diacritics' => ['ƙ', 'ɗ', 'ɓ', 'ƴ'],
        ],
    ];

    /**
     * Detect language and confidence from text turn.
     *
     * @return array{
     *     detected_language: string,
     *     confidence: float,
     *     is_code_switching: bool,
     *     is_low_confidence: bool,
     *     detected_languages: array<string, float>
     * }
     */
    public function detect(string $text, ?string $requestedLanguage = null): array
    {
        $cleaned = mb_strtolower(trim($text), 'UTF-8');
        if (empty($cleaned)) {
            return [
                'detected_language' => $requestedLanguage ?: 'en',
                'confidence' => 1.0,
                'is_code_switching' => false,
                'is_low_confidence' => false,
                'detected_languages' => [$requestedLanguage ?: 'en' => 1.0],
            ];
        }

        $scores = ['en' => 0.1, 'yo' => 0.0, 'ig' => 0.0, 'ha' => 0.0];
        $tokens = preg_split('/[\s\p{P}]+/u', $cleaned, -1, PREG_SPLIT_NO_EMPTY);
        $totalTokens = max(1, count($tokens));

        // 1. Keyword matching
        foreach ($tokens as $token) {
            foreach ($this->markers as $lang => $data) {
                if (in_array($token, $data['keywords'], true)) {
                    $scores[$lang] += 2.0;
                }
            }
        }

        // 2. Diacritic / Orthography matching
        foreach ($this->markers as $lang => $data) {
            foreach ($data['diacritics'] as $char) {
                if (mb_strpos($cleaned, $char, 0, 'UTF-8') !== false) {
                    $scores[$lang] += 3.0;
                }
            }
        }

        // 3. English heuristic
        $englishTokens = ['the', 'is', 'are', 'i', 'my', 'order', 'where', 'track', 'product', 'help', 'return', 'refund', 'can', 'you', 'please', 'size', 'delivery', 'address', 'payment'];
        foreach ($tokens as $token) {
            if (in_array($token, $englishTokens, true)) {
                $scores['en'] += 1.0;
            }
        }

        // Normalize scores
        $totalScore = array_sum($scores);
        $normalized = [];
        foreach ($scores as $lang => $s) {
            $normalized[$lang] = $totalScore > 0 ? round($s / $totalScore, 3) : 0.25;
        }
        arsort($normalized);

        $detectedLang = array_key_first($normalized);
        $confidence = $normalized[$detectedLang];

        // Code-switching detection (e.g. English tokens combined with Yoruba/Igbo/Hausa tokens)
        $hasEn = $scores['en'] > 0.5;
        $hasAfricanLang = ($scores['yo'] > 1.0 || $scores['ig'] > 1.0 || $scores['ha'] > 1.0);
        $isCodeSwitching = $hasEn && $hasAfricanLang;

        $isLowConfidence = $confidence < self::LOW_CONFIDENCE_THRESHOLD;

        // If low confidence and requestedLanguage matches a known language, respect requested
        if ($isLowConfidence && $requestedLanguage && in_array($requestedLanguage, self::SUPPORTED_LANGUAGES, true)) {
            $detectedLang = $requestedLanguage;
        }

        return [
            'detected_language' => $detectedLang,
            'confidence' => $confidence,
            'is_code_switching' => $isCodeSwitching,
            'is_low_confidence' => $isLowConfidence,
            'detected_languages' => $normalized,
        ];
    }

    /**
     * Resolve effective language considering user preference, requested language, and detected result.
     */
    public function resolveEffectiveLanguage(
        ?string $conversationLang,
        ?string $userPreferredLang,
        ?string $requestedLang,
        array $detectionResult
    ): string {
        // 1. Explicit requested language if provided and supported
        if ($requestedLang && in_array($requestedLang, self::SUPPORTED_LANGUAGES, true)) {
            return $requestedLang;
        }

        // 2. High confidence detection
        if (!$detectionResult['is_low_confidence'] && in_array($detectionResult['detected_language'], self::SUPPORTED_LANGUAGES, true)) {
            return $detectionResult['detected_language'];
        }

        // 3. User preferred language
        if ($userPreferredLang && in_array($userPreferredLang, self::SUPPORTED_LANGUAGES, true)) {
            return $userPreferredLang;
        }

        // 4. Conversation language
        if ($conversationLang && in_array($conversationLang, self::SUPPORTED_LANGUAGES, true)) {
            return $conversationLang;
        }

        // 5. English default fallback
        return 'en';
    }
}
