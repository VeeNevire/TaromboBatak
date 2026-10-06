<?php

namespace App\Services;

use App\Models\FamilyTree;
use App\Models\FamilyTreeActivity;
use App\Models\FamilyTreeShare;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FamilyTreeActivityHistory
{
    /** @return Builder<FamilyTreeActivity> */
    private function visibleActivities(User $user): Builder
    {
        return FamilyTreeActivity::query()
            ->when(! $user->isStaff(), fn ($query) => $query->where(fn ($access) => $access
                ->where('owner_id', $user->id)
                ->orWhere('actor_id', $user->id)
                ->orWhereHas('familyTree.shares', fn ($shares) => $shares
                    ->whereBelongsTo($user, 'recipient')
                    ->where('status', FamilyTreeShare::STATUS_ACCEPTED))));
    }

    /** @return Collection<int, User> */
    public function accounts(User $user): Collection
    {
        return User::query()
            ->when(! $user->isStaff(), fn ($query) => $query->where(fn ($accounts) => $accounts
                ->whereKey($user->id)
                ->orWhereIn('id', $this->visibleActivities($user)->select('actor_id'))))
            ->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Account creation comes from the original timestamp, so existing accounts
     * have a starting point without inventing missing historical activity.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(User $user, array $filters): LengthAwarePaginator
    {
        $treeLogs = $this->visibleActivities($user)
            ->select(['id', 'actor_id', 'family_tree_id', 'tree_name', 'member_name', 'action', 'description', 'created_at'])
            ->selectRaw('? as source', ['tree']);
        $accounts = User::query()
            ->when(! $user->isStaff(), fn ($query) => $query->whereKey($user->id));
        $accounts->select(['id'])->selectRaw('id as actor_id, NULL as family_tree_id, NULL as tree_name, NULL as member_name, ? as action, ? as description, created_at, ? as source', [
            'account_created', 'Akun dibuat.', 'account',
        ]);

        $query = DB::query()->fromSub($treeLogs->toBase()->unionAll($accounts->toBase()), 'history')
            ->when(! empty($filters['account_id']), fn ($query) => $query->where('actor_id', $filters['account_id']));

        $search = trim($filters['search'] ?? '');
        if ($search !== '') {
            $pattern = '%'.$search.'%';
            $query->where(fn ($matches) => $matches
                ->where('tree_name', 'like', $pattern)
                ->orWhere('member_name', 'like', $pattern)
                ->orWhere('description', 'like', $pattern)
                ->orWhere('action', 'like', $pattern)
                ->orWhereIn('actor_id', User::query()->select('id')->where('name', 'like', $pattern)));
        }

        if (! empty($filters['date'])) {
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $filters['date'], 'Asia/Jakarta');
            $query->where('created_at', '>=', $start->utc())
                ->where('created_at', '<', $start->addDay()->utc());
        }

        $direction = ($filters['order'] ?? 'newest') === 'oldest' ? 'asc' : 'desc';
        $page = $query->orderBy('created_at', $direction)->orderBy('source')->orderBy('id', $direction)
            ->paginate(25)->withQueryString();
        $rows = collect($page->items());
        $actors = User::query()->whereIn('id', $rows->pluck('actor_id')->filter())->pluck('name', 'id');
        $trees = FamilyTree::query()->whereIn('id', $rows->pluck('family_tree_id')->filter())
            ->with('rootPerson:id,name')->get()->keyBy('id');

        return $page->through(fn ($activity) => [
            'id' => $activity->source.'-'.$activity->id,
            'tree_name' => $activity->tree_name,
            'father_name' => $trees->get($activity->family_tree_id)?->rootPerson?->name,
            'member_name' => $activity->member_name,
            'action' => $activity->action,
            'description' => $activity->description,
            'actor' => $actors->get($activity->actor_id, 'Sistem'),
            'created_at' => $activity->created_at
                ? CarbonImmutable::parse($activity->created_at, 'UTC')->setTimezone('Asia/Jakarta')->translatedFormat('d M Y, H:i').' WIB'
                : null,
        ]);
    }
}
