<?php

namespace App\Services;

use App\Models\TaromboSnapshot;
use App\Models\User;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use GdImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class TaromboQrComposer
{
    public function moduleCount(string $url): int
    {
        return Encoder::encode($url, ErrorCorrectionLevel::M())->getMatrix()->getWidth() + 8;
    }

    public function qrImage(string $url, int $requestedSize = 400): GdImage
    {
        $matrix = Encoder::encode($url, ErrorCorrectionLevel::M())->getMatrix();
        $modules = $matrix->getWidth() + 8;
        $scale = max(2, (int) floor($requestedSize / $modules));
        $image = imagecreatetruecolor($modules * $scale, $modules * $scale);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefill($image, 0, 0, $white);

        for ($y = 0; $y < $matrix->getHeight(); $y++) {
            for ($x = 0; $x < $matrix->getWidth(); $x++) {
                if ($matrix->get($x, $y) === 1) {
                    imagefilledrectangle($image, ($x + 4) * $scale, ($y + 4) * $scale, ($x + 5) * $scale - 1, ($y + 5) * $scale - 1, $black);
                }
            }
        }

        return $image;
    }

    public function png(GdImage $image): string
    {
        ob_start();
        try {
            imagepng($image);

            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    /** @param array{token: string, x: int|float, y: int|float, size: int|float} $data */
    public function attach(TaromboSnapshot $snapshot, User $recipient, array $data): TaromboSnapshot
    {
        $disk = Storage::disk('local');
        $preview = $snapshot->path;
        if (! $disk->exists($preview) || $snapshot->tarombo_frame_id === null) {
            throw ValidationException::withMessages(['image' => 'Gambar Hasil Compile tidak tersedia.']);
        }

        $source = imagecreatefromstring($disk->get($preview));
        if ($source === false) {
            throw ValidationException::withMessages(['image' => 'Gambar Hasil Compile tidak dapat dibaca.']);
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $url = route('tarombo.qr.show', ['token' => $data['token']]);
        $qr = $this->qrImage($url, (int) round(min($width, $height) * $data['size'] / 100));
        $size = imagesx($qr);
        if ($size > min($width, $height)) {
            imagedestroy($source);
            imagedestroy($qr);
            throw ValidationException::withMessages(['image' => 'Resolusi gambar terlalu kecil untuk QR yang dapat dipindai. Simpan gambar dengan resolusi lebih tinggi.']);
        }
        $output = imagecreatetruecolor($width, $height);
        imagefill($output, 0, 0, imagecolorallocate($output, 255, 255, 255));
        imagecopy($output, $source, 0, 0, 0, 0, $width, $height);
        $x = (int) round(($width - $size) * $data['x'] / 100);
        $y = (int) round(($height - $size) * $data['y'] / 100);
        imagecopy($output, $qr, $x, $y, 0, 0, $size, $size);
        $bytes = $this->png($output);
        imagedestroy($source);
        imagedestroy($qr);
        imagedestroy($output);
        $path = 'tarombo-snapshots/'.$recipient->id.'/'.Str::uuid().'.png';

        try {
            return DB::transaction(function () use ($snapshot, $recipient, $data, $disk, $path, $bytes) {
                if (! $disk->put($path, $bytes)) {
                    throw new \RuntimeException('Gambar QR gagal disimpan.');
                }

                return $recipient->taromboSnapshots()->create([
                    'title' => Str::limit(($snapshot->title ?? $snapshot->centerPerson?->name ?? 'Pohon Tarombo').' · QR', 120, ''),
                    'view' => $snapshot->view,
                    'center_person_id' => $snapshot->center_person_id,
                    'tarombo_frame_id' => $snapshot->tarombo_frame_id,
                    'path' => $path,
                    'qr_token' => $data['token'],
                ]);
            });
        } catch (Throwable $exception) {
            $disk->delete($path);
            throw $exception;
        }
    }
}
