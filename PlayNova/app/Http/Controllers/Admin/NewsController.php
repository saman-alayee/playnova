<?php

namespace App\Http\Controllers\Admin;

use App\Models\News;
use Illuminate\Http\Request;

class NewsController extends BaseAdminController
{
    public function news()
    {
        $newsItems = News::orderByDesc('created_at')->paginate(25);
        return view('admin.news', compact('newsItems'));
    }

    public function storeNews(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'image' => 'nullable|image|max:2048',
        ]);
        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('news', 'public');
        }
        $validated['is_published'] = true;
        News::create($validated);
        return back()->with('success', 'خبر جدید منتشر شد.');
    }

    public function deleteNews(News $news)
    {
        $news->delete();
        return back()->with('success', 'خبر حذف شد.');
    }
}
