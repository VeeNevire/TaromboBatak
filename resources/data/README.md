# Data wilayah Indonesia

Data wilayah disimpan lokal agar aplikasi tidak bergantung pada API eksternal:

- `indonesia-regions.json`: 38 provinsi dan 514 kabupaten/kota.
- `indonesia-districts/`: 514 file JSON per kabupaten/kota, berisi 7.285 kecamatan.
- `indonesia-villages/`: 514 file JSON per kabupaten/kota, berisi 83.762 desa/kelurahan.

Kode wilayah mengikuti Keputusan Menteri Dalam Negeri tahun 2025.

Data tingkat provinsi dan kabupaten/kota diadaptasi dari proyek MIT
[`cahyadsn/wilayah`](https://github.com/cahyadsn/wilayah). Kode wilayah tetap
memakai format resmi Kemendagri, misalnya `12` untuk Sumatera Utara dan `12.71`
untuk Kota Medan.

Data kecamatan dan desa/kelurahan diadaptasi dari dataset CC BY 4.0
[`indrayoga/data-wilayah-indonesia`](https://github.com/indrayoga/data-wilayah-indonesia),
yang bersumber dari data Kepmendagri Nomor 300.2.2-2138 Tahun 2025.
