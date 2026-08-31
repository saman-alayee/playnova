<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\AuthorizesAdmin;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Controllers\Api\V1\Concerns\HandlesTicketAttachments;
use App\Http\Resources\V1\TicketResource;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketAdminController extends BaseApiController
{
    use AuthorizesAdmin;
    use HandlesTicketAttachments;

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $query = Ticket::query()
            ->with('user:id,username,mobile,email')
            ->withCount('messages');

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                if (ctype_digit($search)) {
                    $q->orWhere('id', (int) $search);
                }

                $q->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('username', 'like', "%{$search}%")
                            ->orWhere('mobile', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $status = (string) $request->query('status', 'all');
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $priority = (string) $request->query('priority', 'all');
        if ($priority !== 'all') {
            $query->where('priority', $priority);
        }

        $sort = (string) $request->query('sort', 'newest');
        if ($sort === 'priority') {
            $query->orderByRaw("FIELD(priority, 'high', 'medium', 'low')")->orderByDesc('updated_at');
        } else {
            $query->orderByDesc('updated_at');
        }

        return $this->paginated($query->paginate(25), TicketResource::class);
    }

    public function show(Ticket $ticket): JsonResponse
    {
        $this->authorizeAdmin();

        $ticket->load(['user', 'messages.user']);

        return $this->success(new TicketResource($ticket));
    }

    public function updateStatus(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'status' => 'required|in:open,in_progress,resolved,closed',
        ]);

        $ticket->update(['status' => $validated['status']]);

        return $this->success(new TicketResource($ticket->fresh(['user'])), 'وضعیت تیکت به‌روزرسانی شد.');
    }

    public function reply(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'body' => 'required|string|max:5000',
            'attachment' => 'nullable|image|max:5120',
        ]);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
            'attachment_path' => $this->storeTicketAttachment($request),
            'is_admin' => true,
        ]);

        if ($ticket->status === 'open') {
            $ticket->update(['status' => 'in_progress']);
        } else {
            $ticket->touch();
        }

        $ticket->load(['user', 'messages.user']);

        return $this->success(new TicketResource($ticket->fresh()), 'پاسخ ارسال شد.');
    }

    public function attachment(TicketMessage $message)
    {
        $this->authorizeAdmin();

        return $this->ticketAttachmentResponse($message);
    }
}
