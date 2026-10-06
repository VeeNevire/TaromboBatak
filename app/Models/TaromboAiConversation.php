<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'marga_id'])]
class TaromboAiConversation extends Model
{
    public function messages(): HasMany
    {
        return $this->hasMany(TaromboAiMessage::class, 'conversation_id');
    }
}
