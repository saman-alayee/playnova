<?php

namespace App\Http\Controllers;

use App\Modules\Content\Services\ContentCacheService;

class PageController extends Controller
{
    public function privacy(ContentCacheService $content)
    {
        $contentText = $content->privacyContent();

        return view('privacy', ['content' => $contentText]);
    }

    public function about(ContentCacheService $content)
    {
        $contentText = $content->aboutContent();

        return view('about', ['content' => $contentText]);
    }

    public function contact(ContentCacheService $content)
    {
        $info = $content->contactInfo();

        return view('contact', [
            'email' => $info['email'],
            'phone' => $info['phone'],
        ]);
    }
}
