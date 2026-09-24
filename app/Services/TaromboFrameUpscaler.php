<?php

namespace App\Services;

use App\Models\TaromboFrame;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class TaromboFrameUpscaler
{
    private const PROMPT = 'Tingkatkan resolusi dan ketajaman gambar frame dekoratif ini menjadi definisi tinggi. Pertahankan desain, ornamen, warna, proporsi, dan komposisi persis seperti aslinya tanpa perubahan apa pun. Jangan menambah, menghapus, atau menggeser elemen apa pun, jangan menambahkan watermark atau teks baru. Hasilkan versi yang identik namun lebih tajam dan detail.';

    /** @return array{path: string, width: int, height: int} */
    public function upscale(TaromboFrame $frame): array
    {
        $apiKey = config('services.openai.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('Generator AI belum dikonfigurasi. Tambahkan OPENAI_API_KEY pada konfigurasi aplikasi.');
        }

        $framePath = Storage::disk('local')->path($frame->path);
        $frameHandle = @fopen($framePath, 'rb');

        if ($frameHandle === false) {
            throw new RuntimeException('Gambar frame tidak dapat dibuka.');
        }

        try {
            $response = $this->openAiRequest($apiKey)
                ->attach('image[]', $frameHandle, 'frame-tarombo.jpg')
                ->post('/images/edits', [
                    'model' => config('services.openai.image_model', 'gpt-image-1.5'),
                    'prompt' => self::PROMPT,
                    'quality' => 'high',
                    'input_fidelity' => 'high',
                    'output_format' => 'jpeg',
                    'output_compression' => 92,
                    'size' => $this->targetSize($frame),
                    'n' => 1,
                ]);
        } finally {
            fclose($frameHandle);
        }

        if ($response->failed()) {
            throw new RuntimeException('Generator AI sedang tidak dapat meningkatkan resolusi frame. Silakan coba lagi beberapa saat lagi.');
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

        $path = 'tarombo-frames/upscaled-'.now()->format('YmdHis').'-'.str()->uuid().'.jpg';

        if (! Storage::disk('local')->put($path, $image)) {
            throw new RuntimeException('Gambar hasil AI gagal disimpan.');
        }

        return [
            'path' => $path,
            'width' => $imageDetails[0],
            'height' => $imageDetails[1],
        ];
    }

    private function targetSize(TaromboFrame $frame): string
    {
        if ($frame->canvas_width === $frame->canvas_height) {
            return '1024x1024';
        }

        return $frame->canvas_width > $frame->canvas_height ? '1536x1024' : '1024x1536';
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
