<?php

namespace App\Http\Controllers\Admin;

use App\Models\Ticket;

class TicketController extends BaseAdminController
{
    public function tickets()
    {
        $tickets = Ticket::with('user')->orderByDesc('created_at')->paginate(25);
        return view('admin.tickets', compact('tickets'));
    }
}
