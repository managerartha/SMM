<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LmsImport extends Model
{
    protected $fillable = ['kind', 'source', 'file_name', 'created_count', 'updated_count', 'skipped_count', 'errors', 'imported_by'];

    protected $casts = ['errors' => 'array'];

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
