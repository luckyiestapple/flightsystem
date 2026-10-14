# Airport Operations REST API

Dokumentasi ini menjelaskan API CRUD JSON untuk seluruh tabel pada migration
`CreateAirportOperations`: `airlines`, `airports`, `routes`, dan `flights`.
Semua endpoint menggunakan prefix `/api/flights`.

## Persiapan

Migration saat ini ditulis khusus untuk PostgreSQL dan membuat schema
`airport_operations`. Konfigurasi koneksi `default` membaca nilai berikut dari
environment. CI4 memuat `.env` seperti biasa; jika `.env` tidak ada, konfigurasi
database proyek ini memuat file `env`:

```ini
database.default.hostname = localhost
database.default.database = nama_database
database.default.username = nama_user
database.default.password = kata_sandi
database.default.DBDriver = Postgre
database.default.port = 5432
```

Driver default adalah `Postgre` dan port default `5432`. Isi nilai koneksi sesuai
server PostgreSQL Anda sebelum menjalankan:

```sh
php spark migrate
php spark routes
```

Kirim dan terima data sebagai JSON dengan header:

```http
Content-Type: application/json
Accept: application/json
```

API ini belum memasang autentikasi atau otorisasi. Pasang filter autentikasi
sebelum mengekspos endpoint ke jaringan publik.

## Daftar endpoint

`{resource}` adalah salah satu dari `airlines`, `airports`, `routes`, atau
`flights`. `{id}` adalah UUID record.

| Method | Endpoint | Kegunaan |
| --- | --- | --- |
| `POST` | `/api/flights/{resource}` | Membuat record |
| `GET` | `/api/flights/{resource}` | Mengambil seluruh data |
| `GET` | `/api/flights/search/{resource}` | Mencari, memfilter, dan mengurutkan data |
| `GET` | `/api/flights/{resource}/{id}` | Mengambil detail satu record |
| `PUT` | `/api/flights/{resource}/{id}` | Mengganti data record |
| `PATCH` | `/api/flights/{resource}/{id}` | Mengubah sebagian field |
| `DELETE` | `/api/flights/{resource}/{id}` | Menghapus record |

Collection `GET` juga menerima query pencarian, filter, dan sorting yang sama
dengan endpoint `/api/flights/search/{resource}`.

## Bentuk respons

Semua respons sukses maupun gagal menggunakan tiga key yang sama:
`status`, `data`, dan `message`. Respons sukses memakai `status: "success"`
dan `message: null`; respons gagal memakai `status: "error"`, `data: null`,
dan `message` berisi keterangan error.

```json
{
  "status": "success",
  "data": {
    "flight_id": "uuid-flight-001",
    "flight_number": "GA204",
    "flight_status": "SCHEDULED"
  },
  "message": null
}
```

Pada collection/search, `data` berupa array record (termasuk array kosong jika
tidak ditemukan). Pada detail/create/update, `data` berupa object record.
Delete sukses mengembalikan `data: null`. Create mengembalikan HTTP `201`;
operasi sukses lainnya mengembalikan HTTP `200`.

Kesalahan validasi berstatus `422`; rincian kesalahan field disertakan dalam
string `message`. JSON tidak valid atau parameter query tidak valid
mengembalikan `400`; record tidak ditemukan mengembalikan `404`; penghapusan
record yang masih direferensikan mengembalikan `409`. Jika record yang akan
dihapus sudah tidak ada, DELETE juga mengembalikan `404` dengan pesan
`Data tidak ditemukan.`.

Contoh kesalahan:

```json
{
  "status": "error",
  "data": null,
  "message": "airline_code: The airline_code field is required."
}
```

## Schema dan payload

Field ID dibuat oleh API dan tidak boleh dikirim saat create/update. Field
timestamp dikelola database/API, bukan payload klien. Field yang tidak tercantum
sebagai writable akan ditolak dengan `422`.

### Airlines

Tabel `airport_operations.airlines`.

| Field | Tipe/skema | Create | Keterangan |
| --- | --- | --- | --- |
| `airline_id` | UUID, PK | otomatis | Dibuat oleh API |
| `airline_code` | string, maks. 10, unik | wajib | Alfanumerik |
| `airline_name` | string, maks. 150 | wajib | Nama maskapai |
| `country` | string, maks. 100 | wajib | Negara |
| `contact` | string/null, maks. 100 | opsional | Boleh `null`, string kosong ditolak |
| `status` | `ACTIVE` atau `INACTIVE` | opsional | Default `ACTIVE` |
| `created_at` | timestamp | otomatis | Dibuat database |

Contoh:

```http
POST /api/flights/airlines
```

```json
{
  "airline_code": "GA",
  "airline_name": "Garuda Indonesia",
  "country": "Indonesia",
  "contact": "+62-21-555-0100",
  "status": "ACTIVE"
}
```

### Airports

Tabel `airport_operations.airports`.

| Field | Tipe/skema | Create | Keterangan |
| --- | --- | --- | --- |
| `airport_id` | UUID, PK | otomatis | Dibuat oleh API |
| `airport_code` | string, maks. 10, unik | wajib | Alfanumerik |
| `airport_name` | string, maks. 150 | wajib | Nama bandara |
| `city` | string, maks. 100 | wajib | Kota |
| `country` | string, maks. 100 | wajib | Negara |
| `timezone` | string, maks. 50 | wajib | Contoh `Asia/Jakarta` |
| `status` | `ACTIVE` atau `INACTIVE` | opsional | Default `ACTIVE` |
| `created_at`, `updated_at` | timestamp | otomatis | `updated_at` diperbarui saat PUT/PATCH |

### Routes

Tabel `airport_operations.routes`.

| Field | Tipe/skema | Create | Keterangan |
| --- | --- | --- | --- |
| `route_id` | UUID, PK | otomatis | Dibuat oleh API |
| `origin_airport_id` | UUID, FK ke airport | wajib | Harus menunjuk bandara yang ada |
| `destination_airport_id` | UUID, FK ke airport | wajib | Harus ada dan berbeda dari asal |
| `distance_km` | angka desimal positif/null | opsional | Boleh `null` |
| `estimated_duration_minutes` | integer positif/null | opsional | Boleh `null` |
| `created_at` | timestamp | otomatis | Dibuat database |

Contoh:

```json
{
  "origin_airport_id": "850a46d0-79c0-4db4-9241-51bdaea8f57d",
  "destination_airport_id": "01a423ce-cc53-4c18-a522-4de8ba31b2d5",
  "distance_km": 1124.50,
  "estimated_duration_minutes": 110
}
```

### Flights

Tabel `airport_operations.flights`.

| Field | Tipe/skema | Create | Keterangan |
| --- | --- | --- | --- |
| `flight_id` | UUID, PK | otomatis | Dibuat oleh API |
| `flight_number` | string, maks. 20 | wajib | Nomor penerbangan |
| `airline_id` | UUID, FK ke airline | wajib | Harus menunjuk maskapai yang ada |
| `aircraft_id` | UUID/null | opsional | Tidak mempunyai tabel/FK pada migration saat ini |
| `route_id` | UUID, FK ke route | wajib | Harus menunjuk rute yang ada |
| `departure_gate_id` | UUID/null | opsional | Tidak mempunyai tabel/FK pada migration saat ini |
| `arrival_gate_id` | UUID/null | opsional | Tidak mempunyai tabel/FK pada migration saat ini |
| `scheduled_departure` | timestamp | wajib | Format `YYYY-MM-DD HH:MM:SS` |
| `estimated_departure` | timestamp/null | opsional | Format yang sama |
| `actual_departure` | timestamp/null | opsional | Format yang sama |
| `scheduled_arrival` | timestamp | wajib | Harus setelah scheduled departure |
| `estimated_arrival` | timestamp/null | opsional | Format yang sama |
| `actual_arrival` | timestamp/null | opsional | Format yang sama |
| `flight_status` | enum status | opsional | Default `SCHEDULED` |
| `created_at`, `updated_at` | timestamp | otomatis | `updated_at` diperbarui saat PUT/PATCH |

Status yang diterima: `SCHEDULED`, `BOARDING`, `DELAYED`, `DEPARTED`,
`ARRIVED`, `CANCELLED`, dan `DIVERTED`.

Contoh:

```json
{
  "flight_number": "GA204",
  "airline_id": "4d206f9c-257d-4db2-a5ca-67a337a7cc1b",
  "route_id": "c974888f-ec55-46a8-885e-2d790a1868b6",
  "scheduled_departure": "2026-11-10 08:00:00",
  "scheduled_arrival": "2026-11-10 10:00:00",
  "flight_status": "SCHEDULED"
}
```

## Membaca seluruh data

```http
GET /api/flights/flights
```

Collection mengembalikan semua record tanpa pagination atau metadata tambahan.
Parameter `page` dan `per_page` tidak digunakan.

Detail:

```http
GET /api/flights/flights/c974888f-ec55-46a8-885e-2d790a1868b6
```

## Edit data

### PUT — mengganti data

PUT menerima field non-nullable yang wajib untuk resource tersebut. Field
nullable yang tidak dikirim akan dikosongkan (`null`); field nullable yang
disertakan juga boleh diberi nilai `null`. Field status harus selalu dikirim
untuk airline, airport, dan flight. Object kosong, field wajib yang hilang,
`null` pada field wajib, dan string kosong (termasuk string yang hanya berisi
spasi) ditolak dengan HTTP `422`. Untuk mengosongkan field nullable kirim
`null`, bukan string kosong. Field non-nullable yang dikirim dengan `null` juga
ditolak.

```http
PUT /api/flights/airlines/{airline_id}
```

```json
{
  "airline_code": "GA",
  "airline_name": "Garuda Indonesia",
  "country": "Indonesia",
  "status": "ACTIVE"
}
```

### PATCH — mengubah sebagian field

PATCH hanya mengubah field yang dikirim. Field lain tetap seperti semula.
Kirim setidaknya satu field. Untuk menghapus nilai field nullable, kirim
`null` secara eksplisit. Object kosong, nilai string kosong, dan nilai yang
hanya berisi spasi ditolak dengan HTTP `422`. `null` ditolak untuk field
non-nullable.

```http
PATCH /api/flights/airlines/{airline_id}
```

```json
{
  "contact": null
}
```

## Hapus data

```http
DELETE /api/flights/airlines/{airline_id}
```

Airport yang dipakai route, airline yang dipakai flight, dan route yang dipakai
flight tidak dapat dihapus (`409 Conflict`). Hapus atau ubah relasi yang
menggunakannya terlebih dahulu. Penghapusan flight yang tidak direferensikan
mengembalikan:

```json
{
  "status": "success",
  "data": null,
  "message": null
}
```

## Pencarian, filter, dan sorting

Pencarian teks dilakukan case-insensitive pada kolom teks yang didukung. Filter
bersifat exact-match; beberapa filter sekaligus digabungkan dengan kondisi AND.
Gunakan `/api/flights/search/{resource}` atau collection endpoint.

```http
GET /api/flights/search/airports?q=jakarta&filter[status]=ACTIVE&sort=airport_name&order=ASC
```

Untuk URL yang mengharuskan encoding bracket, gunakan
`filter%5Bstatus%5D=ACTIVE`.

Kolom `q` dan filter per resource:

| Resource | `q` mencari pada | Field yang dapat difilter |
| --- | --- | --- |
| `airlines` | `airline_code`, `airline_name`, `country`, `contact` | `airline_code`, `airline_name`, `country`, `status` |
| `airports` | `airport_code`, `airport_name`, `city`, `country`, `timezone` | `airport_code`, `city`, `country`, `timezone`, `status` |
| `routes` | Tidak tersedia; gunakan filter exact | `origin_airport_id`, `destination_airport_id`, `distance_km`, `estimated_duration_minutes` |
| `flights` | `flight_number`, `flight_status` | `flight_number`, `airline_id`, `aircraft_id`, `route_id`, `departure_gate_id`, `arrival_gate_id`, `flight_status`, `scheduled_departure`, `estimated_departure`, `actual_departure`, `scheduled_arrival`, `estimated_arrival`, `actual_arrival` |

Contoh mencari flight maskapai tertentu:

```http
GET /api/flights/search/flights?filter[airline_id]=4d206f9c-257d-4db2-a5ca-67a337a7cc1b&filter[flight_status]=SCHEDULED
```

Sorting menggunakan `sort` dan `order` (`ASC` atau `DESC`). Hanya kolom berikut
yang diterima:

- `airlines`: `airline_code`, `airline_name`, `country`, `status`, `created_at`.
- `airports`: `airport_code`, `airport_name`, `city`, `country`, `timezone`,
  `status`, `created_at`, `updated_at`.
- `routes`: `origin_airport_id`, `destination_airport_id`, `distance_km`,
  `estimated_duration_minutes`, `created_at`.
- `flights`: `flight_number`, `flight_status`, `scheduled_departure`,
  `scheduled_arrival`, `created_at`, `updated_at`.

Jika sorting tidak dikirim, hasil diurutkan berdasarkan primary key ascending.
Field filter/sort di luar whitelist atau nilai `order` yang tidak valid
menghasilkan HTTP `400`.
