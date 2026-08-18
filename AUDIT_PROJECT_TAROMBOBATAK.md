# Audit Project TaromboBatak

## Tujuan

Dokumen ini merangkum hal-hal yang kurang masuk akal, berisiko, belum konsisten, dan masih kurang dari sisi engineering pada project TaromboBatak. Audit difokuskan pada kebenaran data silsilah, keamanan, aturan akses, performa, testing, dan kesiapan produksi.

> **Status implementasi: selesai pada 18 Agustus 2026.** Bagian temuan di bawah dipertahankan sebagai baseline audit. Tabel berikut adalah status mutakhir setelah perbaikan.

## Status Implementasi

| Prioritas | Temuan | Status | Implementasi |
| --- | --- | --- | --- |
| P0 | Nested person ID lintas keluarga | Selesai | ID sibling dan anak divalidasi terhadap struktur keluarga sebelum transaksi. |
| P0 | Chain stale setelah reparenting | Selesai | Lineage lama dan baru dihitung ulang; root tanpa anak dibersihkan. |
| P0 | Circular parent | Selesai | Jalur ayah dan ibu diperiksa sebelum perubahan disimpan. |
| P0 | Relasi sintetis multiple root | Selesai | Frontend mempertahankan forest dan memutus data cycle legacy tanpa membuat hubungan palsu. |
| P1 | Delete merusak relasi | Selesai | Person yang masih direferensikan sebagai orang tua tidak dapat dihapus. |
| P1 | Role tidak konsisten | Selesai | Semantik role disatukan dan otorisasi person dipusatkan di `PersonPolicy`. |
| P1 | Data pribadi terbuka publik | Selesai | `is_public` default private, publikasi hanya staff, dan payload publik memakai allowlist aman. |
| P1 | Parent digabung berdasarkan nama | Selesai | Reuse dibatasi oleh nama+marga dan hanya dilakukan jika hasilnya tunggal/tidak ambigu. |
| P1 | Race condition root chain | Selesai | Seluruh recompute chain dilindungi distributed cache lock. |
| P2 | Payload tree tanpa batas | Selesai | Tree publik dan silsilah person memiliki batas depth/node terkonfigurasi. |
| P2 | Query `CURDATE()` tidak portable | Selesai | Tanggal dikirim sebagai bound parameter dari PHP. |
| P2 | Statistik generasi salah/duplikat | Selesai | Perhitungan dipusatkan di `TaromboStatisticsService` dengan cycle detection. |
| P2 | Row tersimpan tidak hilang dari form | Diputuskan | Semantik non-destructive dipertahankan dan UI sudah menjelaskan bahwa row tersimpan tidak otomatis dihapus. |
| P2 | Kontrol frontend belum berfungsi | Selesai | Kontrol palsu dihapus, empty state dijaga, dan explicit `any` dilarang ESLint. |
| P3 | Environment tidak konsisten | Selesai | `.env.example` memakai identitas project, locale Indonesia, dan konfigurasi batas tree. |
| P3 | Terminologi command lama | Selesai | Command menjadi `people:recompute-chain`. |
| P3 | Risiko artefak development saat deploy | Selesai | README dan CI memastikan production build serta menolak `public/hot`. |

### Verifikasi yang Ditambahkan

- Regression test nested-ID lintas keluarga.
- Regression test cycle dan self/descendant parent.
- Regression test reparenting beserta descendant dan lineage lama.
- Regression test delete parent.
- Test privacy payload publik dan larangan user mempublikasikan data.
- Test batas node tree publik.
- Test scope role dan kedalaman generasi lintas marga.
- Test query kegiatan pada SQLite.
- CI menjalankan lint, formatting, type check, PHPStan, Pest, dan production build.

### Residual Risk

- Cache lock mencegah race melalui aplikasi, tetapi perubahan chain langsung melalui SQL tetap berada di luar proteksi aplikasi.
- Transformasi forest diverifikasi oleh TypeScript, lint, dan build; project belum memiliki test runner unit khusus frontend.
- Family form sengaja bersifat non-destructive. Penghapusan person tetap dilakukan melalui alur Data Anggota agar dampak relasi terlihat jelas.
- Model visibility sengaja menggunakan boolean public/private. Workflow moderasi bertingkat dapat ditambahkan jika kebutuhan operasional berkembang.

Temuan diurutkan berdasarkan prioritas:

- **P0 - Kritis:** berpotensi merusak atau memalsukan data dan harus diperbaiki sebelum pengembangan fitur lain.
- **P1 - Tinggi:** dapat menyebabkan perilaku salah, kebocoran data, atau aturan bisnis yang tidak konsisten.
- **P2 - Menengah:** berdampak pada performa, maintainability, dan pengalaman pengguna.
- **P3 - Rendah:** cleanup dan peningkatan kualitas yang tetap penting, tetapi tidak mendesak.

## Ringkasan Prioritas

Urutan pengerjaan yang paling disarankan:

1. Amankan seluruh ID person yang dikirim melalui form keluarga.
2. Jaga konsistensi `father_id`, `birth_order`, dan `chain` saat update, reparenting, dan delete.
3. Cegah circular parent dan relasi seseorang dengan dirinya sendiri.
4. Hentikan frontend membuat hubungan keluarga sintetis pada multiple root.
5. Samakan aturan role antara route, controller, UI, dan test.
6. Tentukan kebijakan privasi data silsilah publik.
7. Tambahkan test regresi untuk seluruh kasus kritis.
8. Optimalkan payload dan pemrosesan tree setelah kebenaran data terjamin.

## P0 - Temuan Kritis

### 1. Nested Person ID Dapat Mengubah Keluarga Lain

**Lokasi terkait:**

- `app/Http/Controllers/PersonController.php:353-373`
- `app/Http/Controllers/PersonController.php:602-608`
- `app/Services/FamilyEntryService.php:188-245`
- `app/Http/Requests/StorePersonRequest.php:42-57`
- `app/Http/Requests/UpdatePersonRequest.php:42-57`

User biasa hanya diperiksa sebagai pemilik person yang sedang diedit. Akan tetapi, request menerima `children.*.id` dan `ownChildren.*.id`, lalu service menjalankan `Person::find($row['id'])` dan memperbarui person tersebut tanpa memeriksa bahwa ID itu benar-benar anggota keluarga yang boleh diedit user.

**Risiko:**

- User dapat mengirim ID person lain secara manual.
- Nama, marga, ayah, ibu, pasangan, dan urutan lahir person lain dapat berubah.
- Kepemilikan person fokus tidak cukup untuk mengamankan seluruh nested payload.

**Perbaikan yang disarankan:**

- Validasi bahwa `children.*.id` hanya berasal dari sibling group person fokus.
- Validasi bahwa `ownChildren.*.id` benar-benar memiliki `father_id` sesuai person fokus.
- Untuk user biasa, pastikan seluruh person berada dalam scope marga dan ownership yang diizinkan.
- Pindahkan aturan akses ke Policy atau service authorization yang terpusat.
- Tolak seluruh request jika satu saja nested ID berada di luar scope.

### 2. Chain Dapat Tidak Sinkron Setelah Reparenting

**Lokasi terkait:**

- `app/Services/FamilyEntryService.php:25-110`
- `app/Services/ChainNumberingService.php:21-31`
- `tarombo-batak-chain-numbering.md`

Setelah update keluarga, service hanya menghitung ulang lineage ayah baru. Ayah lama dan subtree yang sebelumnya terhubung tidak selalu dihitung ulang.

Padahal `chain` merupakan cache dari `father_id` dan `birth_order`. Ketika parent berubah, chain lama tidak boleh dipertahankan.

**Risiko:**

- `father_id` menunjuk ayah baru tetapi `chain` masih mengikuti ayah lama.
- Chain descendant ikut menjadi stale.
- Pencarian descendant berdasarkan prefix chain menghasilkan data salah.

**Perbaikan yang disarankan:**

- Simpan `oldFatherId` sebelum person diperbarui.
- Hitung ulang lineage ayah lama dan ayah baru.
- Hitung ulang seluruh subtree person yang dipindahkan.
- Jalankan perubahan relasi dan recompute dalam transaksi yang konsisten.
- Tambahkan test reparenting beserta descendant bertingkat.

### 3. Circular Parent Belum Dicegah

**Lokasi terkait:**

- `app/Http/Requests/StorePersonRequest.php:22-23`
- `app/Http/Requests/UpdatePersonRequest.php:22-23`
- `app/Services/ChainNumberingService.php:53-69`
- `app/Models/Person.php:157-175`

Saat ini `father_id` dan `mother_id` hanya diperiksa dengan aturan `exists`. Belum ada validasi yang mencegah:

- person menjadi ayah atau ibu dirinya sendiri;
- descendant dijadikan ayah dari ancestor;
- cycle A -> B -> A;
- cycle yang lebih panjang.

Kode traversal memang memiliki `visited` atau batas iterasi, tetapi itu hanya mencegah infinite loop. Data yang salah tetap tersimpan.

**Perbaikan yang disarankan:**

- Tolak `father_id` dan `mother_id` yang sama dengan person fokus.
- Telusuri ancestry kandidat parent sebelum update.
- Tolak kandidat parent jika person fokus ditemukan di ancestry-nya.
- Tambahkan test self-parent dan cycle dua atau lebih tingkat.

### 4. Frontend Membuat Hubungan Genealogis Palsu

**Lokasi terkait:**

- `resources/js/data/tarombo-tree.ts:163-203`
- `resources/js/data/tarombo-tree.ts:571-703`

`buildTaromboPeople()` memilih satu root pertama. Person yang tidak mempunyai parent valid atau berada di root lain kemudian dipaksa memiliki root pertama sebagai parent.

**Risiko:**

- Rumpun berbeda terlihat sebagai keturunan root pertama.
- Data pending terlihat sudah tersambung.
- Visualisasi menyampaikan fakta genealogis yang tidak ada di database.

Untuk aplikasi silsilah, relasi yang tidak diketahui harus tetap ditampilkan sebagai tidak diketahui. UI tidak boleh menebak hubungan keluarga.

**Perbaikan yang disarankan:**

- Pertahankan `parentId: null` untuk seluruh root.
- Render data sebagai forest atau sediakan pemilih root/rumpun.
- Bedakan root resmi dengan root pending secara visual.
- Jangan membuat edge yang tidak berasal dari data backend.
- Tambahkan unit test TypeScript untuk multiple roots dan missing parent.

## P1 - Prioritas Tinggi

### 5. Penghapusan Person Tidak Menjaga Integritas Silsilah

**Lokasi terkait:**

- `app/Http/Controllers/PersonController.php:383-389`
- `database/migrations/2026_08_05_000006_expand_people_for_family_entries.php:17-21`

Person langsung dihapus. Foreign key parent menggunakan perilaku `nullOnDelete`, sehingga anak dapat berubah menjadi root tanpa proses domain tambahan dan chain lama dapat tertinggal.

**Perbaikan yang disarankan:**

- Tentukan apakah person dengan descendant boleh dihapus.
- Tampilkan dampak penghapusan sebelum konfirmasi.
- Pertimbangkan soft delete dan audit log.
- Jika penghapusan diizinkan, recompute seluruh descendant dan lineage terdampak.
- Jangan menghapus orang yang masih direferensikan tanpa keputusan eksplisit.

### 6. Aturan Role Tidak Konsisten

**Lokasi terkait:**

- `routes/web.php:36-68`
- `app/Http/Controllers/PersonController.php:81-160`
- `resources/js/pages/people/index.tsx:93-106`
- `tests/Feature/RoleAccessTest.php:32-52`

Route, controller, UI, dan test memiliki asumsi berbeda tentang user biasa:

- UI mengizinkan user yang mempunyai marga menambah keluarga.
- Controller juga mengizinkannya.
- Test masih menganggap route create sebagai admin-only.
- Beberapa fitur menganggap sub-admin sebagai staff penuh, sedangkan fitur lain hanya memberi akses global kepada admin.

**Keputusan yang harus dibuat:**

- Apakah user biasa boleh menambah keluarga sendiri?
- Apakah sub-admin hanya mengelola satu marga atau seluruh data?
- Siapa yang boleh melihat detail lengkap, menghapus person, dan mengelola konten publik?

Setelah keputusan dibuat, gunakan Policy/Gate dan samakan route, controller, UI, serta test.

### 7. Data Personal Dipublikasikan Tanpa Visibility Model

**Lokasi terkait:**

- `routes/web.php:18`
- `app/Http/Controllers/TaromboController.php:41-53`
- `app/Services/TaromboTreeService.php:17-45`

Halaman publik mengirim seluruh data person, termasuk nama, tahun lahir, gambar, bio, pasangan, dan daftar anak.

**Yang masih kurang:**

- status public/private pada person;
- perlindungan khusus untuk orang yang masih hidup;
- consent pemilik data;
- mekanisme permintaan koreksi atau penghapusan;
- moderation dan riwayat perubahan;
- pembatasan field yang boleh ditampilkan secara publik.

**Perbaikan yang disarankan:**

- Tambahkan visibility status, misalnya `public`, `family_only`, dan `private`.
- Tentukan aturan untuk person yang masih hidup.
- Buat public resource/payload terpisah yang hanya berisi field aman.
- Jangan menggunakan payload internal sebagai payload publik.

### 8. Reuse Parent Berdasarkan Nama Saja

**Lokasi terkait:**

- `app/Services/FamilyEntryService.php:128-180`

Parent free-text dicari berdasarkan nama dan record pertama digunakan kembali. Dua orang berbeda dengan nama sama dapat tergabung menjadi satu identitas.

**Perbaikan yang disarankan:**

- Utamakan pemilihan person berdasarkan ID.
- Saat nama sama ditemukan, tampilkan pilihan disambiguasi.
- Gunakan marga, tahun lahir, ayah, atau identifier genealogis sebagai pembeda.
- Jangan otomatis menggabungkan person hanya karena nama sama.

### 9. Root Chain Rawan Race Condition

**Lokasi terkait:**

- `app/Services/ChainNumberingService.php:190-223`
- `database/migrations/2026_08_17_000001_add_chain_remove_nomor_leader_to_people_table.php:14-21`

Nomor root menggunakan pola mengambil nilai terbesar lalu menambah satu. Dua request bersamaan dapat memperoleh nomor yang sama. Kolom `chain` hanya memiliki index, bukan unique constraint.

**Perbaikan yang disarankan:**

- Gunakan lock saat mengalokasikan root chain.
- Pertimbangkan unique constraint jika aturan domain memang menjamin chain unik.
- Tangani retry ketika terjadi konflik.
- Tambahkan test integrasi untuk uniqueness.

## P2 - Prioritas Menengah

### 10. Payload Tree Tidak Memiliki Batas

**Lokasi terkait:**

- `app/Services/TaromboTreeService.php:17-45`
- `app/Http/Controllers/TaromboController.php:24-47`
- `app/Http/Controllers/PersonController.php:209-223`

Seluruh database person dikirim sekaligus ke browser. Halaman silsilah individual juga mengambil seluruh person, meskipun hanya membutuhkan keluarga terkait.

**Dampak:**

- response Inertia membesar;
- penggunaan memori browser meningkat;
- layout tree menjadi berat;
- data yang tidak diperlukan ikut terekspos.

**Perbaikan yang disarankan:**

- Ambil ancestor dan descendant yang relevan saja.
- Tambahkan batas kedalaman.
- Gunakan lazy loading untuk membuka cabang.
- Pisahkan payload ringkas tree dari payload detail profile.

### 11. Query Event Tidak Portable

**Lokasi terkait:**

- `app/Http/Controllers/EventController.php:46-66`
- `config/database.php:20`

Query memakai `CURDATE()`, yang spesifik MySQL, sementara default konfigurasi Laravel di project adalah SQLite.

**Perbaikan yang disarankan:**

- Kirim tanggal hari ini sebagai parameter query dari PHP.
- Hindari fungsi SQL yang spesifik driver jika project mendukung SQLite untuk test/development.
- Tambahkan feature test untuk halaman kegiatan publik.

### 12. Statistik Generasi Dapat Salah

**Lokasi terkait:**

- `app/Http/Controllers/DashboardController.php:74-101`
- `app/Http/Controllers/TaromboController.php:56-81`
- `app/Http/Controllers/MargaController.php:70-95`

Perhitungan depth diduplikasi di beberapa controller. Pada dashboard user, parent yang berada di luar scope marga tidak tersedia sehingga traversal dapat berhenti terlalu cepat. Cycle juga hanya dibatasi sampai 1000 iterasi dan dapat menghasilkan statistik tidak masuk akal.

**Perbaikan yang disarankan:**

- Buat satu service statistik silsilah.
- Hitung berdasarkan chain yang valid atau traversal graph yang memiliki cycle detection.
- Tetap muat ancestor yang diperlukan walaupun berada di luar filter tampilan.
- Jangan menjadikan batas 1000 sebagai pengganti validasi integritas.

### 13. Family Form Tidak Menghapus Row yang Dihilangkan

**Lokasi terkait:**

- `app/Services/FamilyEntryService.php:188-245`
- `resources/js/pages/people/family-form.tsx`

Service hanya melakukan upsert. Row yang dihapus dari form tetap tersimpan di database. Ini tidak intuitif jika halaman dipersepsikan sebagai editor seluruh keluarga.

**Perbaikan yang disarankan:**

- Tegaskan apakah form adalah full synchronization atau hanya penambahan/update.
- Jika full synchronization, kirim daftar ID yang dipertahankan dan proses penghapusan dengan aman.
- Jika bukan, ubah copy UI agar tidak memberi kesan bahwa row akan dihapus.

### 14. Fitur Frontend yang Belum Selesai

**Lokasi terkait:**

- `resources/js/components/landing/profile-card.tsx:76-81`
- `resources/js/components/landing/tarombo-diagram.tsx:186-202`
- `resources/js/pages/tarombo/index.tsx`
- `resources/js/pages/tarombo/public.tsx`

Beberapa kontrol terlihat aktif tetapi belum berfungsi:

- tombol `Lihat Profil Lengkap` tidak memiliki aksi;
- input pencarian internal diagram tidak terhubung ke state;
- tombol filter tidak memiliki handler;
- beberapa callback masih menggunakan `any`;
- empty state tree perlu dijaga sebelum fungsi layout dipanggil.

Kontrol tanpa fungsi sebaiknya diimplementasikan atau dihapus sementara agar tidak membingungkan pengguna.

## P3 - Cleanup dan Maintainability

### 15. Konfigurasi Environment Tidak Konsisten

**Lokasi terkait:**

- `.env.example:1-28`
- `config/database.php:20`

`.env.example` menggunakan MySQL, sedangkan fallback aplikasi menggunakan SQLite. Nama aplikasi dan locale juga masih mengikuti skeleton Laravel. Beberapa baris database memiliki spasi awal.

**Perbaikan yang disarankan:**

- Tentukan satu baseline development resmi.
- Ubah `APP_NAME`, locale, faker locale, dan konfigurasi database contoh.
- Dokumentasikan konfigurasi production secara terpisah tanpa menyimpan secret.

### 16. Terminologi Lama Sudah Diperbarui

**Lokasi terkait:**

- `app/Console/Commands/RecomputeChain.php`
- `database/migrations/2026_08_17_000001_add_chain_remove_nomor_leader_to_people_table.php`

Command telah diperbarui menjadi `people:recompute-chain` agar sesuai dengan model domain saat ini.

**Perbaikan yang disarankan:**

- Ubah nama command agar sesuai domain saat ini.
- Samakan istilah pada UI, dokumentasi, test, dan source code.

### 17. Artefak Development Harus Dijaga dari Deployment

**Lokasi terkait:**

- `.gitignore`
- `public/hot`
- `public/build`
- `storage/inertia-devtools`

File tersebut sudah di-ignore, tetapi pipeline deployment tetap harus memastikan `public/hot` tidak terbawa dan asset production selalu dibangun.

## Kekurangan Testing

Test yang ada sudah mencakup authentication, role dasar, penyimpanan keluarga, pending father, dan chain numbering. Namun risiko domain terpenting belum memiliki test regresi.

Test yang paling perlu ditambahkan:

1. User tidak dapat mengubah `children.*.id` milik keluarga lain.
2. User tidak dapat mengubah `ownChildren.*.id` yang bukan anak person fokus.
3. Person tidak dapat menjadi parent dirinya sendiri.
4. Descendant tidak dapat dijadikan parent ancestor.
5. Reparenting memperbarui chain person dan seluruh descendant.
6. Reparenting juga membersihkan lineage ayah lama.
7. Delete person dengan child mengikuti aturan domain yang disepakati.
8. Multiple roots tidak menghasilkan relasi sintetis di frontend.
9. Public payload tidak memuat field private.
10. Admin, sub-admin, dan user memperoleh scope yang konsisten.
11. Halaman event berjalan pada database test SQLite.
12. Tree dapat menangani database kosong tanpa error.

## Roadmap Implementasi

### Fase 1 - Amankan Kebenaran Data

- Tambahkan authorization nested ID.
- Tambahkan validasi self-parent dan cycle.
- Perbaiki reparenting dan recompute chain.
- Tentukan perilaku delete person.
- Tambahkan test regresi untuk seluruh perubahan.

**Fase ini harus selesai sebelum fitur baru ditambahkan.**

### Fase 2 - Benahi Model Akses dan Visualisasi

- Putuskan capability admin, sub-admin, dan user.
- Terapkan Policy/Gate secara konsisten.
- Ubah visualisasi agar mendukung multiple roots.
- Bedakan lineage resmi dan pending secara jelas.
- Tambahkan test frontend untuk transformasi tree.

### Fase 3 - Privacy dan Skalabilitas

- Tambahkan visibility model.
- Buat payload publik khusus.
- Terapkan lazy loading dan depth limit.
- Pisahkan summary tree dari detail person.
- Tambahkan audit trail dan proses koreksi data.

### Fase 4 - Quality dan Production Readiness

- Perbaiki query lintas database.
- Satukan service statistik generasi.
- Selesaikan atau hapus kontrol frontend yang belum berfungsi.
- Rapikan environment example dan terminologi.
- Tambahkan CI untuk lint, type check, static analysis, test, dan build.

## Definisi Selesai untuk Prioritas Kritis

Perbaikan P0 dianggap selesai jika:

- nested person ID di luar scope selalu menghasilkan `403` atau validation error;
- tidak ada self-parent atau cycle yang dapat disimpan;
- `chain` selalu sesuai `father_id` dan `birth_order` setelah reparenting;
- descendant ikut memperoleh chain baru;
- multiple roots tetap terpisah di frontend;
- seluruh kasus tersebut memiliki test regresi yang lulus;
- test suite, lint, static analysis, type check, dan build berhasil.

## Kesimpulan

Project sudah memiliki fondasi stack yang modern dan domain tarombo yang cukup jelas. Masalah utamanya bukan pada tampilan, tetapi pada **kepercayaan terhadap data**. Aplikasi silsilah harus mengutamakan kebenaran relasi, jejak perubahan, dan privasi. Karena itu, authorization, cycle prevention, chain consistency, dan multiple-root handling harus dikerjakan lebih dahulu daripada fitur atau polishing UI baru.
