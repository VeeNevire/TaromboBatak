<?php

namespace App\Http\Middleware;

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

        $this->logger->log(
            $actor,
            $actor,
            $routeName ?? strtolower($request->method()).' '.$request->path(),
            $this->description($request, $routeName, $personBeforeChange),
            [
                'method' => $request->method(),
                'route' => $routeName,
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

    private function description(Request $request, ?string $routeName, ?Person $personBeforeChange): string
    {
        if ($routeName === 'people.update') {
            $person = $personBeforeChange?->fresh(['father:id,name']);

            if ($person !== null) {
                return $this->personDescription('Mengubah data', $person);
            }
        }

        if ($routeName === 'people.destroy' && $personBeforeChange !== null) {
            return $this->personDescription('Menghapus data', $personBeforeChange);
        }

        return 'Melakukan perubahan data'.($routeName !== null ? ' melalui '.$routeName.'.' : '.');
    }

    private function personDescription(string $action, Person $person): string
    {
        $fatherName = $person->father?->name ?? 'ayah belum dicatat';

        return "$action {$person->name}, anak dari {$fatherName}.";
    }
}
