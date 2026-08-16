<?php

namespace App\Support\Contracts;

interface TextToSpeechInterface
{
    /**
     * Synthesize clean assistant text into spoken audio.
     *
     * @param string $text Clean, customer-visible assistant response text
     * @param string $language ISO 639-1 language code (en, yo, ig, ha)
     * @param array $options Optional voice parameters (voice_id, speed, format)
     * @return array [
     *     'audio_content' => ?string, // Base64 or binary audio stream
     *     'audio_url' => ?string,     // Temporary URL if stored on disk/S3
     *     'format' => string,         // mp3, wav, ogg
     *     'duration_seconds' => float,
     *     'language' => string,
     *     'error' => ?string
     * ]
     */
    public function synthesize(string $text, string $language = 'en', array $options = []): array;
}
