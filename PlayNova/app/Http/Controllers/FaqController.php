<?php

namespace App\Http\Controllers;

use App\Modules\Content\Services\ContentCacheService;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function index(Request $request, ContentCacheService $content)
    {
        $faq = $content->faq($request->query('cat'));

        return view('faq', [
            'categories' => $faq['categories'],
            'activeKey' => $faq['active_key'],
            'activeCategory' => $faq['active_category'],
            'supportPhone' => $faq['support_phone'],
        ]);
    }
}
