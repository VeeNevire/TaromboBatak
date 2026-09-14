<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaromboAiPrompt extends Model
{
    public const FRAME_COMPOSITION_KEY = 'frame_composition';

    public const DEFAULT_FRAME_COMPOSITION = 'Gabungkan dua gambar referensi menjadi satu JPG final. Gambar pertama adalah pohon silsilah Tarombo yang harus dipertahankan lengkap, tajam, dan terbaca. Gambar kedua adalah template frame yang harus dipertahankan utuh, termasuk ornamen, bingkai, warna, dan proporsinya. Analisis area konten pada template frame, tempatkan pohon Tarombo secara proporsional dan rapi di area yang tepat tanpa menutupi ornamen atau teks frame. Jangan menambah watermark, jangan mengubah isi pohon, dan jangan menambahkan teks baru.';

    protected $fillable = [
        'key',
        'prompt',
    ];
}
