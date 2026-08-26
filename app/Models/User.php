<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string $role
 * @property int|null $marga_id
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Marga|null $marga
 * @property-read Collection<int, FamilyTree> $familyTrees
 * @property-read Collection<int, ContributionRequest> $contributionRequests
 * @property-read Collection<int, Event> $events
 * @property-read Collection<int, Story> $stories
 * @property-read Collection<int, FeedPost> $feedPosts
 * @property-read Collection<int, FeedComment> $feedComments
 */
#[Fillable(['name', 'email', 'password', 'role', 'marga_id'])]
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
            && $this->marga_id !== null
            && $this->marga_id === $contact->marga_id;
    }

    /**
     * @return BelongsTo<Marga, $this>
     */
    public function marga(): BelongsTo
    {
        return $this->belongsTo(Marga::class);
    }

    /**
     * @return HasMany<FamilyTree, $this>
     */
    public function familyTrees(): HasMany
    {
        return $this->hasMany(FamilyTree::class);
    }

    /** @return HasMany<ContributionRequest, $this> */
    public function contributionRequests(): HasMany
    {
        return $this->hasMany(ContributionRequest::class, 'requester_id');
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

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
