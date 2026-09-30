# Arsitektur

Dokumen ini memetakan struktur internal TaromboBatak: layer aplikasi, alur
request, peta controller/service/model, dan konvensi frontend.

Kembali ke [README](../README.md).

---

## Layer Aplikasi

```
Request HTTP
    ↓
Middleware (auth, role, activity log, appearance)
    ↓
Route  (routes/web.php, routes/api.php, routes/settings.php)
    ↓
Controller  (validasi + orkestrasi)
    ↓
FormRequest  (validasi input)  ─┐
Policy      (otorisasi)         ├─ sebelum logika domain
Service     (aturan domain)     ┘
    ↓
Model / Database
    ↓
Event / Job / Notification  (realtime + antrean)
    ↓
Inertia response  →  halaman React
```

Prinsip yang dipegang project:

- **Controller tipis.** Logika domain berada di `app/Services`, bukan di
  controller.
- **Validasi di FormRequest.** 55 form request di `app/Http/Requests`.
- **Otorisasi di Policy.** 9 policy di `app/Policies`. Role dicek di middleware
  (pintu masuk area), authorization per-objek dicek di policy.
- **Broadcast lewat event.** 4 event di `app/Events` dipancarkan ke Reverb
  (kecuali `TelegramMessageReceived` yang `ShouldBroadcastNow`).
- **Pekerjaan berat lewat queue.** 3 job di `app/Jobs`, queue driver
  `database`.

---

## Alur Request Inertia

Entry point server adalah `resources/views/app.blade.php` — satu-satunya Blade
view di project.

1. Blade menyuntikkan skrip deteksi dark mode sebelum paint, memuat favicon dan
   font Bunny, serta menginisialisasi Google Analytics (jika ID terisi).
2. `@vite` memuat `app.css` dan `app.tsx`. Directive `@vite` juga memuat
   `resources/js/pages/{$page['component']}.tsx` sehingga setiap halaman di
   code-split.
3. `<x-inertia::head>` dan `<x-inertia::app` merender hasil.

`app/Http/Middleware/HandleInertiaRequests.php` menjadi titik berbagi (share point)
untuk data global: user yang terautentikasi, flash message, data navigasi, dan
pengaturan yang dipakai seluruh halaman.

Kossil broadcast dilakukan lewat Laravel Echo yang dikonfigurasi di
`resources/js/app.tsx`:

```ts
configureEcho({ broadcaster: 'reverb' })
```

Halaman yang subscribing: `pages/contacts/index.tsx`, `pages/groups/show.tsx`,
`pages/marga/chat.tsx`, `pages/telegram/messages.tsx`.

---

## Peta Controller (55 file)

| Domain | Controller | Ringkasan |
| --- | --- | --- |
| **Public shell** | `DashboardController`, `NewsFeedController`, `StatusController`, `BudayaController`, `KomunitasController`, `TentangController` | Landing, feed, halaman statis. `HomeController` adalah kode mati (tidak dirujuk route). |
| **Silsilah** | `PersonController`, `TaromboController`, `SharedFamilyTreePersonController` | CRUD person, preview publik, silsilah per orang, pohon tarombo, versi alternatif. |
| **Family tree** | `FamilyTreeShareController`, `FamilyTreeAppendRequestController`, `FamilyTreeDeletionController`, `FamilyTreeActivityController` | Berbagi pohon, pengajuan append, penghapusan, log aktivitas. |
| **Marga** | `MargaController`, `MargaBranchEntryController`, `MargaSiblingOrderController`, `MargaChatController` | CRUD marga, cabang marga, urutan saudara, chat marga. |
| **Konten** | `StoryController`, `EventController`, `FeedPostController`, `FeedPostLikeController`, `FeedCommentController`, `FeedItemEngagementController` | Cerita, kegiatan, news feed, like/komentar (termasuk polimorfik untuk story/event). |
| **Kontribusi** | `ContributionController`, `IdentityRequestController` | Pengajuan & review kontribusi data, identitas, akses marga. |
| **Messaging** | `ContactController`, `MessageController`, `MessageAttachmentController`, `MessageLogController`, `ContactRequestController` | Chat personal, lampiran, log pesan, permintaan kontak. |
| **Grup** | `ChatGroupController`, `ChatGroupMemberController`, `GroupMessageController`, `TelegramGroupLinkController`, `TelegramAnnouncementController` | Grup, anggota, pesan, tautan & pengumuman Telegram. |
| **Telegram** | `TelegramMessagesController` | Lihat, sinkron, balas, tandai dibaca pesan. |
| **Tarombo gambar** | `TaromboFrameController`, `TaromboSnapshotController`, `TaromboCompileDraftController` | Template frame, kompilasi & unduh gambar pohon, draft. |
| **Berita marga** | `MargaNewsController`, `MargaNewsTopicController`, `MargaNewsSourceController`, `MargaNewsAutomationController` | Review berita, topik, sumber, pengaturan otomatisasi. |
| **Admin** | `AccountController`, `SubAdminController`, `TreeActivityLogController`, `TrafficMonitorController` | Manajemen akun/sub-admin, review log pohon, monitor traffic. |
| **Auth** | `Auth\GoogleAuthController` | Login Google OAuth + melengkapi profil. |
| **Settings** | `Settings\ProfileController`, `Settings\SecurityController`, `Settings\TelegramConnectionController`, `Settings\TelegramMtprotoController` | Profil, keamanan, koneksi Telegram (bot link & MTProto). |
| **API** | `Api\MargaNewsAgentController` | Endpoint untuk agen berita Hermes (task & ingest). |
| **Region** | `IndonesiaRegionController` | Ambil daftar kabupaten/kota dan desa/kelurahan. |

---

## Peta Service (32 file)

### Domain silsilah & pohon

| Service | Tanggung jawab |
| --- | --- |
| `ChainNumberingService` | Penomoran chain global (pola patrilineal) untuk `people`. |
| `FamilyTreeChainNumberingService` | Penomoran chain per-pohon untuk `family_tree_nodes`. |
| `FamilyEntryService` | Tulis person/pasangan/ayah secara transaksional (form keluarga). |
| `FamilyTreeStructureService` | Struktur graph node pohon, penguncian struktur. |
| `FamilyTreeVersionService` | Penyalinan & versioning pohon. |
| `FamilyTreeInheritanceService` | Penurunan (mis. nama keluarga antar node). |
| `FamilyTreeDescendantSyncService` | Sinkronisasi keturunan pohon dengan `people`. |
| `FatherConnectionService` | Rekonsiliasi `father_id` global antar pohon. |
| `LocalOnlyFatherLinkFinder` | Menemukan ayah yang hanya ada di pohon lokal (drift). |
| `DuplicateChildGuard` | Cegah anak duplikat. |
| `TreeProtectionService` | Lindungi struktur pohon dari perubahan merusak. |
| `MargaIdentityPersonService` | Kelola person identitas (tokoh) marga. |

### Tarombo (visualisasi)

| Service | Tanggung jawab |
| --- | --- |
| `TaromboTreeService` | Build payload pohon + batas depth/node. |
| `TaromboStatisticsService` | Statistik generasi & hitungan (dengan cycle detection). |
| `TaromboFrameUpscaler` | Upscale gambar frame via OpenAI (`gpt-image`). |

### Feed & berita

| Service | Tanggung jawab |
| --- | --- |
| `NewsFeedService` | Paginasi cursor, visibilitas feed, tandai dibaca, payload status. |
| `MargaNewsAutomationRunner` | Entry point scheduler, dilindungi lease. |
| `HermesRunClient` | Client HTTP ke Hermes (`POST/GET /runs` + polling). |
| `MargaNewsIngestor` | Validasi domain, minimal 200 kata, dedupe, set status. |

### Telegram

| Service | Tanggung jawab |
| --- | --- |
| `TelegramBotApi` | Implementasi Bot API (`getUpdates`, `sendMessage`, dll). |
| `TelegramMtproto` | Klien MTProto (login via QR/kode, 2FA, history). |
| `TelegramMessageEventHandler` | Event handler MTProto. |
| `TelegramMessageImporter` | Simpan pesan ke `telegram_messages`. |
| `TelegramGroupSync` | Sinkronisasi grup/dialog. |
| `TelegramUpdateProcessor` | Proses update (dispatch ke importer/event). |

### Cross-cutting

| Service | Tanggung jawab |
| --- | --- |
| `AccountActivityLogger` | Tulis `activity_logs` (audit akun/sub-admin). |
| `FamilyTreeActivityLogger` | Log aktivitas pohon. |
| `TreeActivityLogger` | Log aktivitas pohon besar. |
| `FormSubmissionGuard` | Idempotency claim via tabel `form_submissions`. |

---

## Model (48 file)

Semua model memakai atribut `#[Fillable([...])]` (Laravel 13), bukan properti
`$fillable`. Harap perhatikan saat menulis tooling yang melakukan introspeksi
`$fillable`.

### Inti domain

| Model | Tabel | Relasi kunci |
| --- | --- | --- |
| `User` | `users` | `marga`, `managedMargas`, `currentPerson`, `familyTrees`, dan banyak lagi. |
| `Person` | `people` | `marga`, `father`, `mother`, `children`, `wives`/`husbands`, `siblings`, `familyTrees`. |
| `Marga` | `margas` | `people`, `identityPerson`, `news`, `managedByUsers`, `accessRequests`. |
| `FamilyTree` | `family_trees` | `user`, `rootPerson`, `people`, `nodes`, `shares`. |
| `FamilyTreeNode` | `family_tree_nodes` | `familyTree`, `person`, `fatherNode`/`motherNode`, `children`. |

### Workflow & review

`ContributionRequest`, `IdentityRequest`, `MargaAccessRequest`,
`FamilyTreeAppendRequest`, `FamilyTreeDeletionRequest`, `TreeActivityLog`,
`TreeChangeRequest`, `FamilyTreeActivity`, `ActivityLog`.

### Konten & feed

`Story`, `Event`, `FeedPost`, `FeedPostLike`, `FeedPostComment`, `FeedPostImage`,
`FeedItemLike`, `FeedItemComment` (dua terakhir polimorfik agar story/event
juga bisa di-like/dikomentar).

### Messaging

`Conversation`, `Message`, `MessageAttachment`, `ChatGroup`, `ChatGroupMember`,
`GroupMessage`, `MargaChatConversation`, `ContactRequest`, `ContactDisconnect`.

### Berita marga

`MargaNews`, `MargaNewsTopic`, `MargaNewsSource`, `MargaNewsAutomationSetting`
(singleton).

### Telegram

`TelegramAccount`, `TelegramAuthSession`, `TelegramDialog`, `TelegramMessage`,
`TelegramUpdate`, `TelegramLinkToken`, `TelegramAnnouncement`,
`TelegramAnnouncementRecipient`.

### Gambar & tarombo

`TaromboFrame`, `TaromboSnapshot`, `TaromboCompileDraft`.

### Auth

`OAuthAccount`.

---

## Policy (9 file)

| Policy | Aturan inti |
| --- | --- |
| `PersonPolicy` | `viewAny` publik bila `is_public`, else butuh auth. `update`/`delete` dibatasi staff, kontributor marga, pemilik, atau claimant. |
| `FamilyTreePolicy` | `view` bila pemilik, akses marga disetujui, kontributor root marga, atau share diterima. `update`/`delete` hanya pemilik. |
| `EventPolicy` | `create` staff/anggota marga. `update`/`delete` pembuat atau staff. `approve`/`reject` kontributor+. |
| `StoryPolicy` | Bentuk sama seperti `EventPolicy`. |
| `FeedPostPolicy` | `view` bila publik, penulis, atau audiens marga cocok. `update` penulis. `delete` penulis/staff. |
| `ChatGroupPolicy` | `view` bila anggota. `update`/`delete` pemilik. `announce` pemilik atau sub-admin marga yg sama. |
| `TaromboSnapshotPolicy` | `download` khusus staff. `delete` pemilik. |
| `ContactRequestPolicy` | `review` penerima, selama masih pending. |
| `IdentityRequestPolicy` | `review` kontributor+, admin atau marga sama. `cancel` admin. |

---

## Middleware (8 file)

| Middleware | Alias | Fungsi |
| --- | --- | --- |
| `EnsureUserIsAdmin` | `role.admin` | Wajib admin. |
| `EnsureUserIsStaff` | `role.staff` | Wajib staff (admin/subadmin). |
| `EnsureUserCanReviewContributions` | `role.contributor` | Wajib kontributor. |
| `EnsureUserIsActive` | — | Logout akun non-aktif. |
| `HandleAppearance` | — | Tema gelap/terang dari cookie. |
| `HandleInertiaRequests` | — | Share data global ke Inertia. |
| `LogSubAdminActivity` | — | Audit aksi sub-admin. |
| `AuthenticateMargaNewsAgent` | — | Auth API Hermes (Bearer token). |

---

## Job, Event, Notification

**Job (3):**

- `ProcessTelegramUpdate` — proses update Telegram (unique, backoff).
- `SendGroupMessageToTelegram` — kirim pesan grup ke Telegram.
- `SendTelegramAnnouncementRecipient` — fan-out pengumuman per penerima.

**Event (4, broadcast via Reverb):**

- `MessageSent`, `MessageRead` (channel privat percakapan).
- `GroupMessageSent` (channel `groups.{id}`, `ShouldDispatchAfterCommit`).
- `TelegramMessageReceived` (`ShouldBroadcastNow`).

**Notification (6):** `EventSubmitted`, `StorySubmitted`, `FatherMatchSubmitted`,
`FamilyTreeAppendSubmitted`, `FamilyTreeDeletionSubmitted`,
`MargaAccessRequested`.

Tidak ada directory `app/Listeners`.

---

## Frontend

### Entry point & layout

- `resources/js/app.tsx` — satu-satunya entry. Mengatur Echo, Google Analytics
  (page-view per navigasi Inertia), pemilihan layout per halaman, `Toaster`,
  `TooltipProvider`, dan inisialisasi tema.
- Resolusi layout: halaman tanpa layout (landing & auth tertentu), `AuthLayout`
  (auth), `[AppLayout, SettingsLayout]` (settings), `AppDashboardLayout`
  (dashboard), default `AppLayout`.

Layout (9 file) di `resources/js/layouts/`:

```
app-layout.tsx                 → wrapper ke app/app-sidebar-layout
app/app-sidebar-layout.tsx
app/app-header-layout.tsx
app/app-dashboard-layout.tsx
auth-layout.tsx
auth/auth-card-layout.tsx
auth/auth-simple-layout.tsx
auth/auth-split-layout.tsx
settings/layout.tsx
```

### Direktori

| Direktori | Isi |
| --- | --- |
| `pages/` (64) | Halaman Inertia, dikelompokkan per domain. |
| `components/ui/` (27) | Wrapper shadcn/Radix (button, dialog, select, sidebar, tab, dll). |
| `components/landing/` (14) | Komponen landing + `landing/diagram/` (node & edge React Flow). |
| `components/people/` (8) | Kartu node, pohon keturunan, dialog ringkasan person, dsb. |
| `components/tarombo/` (3) | `tarombo-explorer.tsx` (terbesar, ±2700 baris), pengaturan gaya, pemilih pohon. |
| `components/news-feed/`, `components/chat/`, `components/marga-news/` | Kartu feed, kartu lampiran, bagian berita. |
| `data/` (6) | `tarombo-tree.ts` (algoritma inti), `budaya`, `komunitas`, `landing`, `tentang`, `tarombo-tree.json`. |
| `hooks/` (8) | `use-appearance`, `use-clipboard`, `use-flash-toast`, `use-media-query`, dll. |
| `lib/` (5) | `utils.ts` (`cn`, `toUrl`), `google-analytics.ts`, komposisi canvas & teks tarombo. |
| `types/` | `auth`, `navigation`, `ui`, deklarasi global. |

### Styling

Tailwind v4 dengan konfigurasi CSS-first di `resources/css/app.css`:

- `@import 'tailwindcss'` + `@import 'tw-animate-css'`.
- `@theme` mendefinisikan palet Batak (`--color-tb-primary: #b34b1e`,
  `tb-surface`, `tb-outline`, dll) serta token font, plus token shadcn.
- `@custom-variant dark` untuk mode gelap; blok `.dark` mengulang token `tb-*`.
- Keyframes kustom: `tb-node-in`, `tb-edge-flow`, `tb-pulse`,
  `tb-gorga-drift`, `tb-sheen`, `tb-theme-reveal`, dengan blok
  `prefers-reduced-motion`.

Tidak ada `tailwind.config.js`; Tailwind berjalan lewat plugin Vite
`@tailwindcss/vite`.

### Wayfinder (route typed)

Helper route frontend di-generate otomatis ke:

- `resources/js/routes/` — helper per nama route.
- `resources/js/actions/` — helper per controller action.

> Direktori ini **generated** dan tidak diedit manual. Jalankan
> `php artisan wayfinder:generate --with-form` setiap ada perubahan route.
> Plugin Vite `wayfinder({ formVariants: true })` mengotomatiskannya saat dev.

### i18n

Belum ada i18n. Semua copy hard-coded dalam Bahasa Indonesia, diformat dengan
`Intl` locale `id-ID`. Locale aplikasi `id` berasal dari `app()->getLocale()`.

---

## Konvensi Penting

- **Model pakai `#[Fillable]`** (atribut Laravel 13), bukan `$fillable`.
- **Penghapusan person diblokir bila masih direferensikan** sebagai orang tua.
  Form keluarga bersifat non-destructive: baris yang dihapus dari form tidak
  otomatis hilang dari database (dihapus lewat alur Data Anggota).
- **Publikasi person hanya staff**; `is_public` default private.
- **Rantai `chain` harus sinkron dengan `father_id` + `birth_order`.** Setelah
  reparenting, jalankan `php artisan people:recompute-chain` bila perlu.
- **Tidak ada listener**; event langsung broadcast atau didispatch ke job.
