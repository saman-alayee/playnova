<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Controllers\Api\V1\Admin\Concerns\AuthorizesAdmin;
use App\Models\Discount;
use App\Models\News;
use App\Models\Rule;
use App\Modules\Content\Services\ContentCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentAdminController extends BaseApiController
{
    use AuthorizesAdmin;

    public function discounts(): JsonResponse
    {
        $this->authorizeAdmin();

        return $this->paginated(Discount::orderByDesc('created_at')->paginate(25));
    }

    public function storeDiscount(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:discounts,code',
            'type' => 'required|in:percentage,fixed,percent',
            'value' => 'required|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:0',
            'expires_at' => 'nullable|date',
        ]);

        if ($validated['type'] === 'percent') {
            $validated['type'] = 'percentage';
        }

        $validated['is_active'] = true;
        $validated['usage_limit'] = $validated['usage_limit'] ?? 0;
        $discount = Discount::create($validated);

        return $this->success($discount, 'کد تخفیف ایجاد شد.', 201);
    }

    public function deleteDiscount(Discount $discount): JsonResponse
    {
        $this->authorizeAdmin();
        $discount->delete();

        return $this->success(null, 'کد تخفیف حذف شد.');
    }

    public function news(): JsonResponse
    {
        $this->authorizeAdmin();

        return $this->paginated(News::orderByDesc('created_at')->paginate(25));
    }

    public function storeNews(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('news', 'public');
        }

        $validated['is_published'] = true;
        $item = News::create($validated);

        ContentCacheService::forgetAll();

        return $this->success($item, 'خبر منتشر شد.', 201);
    }

    public function deleteNews(News $news): JsonResponse
    {
        $this->authorizeAdmin();
        $news->delete();
        ContentCacheService::forgetAll();

        return $this->success(null, 'خبر حذف شد.');
    }

    public function rules(): JsonResponse
    {
        $this->authorizeAdmin();

        return $this->success(Rule::orderBy('id')->get());
    }

    public function storeRule(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $request->validate(['content' => 'required|string']);

        $rule = Rule::create(['content' => $request->content]);
        ContentCacheService::forgetAll();

        return $this->success($rule, 'بخش قوانین اضافه شد.', 201);
    }

    public function updateRule(Request $request, Rule $rule): JsonResponse
    {
        $this->authorizeAdmin();

        $request->validate(['content' => 'required|string']);
        $rule->update(['content' => $request->content]);
        ContentCacheService::forgetAll();

        return $this->success($rule->fresh(), 'بخش قوانین ویرایش شد.');
    }

    public function deleteRule(Rule $rule): JsonResponse
    {
        $this->authorizeAdmin();
        $rule->delete();
        ContentCacheService::forgetAll();

        return $this->success(null, 'بخش قوانین حذف شد.');
    }
}
