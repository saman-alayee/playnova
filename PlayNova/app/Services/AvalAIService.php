<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AvalAIService
{
    public function isConfigured(): bool
    {
        return Setting::isAvalAiConfigured();
    }

    /**
     * @param  array<int, array<string, mixed>>  $contentParts  OpenAI-style multimodal content
     */
    public function chatWithVision(array $contentParts, ?string $systemPrompt = null, ?string $model = null): string
    {
        if (! Setting::isAvalAiActive()) {
            throw new RuntimeException('سرویس هوش مصنوعی در پنل مدیریت غیرفعال است.');
        }

        $apiKey = Setting::getAvalAiApiKey();
        if (! filled($apiKey)) {
            throw new RuntimeException('کلید API سرویس AvalAI تنظیم نشده است.');
        }

        $messages = [];

        if ($systemPrompt) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }

        $messages[] = ['role' => 'user', 'content' => $contentParts];

        $baseUrl = Setting::getAvalAiBaseUrl();

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(Setting::getAvalAiTimeout())
                ->post("{$baseUrl}/chat/completions", [
                    'model' => $model ?? Setting::getAvalAiVisionModel(),
                    'messages' => $messages,
                    'temperature' => 0.1,
                    'max_tokens' => 4096,
                ])
                ->throw();
        } catch (RequestException $e) {
            $body = $e->response?->json();
            $message = is_array($body) ? ($body['error']['message'] ?? $body['message'] ?? null) : null;

            throw new RuntimeException($message ?: 'خطا در ارتباط با AvalAI.', 0, $e);
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('پاسخی از هوش مصنوعی دریافت نشد.');
        }

        return trim($content);
    }
}
