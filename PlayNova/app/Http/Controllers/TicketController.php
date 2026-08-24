<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TicketController extends Controller
{
    public function index()
    {
        $tickets = Auth::user()->tickets()->orderByDesc('created_at')->get();

        return view('tickets', compact('tickets'));
    }

    public function show(Ticket $ticket)
    {
        if (! $this->userCanAccessTicket($ticket)) {
            abort(403);
        }

        $ticket->load(['messages.user', 'user']);

        return view('tickets-show', compact('ticket'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
            'priority' => 'nullable|in:low,medium,high',
            'attachment' => 'nullable|image|max:5120',
        ]);

        $ticket = Auth::user()->tickets()->create([
            'subject' => $request->subject,
            'message' => $request->message,
            'priority' => $request->priority ?? 'medium',
            'status' => 'open',
        ]);

        $attachmentPath = $this->storeAttachment($request);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'body' => $request->message,
            'attachment_path' => $attachmentPath,
            'is_admin' => false,
        ]);

        return redirect()->route('tickets.show', $ticket)->with('success', 'تیکت شما با موفقیت ثبت شد.');
    }

    public function reply(Request $request, Ticket $ticket)
    {
        if (! $this->userCanAccessTicket($ticket)) {
            abort(403);
        }

        $request->validate([
            'body' => 'required|string|max:5000',
            'attachment' => 'nullable|image|max:5120',
        ]);

        $attachmentPath = $this->storeAttachment($request);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'body' => $request->body,
            'attachment_path' => $attachmentPath,
            'is_admin' => Auth::user()->isAdmin(),
        ]);

        if ($ticket->status === 'closed') {
            $ticket->update(['status' => 'open']);
        } elseif (Auth::user()->isAdmin() && $ticket->status === 'open') {
            $ticket->update(['status' => 'in_progress']);
        }

        return back()->with('success', 'پاسخ ارسال شد.');
    }

    public function updateStatus(Request $request, Ticket $ticket)
    {
        $request->validate([
            'status' => 'required|in:open,in_progress,resolved,closed',
        ]);

        $ticket->update(['status' => $request->status]);

        return back()->with('success', 'وضعیت تیکت به‌روزرسانی شد.');
    }

    protected function userCanAccessTicket(Ticket $ticket): bool
    {
        return (int) $ticket->user_id === (int) Auth::id() || Auth::user()->isAdmin();
    }

    protected function storeAttachment(Request $request): ?string
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
}
