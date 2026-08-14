<?php

namespace App\Support\Models;

use App\Models\User;
use App\Support\Enums\SupportPriority;
use App\Support\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SupportTicket extends Model
{
    use HasFactory;

    protected $table = 'support_tickets';

    protected $fillable = [
        'public_id',
        'ticket_number',
        'conversation_id',
        'customer_id',
        'department_id',
        'assigned_agent_id',
        'category',
        'priority',
        'status',
        'subject',
        'description',
        'resolution',
        'sla_due_at',
        'resolved_at',
        'closed_at',
        'metadata',
    ];

    protected $casts = [
        'priority' => SupportPriority::class,
        'status' => TicketStatus::class,
        'sla_due_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (SupportTicket $ticket) {
            if (empty($ticket->public_id)) {
                $ticket->public_id = (string) Str::ulid();
            }
            if (empty($ticket->ticket_number)) {
                $ticket->ticket_number = '6IX-' . strtoupper(Str::random(6)) . '-' . rand(100, 999);
            }
        });
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(SupportConversation::class, 'conversation_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(SupportDepartment::class, 'department_id');
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeForAgent($query, int $agentId)
    {
        return $query->where('assigned_agent_id', $agentId);
    }

    public function scopeForDepartment($query, int $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeOpen($query)
    {
        return $query->whereNotIn('status', [TicketStatus::RESOLVED, TicketStatus::CLOSED]);
    }
}
