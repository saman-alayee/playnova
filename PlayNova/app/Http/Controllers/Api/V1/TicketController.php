<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\HandlesTicketAttachments;
use App\Http\Resources\V1\TicketResource;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends BaseApiController
{
    use HandlesTicketAttachments;

    public function index(Request $request): JsonResponse
    {
        $query = $request->user()
            ->tickets()
            ->withCount('messages')
            ->orderByDesc('updated_at');

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        return $this->paginated($query->paginate(20), TicketResource::class);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
            'priority' => 'nullable|in:low,medium,high',
            'attachment' => 'nullable|image|max:5120',
        ]);

        $ticket = $request->user()->tickets()->create([
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'priority' => $validated['priority'] ?? 'medium',
            'status' => 'open',
        ]);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'body' => $validated['message'],
            'attachment_path' => $this->storeTicketAttachment($request),
            'is_admin' => false,
        ]);

        $ticket->load(['user', 'messages.user']);

        return $this->success(new TicketResource($ticket), 'تیکت شما با موفقیت ثبت شد.', 201);
    }

    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeTicketAccess($request, $ticket);

        $ticket->load(['user', 'messages.user']);

        return $this->success(new TicketResource($ticket));
    }

    public function reply(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeTicketAccess($request, $ticket);

        if ($ticket->status === 'closed' && ! $request->user()->isAdmin()) {
            return $this->error('این تیکت بسته شده است و امکان ارسال پاسخ وجود ندارد.');
        }

        $validated = $request->validate([
            'body' => 'required|string|max:5000',
            'attachment' => 'nullable|image|max:5120',
        ]);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
            'attachment_path' => $this->storeTicketAttachment($request),
            'is_admin' => $request->user()->isAdmin(),
        ]);

        if ($ticket->status === 'closed') {
            $ticket->update(['status' => 'open']);
        } elseif ($request->user()->isAdmin() && $ticket->status === 'open') {
            $ticket->update(['status' => 'in_progress']);
        } else {
            $ticket->touch();
        }

        $ticket->load(['user', 'messages.user']);

        return $this->success(new TicketResource($ticket->fresh()), 'پاسخ ارسال شد.');
    }

    public function attachment(TicketMessage $message)
    {
        return $this->ticketAttachmentResponse($message);
    }

    protected function authorizeTicketAccess(Request $request, Ticket $ticket): void
    {
        if ((int) $ticket->user_id !== (int) $request->user()->id && ! $request->user()->isAdmin()) {
            abort(403, 'دسترسی به این تیکت مجاز نیست.');
        }
    }
}
