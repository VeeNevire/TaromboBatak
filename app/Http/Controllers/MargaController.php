<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMargaRequest;
use App\Http\Requests\UpdateMargaRequest;
use App\Models\Marga;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class MargaController extends Controller
{
    /**
     * List all marga with their member count.
     */
    public function index(): Response
    {
        $margas = Marga::query()
            ->withCount('people')
            ->orderBy('people_count', 'desc')
            ->orderBy('name')
            ->get()
            ->map(fn (Marga $marga) => [
                'id' => $marga->id,
                'name' => $marga->name,
                'description' => $marga->description,
                'color' => $marga->color,
                'image' => $marga->image,
                'image_url' => $this->imageUrl($marga->image),
                'people_count' => $marga->people_count,
            ]);

        return Inertia::render('marga/index', [
            'margas' => $margas,
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
                'totalPeople' => Person::count(),
                'totalGenerations' => $this->maxGenerationDepth(),
            ],
        ]);
    }

    /**
     * Compute the maximum generation depth starting from root ancestors.
     */
    protected function maxGenerationDepth(): int
    {
        $parents = Person::query()
            ->select('id', 'father_id')
            ->get()
            ->mapWithKeys(fn (Person $person) => [$person->id => $person->father_id]);

        $depth = 0;

        foreach ($parents as $id => $_) {
            $count = 0;
            $current = $id;

            while (isset($parents[$current]) && $count < 1000) {
                $current = $parents[$current];
                $count++;
            }

            $depth = max($depth, $count);
        }

        return $depth + 1;
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

        $data['image'] = $this->resolveImage($request);

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
     * @return string|null  Stored path (margas/...) or the raw URL.
     */
    protected function resolveImage(Request $request): ?string
    {
        $image = $request->file('image');

        if ($image instanceof UploadedFile) {
            return $image->store('margas', 'public');
        }

        $raw = $request->input('image');

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $trimmed = trim($raw);

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
