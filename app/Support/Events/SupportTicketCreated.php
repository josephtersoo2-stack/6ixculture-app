<?php

namespace App\Support\Events;

use App\Support\Models\SupportTicket;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportTicketCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public SupportTicket $ticket) {}
}
