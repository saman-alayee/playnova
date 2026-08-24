<?php

namespace App\Http\Controllers;

use App\Models\TicketMessage;

class TicketAttachmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function ticketAttachment(TicketMessage $message)
    {
        if (! $message->attachment_path) {
            abort(404);
        }

        $ticket = $message->ticket;
        $user = auth()->user();

        if ($ticket->user_id !== $user->id && ! $user->isAdmin()) {
            abort(403);
        }

        $fullPath = storage_path('app/' . $message->attachment_path);

        if (! file_exists($fullPath)) {
            abort(404);
        }

        return response()->file($fullPath);
    }
}
