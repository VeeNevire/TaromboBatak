<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMargaRequest;
use App\Http\Requests\UpdateMargaRequest;
use App\Models\Event;
use App\Models\FeedPost;
use App\Models\Marga;
use App\Models\Person;
use App\Models\Story;
use App\Services\MargaIdentityPersonService;
use App\Services\TaromboStatisticsService;
use App\Services\TaromboTreeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class MargaController extends Controller
{
    /**
     * List marga with their member count. Staff see every marga and management
     * controls; everyone else (including guests) sees only public marga.
     */
    public function index(Request $request): Response
    {
        $canManage = $request->user()?->isStaff() ?? false;

        $margas = Marga::query()
            ->when(! $canManage, fn ($query) => $query->where('is_public', true))
            ->with('identityPerson:id,name')
            ->withCount('people')
            ->orderBy('name')
            ->get()
            ->map(fn (Marga $marga) => [
                'id' => $marga->id,
                'name' => $marga->name,
                'description' => $marga->description,
                'color' => $marga->color,
                'image' => $marga->image,
                'image_url' => $this->imageUrl($marga->image),
                'identity_person_id' => $marga->identity_person_id,
                'identity_person_name' => $marga->identityPerson?->name,
                'people_count' => $marga->people_count,
                'is_public' => $marga->is_public,
            ]);

        return Inertia::render('marga/index', [
            'margas' => $margas,
            'canManage' => $canManage,
            'identityPersonOptions' => $canManage
                ? app(MargaIdentityPersonService::class)->options()->all()
                : [],
        ]);
    }

    /**
     * Show a public marga's upper or lower silsilah tree. Open to guests.
     */
    public function tree(Marga $marga, string $direction): Response
    {
        abort_unless($marga->is_public, 404);

        $service = app(TaromboTreeService::class);
        $rows = $service->rowsForMarga($marga, $direction);

        return Inertia::render('tarombo/fullscreen', [
            'people' => $rows,
            'margas' => $service->margas(),
            'alternativeTrees' => [],
            'view' => 'tree',
            'identity' => [
                'canSelectAnyPerson' => false,
                'currentPersonId' => null,
                'currentPersonName' => null,
                'request' => null,
            ],
            'initialPersonId' => null,
            'familyTreeOptions' => [],
            'selectedFamilyTreeId' => null,
            'selectedMargaId' => $marga->id,
            'selectedTreePeople' => $rows,
            'margaTree' => [
                'margaName' => $marga->name,
                'identityPersonId' => $marga->identity_person_id !== null
                    ? (string) $marga->identity_person_id
                    : null,
                'direction' => $direction,
            ],
        ]);
    }

    public function relatedContent(Request $request, Marga $marga): JsonResponse
    {
        $validated = $request->validate([
            'tab' => ['required', 'in:stories,events,statuses'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $tab = $validated['tab'];
        $query = match ($tab) {
            'stories' => Story::query()->publiclyVisible()->whereHas('relatedMargas', fn ($query) => $query->whereKey($marga->id)),
            'events' => Event::query()->publiclyVisible()->where(function ($query) use ($marga) {
                $query->whereHas('relatedMargas', fn ($query) => $query->whereKey($marga->id))
                    ->orWhere(fn ($query) => $query->whereDoesntHave('relatedMargas')->where('marga_id', $marga->id));
            }),
            'statuses' => FeedPost::query()->visibleTo($request->user())->where('audience', 'marga')
                ->whereHas('audienceMargas', fn ($query) => $query->whereKey($marga->id)),
        };

        $items = $query->with($tab === 'statuses' ? 'author:id,name' : 'creator:id,name')
            ->latest()->orderByDesc('id')->paginate(10)
            ->through(fn ($item) => [
                'id' => $item->id,
                'title' => $tab === 'statuses' ? null : $item->title,
                'body' => $tab === 'statuses' ? $item->body : $item->description,
                'author' => $tab === 'statuses' ? $item->author->name : ($item->creator?->name ?? 'Tim Tarombo Batak'),
                'date' => $tab === 'events' ? $item->date->format('d M Y') : $item->created_at?->format('d M Y H:i'),
                'location' => $tab === 'events' ? $item->location : null,
            ]);

        return response()->json([
            'items' => $items->items(),
            'current_page' => $items->currentPage(),
            'last_page' => $items->lastPage(),
            'total' => $items->total(),
        ]);
    }

    /**
     * Show the public marga page.
     */
    public function public(): Response
    {
        $margas = Marga::query()
            ->withCount('people')
            ->orderByDesc('people_count')
            ->orderBy('name')
            ->get()
            ->map(fn (Marga $marga) => [
                'name' => $marga->name,
                'color' => $marga->color,
                'image_url' => $this->imageUrl($marga->image),
                'count' => $marga->people_count,
            ]);

        return Inertia::render('marga/public', [
            'margas' => $margas,
            'stats' => [
                'totalMargas' => Marga::count(),
                'totalPeople' => Person::query()->public()->count(),
                'totalGenerations' => app(TaromboStatisticsService::class)
                    ->maxGenerationDepth(Person::query()->public()),
            ],
        ]);
    }

    /**
     * Store a newly created marga.
     */
    public function store(StoreMargaRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $data['image'] = $this->resolveImage($request);

        Marga::create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Marga berhasil ditambahkan.')]);

        return to_route('marga.index');
    }

    /**
     * Update the specified marga.
     */
    public function update(UpdateMargaRequest $request, Marga $marga): RedirectResponse
    {
        $data = $request->validated();

        $data['image'] = $this->resolveImage($request, $marga->image);

        if ($marga->image !== null && $data['image'] !== $marga->image) {
            $this->deleteStoredImage($marga->image);
        }

        $marga->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Marga berhasil diperbarui.')]);

        return to_route('marga.index');
    }

    /**
     * Remove the specified marga.
     */
    public function destroy(Marga $marga): RedirectResponse
    {
        $this->deleteStoredImage($marga->image);

        $marga->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Marga berhasil dihapus.')]);

        return to_route('marga.index');
    }

    /**
     * Persist an uploaded image file or keep a plain URL string.
     *
     * @return string|null Stored path (margas/...) or the raw URL.
     */
    protected function resolveImage(Request $request, ?string $existingImage = null): ?string
    {
        $image = $request->file('image');

        if ($image instanceof UploadedFile) {
            $path = $image->store('margas', 'public');

            return $path !== false ? $path : null;
        }

        $raw = $request->input('image');

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $trimmed = trim($raw);

        if ($existingImage !== null && $trimmed === $this->imageUrl($existingImage)) {
            return $existingImage;
        }

        if (str_starts_with($trimmed, '/storage/')) {
            return $trimmed;
        }

        return $trimmed;
    }

    /**
     * Build a publicly reachable URL for the marga image.
     */
    protected function imageUrl(?string $image): ?string
    {
        if ($image === null) {
            return null;
        }

        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }

        return Storage::disk('public')->url($image);
    }

    /**
     * Remove a stored marga image file from the public disk when it was not a
     * plain external URL.
     */
    protected function deleteStoredImage(?string $image): void
    {
        if ($image === null || str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return;
        }

        $path = str_starts_with($image, '/storage/')
            ? ltrim(substr($image, strlen('/storage/')), '/')
            : $image;

        Storage::disk('public')->delete($path);
    }
}
