# Hermes Agent untuk "Berita Marga-Marga"

Hermes Agent (di VPS) mencari berita kegiatan marga di web, lalu mengirimkannya ke Laravel.
Laravel menyimpan setiap berita sebagai **Menunggu**; Admin/Sub Admin menyetujuinya di
**Review Berita Marga** sebelum tampil di menu publik **Berita Marga-Marga** (`/berita-marga`).

```
Admin ── atur kata kunci ──▶ Topik Berita Marga (Laravel)
Hermes Agent ── GET  /api/berita-marga/tugas ──▶ Laravel   (ambil kata kunci)
Hermes Agent ── cari di web + saring dengan AI
Hermes Agent ── POST /api/berita-marga/masuk ──▶ Laravel   (kirim berita)
Admin/Sub Admin ── setujui ──▶ tampil di /berita-marga
```

## 1. Siapkan Laravel (laptop)

1. Buat token rahasia untuk agen:
   ```bash
   php -r "echo bin2hex(random_bytes(32));"
   ```
2. Isi di `.env` Laravel:
   ```
   MARGA_NEWS_AGENT_TOKEN=<token-di-atas>
   ```
3. Jalankan Laravel seperti biasa (`composer run dev`, server di `http://127.0.0.1:8000`).
4. Cek sendiri dari laptop:
   ```bash
   curl -H "Authorization: Bearer <token>" -H "Accept: application/json" http://127.0.0.1:8000/api/berita-marga/tugas
   ```
   Jawaban berisi `topics`, `margas`, `known_urls`, dan `instructions`.

Kata kunci dikelola admin di menu **Administrasi → Topik Berita Marga**. Halaman itu juga
menampilkan kapan agen terakhir mengambil tugas dan mengirim berita.

## 2. Sambungkan VPS ke laptop (SSH reverse tunnel)

Laptop biasanya tidak bisa dihubungi langsung dari internet, jadi **laptop yang membuka
koneksi ke VPS** dan meneruskan port Laravel ke sana.

Jalankan di laptop (biarkan jendela ini tetap terbuka selama tes):

```bash
ssh -N -R 8000:127.0.0.1:8000 user@alamat-vps
```

- `-R 8000:127.0.0.1:8000`: port 8000 **di VPS** diteruskan ke port 8000 **di laptop**.
- `-N`: hanya membuka tunnel, tanpa shell.
- Secara bawaan port itu hanya bisa diakses dari dalam VPS (bukan dari internet).
- Jika port 8000 di VPS sudah dipakai, ganti angka pertama, mis. `-R 18000:127.0.0.1:8000`,
  lalu gunakan `http://localhost:18000` di VPS.
- Agar tunnel tersambung ulang otomatis bila putus, bisa pakai `autossh`:
  `autossh -M 0 -N -o ServerAliveInterval=30 -R 8000:127.0.0.1:8000 user@alamat-vps`.

Uji dari **dalam VPS** (terminal SSH biasa ke VPS):

```bash
curl -H "Authorization: Bearer <token>" -H "Accept: application/json" http://localhost:8000/api/berita-marga/tugas
```

Jika JSON muncul, VPS sudah tersambung ke Laravel di laptop.

## 3. Konfigurasi Hermes Agent di VPS

Pasang Hermes Agent sesuai panduan resminya dan pilih provider AI (OpenAI, OpenRouter, dll.)
di konfigurasi Hermes Agent sendiri. Lalu sediakan dua variabel lingkungan untuk agen:

```
TAROMBO_API_URL=http://localhost:8000
TAROMBO_AGENT_TOKEN=<token-yang-sama-dengan-.env-Laravel>
```

Jangan menulis token langsung di dalam prompt; simpan di variabel lingkungan/konfigurasi
rahasia agen.

### Instruksi tugas untuk agen

Gunakan teks berikut sebagai tugas terjadwal (disarankan tiap 6 jam):

> Kamu adalah pengumpul berita kegiatan marga Batak untuk situs Tarombo Batak.
>
> 1. Ambil daftar tugas:
>    `curl -s -H "Authorization: Bearer $TAROMBO_AGENT_TOKEN" -H "Accept: application/json" "$TAROMBO_API_URL/api/berita-marga/tugas"`
> 2. Untuk setiap item di `topics`, cari di web berita **terbaru (maksimal 6 bulan terakhir)** berbahasa Indonesia
>    dengan kata kunci `keyword` (dan nama `marga` bila ada). Perhatikan `notes` bila diisi.
> 3. Simpan hanya berita yang **benar-benar tentang kegiatan marga Batak**: punguan, parsadaan, pomparan,
>    pesta bona taon, pesta tugu, pelantikan pengurus, mubes, arisan/partangiangan marga, dan sejenisnya.
>    Buang berita yang memakai kata "marga" dalam arti lain (mis. "Sapta Marga" TNI), iklan, dan duplikat.
>    Lewati URL yang sudah ada di `known_urls`.
> 4. Untuk setiap berita, siapkan:
>    - `topic_id`: id topik yang menemukannya
>    - `title`: judul asli artikel
>    - `url`: tautan **artikel asli** (bukan halaman pencarian)
>    - `publisher`: nama portal
>    - `published_at`: tanggal terbit, format ISO 8601 (mis. `2026-04-19T10:00:00+07:00`)
>    - `excerpt`: kutipan singkat (maksimal 2 kalimat) dari artikel
>    - `summary`: ringkasan 1–2 kalimat dengan kata-katamu sendiri, bahasa Indonesia
>    - `margas`: daftar nama marga yang disebut, ditulis persis seperti di daftar `margas`
> 5. Kirim paling banyak 50 berita per permintaan:
>    `curl -s -X POST -H "Authorization: Bearer $TAROMBO_AGENT_TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" -d @berita.json "$TAROMBO_API_URL/api/berita-marga/masuk"`
>    dengan isi `berita.json`: `{"agent": "hermes-vps", "items": [ ... ]}`.
> 6. Laporkan jumlah `accepted` dan `duplicates` dari jawaban server.

## 4. Format API

### `GET /api/berita-marga/tugas`

```json
{
  "topics": [{ "id": 1, "keyword": "pesta bona taon marga", "marga": null, "notes": null }],
  "margas": ["Silaban", "Sihombing", "..."],
  "known_urls": ["https://..."],
  "instructions": "..."
}
```

### `POST /api/berita-marga/masuk`

```json
{
  "agent": "hermes-vps",
  "items": [
    {
      "topic_id": 1,
      "title": "Seribuan Pomparan Borsak Jungjungan Silaban Marpesta Bona Taon di Medan",
      "url": "https://www.hariansib.com/...",
      "publisher": "harianSIB.com",
      "published_at": "2025-01-19T09:00:00+07:00",
      "excerpt": "Ribuan pomparan Silaban berkumpul ...",
      "summary": "Pomparan Borsak Junjungan Silaban menggelar pesta bona taon di Medan.",
      "margas": ["Silaban"]
    }
  ]
}
```

Jawaban `201`: `{"accepted": 1, "duplicates": 0}`.

- Maksimal 100 item per permintaan; `url` harus `http`/`https`.
- Berita yang sama (URL sama, termasuk beda parameter `utm_`, atau judul sama) dihitung duplikat.
- Laravel juga menandai marga otomatis dari judul/cuplikan; admin bisa mengoreksinya saat review.
- `401` = token salah, `503` = `MARGA_NEWS_AGENT_TOKEN` belum diisi, `422` = data tidak valid,
  `429` = lebih dari 30 permintaan per menit.

## 5. Kalau Hermes-nya server run/poll (dijaga Supervisor)

Sebagian setup Hermes bukan agen yang menjadwalkan diri sendiri, tapi **server HTTP** yang
menerima "run" lalu dipanggil dan ditunggu sampai selesai — polanya sama seperti env `.env`
teman Anda: `HERMES_BASE_URL`, `HERMES_TOKEN`, `HERMES_RUNS_ENDPOINT`,
`HERMES_TIMEOUT`/`HERMES_RUN_TIMEOUT`/`HERMES_POLL_SECONDS`. `API_SERVER_KEY` di sisi Hermes
harus sama dengan `MARGA_NEWS_AGENT_TOKEN` di `.env` Laravel, karena Hermes memanggil balik
`api/berita-marga/masuk` dengan token itu.

Kalau Hermes dijalankan begini (server dijaga **Supervisor** di VPS, bukan cron):

1. Isi di `.env` Laravel:
   ```
   HERMES_BASE_URL=http://localhost:8000   # atau alamat lain via SSH tunnel (lihat bagian 2)
   HERMES_TOKEN=<token untuk memanggil Hermes>
   HERMES_RUNS_ENDPOINT=/runs
   HERMES_TIMEOUT=90
   HERMES_RUN_TIMEOUT=300
   HERMES_POLL_SECONDS=2
   ```
2. Jalankan manual untuk tes:
   ```bash
   php artisan marga-news:run-hermes
   ```
   Perintah ini mengambil topik aktif, memanggil Hermes per topik (`POST {HERMES_BASE_URL}{HERMES_RUNS_ENDPOINT}`),
   menunggu run selesai (polling `GET .../runs/{id}` tiap `HERMES_POLL_SECONDS`, maksimal
   `HERMES_RUN_TIMEOUT` detik), lalu menyimpan hasilnya lewat proses yang sama dengan
   `POST /api/berita-marga/masuk` (dedupe + tandai marga + status Menunggu).
3. Server Hermes (`app/Services/HermesRunClient.php`) mengasumsikan bentuk run seperti ini —
   **sesuaikan bila API Hermes teman Anda berbeda**:
   ```json
   // POST /runs -> { "id": "...", "status": "queued" }
   // GET  /runs/{id} -> { "id": "...", "status": "completed", "output": { "items": [ ... ] } }
   ```
   `status` yang dianggap selesai: `completed`; yang dianggap gagal: `failed`/`error`.
   `output.items` (atau `output` langsung berupa array) memakai field yang sama seperti item di
   bagian 4 (`title`, `url`, `publisher`, `published_at`, `excerpt`, `summary`, `margas`).
4. Jadwalkan di `routes/console.php`, mis. tiap 6 jam:
   ```php
   Schedule::command('marga-news:run-hermes')->everySixHours()->withoutOverlapping();
   ```
   (server produksi perlu cron `php artisan schedule:run` tiap menit — belum ada jadwal
   apa pun di proyek ini sekarang).

## 6. Setelah tes: pindah ke server produksi

Saat Laravel sudah di-deploy, tunnel tidak diperlukan lagi: ubah `TAROMBO_API_URL` di VPS ke
alamat website (mis. `https://www.tarombo…`) dan isi `MARGA_NEWS_AGENT_TOKEN` di `.env` produksi
(token baru, jangan memakai token tes).
