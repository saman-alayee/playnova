<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\HandlesKycDocuments;
use App\Models\KycSubmission;
use App\Services\KycEncryptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use RuntimeException;

class KycController extends BaseApiController
{
    use HandlesKycDocuments;

    public function index(): JsonResponse
    {
        $submission = KycSubmission::where('user_id', Auth::id())->latest()->first();

        return $this->success([
            'status' => $submission?->status,
            'rejection_reason' => $submission?->status === 'rejected' ? $submission->admin_note : null,
            'submission' => $submission ? [
                'id' => $submission->id,
                'status' => $submission->status,
                'reviewed_at' => $submission->reviewed_at?->toIso8601String(),
                'admin_note' => $submission->admin_note,
                'created_at' => $submission->created_at?->toIso8601String(),
            ] : null,
        ]);
    }

    public function store(Request $request, KycEncryptionService $crypto): JsonResponse
    {
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
            return $this->error('درخواست احراز هویت قبلی شما در حال بررسی یا تأیید شده است.', 422);
        }

        try {
            $uploaded = $request->file('document');
            $preparedPath = $this->prepareKycImage($uploaded);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422, ['document' => [$e->getMessage()]]);
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

        $submission = KycSubmission::create([
            'user_id' => $userId,
            'national_id_encrypted' => null,
            'card_front_path' => null,
            'card_back_path' => null,
            'document_path' => $documentPath,
            'status' => 'pending',
        ]);

        return $this->success([
            'status' => $submission->status,
            'submission' => [
                'id' => $submission->id,
                'status' => $submission->status,
                'created_at' => $submission->created_at?->toIso8601String(),
            ],
        ], 'مدارک احراز هویت با موفقیت ارسال شد و در انتظار بررسی است.', 201);
    }
}
