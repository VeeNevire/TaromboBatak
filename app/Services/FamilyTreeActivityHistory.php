<?php

namespace App\Services;

use App\Models\FamilyTreeActivity;
use App\Models\FamilyTreeShare;
use App\Models\Person;
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
            ->select(['id', 'actor_id', 'family_tree_id', 'tree_name', 'member_name', 'father_name', 'action', 'description', 'created_at'])
            ->selectRaw('? as source', ['tree']);
        $accounts = User::query()
            ->when(! $user->isStaff(), fn ($query) => $query->whereKey($user->id));
        $accounts->select(['id'])->selectRaw('id as actor_id, NULL as family_tree_id, NULL as tree_name, NULL as member_name, NULL as father_name, ? as action, ? as description, created_at, ? as source', [
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
                ->orWhere('father_name', 'like', $pattern)
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
        $legacyFathers = $this->legacyFatherNames($rows);

        return $page->through(fn ($activity) => [
            'id' => $activity->source.'-'.$activity->id,
            'tree_name' => $activity->tree_name,
            'father_name' => $activity->father_name
                ?? $legacyFathers->get($activity->family_tree_id.'|'.$activity->member_name),
            'member_name' => $activity->member_name,
            'action' => $activity->action,
            'description' => $activity->description,
            'actor' => $actors->get($activity->actor_id, 'Sistem'),
            'created_at' => $activity->created_at
                ? CarbonImmutable::parse($activity->created_at, 'UTC')->setTimezone('Asia/Jakarta')->translatedFormat('d M Y, H:i').' WIB'
                : null,
        ]);
    }

    /**
     * Logs written before father_name existed only know the member's name.
     * Recover the father from the member with that name in the same tree,
     * skipping names that are ambiguous inside the tree.
     *
     * @param  Collection<int, object>  $rows
     * @return Collection<string, string>
     */
    private function legacyFatherNames(Collection $rows): Collection
    {
        $missing = $rows->filter(fn ($row) => $row->father_name === null && $row->family_tree_id && $row->member_name);

        if ($missing->isEmpty()) {
            return collect();
        }

        return Person::query()
            ->join('family_tree_person', 'family_tree_person.person_id', '=', 'people.id')
            ->whereIn('family_tree_person.family_tree_id', $missing->pluck('family_tree_id')->unique())
            ->whereIn('people.name', $missing->pluck('member_name')->unique())
            ->with('father:id,name')
            ->get(['people.id', 'people.name', 'people.father_id', 'people.pending_father', 'family_tree_person.family_tree_id'])
            ->groupBy(fn (Person $person) => $person->family_tree_id.'|'.$person->name)
            ->filter(fn (Collection $matches) => $matches->count() === 1)
            ->map(fn (Collection $matches) => $matches->first()->pending_father ? null : $matches->first()->father?->name)
            ->filter();
    }
}
