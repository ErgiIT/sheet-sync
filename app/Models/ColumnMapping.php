<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ColumnType;
use Database\Factories\ColumnMappingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ColumnMapping extends Model
{
    /** @use HasFactory<ColumnMappingFactory> */
    use HasFactory;

    protected $fillable = [
        'tab_mapping_id',
        'source_column',
        'target_column',
        'type',
        'position',
    ];

    protected $casts = [
        'type' => ColumnType::class,
        'position' => 'integer',
    ];

    public function tabMapping(): BelongsTo
    {
        return $this->belongsTo(TabMapping::class);
    }
}
