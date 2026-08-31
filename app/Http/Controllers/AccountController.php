<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\ActivityLog;
use App\Models\Marga;
use App\Models\User;
use App\Services\AccountActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        $accounts = User::query()
            ->with(['marga:id,name', 'currentPerson:id,name', 'managedMargas:id,name'])
            ->when($request->filled('search'), fn ($query) => $query->where(function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->string('role')))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (User $user) => $this->accountPayload($user));

        return Inertia::render('accounts/index', [
            'accounts' => $accounts,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'role' => $request->string('role')->toString(),
            ],
        ]);
    }

    public function activityLog(User $account): JsonResponse
    {
        return response()->json([
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'email' => $account->email,
            ],
            'logs' => ActivityLog::query()
                ->where('account_id', $account->id)
                ->with('actor:id,name')
                ->latest()
                ->limit(100)
                ->get()
                ->map(fn (ActivityLog $log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'description' => $log->description,
                    'actor' => $log->actor?->name ?? 'Sistem',
                    'created_at' => $log->created_at?->format('d M Y H:i'),
                ])
                ->values(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('accounts/form', [
            'account' => null,
            'margas' => $this->margaOptions(),
            'managedMargaOptions' => $this->managedMargaOptions(),
            'managedMargaIds' => [],
        ]);
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $managedMargaIds = $validated['managed_marga_ids'] ?? [];
        unset($validated['managed_marga_ids']);

        DB::transaction(function () use ($validated, $managedMargaIds, $request): void {
            $account = User::create([...$validated, 'email_verified_at' => now()]);
            if ($account->isContributor()) {
                $account->managedMargas()->sync($managedMargaIds);
            }

            app(AccountActivityLogger::class)->log(
                $account,
                $request->user(),
                'created',
                'Akun dibuat.',
                ['role' => $account->role, 'marga_id' => $account->marga_id],
            );
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Akun berhasil ditambahkan.']);

        return to_route('accounts.index');
    }

    public function edit(User $account): Response
    {
        return Inertia::render('accounts/form', [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'email' => $account->email,
                'role' => $account->role,
                'marga_id' => $account->marga_id,
            ],
            'margas' => $this->margaOptions(),
            'managedMargaOptions' => $this->managedMargaOptions(),
            'managedMargaIds' => $account->managedMargas()->pluck('margas.id')->map(fn (int $id) => $id)->all(),
        ]);
    }

    public function update(UpdateAccountRequest $request, User $account): RedirectResponse
    {
        $validated = $request->validated();
        $managedMargaIds = $validated['managed_marga_ids'] ?? [];
        unset($validated['managed_marga_ids']);

        DB::transaction(function () use ($validated, $managedMargaIds, $account, $request): void {
            $before = [
                'name' => $account->name,
                'email' => $account->email,
                'role' => $account->role,
                'marga_id' => $account->marga_id,
                'managed_margas' => $account->managedMargas()->pluck('margas.name')->values()->all(),
            ];
            $account->fill(collect($validated)->except('password')->all());
            if (array_key_exists('password', $validated) && filled($validated['password'])) {
                $account->password = $validated['password'];
            }
            $account->save();
            $account->managedMargas()->sync($account->isContributor() ? $managedMargaIds : []);

            $after = [
                'name' => $account->name,
                'email' => $account->email,
                'role' => $account->role,
                'marga_id' => $account->marga_id,
                'managed_margas' => $account->managedMargas()->pluck('margas.name')->values()->all(),
            ];
            $changes = array_keys(array_filter($after, fn ($value, $key) => $value !== $before[$key], ARRAY_FILTER_USE_BOTH));

            if ($changes !== []) {
                app(AccountActivityLogger::class)->log(
                    $account,
                    $request->user(),
                    'updated',
                    'Data akun diperbarui.',
                    ['changes' => $changes, 'before' => $before, 'after' => $after],
                );
            }
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Akun berhasil diperbarui.']);

        return to_route('accounts.index');
    }

    public function destroy(Request $request, User $account): RedirectResponse
    {
        abort_if($account->id === $request->user()?->id, 403, 'Anda tidak dapat menghapus akun sendiri.');
        DB::transaction(function () use ($account, $request): void {
            app(AccountActivityLogger::class)->log(
                $account,
                $request->user(),
                'deleted',
                'Akun dihapus.',
            );
            $account->delete();
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Akun berhasil dihapus.']);

        return to_route('accounts.index');
    }

    /** @return array<string, mixed> */
    private function accountPayload(User $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'email' => $account->email,
            'role' => $account->role,
            'marga' => $account->marga?->name,
            'marga_id' => $account->marga_id,
            'managed_margas' => $account->managedMargas->pluck('name')->values()->all(),
            'current_person' => $account->currentPerson?->name,
            'created_at' => $account->created_at?->format('d M Y'),
        ];
    }

    /** @return array<int, array{id: int, name: string}> */
    private function margaOptions(): array
    {
        return Marga::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Marga $marga) => ['id' => $marga->id, 'name' => $marga->name])
            ->all();
    }

    /** @return array<int, array{id: int, name: string}> */
    private function managedMargaOptions(): array
    {
        return Marga::query()
            ->whereNotNull('identity_person_id')
            ->whereHas('people')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Marga $marga) => ['id' => $marga->id, 'name' => $marga->name])
            ->all();
    }
}
