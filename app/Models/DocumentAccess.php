<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentAccess extends Model
{
    protected $table = 'document_access';

    protected $fillable = ['document_id', 'division_id', 'role_name'];

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }
}
