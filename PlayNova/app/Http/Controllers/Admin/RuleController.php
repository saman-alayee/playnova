<?php

namespace App\Http\Controllers\Admin;

use App\Models\Rule;
use Illuminate\Http\Request;

class RuleController extends BaseAdminController
{
    public function editRulesForm()
    {
        $rule = Rule::first();
        return view('admin.rules', compact('rule'));
    }

    public function updateRules(Request $request)
    {
        $request->validate([
            'content' => 'required|string',
        ]);

        $rule = Rule::first();
        if (!$rule) {
            Rule::create(['content' => $request->content]);
        } else {
            $rule->update(['content' => $request->content]);
        }

        return back()->with('success', 'قوانین با موفقیت به‌روزرسانی شد.');
    }

    public function manageRules()
    {
        $rules = Rule::orderBy('id')->get();
        return view('admin.rules-manage', compact('rules'));
    }

    public function storeRule(Request $request)
    {
        $request->validate([
            'content' => 'required|string',
        ]);

        Rule::create([
            'content' => $request->content,
        ]);

        return redirect()->route('admin.rules.manage')->with('success', 'بخش جدید با موفقیت اضافه شد.');
    }

    public function editRule($id)
    {
        $rule = Rule::findOrFail($id);
        return view('admin.rules-edit', compact('rule'));
    }

    public function updateRule(Request $request, $id)
    {
        $request->validate([
            'content' => 'required|string',
        ]);

        $rule = Rule::findOrFail($id);
        $rule->update(['content' => $request->content]);

        return redirect()->route('admin.rules.manage')->with('success', 'بخش قوانین با موفقیت ویرایش شد.');
    }

    public function deleteRule($id)
    {
        $rule = Rule::findOrFail($id);
        $rule->delete();

        return redirect()->route('admin.rules.manage')->with('success', 'بخش قوانین با موفقیت حذف شد.');
    }
}
