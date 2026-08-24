<?php

namespace App\Http\Controllers\Admin;

use App\Models\KycSubmission;
use App\Services\KycEncryptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class KycController extends BaseAdminController
{
    public function kycList()
    {
        $submissions = KycSubmission::with('user')->orderByDesc('created_at')->paginate(20);

        return view('admin.kyc', compact('submissions'));
    }

    public function kycUpdateStatus(Request $request, KycSubmission $submission)
    {
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
            } elseif ($request->status === 'rejected') {
                $user->kyc_verified_at = null;
                $user->save();
            }
        }

        return back()->with('success', 'وضعیت احراز هویت به‌روزرسانی شد.');
    }

    public function kycDocument(KycSubmission $submission, string $side, KycEncryptionService $crypto)
    {
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
        $mime = 'image/jpeg';

        return response()->file($temp, ['Content-Type' => $mime])->deleteFileAfterSend(true);
    }
}
