<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $is_active
 * @property string $role
 * @property int|null $marga_id
 * @property int|null $current_person_id
 * @property string|null $province_code
 * @property string|null $regency_code
 * @property string|null $district_code
 * @property string|null $village_code
 * @property Carbon|null $news_feed_read_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Marga|null $marga
 * @property-read Collection<int, Marga> $managedMargas
 * @property-read Person|null $currentPerson
 * @property-read Collection<int, FamilyTree> $familyTrees
 * @property-read Collection<int, ContributionRequest> $contributionRequests
 * @property-read Collection<int, MargaAccessRequest> $margaAccessRequests
 * @property-read Collection<int, ContactRequest> $sentContactRequests
 * @property-read Collection<int, ContactRequest> $receivedContactRequests
 * @property-read Collection<int, Event> $events
 * @property-read Collection<int, Story> $stories
 * @property-read Collection<int, FeedPost> $feedPosts
 * @property-read Collection<int, FeedComment> $feedComments
 * @property-read Collection<int, TaromboSnapshot> $taromboSnapshots
 * @property-read Collection<int, FamilyTreeShare> $receivedFamilyTreeShares
 * @property-read Collection<int, FamilyTreeAppendRequest> $familyTreeAppendRequests
 * @property-read TelegramAccount|null $telegramAccount
 * @property-read Collection<int, OAuthAccount> $oauthAccounts
 * @property-read Collection<int, ChatGroup> $ownedChatGroups
 * @property-read Collection<int, ChatGroupMember> $chatGroupMemberships
 */
#[Fillable(['name', 'email', 'password', 'is_active', 'role', 'marga_id', 'current_person_id', 'province_code', 'regency_code', 'district_code', 'village_code'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Determine whether the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Determine whether the user is a sub-admin.
     */
    public function isSubAdmin(): bool
    {
        return $this->role === 'subadmin';
    }

    /**
     * Determine whether the user is part of the staff (admin or sub-admin).
     */
    public function isStaff(): bool
    {
        return $this->isAdmin() || $this->isSubAdmin();
    }

    public function isActive(): bool
    {
        return $this->is_active !== false;
    }

    public function isContributor(): bool
    {
        return in_array($this->role, ['contributor_main', 'contributor_member'], true);
    }

    public function canReviewContributions(): bool
    {
        return $this->isAdmin() || $this->isContributor();
    }

    public function canChatWith(User $contact): bool
    {
        return ! $this->isAdmin()
            && ! $contact->isAdmin()
            && $this->id !== $contact->id
            && ! ContactDisconnect::query()
                ->where(ContactDisconnect::attributesFor($this->id, $contact->id))
                ->exists()
            && ($contact->hasActiveMtprotoSession()
                || ($this->marga_id !== null && $this->marga_id === $contact->marga_id)
                || Conversation::between($this, $contact)->exists()
                || ContactRequest::query()
                    ->where('status', ContactRequest::STATUS_APPROVED)
                    ->where(function ($query) use ($contact) {
                        $query->where(function ($query) use ($contact) {
                            $query->where('requester_id', $this->id)->where('recipient_id', $contact->id);
                        })->orWhere(function ($query) use ($contact) {
                            $query->where('requester_id', $contact->id)->where('recipient_id', $this->id);
                        });
                    })
                    ->exists());
    }

    public function hasActiveMtprotoSession(): bool
    {
        if ($this->relationLoaded('telegramAccount')) {
            return $this->telegramAccount?->isMtprotoConnected() ?? false;
        }

        return $this->telegramAccount()
            ->whereNotNull('session_path')
            ->where('connection_status', TelegramAccount::STATUS_CONNECTED)
            ->exists();
    }

    public function canUseGroups(): bool
    {
        return ! $this->isAdmin() && $this->marga_id !== null;
    }

    /** @return HasOne<TelegramAccount, $this> */
    public function telegramAccount(): HasOne
    {
        return $this->hasOne(TelegramAccount::class);
    }

    /** @return HasOne<TelegramAuthSession, $this> */
    public function telegramAuthSession(): HasOne
    {
        return $this->hasOne(TelegramAuthSession::class);
    }

    /** @return HasMany<OAuthAccount, $this> */
    public function oauthAccounts(): HasMany
    {
        return $this->hasMany(OAuthAccount::class);
    }

    /** @return HasMany<ChatGroup, $this> */
    public function ownedChatGroups(): HasMany
    {
        return $this->hasMany(ChatGroup::class, 'owner_id');
    }

    /** @return HasMany<ChatGroupMember, $this> */
    public function chatGroupMemberships(): HasMany
    {
        return $this->hasMany(ChatGroupMember::class);
    }

    /**
     * @return BelongsTo<Marga, $this>
     */
    public function marga(): BelongsTo
    {
        return $this->belongsTo(Marga::class);
    }

    /** @return BelongsToMany<Marga, $this> */
    public function managedMargas(): BelongsToMany
    {
        return $this->belongsToMany(Marga::class, 'marga_user');
    }

    /** @return \Illuminate\Support\Collection<int, int> */
    public function accessibleMargaIds(): \Illuminate\Support\Collection
    {
        return $this->managedMargas()
            ->pluck('margas.id')
            ->push($this->marga_id)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * Unread chat message count per visible marga, for any signed-in user.
     * Margas with nothing unread are omitted.
     *
     * @return \Illuminate\Support\Collection<int, int> Keyed by marga id.
     */
    public function unreadMargaMessageCounts(): \Illuminate\Support\Collection
    {
        $margaIds = Marga::query()
            ->when(! $this->isStaff(), fn ($query) => $query->where('is_public', true))
            ->pluck('id');

        if ($margaIds->isEmpty()) {
            return collect();
        }

        $reads = MargaChatRead::query()
            ->where('user_id', $this->id)
            ->whereIn('marga_id', $margaIds)
            ->get(['marga_id', 'last_read_at'])
            ->mapWithKeys(fn (MargaChatRead $read) => [$read->marga_id => $read->last_read_at]);

        // ponytail: load-and-filter; move to a join if message volume grows.
        return MargaMessage::query()
            ->whereIn('marga_id', $margaIds)
            ->where(fn ($query) => $query
                ->whereNull('sender_id')
                ->orWhere('sender_id', '!=', $this->id))
            ->get(['id', 'marga_id', 'created_at'])
            ->groupBy('marga_id')
            ->map(function ($messages, int $margaId) use ($reads): int {
                $readAt = $reads->get($margaId);

                return $messages
                    ->filter(fn (MargaMessage $message) => $readAt === null
                        || $message->created_at->gt($readAt))
                    ->count();
            })
            ->filter(fn (int $count) => $count > 0);
    }

    /** @return BelongsTo<Person, $this> */
    public function currentPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'current_person_id');
    }

    /**
     * @return HasMany<FamilyTree, $this>
     */
    public function familyTrees(): HasMany
    {
        return $this->hasMany(FamilyTree::class);
    }

    /** @return HasMany<FamilyTreeShare, $this> */
    public function receivedFamilyTreeShares(): HasMany
    {
        return $this->hasMany(FamilyTreeShare::class, 'recipient_id');
    }

    /** @return HasMany<FamilyTreeAppendRequest, $this> */
    public function familyTreeAppendRequests(): HasMany
    {
        return $this->hasMany(FamilyTreeAppendRequest::class, 'requester_id');
    }

    /** @return HasMany<ContributionRequest, $this> */
    public function contributionRequests(): HasMany
    {
        return $this->hasMany(ContributionRequest::class, 'requester_id');
    }

    /** @return HasMany<MargaAccessRequest, $this> */
    public function margaAccessRequests(): HasMany
    {
        return $this->hasMany(MargaAccessRequest::class, 'requester_id');
    }

    /** @return \Illuminate\Support\Collection<int, int> */
    public function approvedMargaAccessIds(): \Illuminate\Support\Collection
    {
        return $this->margaAccessRequests()
            ->where('status', MargaAccessRequest::STATUS_APPROVED)
            ->pluck('marga_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /** @return HasMany<ContactRequest, $this> */
    public function sentContactRequests(): HasMany
    {
        return $this->hasMany(ContactRequest::class, 'requester_id');
    }

    /** @return HasMany<ContactRequest, $this> */
    public function receivedContactRequests(): HasMany
    {
        return $this->hasMany(ContactRequest::class, 'recipient_id');
    }

    /** @return HasMany<Event, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'created_by');
    }

    /** @return HasMany<Story, $this> */
    public function stories(): HasMany
    {
        return $this->hasMany(Story::class, 'created_by');
    }

    /** @return HasMany<FeedPost, $this> */
    public function feedPosts(): HasMany
    {
        return $this->hasMany(FeedPost::class);
    }

    /** @return HasMany<FeedComment, $this> */
    public function feedComments(): HasMany
    {
        return $this->hasMany(FeedComment::class);
    }

    /** @return HasMany<TaromboSnapshot, $this> */
    public function taromboSnapshots(): HasMany
    {
        return $this->hasMany(TaromboSnapshot::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'news_feed_read_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
