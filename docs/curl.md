# Uji API dengan cURL

Contoh berikut memakai `curl.exe` dari Windows PowerShell dan server lokal CI4
di `http://localhost:8080`. Sesuaikan `$baseUrl` jika server Anda menggunakan
host atau port lain; nilainya harus sama dengan `app.baseURL` di `.env` tanpa
trailing slash. Windows PowerShell tidak membaca file `.env` aplikasi secara
otomatis. Pastikan migrasi PostgreSQL sudah dijalankan dan API dapat terhubung
ke database.

Seluruh contoh memakai resource `airlines` agar tidak memerlukan data relasi
seperti airport atau route. Skrip membuat airline dengan kode unik acak. Simpan
dan jalankan perintah dalam **sesi PowerShell yang sama**, karena `$airlineId`
dipakai oleh beberapa langkah berikutnya.

## 1. Ambil semua data

```powershell
$baseUrl = "http://localhost:8080"
curl.exe -i -H "Accept: application/json" "$baseUrl/api/airlines"
```

HTTP yang diharapkan: `200 OK`. `data` berisi array semua airline, tanpa
pagination.

## 2. Input data

Kode airline dibuat secara acak supaya tidak bentrok dengan kode unik yang
mungkin sudah ada. Respons create memberikan `airline_id` yang dipakai
selanjutnya.

```powershell
$airlineCode = "T" + (Get-Random -Minimum 100000000 -Maximum 999999999)
$createBody = @{
    airline_code = $airlineCode
    airline_name = "Airline Uji Coba"
    country      = "Indonesia"
    contact      = "+62-21-555-0100"
    status       = "ACTIVE"
} | ConvertTo-Json -Compress

$created = curl.exe -sS `
    -X POST "$baseUrl/api/airlines" `
    -H "Accept: application/json" `
    -H "Content-Type: application/json" `
    --data-raw $createBody | ConvertFrom-Json

$created | ConvertTo-Json -Depth 10
$airlineId = $created.data.airline_id
"Airline ID untuk langkah selanjutnya: $airlineId"
```

HTTP yang diharapkan: `201 Created`, `status: 201`, dan `data.airline_id`
terisi. Jika respons bukan sukses, periksa respons API sebelum melanjutkan.

## Uji validasi POST

Kirim kode yang sama sekali lagi untuk memicu validasi unik. HTTP yang
diharapkan adalah `400 Bad Request`, dengan pesan yang terpetakan pada field:

```powershell
$duplicateBody = @{
    airline_code = $airlineCode
    airline_name = "Kode Duplikat"
    country      = "Indonesia"
} | ConvertTo-Json -Compress

curl.exe -i `
    -X POST "$baseUrl/api/airlines" `
    -H "Accept: application/json" `
    -H "Content-Type: application/json" `
    --data-raw $duplicateBody
```

Contoh respons:

```json
{
  "status": 400,
  "error": 400,
  "messages": {
    "airline_code": "Kode T123456789 sudah digunakan."
  }
}
```

## 3. Ambil detail data

```powershell
curl.exe -i -H "Accept: application/json" "$baseUrl/api/airlines/$airlineId"
```

HTTP yang diharapkan: `200 OK`; `data` berisi object airline yang baru dibuat.

## 4. Mengambil data yang tidak ada

UUID berikut valid, tetapi digunakan sebagai ID yang tidak dibuat oleh langkah
uji coba ini.

```powershell
$missingId = "00000000-0000-4000-8000-000000000000"
curl.exe -i -H "Accept: application/json" "$baseUrl/api/airlines/$missingId"
```

HTTP yang diharapkan: `404 Not Found`:

```json
{
  "status": 404,
  "error": 404,
  "messages": {
    "_request": "Data tidak ditemukan."
  }
}
```

## 5. Mengedit data (PUT)

PUT mengganti keseluruhan field writable. Kirim semua field wajib, termasuk
`status`.

```powershell
$putBody = @{
    airline_code = $airlineCode
    airline_name = "Airline Uji Coba Diperbarui"
    country      = "Indonesia"
    contact      = "+62-21-555-0101"
    status       = "ACTIVE"
} | ConvertTo-Json -Compress

curl.exe -i `
    -X PUT "$baseUrl/api/airlines/$airlineId" `
    -H "Accept: application/json" `
    -H "Content-Type: application/json" `
    --data-raw $putBody
```

HTTP yang diharapkan: `200 OK`; `data.airline_name` berisi nama yang
diperbarui.

## 6. Update sebagian (PATCH)

PATCH hanya mengubah field yang dikirim. Contoh ini hanya memperbarui negara.

```powershell
$patchBody = @{
    country = "Indonesia - Uji PATCH"
} | ConvertTo-Json -Compress

curl.exe -i `
    -X PATCH "$baseUrl/api/airlines/$airlineId" `
    -H "Accept: application/json" `
    -H "Content-Type: application/json" `
    --data-raw $patchBody
```

HTTP yang diharapkan: `200 OK`; nilai `country` berubah, sedangkan field lain
tetap.

## 7. Tampilkan data yang dicari

Pencarian `q` pada airlines mencocokkan kode, nama, negara, dan contact.
Gunakan kode acak dari langkah input agar hasilnya menunjuk record uji coba.

```powershell
curl.exe -i -H "Accept: application/json" "$baseUrl/api/search/airlines?q=$airlineCode"
```

HTTP yang diharapkan: `200 OK`; `data` berupa array yang memuat airline dengan
`airline_code` sama dengan `$airlineCode`.

## 8. Delete data

```powershell
curl.exe -i -X DELETE -H "Accept: application/json" "$baseUrl/api/airlines/$airlineId"
```

HTTP yang diharapkan: `200 OK`, dengan pesan konfirmasi, misalnya:

```json
{
  "status": 200,
  "message": "Data dengan id <airlineId> berhasil dihapus.",
  "data": null
}
```

## 9. Delete ulang untuk memastikan data sudah terhapus

Jalankan request yang sama sekali lagi. Request kedua harus menghasilkan `404`,
yang memastikan record tidak lagi tersedia untuk dihapus:

```powershell
curl.exe -i -X DELETE -H "Accept: application/json" "$baseUrl/api/airlines/$airlineId"
```

HTTP yang diharapkan: `404 Not Found`. Anda juga dapat memastikan record sudah
tidak bisa dibaca dengan memanggil detailnya kembali:

```powershell
curl.exe -i -H "Accept: application/json" "$baseUrl/api/airlines/$airlineId"
```

Detail setelah delete juga harus menghasilkan `404 Not Found`.

## Body POST kosong, object kosong, atau JSON rusak

Body tanpa isi ditolak dengan HTTP `400` karena body bukan JSON valid:

```powershell
curl.exe -i `
    -X POST "$baseUrl/api/airlines" `
    -H "Accept: application/json" `
    -H "Content-Type: application/json" `
    --data-raw ''
```

Object JSON kosong ditolak dengan HTTP `400` karena field wajib tidak ada:

```powershell
curl.exe -i `
    -X POST "$baseUrl/api/airlines" `
    -H "Accept: application/json" `
    -H "Content-Type: application/json" `
    --data-raw '{}'
```

JSON rusak juga ditolak dengan HTTP `400`:

```powershell
curl.exe -i `
    -X POST "$baseUrl/api/airlines" `
    -H "Accept: application/json" `
    -H "Content-Type: application/json" `
    --data-raw '{'
```

## 10. Uji Airports, Routes, dan Flights

Airport, route, dan flight memiliki relasi berurutan: route memerlukan dua
airport, sedangkan flight memerlukan airline dan route. Contoh berikut membuat
data uji dari awal, lalu menjalankan request dalam urutan yang aman. Jalankan
semua perintah di sesi PowerShell yang sama.

### Buat dua airport

Kode dibuat acak agar tidak bentrok dengan airport yang sudah ada.

```powershell
$originCode = "O" + (Get-Random -Minimum 100000 -Maximum 999999)
$destinationCode = "D" + (Get-Random -Minimum 100000 -Maximum 999999)

$originBody = @{
    airport_code = $originCode
    airport_name = "Airport Asal Uji"
    city         = "Jakarta"
    country      = "Indonesia"
    timezone     = "Asia/Jakarta"
    status       = "ACTIVE"
} | ConvertTo-Json -Compress

$origin = curl.exe -sS `
    -X POST "$baseUrl/api/airports" `
    -H "Accept: application/json" `
    -H "Content-Type: application/json" `
    --data-raw $originBody | ConvertFrom-Json
$origin | ConvertTo-Json -Depth 10
$originAirportId = $origin.data.airport_id

$destinationBody = @{
    airport_code = $destinationCode
    airport_name = "Airport Tujuan Uji"
    city         = "Denpasar"
    country      = "Indonesia"
    timezone     = "Asia/Makassar"
    status       = "ACTIVE"
} | ConvertTo-Json -Compress

$destination = curl.exe -sS `
    -X POST "$baseUrl/api/airports" `
    -H "Accept: application/json" `
    -H "Content-Type: application/json" `
    --data-raw $destinationBody | ConvertFrom-Json
$destination | ConvertTo-Json -Depth 10
$destinationAirportId = $destination.data.airport_id
```

Kedua request diharapkan menghasilkan `201 Created`. Simpan
`$originAirportId` dan `$destinationAirportId` untuk membuat route.

### Baca, cari, dan edit airport

```powershell
curl.exe -i -H "Accept: application/json" "$baseUrl/api/airports"
curl.exe -i -H "Accept: application/json" "$baseUrl/api/airports/$originAirportId"
curl.exe -i --get `
    --data-urlencode "q=$originCode" `
    --data-urlencode "filter[status]=ACTIVE" `
    --data-urlencode "sort=airport_code" `
    --data-urlencode "order=ASC" `
    "$baseUrl/api/search/airports"
```

PUT mengganti data airport dan wajib menyertakan semua field wajib serta
`status`. PATCH hanya mengubah field yang dikirim.

```powershell
$airportPutBody = @{
    airport_code = $originCode
    airport_name = "Airport Asal Uji Diperbarui"
    city         = "Jakarta"
    country      = "Indonesia"
    timezone     = "Asia/Jakarta"
    status       = "ACTIVE"
} | ConvertTo-Json -Compress

curl.exe -i `
    -X PUT "$baseUrl/api/airports/$originAirportId" `
    -H "Accept: application/json" `
    -H "Content-Type: application/json" `
    --data-raw $airportPutBody

$airportPatchBody = @{ status = "INACTIVE" } | ConvertTo-Json -Compress
curl.exe -i `
    -X PATCH "$baseUrl/api/airports/$originAirportId" `
    -H "Accept: application/json" `
    -H "Content-Type: application/json" `
    --data-raw $airportPatchBody

$airportPatchBody = @{ status = "ACTIVE" } | ConvertTo-Json -Compress
curl.exe -i `
    -X PATCH "$baseUrl/api/airports/$originAirportId" `
    -H "Accept: application/json" `
    -H "Content-Type: application/json" `
    --data-raw $airportPatchBody
```

Airport asal dikembalikan ke `ACTIVE` supaya siap digunakan oleh route.

### Buat airline dan route

Flight memerlukan `airline_id` dan `route_id`. Buat airline jika belum
mempunyai ID airline yang dapat digunakan:

```powershell
$airlineCode = "T" + (Get-Random -Minimum 100000000 -Maximum 999999999)
$airlineBody = @{
    airline_code = $airlineCode
    airline_name = "Airline Uji Airport Flight"
    country      = "Indonesia"
    status       = "ACTIVE"
} | ConvertTo-Json -Compress

$airline = curl.exe -sS `
    -X POST "$baseUrl/api/airlines" `
    -H "Accept: application/json" `
    -H "Content-Type: application/json" `
    --data-raw $airlineBody | ConvertFrom-Json
$airline | ConvertTo-Json -Depth 10
$airlineId = $airline.data.airline_id
```

Buat route dengan dua airport yang baru dibuat:

```powershell
$routeBody = @{
    origin_airport_id          = $originAirportId
    destination_airport_id     = $destinationAirportId
    distance_km                = 984
    estimated_duration_minutes = 105
} | ConvertTo-Json -Compress

$route = curl.exe -sS `
    -X POST "$baseUrl/api/routes" `
    -H "Accept: application/json" `
    -H "Content-Type: application/json" `
    --data-raw $routeBody | ConvertFrom-Json
$route | ConvertTo-Json -Depth 10
$routeId = $route.data.route_id
```

Kedua request diharapkan menghasilkan `201 Created`. Airport asal dan tujuan
harus berbeda dan sudah tersimpan. Routes tidak mendukung pencarian teks `q`;
gunakan filter exact-match untuk mencari route:

```powershell
curl.exe -i --get `
    --data-urlencode "filter[origin_airport_id]=$originAirportId" `
    --data-urlencode "filter[destination_airport_id]=$destinationAirportId" `
    --data-urlencode "sort=created_at" `
    --data-urlencode "order=DESC" `
    "$baseUrl/api/search/routes"
curl.exe -i -H "Accept: application/json" "$baseUrl/api/routes/$routeId"
```

PUT route memerlukan kedua airport; field jarak dan durasi boleh dihilangkan
atau diberi `null`. PATCH hanya mengubah field yang dikirim.

```powershell
$routePutBody = @{
    origin_airport_id          = $originAirportId
    destination_airport_id     = $destinationAirportId
    distance_km                = 990
    estimated_duration_minutes = 110
} | ConvertTo-Json -Compress

curl.exe -i `
    -X PUT "$baseUrl/api/routes/$routeId" `
    -H "Accept: application/json" `
    -H "Content-Type: application/json" `
    --data-raw $routePutBody

$routePatchBody = @{ estimated_duration_minutes = 115 } | ConvertTo-Json -Compress
curl.exe -i `
    -X PATCH "$baseUrl/api/routes/$routeId" `
    -H "Accept: application/json" `
    -H "Content-Type: application/json" `
    --data-raw $routePatchBody
```

### Buat, baca, cari, dan edit flight

Tanggal berikut merupakan contoh. Flight memerlukan airline dan route yang
sudah ada; waktu tiba terjadwal harus setelah waktu berangkat.

```powershell
$flightBody = @{
    flight_number       = "GA204"
    airline_id          = $airlineId
    route_id            = $routeId
    scheduled_departure = "2026-11-10 08:00:00"
    scheduled_arrival   = "2026-11-10 10:00:00"
    flight_status       = "SCHEDULED"
} | ConvertTo-Json -Compress

$flight = curl.exe -sS `
    -X POST "$baseUrl/api/flights" `
    -H "Accept: application/json" `
    -H "Content-Type: application/json" `
    --data-raw $flightBody | ConvertFrom-Json
$flight | ConvertTo-Json -Depth 10
$flightId = $flight.data.flight_id
```

Request diharapkan menghasilkan `201 Created` dan memberikan `flight_id`.
Field `aircraft_id` dan gate bersifat opsional; jika dikirim harus berupa UUID
atau `null`. `place_id` dan `pilot_id` adalah ID eksternal opsional berupa
string hingga 255 karakter atau angka, bukan record yang dibuat oleh endpoint
API ini. Bentuk ID eksternal tidak harus UUID. Gunakan string jika ID memiliki
nol di depan, misalnya `"00123"`, agar nilainya tidak berubah saat dikirim
sebagai JSON.

```powershell
curl.exe -i -H "Accept: application/json" "$baseUrl/api/flights"
curl.exe -i -H "Accept: application/json" "$baseUrl/api/flights/$flightId"
curl.exe -i --get `
    --data-urlencode "q=GA204" `
    --data-urlencode "filter[flight_status]=SCHEDULED" `
    --data-urlencode "sort=scheduled_departure" `
    --data-urlencode "order=ASC" `
    "$baseUrl/api/search/flights"
```

PUT mengganti data flight, sehingga field wajib termasuk `flight_status` harus
dikirim. PATCH berikut hanya mengubah status:

```powershell
$flightPutBody = @{
    flight_number       = "GA204"
    airline_id          = $airlineId
    route_id            = $routeId
    scheduled_departure = "2026-11-10 08:00:00"
    scheduled_arrival   = "2026-11-10 10:00:00"
    flight_status       = "BOARDING"
} | ConvertTo-Json -Compress

curl.exe -i `
    -X PUT "$baseUrl/api/flights/$flightId" `
    -H "Accept: application/json" `
    -H "Content-Type: application/json" `
    --data-raw $flightPutBody

$flightPatchBody = @{ flight_status = "DELAYED" } | ConvertTo-Json -Compress
curl.exe -i `
    -X PATCH "$baseUrl/api/flights/$flightId" `
    -H "Accept: application/json" `
    -H "Content-Type: application/json" `
    --data-raw $flightPatchBody
```

### Hapus data sesuai urutan relasi

Flight merujuk airline dan route; route merujuk kedua airport. Hapus flight
terlebih dahulu, kemudian route, airline, dan terakhir kedua airport. Jika
record masih direferensikan, API menolak penghapusan dengan `409 Conflict`.

```powershell
curl.exe -i -X DELETE -H "Accept: application/json" "$baseUrl/api/flights/$flightId"
curl.exe -i -X DELETE -H "Accept: application/json" "$baseUrl/api/routes/$routeId"
curl.exe -i -X DELETE -H "Accept: application/json" "$baseUrl/api/airlines/$airlineId"
curl.exe -i -X DELETE -H "Accept: application/json" "$baseUrl/api/airports/$originAirportId"
curl.exe -i -X DELETE -H "Accept: application/json" "$baseUrl/api/airports/$destinationAirportId"
```

Setiap penghapusan sukses mengembalikan `200 OK` dengan `data: null`.

## 11. Mengakses API Place dan Pilot

API Place dan Pilot adalah project terpisah. Atur URL root kedua project di
`.env` aplikasi Flight sebelum menjalankan request; jangan sertakan `/api` di
nilai URL:

```ini
api.place = http://IP-PLACE:PORT
api.pilot = http://IP-PILOT:PORT
```

Ganti `IP-PLACE:PORT` dan `IP-PILOT:PORT` dengan alamat yang dapat dijangkau
dari server Flight. Contoh berikut memakai endpoint lokal Flight sebagai proxy;
response JSON dan status HTTP diteruskan dari API asal.

```powershell
curl.exe -i -H "Accept: application/json" "$baseUrl/api/place/"
curl.exe -i -H "Accept: application/json" "$baseUrl/api/places/$placeId"

curl.exe -i -H "Accept: application/json" "$baseUrl/api/pilot"
curl.exe -i -H "Accept: application/json" "$baseUrl/api/pilot/$pilotId"
```

`$placeId` dan `$pilotId` dapat diambil dari property `id` pada data collection
masing-masing API. Pilih ID tersebut untuk dikirim saat membuat atau mengubah
flight:

```powershell
$flightBody = @{
    flight_number       = "GA204"
    airline_id          = $airlineId
    route_id            = $routeId
    place_id            = $placeId
    pilot_id            = $pilotId
    scheduled_departure = "2026-11-10 08:00:00"
    scheduled_arrival   = "2026-11-10 10:00:00"
    flight_status       = "SCHEDULED"
} | ConvertTo-Json -Compress
```

API Flight menyimpan `place_id` dan `pilot_id` sebagai teks. Saat membaca flight melalui
`/api/flights` atau `/api/flights/{flightId}`, detailnya juga
diambil dari kedua project dan ditambahkan di property `place` dan `pilot`.
Jika salah satu service tidak terjangkau atau gagal mengembalikan JSON valid,
request baca flight mengembalikan error `502`; jika URL belum diisi, hasilnya
`503`. URL yang belum dikonfigurasi dan salah satu contoh error tidak mengubah
ID yang sudah tersimpan.
