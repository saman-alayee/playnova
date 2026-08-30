<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Controllers\Api\V1\Admin\Concerns\AuthorizesAdmin;
use App\Jobs\SendUserNotificationJob;
use App\Models\KycSubmission;
use App\Services\KycEncryptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class KycAdminController extends BaseApiController
{
    use AuthorizesAdmin;

    public function updateStatus(Request $request, KycSubmission $submission): JsonResponse
    {
        $this->authorizeAdmin();

        $request->validate([
            'status' => 'required|in:pending,approved,rejected',
            'admin_note' => 'nullable|string|max:1000',
        ]);

        $submission->update([
            'status' => $request->status,
            'admin_note' => $request->admin_note,
            'reviewed_at' => now(),
        ]);

        $user = $submission->user;
        if ($user) {
            if ($request->status === 'approved') {
                $user->kyc_verified_at = now();
                $user->save();

                SendUserNotificationJob::dispatch(
                    (int) $user->id,
                    'احراز هویت تأیید شد',
                    'مدارک احراز هویت شما تأیید شد.',
                    'kyc',
                );
            } elseif ($request->status === 'rejected') {
                $user->kyc_verified_at = null;
                $user->save();

                SendUserNotificationJob::dispatch(
                    (int) $user->id,
                    'احراز هویت رد شد',
                    'مدارک احراز هویت شما رد شد. لطفاً مدارک را اصلاح و دوباره ارسال کنید.',
                    'kyc',
                );
            }
        }

        return $this->success($submission->fresh('user'), 'وضعیت احراز هویت به‌روزرسانی شد.');
    }

    public function document(KycSubmission $submission, string $side, KycEncryptionService $crypto): BinaryFileResponse
    {
        $this->authorizeAdmin();

        $path = match ($side) {
            'document' => $submission->document_path,
            'front' => $submission->card_front_path,
            'back' => $submission->card_back_path,
            default => null,
        };

        if (! $path || ! file_exists($path)) {
            abort(404);
        }

        if (class_exists(\App\Models\KycAccessLog::class) && Schema::hasTable('kyc_access_logs')) {
            \App\Models\KycAccessLog::create([
                'admin_id' => auth()->id(),
                'kyc_submission_id' => $submission->id,
                'action' => 'view_' . $side,
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);
        }

        $temp = $crypto->decryptToTemp($path);

        return response()->file($temp, ['Content-Type' => 'image/jpeg'])->deleteFileAfterSend(true);
    }
}
