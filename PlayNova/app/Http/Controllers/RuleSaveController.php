<?php

namespace App\Http\Controllers;

use App\Models\Rule;
use Illuminate\Http\Request;

class RuleSaveController extends Controller
{
    public function save(Request $request)
    {
        $request->validate([
            'content' => 'required|string',
        ]);

        if ($request->has('section_id')) {
            // ویرایش بخش موجود
            $rule = Rule::find($request->section_id);
            if ($rule) {
                $rule->update(['content' => $request->content]);
            }
        } else {
            // افزودن بخش جدید
            Rule::create(['content' => $request->content]);
        }

        return back()->with('success', 'قوانین با موفقیت ذخیره شد.');
    }
}