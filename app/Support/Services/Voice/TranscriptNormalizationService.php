<?php

namespace App\Support\Services\Voice;

class TranscriptNormalizationService
{
    protected const MAX_TRANSCRIPT_LENGTH = 2000;

    /**
     * Normalize a voice transcript for downstream processing while preserving the raw input.
     *
     * @return array{
     *     normalized_transcript: string,
     *     raw_transcript: string,
     *     disfluencies_removed_count: int,
     *     was_truncated: bool
     * }
     */
    public function normalize(string $rawTranscript): array
    {
        $raw = trim($rawTranscript);
        if (empty($raw)) {
            return [
                'normalized_transcript' => '',
                'raw_transcript' => $raw,
                'disfluencies_removed_count' => 0,
                'was_truncated' => false,
            ];
        }

        // 1. Remove speech disfluency filler words (case-insensitive word boundary)
        $fillers = ['\bum\b', '\buh\b', '\ber\b', '\bah\b', '\bhmm\b', '\buh-huh\b', '\buhm\b'];
        $disfluencyCount = 0;
        $cleaned = $raw;

        foreach ($fillers as $filler) {
            $cleaned = preg_replace_callback('/' . $filler . '[\s,]*/iu', function () use (&$disfluencyCount) {
                $disfluencyCount++;
                return '';
            }, $cleaned);
        }

        // 2. Normalize whitespace
        $cleaned = preg_replace('/\s+/u', ' ', $cleaned);

        // 3. Normalize repeated punctuation (e.g. "???" -> "?", "..." -> ".")
        $cleaned = preg_replace('/([?!.,;:])\1+/u', '$1', $cleaned);

        // 4. Trim and enforce safe length boundary
        $cleaned = trim($cleaned);
        $wasTruncated = false;

        if (mb_strlen($cleaned, 'UTF-8') > self::MAX_TRANSCRIPT_LENGTH) {
            $cleaned = mb_substr($cleaned, 0, self::MAX_TRANSCRIPT_LENGTH, 'UTF-8');
            $wasTruncated = true;
        }

        return [
            'normalized_transcript' => $cleaned ?: $raw,
            'raw_transcript' => $raw,
            'disfluencies_removed_count' => $disfluencyCount,
            'was_truncated' => $wasTruncated,
        ];
    }
}
