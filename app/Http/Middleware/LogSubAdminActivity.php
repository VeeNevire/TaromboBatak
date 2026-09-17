<?php

namespace App\Http\Middleware;

use App\Models\FamilyTree;
use App\Models\Person;
use App\Models\User;
use App\Services\AccountActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogSubAdminActivity
{
    public function __construct(private AccountActivityLogger $logger) {}

    /**
     * Record successful data-changing requests performed by sub-admins.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $personBeforeChange = $this->personFromRoute($request);
        $familyTreeBeforeChange = $this->familyTreeFor($request, $request->user(), $personBeforeChange);
        $response = $next($request);
        $actor = $request->user();
        $routeName = $request->route()?->getName();

        if (! $actor instanceof User
            || ! $actor->isSubAdmin()
            || ! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            || $response->getStatusCode() >= 400
            || $routeName === 'logout'
            || $request->session()->has('errors')) {
            return $response;
        }

        $person = $this->activityPerson($routeName, $personBeforeChange);
        $familyTree = $routeName === 'people.update'
            ? $this->familyTreeFor($request, $actor, $person) ?? $familyTreeBeforeChange
            : $familyTreeBeforeChange;

        $this->logger->log(
            $actor,
            $actor,
            $routeName ?? strtolower($request->method()).' '.$request->path(),
            $this->description($routeName, $person),
            [
                'method' => $request->method(),
                'route' => $routeName,
                ...$this->personMetadata($routeName, $person, $familyTree),
            ],
        );

        return $response;
    }

    private function personFromRoute(Request $request): ?Person
    {
        $person = $request->route('person');

        return $person instanceof Person
            ? $person->loadMissing('father:id,name')
            : null;
    }

    private function activityPerson(?string $routeName, ?Person $personBeforeChange): ?Person
    {
        if ($routeName === 'people.update') {
            return $personBeforeChange?->fresh(['father:id,name']);
        }

        return $routeName === 'people.destroy' ? $personBeforeChange : null;
    }

    private function description(?string $routeName, ?Person $person): string
    {
        if ($routeName === 'people.update' && $person !== null) {
            return $this->personDescription('Mengubah data', $person);
        }

        if ($routeName === 'people.destroy' && $person !== null) {
            return $this->personDescription('Menghapus data', $person);
        }

        return 'Melakukan perubahan data'.($routeName !== null ? ' melalui '.$routeName.'.' : '.');
    }

    /** @return array<string, array<string, int|string|null>|null> */
    private function personMetadata(?string $routeName, ?Person $person, ?FamilyTree $familyTree): array
    {
        if (! in_array($routeName, ['people.update', 'people.destroy'], true) || $person === null) {
            return [];
        }

        return [
            'person' => [
                'id' => $person->id,
                'name' => $person->name,
            ],
            'father' => [
                'id' => $person->father?->id,
                'name' => $person->father?->name,
            ],
            'family_tree' => $familyTree === null ? null : [
                'id' => $familyTree->id,
                'name' => $familyTree->name ?? $familyTree->rootPerson?->name ?? 'Silsilah',
            ],
        ];
    }

    private function familyTreeFor(Request $request, ?User $account, ?Person $person): ?FamilyTree
    {
        $versionTreeId = $request->integer('version_tree');

        if ($versionTreeId > 0) {
            return FamilyTree::query()
                ->with('rootPerson:id,name')
                ->find($versionTreeId);
        }

        if (! $account instanceof User || $person === null) {
            return null;
        }

        return FamilyTree::query()
            ->where('user_id', $account->id)
            ->where(function ($query) use ($person): void {
                $query->where('root_person_id', $person->id)
                    ->orWhereHas('nodes', fn ($nodes) => $nodes->where('person_id', $person->id));
            })
            ->with('rootPerson:id,name')
            ->orderByDesc('is_primary')
            ->latest('updated_at')
            ->first();
    }

    private function personDescription(string $action, Person $person): string
    {
        $fatherName = $person->father?->name ?? 'ayah belum dicatat';

        return "$action {$person->name}, anak dari {$fatherName}.";
    }
}
