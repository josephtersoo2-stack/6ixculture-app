<?php

namespace App\Support\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportLegacyMigrationItem extends Model
{
    use HasFactory;

    protected $table = 'support_legacy_migration_items';

    protected $fillable = [
        'migration_run_id',
        'source_table',
        'source_id',
        'target_table',
        'target_id',
        'source_checksum',
        'state',
        'migrated_at',
        'last_verified_at',
        'metadata',
    ];

    protected $casts = [
        'source_id' => 'integer',
        'target_id' => 'integer',
        'migrated_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(SupportLegacyMigrationRun::class, 'migration_run_id');
    }
}
