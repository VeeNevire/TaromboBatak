<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewQrisPaymentRequest;
use App\Http\Requests\StoreQrisPaymentRequest;
use App\Http\Requests\UpdateQrisConfigurationRequest;
use App\Models\QrisConfiguration;
use App\Models\QrisPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class QrisPaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $canManage = $user->isAdmin();
        $configuration = QrisConfiguration::query()->firstOrCreate(['id' => 1]);
        $status = $request->string('status')->toString();
        $status = in_array($status, ['pending', 'approved', 'rejected'], true) ? $status : 'all';
        $query = QrisPayment::query()->with(['user:id,name,email', 'reviewer:id,name'])
            ->when(! $canManage, fn ($payments) => $payments->where('user_id', $user->id))
            ->when($canManage && $status !== 'all', fn ($payments) => $payments->where('status', $status))
            ->latest('id');

        return Inertia::render('qris/index', [
            'canManage' => $canManage,
            'configuration' => [
                'image_url' => $configuration->image_path
                    ? route('qris.image', ['v' => $configuration->updated_at?->timestamp])
                    : null,
                'instructions' => $configuration->instructions,
            ],
            'payments' => $query->paginate(15)->withQueryString()->through(fn (QrisPayment $payment) => [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'reference' => $payment->reference,
                'note' => $payment->note,
                'status' => $payment->status,
                'review_note' => $payment->review_note,
                'reviewed_by' => $payment->reviewer?->name,
                'reviewed_at' => $payment->reviewed_at?->format('d M Y H:i'),
                'created_at' => $payment->created_at?->format('d M Y H:i'),
                'user' => $canManage ? $payment->user?->only(['id', 'name', 'email']) : null,
                'proof_url' => route('qris.proof', $payment),
            ]),
            'statusFilter' => $status,
            'summary' => $canManage ? [
                'pending' => QrisPayment::query()->where('status', QrisPayment::STATUS_PENDING)->count(),
                'approved_total' => (int) QrisPayment::query()->where('status', QrisPayment::STATUS_APPROVED)->sum('amount'),
                'approved_count' => QrisPayment::query()->where('status', QrisPayment::STATUS_APPROVED)->count(),
            ] : null,
        ]);
    }

    public function image(): HttpResponse
    {
        $configuration = QrisConfiguration::query()->findOrFail(1);
        abort_unless($configuration->image_path && Storage::disk('local')->exists($configuration->image_path), 404);

        return Storage::disk('local')->response($configuration->image_path, 'qris-payment', [
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
        ]);
    }

    public function store(StoreQrisPaymentRequest $request): RedirectResponse
    {
        $configuration = QrisConfiguration::query()->find(1);
        if (! $configuration?->image_path || ! Storage::disk('local')->exists($configuration->image_path)) {
            throw ValidationException::withMessages(['proof' => 'QRIS belum tersedia. Silakan coba kembali nanti.']);
        }

        $proof = $request->file('proof');
        $path = $proof->store('qris/payment-proofs/'.$request->user()->id, 'local');
        QrisPayment::query()->create([
            'user_id' => $request->user()->id,
            'amount' => $request->validated('amount'),
            'reference' => $request->validated('reference'),
            'note' => $request->validated('note'),
            'proof_path' => $path,
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Bukti pembayaran terkirim dan menunggu verifikasi admin.']);
    }

    public function proof(Request $request, QrisPayment $payment): HttpResponse
    {
        abort_unless($request->user()->isAdmin() || $payment->user_id === $request->user()->id, 403);
        abort_unless(Storage::disk('local')->exists($payment->proof_path), 404);

        return Storage::disk('local')->response($payment->proof_path, 'bukti-qris-'.$payment->id, [
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function updateConfiguration(UpdateQrisConfigurationRequest $request): RedirectResponse
    {
        $configuration = QrisConfiguration::query()->firstOrCreate(['id' => 1]);
        $oldImagePath = $configuration->image_path;
        $newImagePath = $request->file('image')?->store('qris/configuration', 'local');

        if ($request->hasFile('image') && ! is_string($newImagePath)) {
            throw ValidationException::withMessages(['image' => 'Gambar QRIS gagal disimpan. Coba unggah kembali.']);
        }

        $configuration->update([
            'image_path' => $newImagePath ?: $oldImagePath,
            'instructions' => $request->validated('instructions'),
            'updated_by' => $request->user()->id,
        ]);

        if ($newImagePath && $oldImagePath) {
            Storage::disk('local')->delete($oldImagePath);
        }

        return back()->with('toast', ['type' => 'success', 'message' => 'Pengaturan QRIS berhasil disimpan.']);
    }

    public function review(ReviewQrisPaymentRequest $request, QrisPayment $payment): RedirectResponse
    {
        DB::transaction(function () use ($request, $payment): void {
            $lockedPayment = QrisPayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($lockedPayment->status !== QrisPayment::STATUS_PENDING) {
                throw ValidationException::withMessages(['status' => 'Pembayaran ini sudah pernah ditinjau.']);
            }

            $lockedPayment->update([
                'status' => $request->validated('status'),
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_note' => $request->validated('review_note'),
            ]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Status pembayaran berhasil diperbarui.']);
    }
}
