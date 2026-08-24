<?php

namespace App\Http\Controllers\Admin;

use App\Models\Setting;
use Illuminate\Http\Request;

class PaymentGatewayController extends BaseAdminController
{
    public function paymentGatewayForm()
    {
        return view('admin.payment-gateway');
    }

    public function updatePaymentGateway(Request $request)
    {
        $request->validate([
            'merchant_id' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
            'sandbox' => 'nullable|boolean',
        ]);

        if ($request->filled('merchant_id')) {
            $merchantId = trim($request->merchant_id);
            if ($merchantId === '') {
                return back()->withInput()->with('error', 'مرچنت کد زیبال نمی‌تواند خالی باشد.');
            }

            Setting::set('zibal_merchant_id', $merchantId);
        }

        Setting::set('payment_gateway_active', $request->boolean('is_active'));
        Setting::set('zibal_sandbox', $request->boolean('sandbox'));
        Setting::set('payment_gateway_provider', 'zibal');

        return back()->with('success', 'تنظیمات درگاه پرداخت زیبال با موفقیت ذخیره شد.');
    }

    public function testPaymentGateway()
    {
        if (! Setting::isZibalConfigured()) {
            return back()->with('error', 'پیکربندی زیبال ناقص است. در Sandbox فعال کنید یا مرچنت کد را وارد کنید.');
        }

        $merchantId = Setting::getZibalMerchantId();
        $mode = Setting::isZibalSandbox() ? 'Sandbox' : 'Production';
        $active = Setting::isPaymentGatewayActive() ? 'فعال' : 'غیرفعال';

        return back()->with('success', "پیکربندی زیبال معتبر است. مرچنت: {$merchantId} ({$mode}) — درگاه: {$active}");
    }
}
