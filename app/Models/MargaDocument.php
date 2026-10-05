<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['marga_id', 'uploaded_by', 'title', 'original_name', 'path', 'mime_type', 'size_bytes'])]
class MargaDocument extends Model
{
    public function marga(): BelongsTo
    {
        return $this->belongsTo(Marga::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
