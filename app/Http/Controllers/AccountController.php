<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\Marga;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        $accounts = User::query()
            ->with('marga:id,name')
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

    public function create(): Response
    {
        return Inertia::render('accounts/form', [
            'account' => null,
            'margas' => $this->margaOptions(),
        ]);
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        User::create([...$request->validated(), 'email_verified_at' => now()]);
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
        ]);
    }

    public function update(UpdateAccountRequest $request, User $account): RedirectResponse
    {
        $account->fill($request->safe()->except('password'));
        if ($request->filled('password')) {
            $account->password = $request->validated('password');
        }
        $account->save();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Akun berhasil diperbarui.']);

        return to_route('accounts.index');
    }

    public function destroy(Request $request, User $account): RedirectResponse
    {
        abort_if($account->id === $request->user()?->id, 403, 'Anda tidak dapat menghapus akun sendiri.');
        $account->delete();
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
}
