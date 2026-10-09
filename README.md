# REST API Mahasiswa (PHP + MySQL)

Proyek praktikum Pemrograman Database Client Server, pertemuan 1 sampai 4.

## Struktur Folder

```
PB-2511500005/
├── api/
│   └── mahasiswa.php        # Endpoint GET (semua, id, search, pagination) & POST
├── helpers/
│   └── response.php         # Fungsi sendResponse() untuk format JSON seragam
├── screenshots/             # Screenshot hasil Thunder Client (GET & POST)
├── config.php               # Koneksi MySQL (mysqli) dengan error handling
├── database.sql             # Struktur tabel + data awal (jurusan, mahasiswa, matakuliah)
├── setup.php                # Pengganti phpMyAdmin: jalankan database.sql dari browser
└── README.md
```

## Cara Menjalankan (tanpa phpMyAdmin)

1. Copy folder ini ke `C:\laragon\www\PB-2511500005`.
2. Buka Laragon, klik **Start All** (Apache dan MySQL harus jalan).
3. Buat database dengan salah satu cara berikut:

   **Cara A (paling mudah): setup.php**
   Buka di browser: `http://localhost/PB-2511500005/setup.php`
   (atau `localhost:8080` jika port Apache kamu 8080).
   Setelah muncul pesan berhasil, **hapus file setup.php**.

   **Cara B: Terminal Laragon**
   Klik tombol Terminal di Laragon, masuk ke folder proyek, lalu jalankan:
   ```
   mysql -u root < database.sql
   ```

   **Cara C: HeidiSQL (sudah ada di Laragon)**
   Buka Database > HeidiSQL, buka file `database.sql`, lalu tekan F9 (Run).

   **Cara D: DBeaver**
   Buka `database.sql`, lalu jalankan dengan Execute Script (Alt+X).

4. Cek di config.php: `user = root` dan `pass = ""` sudah sesuai setting kamu.

## Uji Endpoint dengan Thunder Client

Base URL: `http://localhost/PB-2511500005/api/mahasiswa.php`

| Tes | Method | URL tambahan | Status yang diharapkan |
|-----|--------|--------------|------------------------|
| Semua data | GET | (kosong) | 200 |
| Satu data | GET | `?id=1` | 200 (404 jika tidak ada) |
| Pencarian | GET | `?search=siska` | 200 |
| Pagination | GET | `?page=1&limit=3` | 200 |
| Tambah data | POST | (kosong) | 201 |
| NIM ganda | POST | (kosong) | 409 |
| Jurusan hantu | POST | (kosong) | 404 |
| Field kosong | POST | (kosong) | 400 |
| Method lain | PUT / DELETE | (kosong) | 405 |

Contoh body POST (Body > Raw > JSON), header `Content-Type: application/json`:

```json
{
  "nama": "Citra Dewi",
  "nim": "22123458",
  "jurusan_id": 1
}
```

## Commit dan Push ke GitHub

```
git init
git add .
git commit -m "REST API mahasiswa: GET, search, pagination, POST"
git branch -M main
git remote add origin https://github.com/<username>/<repo>.git
git push -u origin main
```

Lalu kumpulkan URL repository GitHub ke Portal LMS (elearning.atmaluhur.ac.id).
