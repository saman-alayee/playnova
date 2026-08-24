<?php

namespace App\Http\Controllers\Admin;

use App\Models\Discount;
use Illuminate\Http\Request;

class DiscountController extends BaseAdminController
{
    public function discounts()
    {
        $discounts = Discount::orderByDesc('created_at')->paginate(25);
        return view('admin.discounts', compact('discounts'));
    }

    public function storeDiscount(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:discounts,code',
            'type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:0',
            'expires_at' => 'nullable|date',
        ]);

        if ($validated['type'] === 'percent') {
            $validated['type'] = 'percentage';
        }

        $validated['is_active'] = true;
        $validated['usage_limit'] = $validated['usage_limit'] ?? 0;
        Discount::create($validated);
        return back()->with('success', 'کد تخفیف ایجاد شد.');
    }

    public function deleteDiscount(Discount $discount)
    {
        $discount->delete();
        return back()->with('success', 'کد تخفیف حذف شد.');
    }
}
