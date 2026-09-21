<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Links a contact conversation to the marga chat it was started from, so both
 * the sender and the marga's contributors can find the thread.
 *
 * @property int $id
 * @property int $marga_id
 * @property int $conversation_id
 * @property int|null $sender_id
 * @property-read Marga $marga
 * @property-read Conversation $conversation
 * @property-read User|null $sender
 */
#[Fillable(['marga_id', 'conversation_id', 'sender_id'])]
class MargaChatConversation extends Model
{
    /** @return BelongsTo<Marga, $this> */
    public function marga(): BelongsTo
    {
        return $this->belongsTo(Marga::class);
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
