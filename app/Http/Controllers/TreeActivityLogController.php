<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewContributionRequest;
use App\Models\Person;
use App\Models\TreeActivityLog;
use App\Models\TreeChangeRequest;
use App\Models\User;
use App\Services\ChainNumberingService;
use App\Services\FamilyEntryService;
use App\Services\FamilyTreeStructureService;
use App\Services\TreeActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TreeActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $canReviewContributions = $user->canReviewContributions();

        $logs = TreeActivityLog::query()
            ->with(['person:id,name', 'actor:id,name', 'marga:id,name', 'familyTree:id,name'])
            ->where(function (Builder $query) use ($user, $canReviewContributions) {
                $query->when($canReviewContributions, function (Builder $reviewable) use ($user) {
                    $reviewable->when(! $user->isAdmin(), fn (Builder $scope) => $scope
                        ->whereIn('marga_id', $user->accessibleMargaIds()));
                })->orWhere(function (Builder $shared) use ($user) {
                    $shared->where('protection_scope', TreeChangeRequest::SCOPE_SHARED_TREE_OWNER)
                        ->whereHas('familyTree', fn ($trees) => $trees->whereBelongsTo($user));
                });
            })
            ->latest()
            ->paginate(20, ['*'], 'log_page')
            ->withQueryString()
            ->through(fn (TreeActivityLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'summary' => $log->summary,
                'person' => $log->person?->name,
                'tree' => $log->familyTree?->name,
                'actor' => $log->actor?->name,
                'marga' => $log->marga?->name,
                'scope' => $log->protection_scope,
                'details' => $log->details,
                'changed_fields' => $this->changedFields($log),
                'date' => $log->created_at?->translatedFormat('d M Y'),
                'time' => $log->created_at?->format('H:i'),
            ]);

        $changeRequests = TreeChangeRequest::query()
            ->with(['person:id,name', 'requester:id,name', 'familyTree:id,name'])
            ->where('status', TreeChangeRequest::STATUS_PENDING)
            ->where(fn (Builder $query) => $query
                ->where(function (Builder $contributor) use ($user) {
                    $contributor->where('protection_scope', TreeChangeRequest::SCOPE_CONTRIBUTOR)
                        ->when(! $user->isAdmin(), fn (Builder $scope) => $scope
                            ->whereIn('marga_id', $user->accessibleMargaIds()));
                })
                ->orWhere(function (Builder $owner) use ($user) {
                    $owner->where('protection_scope', TreeChangeRequest::SCOPE_SHARED_TREE_OWNER)
                        ->where('reviewer_id', $user->id);
                }))
            ->latest()
            ->get()
            ->map(fn (TreeChangeRequest $change) => [
                'id' => $change->id,
                'action' => $change->action,
                'scope' => $change->protection_scope,
                'person' => $change->person?->name ?? 'Anggota telah dihapus',
                'requester' => $change->requester->name,
                'tree' => $change->familyTree?->name,
                'created_at' => $change->created_at?->format('d M Y H:i'),
            ]);

        return Inertia::render('tree-activity-logs/index', [
            'logs' => $logs,
            'changeRequests' => $changeRequests,
        ]);
    }

    public function approve(Request $request, TreeChangeRequest $treeChangeRequest): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($treeChangeRequest, $user) {
            $change = TreeChangeRequest::query()
                ->with(['person', 'familyTree', 'requester'])
                ->lockForUpdate()
                ->findOrFail($treeChangeRequest->id);
            $this->authorizeReview($user, $change);
            abort_unless($change->status === TreeChangeRequest::STATUS_PENDING, 409, 'Pengajuan sudah ditinjau.');

            $person = $change->person;
            $payload = $change->payload ?? [];

            if ($change->action === TreeChangeRequest::ACTION_UPDATE) {
                $data = $payload['data'] ?? null;
                abort_unless(is_array($data), 422, 'Data perubahan tidak lengkap.');

                if ($change->familyTree !== null && isset($payload['version_tree'])) {
                    app(FamilyTreeStructureService::class)->updateFromFamilyForm($change->familyTree, $person, $data, $change->requester_id);
                } else {
                    app(FamilyEntryService::class)->save(
                        $data,
                        forcedMargaId: $payload['forced_marga_id'] ?? null,
                        createdBy: $change->requester_id,
                    );
                }

                app(TreeActivityLogger::class)->record(
                    $person->fresh(),
                    $change->requester,
                    'edited',
                    "Perubahan {$person->name} disetujui.",
                    ['request_id' => $change->id, 'before' => $payload['before'] ?? []],
                    $change->familyTree,
                );
            } else {
                $childrenExist = Person::query()
                    ->where('father_id', $person->id)
                    ->orWhere('mother_id', $person->id)
                    ->exists();
                abort_if($childrenExist, 422, 'Anggota masih memiliki keturunan dan belum dapat dihapus.');

                app(TreeActivityLogger::class)->record(
                    $person,
                    $change->requester,
                    'removed',
                    "Penghapusan {$person->name} disetujui.",
                    ['request_id' => $change->id],
                    $change->familyTree,
                );
                $father = $person->father;
                $person->delete();
                if ($father !== null) {
                    app(ChainNumberingService::class)->recomputeFromAncestor($father);
                }
            }

            $change->update([
                'status' => TreeChangeRequest::STATUS_APPROVED,
                'reviewer_id' => $user->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Perubahan pohon disetujui dan diterapkan.']);

        return back();
    }

    public function reject(ReviewContributionRequest $request, TreeChangeRequest $treeChangeRequest): RedirectResponse
    {
        DB::transaction(function () use ($request, $treeChangeRequest): void {
            $change = TreeChangeRequest::query()->lockForUpdate()->findOrFail($treeChangeRequest->id);
            $this->authorizeReview($request->user(), $change);
            abort_unless($change->status === TreeChangeRequest::STATUS_PENDING, 409, 'Pengajuan sudah ditinjau.');

            $change->update([
                'status' => TreeChangeRequest::STATUS_REJECTED,
                'reviewer_id' => $request->user()->id,
                'reviewed_at' => now(),
                'rejection_reason' => $request->validated('reason'),
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pengajuan perubahan ditolak.']);

        return back();
    }

    private function authorizeReview(User $user, TreeChangeRequest $change): void
    {
        $allowed = $user->isAdmin()
            || ($change->protection_scope === TreeChangeRequest::SCOPE_CONTRIBUTOR
                && $user->isContributor()
                && $change->marga_id !== null
                && $user->accessibleMargaIds()->contains($change->marga_id))
            || ($change->protection_scope === TreeChangeRequest::SCOPE_SHARED_TREE_OWNER
                && $change->reviewer_id === $user->id);

        abort_unless($allowed, 403, 'Anda tidak berhak meninjau perubahan ini.');
    }

    /** @return array<int, string> */
    private function changedFields(TreeActivityLog $log): array
    {
        $before = $log->details['before'] ?? null;

        if (! is_array($before) || $log->person === null) {
            return [];
        }

        $labels = [
            'name' => 'Nama',
            'alias' => 'Nama panggilan',
            'gender' => 'Jenis kelamin',
            'marga_id' => 'Marga',
            'province_code' => 'Provinsi',
            'regency_code' => 'Kabupaten/kota',
            'district_code' => 'Kecamatan',
            'village_code' => 'Desa/kelurahan',
            'father_id' => 'Ayah',
            'mother_id' => 'Ibu',
            'birth_order' => 'Urutan lahir',
            'birth_year' => 'Tahun lahir',
            'death_year' => 'Tahun meninggal',
            'spouse' => 'Pasangan',
            'spouse_marga' => 'Marga pasangan',
            'image' => 'Foto',
            'bio' => 'Biografi',
            'related_stories' => 'Cerita terkait',
            'is_public' => 'Status publik',
        ];
        $after = $log->person->only(array_keys($labels));

        return collect($labels)
            ->filter(fn (string $label, string $field): bool => array_key_exists($field, $before)
                && array_key_exists($field, $after)
                && $before[$field] !== $after[$field])
            ->values()
            ->all();
    }
}
