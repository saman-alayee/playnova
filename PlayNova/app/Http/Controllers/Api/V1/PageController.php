<?php

namespace App\Http\Controllers\Api\V1;

use App\Modules\Content\Services\ContentCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageController extends BaseApiController
{
    public function privacy(ContentCacheService $content): JsonResponse
    {
        return $this->success(['content' => $content->privacyContent()]);
    }

    public function about(ContentCacheService $content): JsonResponse
    {
        return $this->success(['content' => $content->aboutContent()]);
    }

    public function contact(ContentCacheService $content): JsonResponse
    {
        $info = $content->contactInfo();

        return $this->success([
            'email' => $info['email'],
            'phone' => $info['phone'],
            'address' => $info['address'],
        ]);
    }

    public function faq(Request $request, ContentCacheService $content): JsonResponse
    {
        $faq = $content->faq($request->query('cat'));

        return $this->success([
            'categories' => $faq['categories'],
            'active_key' => $faq['active_key'],
            'active_category' => $faq['active_category'],
            'support_phone' => $faq['support_phone'],
        ]);
    }
}
