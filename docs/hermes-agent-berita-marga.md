# Otomatisasi Berita Marga dengan Hermes API

Laravel bertindak sebagai orchestrator: aplikasi memanggil Hermes API, Hermes mencari berita, lalu Laravel menyimpan berita baru sebagai **Menunggu** untuk direview admin. Proses pencarian tidak menggunakan endpoint berita Laravel.

## Koneksi lokal melalui SSH tunnel

Biarkan tunnel ini tetap berjalan di terminal:

```bash
ssh -N -L 18642:127.0.0.1:8642 root@76.13.22.54
```

Isi `.env` proyek lokal:

```dotenv
HERMES_BASE_URL=http://127.0.0.1:18642/v1
# Opsional; API_SERVER_KEY digunakan jika HERMES_TOKEN kosong.
HERMES_TOKEN=
HERMES_RUNS_ENDPOINT=/runs
HERMES_TIMEOUT=90
HERMES_RUN_TIMEOUT=300
HERMES_POLL_SECONDS=2
```

`HERMES_TOKEN` opsional dan diprioritaskan jika diisi; jika kosong, aplikasi memakai `API_SERVER_KEY`. Jika keduanya kosong, request dikirim tanpa Bearer token, sesuai konfigurasi Hermes API yang menerima akses tanpa autentikasi. Jika API Hermes mewajibkan Bearer token, isi salah satu variabel dengan nilai yang sama seperti `API_SERVER_KEY` pada service Hermes. Jangan commit nilai token.

## Pengaturan admin

Buka **Administrasi → Otomatisasi Berita Marga** untuk mengisi prompt Hermes, mengaktifkan atau menonaktifkan pengambilan berita, dan mengatur jeda 1–10.080 menit. Prompt wajib diisi sebelum otomatisasi diaktifkan. Prompt admin dikirim apa adanya sebagai `input.instructions`; placeholder contoh di halaman hanya panduan dan tidak digunakan otomatis. Jeda dihitung setelah satu siklus selesai. Halaman ini juga menampilkan waktu run, berita baru yang diterima, duplikat, dan error terakhir.

Topik dan kata kunci tetap diatur melalui menu **Topik Berita Marga**. Website sumber diatur admin melalui menu **Sumber Website Berita**. Admin memasukkan URL website langsung, tanpa RSS. Sumber dapat dipakai untuk semua topik atau dibatasi ke beberapa topik. Hermes hanya boleh membaca website pada daftar sumber untuk topik yang sedang dikerjakan. Semua berita yang dikembalikan Hermes masuk ke status Menunggu.

## Cara kerja API Hermes

Setiap siklus untuk topik aktif mengirim `POST {HERMES_BASE_URL}{HERMES_RUNS_ENDPOINT}`. `input` berupa pesan user berbentuk JSON string dengan konteks topik dan sumber website yang diizinkan, sedangkan prompt admin dikirim terpisah sebagai `instructions`:

```json
{
    "input": "{\"keyword\":\"kata kunci topik\",\"marga\":null,\"notes\":null,\"sources\":[{\"id\":1,\"name\":\"Harian SIB\",\"url\":\"https://www.hariansib.com\",\"domain\":\"hariansib.com\"}],\"margas\":[\"daftar nama marga\"],\"known_urls\":[]}",
    "instructions": "prompt admin ditambah aturan wajib: baca website langsung, tanpa RSS, dan jangan gunakan domain lain"
}
```

API Hermes mengembalikan `run_id`; Laravel melakukan polling `GET {HERMES_BASE_URL}{HERMES_RUNS_ENDPOINT}/{run_id}` sampai status `completed`. Output teks Hermes harus berupa JSON object dengan properti `items`, berisi artikel dengan field `source_id`, `title`, `url`, `publisher`, `published_at`, `excerpt`, `summary`, `content`, `image_url`, dan `margas`.

`source_id` wajib merujuk ke website yang mengandung artikel. Laravel memeriksa host URL artikel dan hanya menerima domain yang sama atau subdomain dari website tersebut. `content` harus berupa isi lengkap artikel minimal 200 kata dalam bahasa Indonesia, diambil dengan membuka langsung halaman website dan URL artikel asli, bukan cuplikan pencarian atau RSS. Artikel dari domain lain atau dengan isi kurang dari 200 kata tidak disimpan. `image_url` harus berisi URL absolut gambar utama artikel; isi `null` hanya jika halaman sumber memang tidak menyediakan gambar. Hermes tidak boleh mengarang isi, fakta, atau URL gambar. Gambar ditampilkan pada halaman berita, sementara isi lengkap dapat dibuka melalui kontrol “Baca isi lengkap artikel”. Duplikat tetap disaring sebelum disimpan.

## Menjalankan scheduler

Laravel mengecek pengaturan admin setiap menit dan hanya memanggil Hermes ketika waktu run berikutnya tercapai. Server production perlu menjalankan scheduler Laravel setiap menit, misalnya melalui cron:

```cron
* * * * * cd /path/to/TaromboBatak && php artisan schedule:run >> /dev/null 2>&1
```

Tidak ada command berita khusus yang perlu dijalankan manual. Proses berita dilakukan Hermes melalui API.
