# Flight Operations API

REST API untuk mengelola maskapai, bandara, rute, dan penerbangan. Aplikasi
dibangun dengan CodeIgniter 4 dan PostgreSQL. Data Place dan Pilot diambil dari
dua project API terpisah melalui konfigurasi URL.

## Persiapan lokal

Persyaratan: PHP 8.2+, Composer, PostgreSQL, dan ekstensi PHP yang dibutuhkan
CodeIgniter 4 (`intl`, `mbstring`, `json`, dan `curl`).

1. Install dependency:

   ```powershell
   composer install
   ```

2. Salin `env` menjadi `.env` jika file `.env` belum tersedia.
3. Atur `app.baseURL`, koneksi PostgreSQL, dan URL project Place/Pilot di `.env`:

   ```ini
   app.baseURL = 'http://localhost:8080/'
   database.default.hostname = localhost
   database.default.database = flight_db
   database.default.username = postgres
   database.default.password = your_password
   database.default.DBDriver = Postgre
   database.default.port = 5432

   api.place = http://IP-PLACE:PORT
   api.pilot = http://IP-PILOT:PORT
   ```

   `app.baseURL` adalah URL aplikasi Flight dan diakhiri `/`. URL Place dan
   Pilot harus berupa alamat root yang dapat dijangkau dari mesin Flight,
   tanpa suffix `/api`. Restart server setelah mengubah `.env`.
4. Jalankan migration dan server:

   ```powershell
   php spark migrate
   php spark serve
   ```

Server lokal tersedia di `http://localhost:8080`. Seeder hanya untuk mengisi
data lokal/dev; periksa isi seed sebelum menjalankannya pada database lain:

```powershell
php spark db:seed DatabaseSeeder
```

## Endpoint utama

Resource lokal menggunakan prefix `/api`:

| Resource | Collection | Detail |
| --- | --- | --- |
| Airlines | `/api/airlines` | `/api/airlines/{id}` |
| Airports | `/api/airports` | `/api/airports/{id}` |
| Routes | `/api/routes` | `/api/routes/{id}` |
| Flights | `/api/flights` | `/api/flights/{id}` |

Resource lokal mendukung GET collection/detail, POST, PUT, PATCH, dan DELETE.
Endpoint pencarian tersedia di `/api/search/{resource}`.

Endpoint Place dan Pilot meneruskan GET ke project terpisah:

| Data | Collection | Detail |
| --- | --- | --- |
| Place | `/api/place/` | `/api/places/{id}` |
| Pilot | `/api/pilots` | `/api/pilots/{id}` |

`place_id` dan `pilot_id` pada flight menyimpan ID dari API eksternal. Ketika
membaca flight, respons juga memuat detail terkait pada property `place` dan
`pilot`.

## Struktur project

```text
app/
  Config/          Konfigurasi aplikasi, route, database, API eksternal
  Controllers/     Handler HTTP dan endpoint API
  Database/        Migration, seeder, dan factory
  Libraries/       Klien HTTP API Place/Pilot dan error integrasi
  Models/          Akses tabel database
  Views/           Halaman web CodeIgniter
docs/              Referensi API, contoh cURL, koleksi Postman
tests/
  feature/         Pengujian endpoint dan alur API
public/            Document root web server
```

Struktur `app/` mengikuti konvensi CodeIgniter agar route, autoload, migration,
dan konfigurasi mudah ditemukan. File framework berada di `vendor/`; jangan
mengeditnya untuk mengubah perilaku aplikasi.

## Dokumentasi dan verifikasi

- [Dokumentasi API dan schema](docs/README.md)
- [Contoh request cURL untuk PowerShell](docs/curl.md)
- [Koleksi Postman](docs/postman_collection.json)
- [Environment Postman](docs/postman_environment.json)

Di Postman, impor koleksi dan environment, lalu samakan variabel `base_url`
dengan `app.baseURL` pada `.env` (tanpa trailing `/`). Postman tidak membaca
file `.env` aplikasi secara otomatis.

Jalankan seluruh test:

```powershell
php vendor\bin\phpunit --no-coverage
```

API belum memiliki autentikasi/otorisasi. Gunakan di lingkungan lokal atau
jaringan tepercaya sampai proteksi akses ditambahkan.
