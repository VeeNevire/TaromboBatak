<?php

namespace App\Services;

use App\Models\TaromboFrame;
use App\Models\TaromboSnapshot;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class TaromboFrameComposer
{
    public function compose(TaromboSnapshot $snapshot, TaromboFrame $frame): string
    {
        $apiKey = config('services.openai.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('Generator AI belum dikonfigurasi. Tambahkan OPENAI_API_KEY pada konfigurasi aplikasi.');
        }

        $framePath = Storage::disk('local')->path($frame->path);
        $snapshotPath = Storage::disk('local')->path($snapshot->path);
        $snapshotHandle = @fopen($snapshotPath, 'rb');
        $frameHandle = @fopen($framePath, 'rb');

        if ($snapshotHandle === false || $frameHandle === false) {
            if (is_resource($snapshotHandle)) {
                fclose($snapshotHandle);
            }

            if (is_resource($frameHandle)) {
                fclose($frameHandle);
            }

            throw new RuntimeException('Gambar sumber atau frame tidak dapat dibuka.');
        }

        try {
            $response = $this->openAiRequest($apiKey)
                ->attach('image[]', $snapshotHandle, 'tarombo-sumber.jpg')
                ->attach('image[]', $frameHandle, 'frame-tarombo.jpg')
                ->post('/images/edits', [
                    'model' => config('services.openai.image_model', 'gpt-image-1.5'),
                    'prompt' => 'Gabungkan dua gambar referensi menjadi satu JPG final. Gambar pertama adalah pohon silsilah Tarombo yang harus dipertahankan lengkap, tajam, dan terbaca. Gambar kedua adalah template frame yang harus dipertahankan utuh, termasuk ornamen, bingkai, warna, dan proporsinya. Analisis area konten pada template frame, tempatkan pohon Tarombo secara proporsional dan rapi di area yang tepat tanpa menutupi ornamen atau teks frame. Jangan menambah watermark, jangan mengubah isi pohon, dan jangan menambahkan teks baru.',
                    'quality' => 'high',
                    'input_fidelity' => 'high',
                    'output_format' => 'jpeg',
                    'output_compression' => 92,
                    'size' => 'auto',
                    'n' => 1,
                ]);
        } finally {
            fclose($snapshotHandle);
            fclose($frameHandle);
        }

        if ($response->failed()) {
            throw new RuntimeException('Generator AI sedang tidak dapat membuat gambar. Silakan coba lagi beberapa saat lagi.');
        }

        $encodedImage = $response->json('data.0.b64_json');
        $image = is_string($encodedImage) ? base64_decode($encodedImage, true) : false;

        if ($image === false || $image === '') {
            throw new RuntimeException('Generator AI tidak mengembalikan gambar yang valid.');
        }

        $imageDetails = @getimagesizefromstring($image);

        if ($imageDetails === false || ($imageDetails['mime'] ?? null) !== 'image/jpeg') {
            throw new RuntimeException('Generator AI tidak mengembalikan JPG yang valid.');
        }

        $path = 'tarombo-snapshots/'.$snapshot->user_id.'/ai-frame-'.now()->format('YmdHis').'-'.str()->uuid().'.jpg';

        if (! Storage::disk('local')->put($path, $image)) {
            throw new RuntimeException('Gambar hasil AI gagal disimpan.');
        }

        return $path;
    }

    private function openAiRequest(string $apiKey): PendingRequest
    {
        return Http::baseUrl('https://api.openai.com/v1')
            ->withToken($apiKey)
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(180)
            ->retry([300, 1_000], throw: false, when: function (Throwable $exception): bool {
                return $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->serverError());
            });
    }
}
