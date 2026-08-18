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

    /**
     * Get provider capability metadata (supported languages, audio formats, max duration, code-switching).
     *
     * @return array<string, mixed>
     */
    public function capabilities(): array;

    /**
     * Check if the provider is properly configured with credentials.
     */
    public function isConfigured(): bool;
}
