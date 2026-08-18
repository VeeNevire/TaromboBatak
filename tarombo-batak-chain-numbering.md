# Sistem Penomoran Chain — Tarombo Batak

## 1. Konsep Dasar

Setiap orang dalam silsilah memiliki kode unik berupa string angka yang disebut **chain**. Chain ini merepresentasikan posisi seseorang dalam pohon keturunan, dibaca dari leluhur paling atas hingga ke individu tersebut.

Aturan dasarnya:
- Generasi pertama (leluhur/root) diberi nomor urut 1 digit: `1`, `2`, `3`, dst (jika ada lebih dari satu leluhur root).
- Setiap anak mewarisi chain milik orang tuanya, lalu menambahkan urutan kelahirannya sendiri di belakang.
- Urutan anak dihitung berdasarkan urutan kelahiran (anak pertama = 1, anak kedua = 2, dst).

### Contoh

```
Budi (leluhur)              → chain: 1
├─ Edo (anak ke-1 Budi)      → chain: 1-1
│  ├─ Samsul (anak ke-1 Edo) → chain: 1-1-1
│  └─ Kito (anak ke-2 Edo)   → chain: 1-1-2
└─ Eko (anak ke-2 Budi)      → chain: 1-2
   └─ Miko (anak ke-1 Eko)   → chain: 1-2-1
```

Chain dibaca sebagai "jalur" dari root ke individu. Panjang chain (jumlah segmen) menunjukkan generasi ke berapa orang tersebut berada.

## 2. Format Penulisan Chain

Chain **tidak disimpan sebagai satu digit per generasi tanpa pemisah**, karena akan ambigu jika ada anak ke-10 atau lebih. Sebagai gantinya, digunakan salah satu dari dua pendekatan berikut:

### Opsi A — Separator (direkomendasikan)
Setiap segmen generasi dipisahkan tanda hubung `-`.
```
1-1-2      → anak ke-2 dari anak ke-1 dari leluhur ke-1
1-2-1      → anak ke-1 dari anak ke-2 dari leluhur ke-1
1-10-3     → tetap jelas meskipun ada anak ke-10
```

### Opsi B — Padding tetap (2 digit per segmen)
Setiap segmen dipaksa jadi 2 digit dengan leading zero.
```
01011      → segmen: 01, 01, dst — kurang terbaca manusia tapi lebih pendek
```

**Rekomendasi: gunakan Opsi A (separator `-`)** karena lebih mudah dibaca, di-debug, dan tidak dibatasi jumlah anak (bisa lebih dari 99 anak tanpa masalah parsing).

## 3. Struktur Data

Chain **bukan** satu-satunya sumber kebenaran (source of truth). Struktur data yang digunakan adalah pendekatan **hybrid**:

| Kolom | Tipe | Fungsi |
|---|---|---|
| `id` | integer/UUID | primary key |
| `parent_id` | integer/UUID (nullable) | relasi ke orang tua langsung — **source of truth** hierarki |
| `chain` | string | representasi jalur silsilah — **cache** untuk query cepat |
| `birth_order` | integer | urutan kelahiran di antara saudara kandung |

**Kenapa tetap butuh `parent_id` meskipun sudah ada `chain`?**
Karena jika suatu saat data perlu dikoreksi (misalnya ternyata seseorang salah dimasukkan sebagai anak dari orang yang salah), cukup update `parent_id`-nya saja. Sistem lalu meregenerasi `chain` untuk orang itu dan seluruh keturunannya secara otomatis. Jika hanya mengandalkan `chain` sebagai satu-satunya data, koreksi seperti ini akan sangat sulit dan rawan salah.

## 4. Logika Generate Chain

Saat individu baru ditambahkan sebagai anak dari seseorang:

1. Ambil `chain` milik `parent_id`.
2. Hitung jumlah anak yang sudah terdaftar dari parent tersebut → tentukan `birth_order` anak baru (jumlah anak existing + 1).
3. Chain anak baru = `chain milik parent` + `-` + `birth_order`.
   - Jika parent adalah root (tidak punya parent), chain anak = `birth_order` root itu sendiri (chain 1 digit).

Jika `parent_id` sebuah individu diubah (dipindahkan ke orang tua lain), maka:
1. Chain individu tersebut diregenerasi berdasarkan `parent_id` baru.
2. Semua keturunan individu tersebut (dicari lewat pencocokan awalan/prefix chain lama) ikut diregenerasi chain-nya, karena chain mereka semua diturunkan dari chain leluhur mereka.

## 5. Manfaat Query dari Struktur Ini

Dengan chain berbasis prefix, query pencarian keturunan jadi sederhana:

- **Semua keturunan Edo** (chain `1-1`) → cari semua baris yang chain-nya diawali `1-1-`
- **Generasi tertentu** → hitung jumlah segmen (dipisah `-`) pada chain
- **Orang tua langsung** → tetap pakai `parent_id`, tidak perlu parsing chain
- **Silsilah lengkap seseorang (dari root sampai dia)** → pecah chain berdasarkan `-`, lalu tarik tiap leluhur berdasarkan chain prefix di setiap level

## 6. Catatan untuk Implementasi

- Chain **tidak boleh** dijadikan primary key atau digunakan untuk identifikasi permanen, karena nilainya bisa berubah kalau ada koreksi struktur keturunan.
- Regenerasi chain sebaiknya dipicu otomatis saat data hierarki berubah (insert anak baru, atau update `parent_id`), bukan dihitung manual setiap kali.
- Untuk leluhur root ganda (lebih dari satu marga/silsilah awal dalam satu sistem), root pertama mendapat chain `1`, root kedua `2`, dan seterusnya — mengikuti aturan urutan yang sama seperti level anak.
