<?php

namespace App\Models\Concerns;

use App\Models\StoredFile;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasAttachments
{
    public function attachments(): MorphMany
    {
        return $this->morphMany(StoredFile::class, 'attachable')->orderBy('id');
    }
}
