<?php

namespace App\Support\Events;

use App\Support\Models\SupportMessage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportMessageCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public SupportMessage $message) {}
}
