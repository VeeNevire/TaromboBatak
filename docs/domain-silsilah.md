# Domain Silsilah

Dokumen ini menjelaskan bagaimana data silsilah disimpan, dihitung, dan
dirender: dari tabel `people`, penomoran `chain`, family tree terpisah dengan
versioning, hingga algoritma pohon radial di frontend.

Kembali ke [README](../README.md).

---

## Konsep Utama

Ada **dua sumber data silsilah** yang saling melengkapi:

| Sumber | Tabel | Sifat |
| --- | --- | --- |
| **Data induk global** | `people` | Sumber kebenaran hubungan ayah/ibu antar marga. Dipakai untuk pohon tarombo publik dan silsilah per marga. |
| **Pohon milik akun** | `family_trees` + `family_tree_nodes` | Versi personal yang bisa disusun ulang, dibedakan dari data global. Setiap user bisa punya beberapa versi. |

Artinya `father_id` di `people` adalah referensi global, sedangkan
`father_node_id` di `family_tree_nodes` adalah referensi **lokal terhadap satu
pohon**. Keduanya bisa berbeda sementara, dan ada service khusus untuk
mendeteksi serta merekonsiliasi perbedaan itu
(`LocalOnlyFatherLinkFinder`, `FatherConnectionService`).

---

## Tabel `people`

Kolom yang relevan (dari `#[Fillable]` pada `app/Models/Person.php`):

| Kolom | Tipe | Fungsi |
| --- | --- | --- |
| `name` | string | Nama orang. |
| `alias` | string? | Nama panggilan. |
| `gender` | string? | L/P. |
| `marga_id` | FK? | Marga asal. `nullOnDelete`. |
| `father_id` | FK? | **Sumber kebenaran ayah** (self-referential ke `people`). |
| `mother_id` | FK? | Ibu (self-referential). |
| `birth_order` | uint? | Urutan kelahiran di antara saudara. |
| `sibling_count` | uint? | Jumlah saudara. |
| `chain` | string? | **Cache** posisi dalam pohon (indexed). |
| `pending_father` | bool | Root sementara yang ayahnya belum diketahui. |
| `is_public` | bool | Tampil di payload publik? Default private. |
| `birth_year`, `death_year` | string? | Tahun lahir/meninggal (string, bukan integer). |
| `image`, `bio` | text? | Foto dan bio. |
| `spouse`, `spouse_marga` | string? | Pasangan free-text (untuk kasus belum punya record person). |
| `related_stories` | json? | Relasi ke cerita. |
| `created_by` | FK? | User yang membuat record. |
| `province_code` … `village_code` | string? | Domisili (kode wilayah Indonesia). |

> Kolom `nomor`, `nomor_manual`, dan `is_leader` pernah ada, lalu **dihapus**
> pada migrasi `2026_08_17_000001` dan digantikan oleh `chain`.

Kolom `parent_id` (nama awal) di-rename menjadi `father_id` pada migrasi
`2026_08_05_000006`.

---

## Penomoran Chain

Spesifikasi konseptual lengkap ada di
[tarombo-batak-chain-numbering.md](../tarombo-batak-chain-numbering.md).
Ringkasnya:

- Root (leluhur) mendapat angka tunggal: `1`, `2`, `3`, …
- Anak mewarisi chain ayah lalu menambahkan `birth_order`: `1-1`, `1-2`, …
- `chain` adalah **cache** dari `father_id` + `birth_order`. `father_id` tetap
  sumber kebenaran agar koreksi mudah dilakukan.

Contoh:

```
Budi (root)            → 1
├─ Edo (anak ke-1)     → 1-1
│  ├─ Samsul (ke-1)    → 1-1-1
│  └─ Kito (ke-2)      → 1-1-2
└─ Eko (anak ke-2)     → 1-2
   └─ Miko (ke-1)      → 1-2-1
```

### Aturan penting di `ChainNumberingService`

- **Root tanpa anak tidak diberi chain.** Istri/ibu yang tidak punya anak tidak
  masuk ke rantai patrilineal.
- **Root yang sudah punya chain tidak diubah** lagi (chain stabil).
- **`pending_father` messing.** Root dengan `pending_father = true`:
  - Jika masih tertempel ke ayah sementara (`father_id` ada), chain dikosongkan.
  - Jika sudah lepas dari ayah (`father_id` null) dan sudah punya anak, diberi
    label cadangan berawalan `-` (misal `-1`, `-2`). Label ini tidak pernah
    bentrok dengan chain asli yang selalu numerik.
- **Penguncian.** `recomputeFromAncestor()` dan `recomputeAll()` dibungkus
  `Cache::lock('tarombo-chain-numbering', 10)->block(5, …)` untuk mencegah
  race condition pada alokasi nomor root.
- **`recomputeBranch()`** dipakai saat memindahkan satu orang ke ayah lain, agar
  saudara yang tidak terpengaruh tetap pada posisinya.

### Nomor root

`nextRootChain()` mengambil nilai numerik root terbesar lalu tambah satu.
`nextPendingRootChain()` melakukan hal yang sama untuk label cadangan `-n`.
Keduanya berjalan di dalam lock.

### Perawatan

Setelah import massal atau perbaikan data`:

```bash
php artisan people:recompute-chain
```

Lepas keluarga yang ayahnya belum diketahui dari root sementara:

```bash
php artisan people:unlink-pending-fathers
```

---

## Family Tree

### `family_trees`

Satu pohon milik satu user, berakar pada satu `root_person_id`.

| Kolom | Fungsi |
| --- | --- |
| `user_id` | Pemilik pohon. |
| `root_person_id` | Orang fokus pohon. |
| `name`, `description` | Label dan catatan. |
| `source_name`, `source_url` | Sumber data pohon (misal buku, situs). |
| `based_on_id` | Pohon asal bila ini adalah versi turunan. |
| `is_primary` | Tandai pohon utama (bukan versi alternatif). |

Batasan unik `['user_id', 'root_person_id']` **dilepas** saat versi ditambahkan,
agar satu user bisa punya beberapa versi dari akar yang sama.

`FamilyTree::structureIsLocked()` menentukan apakah struktur pohon masih boleh
diubah.

### `family_tree_nodes`

Node menyimpan penempatan **lokal** dari tiap orang di dalam satu pohon.

| Kolom | Fungsi |
| --- | --- |
| `family_tree_id`, `person_id` | unik bersama. |
| `father_node_id`, `mother_node_id` | Orang tua **dalam pohon ini** (self-referential). |
| `birth_order`, `sibling_count` | Urutan lahir. |
| `chain` | Chain per-pohon. |
| `family_name` | Nama keluarga/cabang; keturunan mewarisi nilai ini lewat `father_node_id`. |
| `pending_father` | Ayah belum diketahui di pohon ini. |
| `structure_overrides` | json, override struktur per node. |
| `is_removed` | Node ditandai terhapus (soft). |

Index penting: `ft_nodes_parent_order_idx` (`family_tree_id`, `father_node_id`,
`birth_order`) dan `ft_nodes_chain_idx` (`family_tree_id`, `chain`).

### Versioning & alur_review

- **Duplikasi** — `FamilyTreeVersionService` menyalin pohon beserta node-nya
  (`duplicateFamilyTree`, `duplicateFamilyVersion` di `PersonController`).
- **Berbagi** — `family_tree_shares` + `PersonShareCode`. Kode berbagi berbentuk
  `TBK-P-{id}-{hmac16}`, ditandatangani dengan `APP_KEY` dan diverifikasi
  memakai `hash_equals` (lihat `app/Support/PersonShareCode.php`).
- **Append** — `family_tree_append_requests` berisi payload orang yang
  orang yang diusulkan untuk ditambahkan; disetujui oleh owner/reviewer.
- **Penghapusan** — `family_tree_deletion_requests` menyimpan nama pohon, root,
  dan marga secara denormalisasi supaya tetap terbaca meski pohon dihapus.
- **Sinkronisasi keturunan** — `FamilyTreeDescendantSyncService`
  (`sync-descendants`)menyelaraskan node pohon dengan data global.
- **Lokal vs global** — `LocalOnlyFatherLinkFinder` menemukan ayah yang hanya
  ada di pohon lokal:

```bash
php artisan tarombo:audit-local-only-links   # read-only
php artisan tarombo:fix-local-only-links     # perbaiki + sinkronkan
```

### Tree protection

`TreeProtectionService` mencegah perubahan merusak, misalnya mengubah
struktur yang sudah dipakai sebagai sumber Approved. Permintaan perubahan besar
dicatat di `tree_change_requests` dan `tree_activity_logs`, lalu direview staff
lewat `TreeActivityLogController`.

---

## Batas Payload

Tree tidak pernah dikirim tanpa batas. Batas diatur di `config/tarombo.php`:

| Kunci | Default env | Arti |
| --- | --- | --- |
| `public_max_depth` | `TAROMBO_PUBLIC_MAX_DEPTH` (11) | Kedalaman pohon publik. |
| `public_max_nodes` | `TAROMBO_PUBLIC_MAX_NODES` (500) | Jumlah node pohon publik. |
| `person_max_depth` | `TAROMBO_PERSON_MAX_DEPTH` (5) | Kedalaman silsilah satu orang. |
| `person_max_nodes` | `TAROMBO_PERSON_MAX_NODES` (500) | Jumlah node silsilah satu orang. |
| `dashboard_max_depth` | `TAROMBO_DASHBOARD_MAX_DEPTH` (40) | Kedalaman pohon dashboard (area login, tidak terekspos publik). |
| `dashboard_max_nodes` | `TAROMBO_DASHBOARD_MAX_NODES` (3000) | Jumlah node pohon dashboard. |

`.env.example` menyetel 6/500/5/500/500; default config memakai 11/500/5/500.
Dashboard lebih dalam karena tidak dapat diakses tanpa login.

### Payload publik

`TaromboTreeService::publicRows()` membangun payload dengan **allowlist
field**:

```php
'id', 'name', 'alias', 'marga', 'hasMarga', 'parentId',
'birthOrder', 'chain', 'pending', 'canEdit' (false), 'location'
```

`bio`, `image`, `spouse`, dan `related_stories` **tidak** masuk payload publik.
Person yang tidak `is_public` juga difilter di query. Hasilnya mengembalikan
`truncated: true` bila data melebihi batas.

---

## Alur Render Pohon (Frontend)

Algoritma inti ada di `resources/js/data/tarombo-tree.ts`.

### Tahap 1 — Normalisasi baris

`buildTaromboPeople(rows)` mengubah baris DB menjadi objek person:

- Deduplikasi berdasarkan `id` (baris pertama menang).
- Membangun `parentById`, **membuang** self-parent dan parent yang menggantung.
- Membangun `childrenOf`.
- BFS dari semua root untuk menentukan `generation` (dimulai dari 1).

### Tahap 2 — Forest, bukan satu pohon

> Relasi yang tidak diketahui **tetap ditampilkan sebagai tidak diketahui**.

Frontend tidak memaksa person tanpa parent valid untuk menempel ke root
pertama. Semua root mempertahankan `parentId: null`, dan data di-render sebagai
*forest*. Data cycle legacy yang tidak punya root sengaja **tidak**
disambungkan:

```ts
// Cycle tanpa root → tetap terputus, bukan dikarang hubungan
{ parentId: null, generation: 1 }
```

Konsekuensinya: UI menampilkan beberapa rumpun terpisah, bukan memaksa
seluruhnya jadi keturunan satu orang.

### Tahap 3 — Layout radial

`buildRadialLayout()`:

1. Urutkan anak berdasarkan `birthOrder`.
2. Pilih root = person pertama dengan `parentId` null.
3. `assignSpans` — alokasikan sudut proporsional terhadap jumlah daun subtree
   (`subtreeLeafCount`).
4. `enforceMinGap` — 8 kali relaxasi maju/mundur per ring untuk mencegah
   tumpang tindih; `minGap = (PERSON_NODE_WIDTH + 24) / ringRadius`.
5. `recenterParents` — geser sudut orang tua ke rata-rata `atan2(sin, cos)` dari
   anak-anaknya.
6. Letakkan node di radius `(generation − 1) * RING_GAP`, tipe node `center`
   untuk root dan `person` untuk keturunan.
7. Buat edge `radial` yang dipotong pada radius avatar, dengan warna per
   generasi.
8. Hitung `outerLabelRadius`, `extent`, sector per marga, ring guides, dan label
   luar. Sector digambar sebagai node non-interaktif (`zIndex: -1`).

### Varian konteks

- `buildRadialLayoutFromPerson(person, …, context)`:
  - `context: 'descendants'` — fokus pada satu orang + keturunannya sampai
    `maxDepth`.
  - `context: 'extended'` — keturunan 3 tingkat ke bawah, 2 ke atas, serta
    saudara (anak lain dari ayah yang difokus).

`getDescendantsUpToDepth()` memakai BFS dengan `visited` sehingga aman dari
cycle.

### Konstanta geometri

`ROOT_NODE_SIZE = 112`, `PERSON_NODE_WIDTH = 72`, `PERSON_NODE_HEIGHT = 104`,
`ROOT_NODE_RADIUS = 56`, `PERSON_AVATAR_RADIUS = 31`, `RING_GAP = 165`,
`INNER_RADIUS = RING_GAP * 0.3`, `LABEL_OFFSET = 16`, `PAD = 36`.

Generasi diberi label `'Pusat'`, `'Anak'`, `'Cucu'`, `'Cicit'` dengan palet
`generationColors` (6 warna) dan `margaColors` (24 warna).

---

## Aturan Integritas Data

Hal-hal yang **tidak boleh** terjadi, dan bagaimana sistem mencegahnya:

| Aturan | Pencegahan |
| --- | --- |
| Nested ID lintas keluarga | `children.*.id` dan `ownChildren.*.id` divalidasi terhadap struktur keluarga sebelum transaksi. |
| Circular parent | Jalur ayah dan ibu diperiksa sebelum menyimpan; self-parent dan cycle ditolak. |
| Chain stale setelah reparenting | Lineage lama dan baru dihitung ulang; subtree ikut diperbarui. |
| Root chain bentrok | `Cache::lock` melindungi seluruh operasi recompute. |
| Parent digabung karena nama sama | Reuse hanya bila nama + marga cocok **dan** hasilnya tunggal. |
| Delete merusak relasi | Person yang masih direferensikan sebagai orang tua tidak dapat dihapus. |
| Relasi sintetis di frontend | Forest dipertahankan; tidak ada edge yang tidak berasal dari backend. |

Test regresi untuk semua kasus ini ada di `tests/Feature/`, antara lain
`FamilyIntegrityTest.php`, `TaromboNumberingTest.php`,
`FamilyTreeVersionTest.php`, `FatherConnectionServiceTest.php`.

---

## Form Keluarga

`FamilyEntryService` menulis person, pasangan, dan ayah secara transaksional.
Sifatnya **non-destructive**: baris yang dihapus dari form **tidak** otomatis
dihapus dari database. UI sudah menjelaskan hal ini. Untuk menghapus person,
gunakan alur **Data Anggota** agar dampaknya ke relasi terlihat.

Untuk mencegah submit ganda, `FormSubmissionGuard` mengklaim idempotency di
tabel `form_submissions` (tanpa model Eloquent) di dalam transaksi write.

---

## Statistik

`TaromboStatisticsService` adalah satu-satunya tempat menghitung generasi dan
statistik silsilah, dengan **cycle detection**. Controller tidak lagi
menduplikasi perhitungan depth. Query yang sebelumnya memakai `CURDATE()` sudah
diportable — tanggal dikirim sebagai bound parameter dari PHP.
