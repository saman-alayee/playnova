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

                    'max_tokens' => 8196,

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

    /**
     * @return list<string>
     */
    public function listModels(): array
    {
        if (! Setting::isAvalAiActive()) {
            throw new RuntimeException('سرویس هوش مصنوعی در پنل مدیریت غیرفعال است.');
        }

        $apiKey = Setting::getAvalAiApiKey();
        if (! filled($apiKey)) {
            throw new RuntimeException('کلید API سرویس AvalAI تنظیم نشده است.');
        }

        $baseUrl = Setting::getAvalAiBaseUrl();

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(min(30, Setting::getAvalAiTimeout()))
                ->get("{$baseUrl}/models")
                ->throw();
        } catch (RequestException $e) {
            $body = $e->response?->json();
            $message = is_array($body) ? ($body['error']['message'] ?? $body['message'] ?? null) : null;

            throw new RuntimeException($message ?: 'خطا در دریافت لیست مدل‌ها از AvalAI.', 0, $e);
        }

        $items = $response->json('data');
        if (! is_array($items)) {
            return [];
        }

        $models = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $id = trim((string) ($item['id'] ?? ''));
            if ($id === '' || ! AvalAiModelCatalog::isUsableForMediaAnalysis($item)) {
                continue;
            }

            $models[] = $id;
        }

        $models = array_values(array_unique($models));
        sort($models, SORT_NATURAL | SORT_FLAG_CASE);

        return $models;
    }

    /**
     * User API lives at /user/v1, not the chat completions /v1 base.
     *
     * @see https://docs.avalai.ir/fa/api-reference/user
     */
    public function userApiBaseUrl(): string
    {
        $chatBase = rtrim(Setting::getAvalAiBaseUrl(), '/');
        if (str_ends_with($chatBase, '/v1')) {
            return preg_replace('#/v1$#', '/user/v1', $chatBase) ?: 'https://api.avalai.ir/user/v1';
        }

        return 'https://api.avalai.ir/user/v1';
    }

    /**
     * @return array{
     *     limit: float,
     *     remaining_irt: float,
     *     remaining_unit: float,
     *     total_unit: float,
     *     exchange_rate: int,
     *     account_tier: int,
     *     packages: list<array<string, mixed>>,
     *     grants: list<array<string, mixed>>
     * }
     */
    public function getCredit(): array
    {
        $apiKey = Setting::getAvalAiApiKey();
        if (! filled($apiKey)) {
            throw new RuntimeException('کلید API سرویس AvalAI تنظیم نشده است.');
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(min(20, Setting::getAvalAiTimeout()))
                ->get($this->userApiBaseUrl().'/credit')
                ->throw();
        } catch (RequestException $e) {
            $body = $e->response?->json();
            $message = is_array($body) ? ($body['error']['message'] ?? $body['message'] ?? null) : null;

            throw new RuntimeException($message ?: 'خطا در دریافت موجودی اعتبار AvalAI.', 0, $e);
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('پاسخ نامعتبر از AvalAI برای موجودی اعتبار.');
        }

        return $this->normalizeCredit($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     limit: float,
     *     remaining_irt: float,
     *     remaining_unit: float,
     *     total_unit: float,
     *     exchange_rate: int,
     *     account_tier: int,
     *     packages: list<array<string, mixed>>,
     *     grants: list<array<string, mixed>>
     * }
     */
    private function normalizeCredit(array $payload): array
    {
        $sources = is_array($payload['credit_sources'] ?? null) ? $payload['credit_sources'] : [];

        return [
            'limit' => (float) ($payload['limit'] ?? 0),
            'remaining_irt' => (float) ($payload['remaining_irt'] ?? 0),
            'remaining_unit' => (float) ($payload['remaining_unit'] ?? 0),
            'total_unit' => (float) ($payload['total_unit'] ?? 0),
            'exchange_rate' => (int) ($payload['exchange_rate'] ?? 0),
            'account_tier' => (int) ($payload['account_tier'] ?? 0),
            'packages' => $this->normalizeCreditSources($sources['packages'] ?? [], true),
            'grants' => $this->normalizeCreditSources($sources['grants'] ?? [], false),
        ];
    }

    /**
     * @param  mixed  $items
     * @return list<array<string, mixed>>
     */
    private function normalizeCreditSources(mixed $items, bool $isPackage): array
    {
        if (! is_array($items)) {
            return [];
        }

        $normalized = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $row = [
                'id' => (string) ($item['id'] ?? ''),
                'name' => (string) ($item['name'] ?? ''),
                'description' => (string) ($item['description'] ?? ''),
                'amount_irt' => (float) ($item['amount_irt'] ?? 0),
                'remaining_irt' => (float) ($item['remaining_irt'] ?? 0),
                'end_date' => isset($item['end_date']) ? (string) $item['end_date'] : null,
            ];

            if ($isPackage) {
                $row['template_id'] = (string) ($item['template_id'] ?? '');
            }

            $normalized[] = $row;
        }

        return $normalized;
    }
}

