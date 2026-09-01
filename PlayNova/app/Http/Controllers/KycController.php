<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesKycDocuments;
use App\Http\Controllers\Concerns\HandlesUploadLimits;
use App\Models\KycSubmission;
use App\Services\KycEncryptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use RuntimeException;

class KycController extends Controller
{
    use HandlesKycDocuments;
    use HandlesUploadLimits;

    public function index()
    {
        $submission = KycSubmission::where('user_id', Auth::id())->latest()->first();

        return view('kyc-index', compact('submission'));
    }

    public function store(Request $request, KycEncryptionService $crypto)
    {
        if ($uploadError = $this->uploadLimitError($request, 'document')) {
            return back()->withErrors(['document' => $uploadError])->withInput();
        }

        $maxKb = $this->kycUploadMaxKilobytes();

        $request->validate([
            'document' => 'required|image|max:' . $maxKb,
        ], [
            'document.required' => 'لطفاً یک تصویر واحد شامل کارت ملی، کارت بانکی و متن تعهدنامه را بارگذاری کنید.',
            'document.image' => 'فایل باید تصویر باشد.',
            'document.max' => $this->kycCanCompress()
                ? 'حداکثر حجم تصویر برای آپلود ۱۰ مگابایت است (پس از ارسال، سایت آن را فشرده می‌کند).'
                : 'حداکثر حجم تصویر ۲ مگابایت است.',
        ]);

        $existing = KycSubmission::where('user_id', Auth::id())
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existing) {
            return back()->with('error', 'درخواست احراز هویت قبلی شما در حال بررسی یا تأیید شده است.');
        }

        try {
            $uploaded = $request->file('document');
            $preparedPath = $this->prepareKycImage($uploaded);
        } catch (RuntimeException $e) {
            return back()->withErrors(['document' => $e->getMessage()])->withInput();
        }

        $userId = Auth::id();
        $baseDir = storage_path('app/private/kyc/' . $userId);
        if (! is_dir($baseDir)) {
            mkdir($baseDir, 0700, true);
        }

        $documentPath = $baseDir . '/document_' . Str::random(16) . '.enc';
        $crypto->encryptFile($preparedPath, $documentPath);

        if ($preparedPath !== $uploaded->getRealPath()) {
            @unlink($preparedPath);
        }

        KycSubmission::create([
            'user_id' => $userId,
            'national_id_encrypted' => null,
            'document_path' => $documentPath,
            'status' => 'pending',
        ]);

        return back()->with('success', 'مدارک احراز هویت با موفقیت ارسال شد و در انتظار بررسی است.');
    }
}

