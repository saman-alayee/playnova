<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\TicketMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

trait HandlesTicketAttachments
{
    protected function storeTicketAttachment(Request $request): ?string
    {
        if (! $request->hasFile('attachment')) {
            return null;
        }

        $dir = storage_path('app/private/tickets');
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        $name = Str::random(20) . '.' . $request->file('attachment')->getClientOriginalExtension();
        $request->file('attachment')->move($dir, $name);

        return 'private/tickets/' . $name;
    }

    protected function ticketAttachmentResponse(TicketMessage $message): BinaryFileResponse
    {
        if (! $message->attachment_path) {
            abort(404, 'پیوست یافت نشد.');
        }

        $ticket = $message->ticket;
        $user = auth()->user();

        if ((int) $ticket->user_id !== (int) $user->id && ! $user->isAdmin()) {
            abort(403);
        }

        $fullPath = storage_path('app/' . $message->attachment_path);

        if (! file_exists($fullPath)) {
            abort(404, 'فایل پیوست روی سرور موجود نیست.');
        }

        return response()->file($fullPath);
    }
}
