<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubAdminRequest;
use App\Http\Requests\UpdateSubAdminRequest;
use App\Models\Marga;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubAdminController extends Controller
{
    /**
     * List all sub-admin accounts.
     */
    public function index(): Response
    {
        $subAdmins = User::query()
            ->where('role', 'subadmin')
            ->with('marga')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'marga' => $user->marga?->name,
                'marga_id' => $user->marga_id,
                'created_at' => $user->created_at?->format('d M Y'),
            ]);

        return Inertia::render('sub-admins/index', [
            'subAdmins' => $subAdmins,
        ]);
    }

    /**
     * Show the create sub-admin form.
     */
    public function create(): Response
    {
        return Inertia::render('sub-admins/form', [
            'subAdmin' => null,
            'margas' => $this->margaOptions(),
        ]);
    }

    /**
     * Store a newly created sub-admin.
     */
    public function store(StoreSubAdminRequest $request): RedirectResponse
    {
        User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'marga_id' => $request->validated('marga_id'),
            'role' => 'subadmin',
            'email_verified_at' => now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sub admin berhasil ditambahkan.')]);

        return to_route('sub-admins.index');
    }

    /**
     * Show the edit sub-admin form.
     */
    public function edit(User $subAdmin): Response
    {
        abort_unless($subAdmin->role === 'subadmin', 404);

        return Inertia::render('sub-admins/form', [
            'subAdmin' => [
                'id' => $subAdmin->id,
                'name' => $subAdmin->name,
                'email' => $subAdmin->email,
                'marga_id' => $subAdmin->marga_id,
            ],
            'margas' => $this->margaOptions(),
        ]);
    }

    /**
     * Update the specified sub-admin.
     */
    public function update(UpdateSubAdminRequest $request, User $subAdmin): RedirectResponse
    {
        abort_unless($subAdmin->role === 'subadmin', 404);

        $subAdmin->fill($request->safe()->except('password'));

        if ($request->filled('password')) {
            $subAdmin->password = $request->validated('password');
        }

        $subAdmin->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sub admin berhasil diperbarui.')]);

        return to_route('sub-admins.index');
    }

    /**
     * Remove the specified sub-admin.
     */
    public function destroy(Request $request, User $subAdmin): RedirectResponse
    {
        abort_unless($subAdmin->role === 'subadmin', 404);

        if ($subAdmin->id === $request->user()?->id) {
            abort(403, 'Anda tidak dapat menghapus akun sendiri.');
        }

        $subAdmin->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sub admin berhasil dihapus.')]);

        return to_route('sub-admins.index');
    }

    /**
     * Marga options for the form select.
     *
     * @return array<int, array{id: int, name: string}>
     */
    protected function margaOptions(): array
    {
        return Marga::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Marga $marga) => [
                'id' => $marga->id,
                'name' => $marga->name,
            ])
            ->all();
    }
}
