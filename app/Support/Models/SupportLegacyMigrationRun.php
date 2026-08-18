<?php

namespace App\Support\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SupportLegacyMigrationRun extends Model
{
    use HasFactory;

    protected $table = 'support_legacy_migration_runs';

    protected $fillable = [
        'public_id',
        'source',
        'mode',
        'status',
        'started_at',
        'completed_at',
        'source_counts',
        'result_counts',
        'error_counts',
        'checksum',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'source_counts' => 'array',
        'result_counts' => 'array',
        'error_counts' => 'array',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (SupportLegacyMigrationRun $run) {
            if (empty($run->public_id)) {
                $run->public_id = (string) Str::ulid();
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupportLegacyMigrationItem::class, 'migration_run_id');
    }
}
