<?php

namespace App\Support\Contracts;

use Illuminate\Http\UploadedFile;

interface SpeechToTextInterface
{
    /**
     * Transcribe uploaded audio or raw audio data into text.
     *
     * @param UploadedFile|string $audio Audio file instance or raw binary/base64 string
     * @param string $language ISO 639-1 language code (en, yo, ig, ha)
     * @return array [
     *     'transcript' => string,
     *     'detected_language' => string,
     *     'duration_seconds' => float,
     *     'confidence' => float,
     *     'error' => ?string
     * ]
     */
    public function transcribe(UploadedFile|string $audio, string $language = 'en'): array;
}
