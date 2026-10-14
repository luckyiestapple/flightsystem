# Uji API dengan cURL

Contoh berikut memakai `curl.exe` dari Windows PowerShell dan server lokal CI4
di `http://localhost:8080`. Sesuaikan `$baseUrl` jika server Anda menggunakan
host atau port lain. Pastikan migrasi PostgreSQL sudah dijalankan dan API dapat
terhubung ke database.

Seluruh contoh memakai resource `airlines` agar tidak memerlukan data relasi
seperti airport atau route. Skrip membuat airline dengan kode unik acak. Simpan
dan jalankan perintah dalam **sesi PowerShell yang sama**, karena `$airlineId`
dipakai oleh beberapa langkah berikutnya.

## 1. Ambil semua data

```powershell
$baseUrl = "http://localhost:8080"
curl.exe -i -H "Accept: application/json" "$baseUrl/api/flights/airlines"
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
    -X POST "$baseUrl/api/flights/airlines" `
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
    -X POST "$baseUrl/api/flights/airlines" `
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
    "airline_code": "Nilai field harus unik."
  }
}
```

## 3. Ambil detail data

```powershell
curl.exe -i -H "Accept: application/json" "$baseUrl/api/flights/airlines/$airlineId"
```

HTTP yang diharapkan: `200 OK`; `data` berisi object airline yang baru dibuat.

## 4. Mengambil data yang tidak ada

UUID berikut valid, tetapi digunakan sebagai ID yang tidak dibuat oleh langkah
uji coba ini.

```powershell
$missingId = "00000000-0000-4000-8000-000000000000"
curl.exe -i -H "Accept: application/json" "$baseUrl/api/flights/airlines/$missingId"
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
    -X PUT "$baseUrl/api/flights/airlines/$airlineId" `
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
    -X PATCH "$baseUrl/api/flights/airlines/$airlineId" `
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
curl.exe -i -H "Accept: application/json" "$baseUrl/api/flights/search/airlines?q=$airlineCode"
```

HTTP yang diharapkan: `200 OK`; `data` berupa array yang memuat airline dengan
`airline_code` sama dengan `$airlineCode`.

## 8. Delete data

```powershell
curl.exe -i -X DELETE -H "Accept: application/json" "$baseUrl/api/flights/airlines/$airlineId"
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
curl.exe -i -X DELETE -H "Accept: application/json" "$baseUrl/api/flights/airlines/$airlineId"
```

HTTP yang diharapkan: `404 Not Found`. Anda juga dapat memastikan record sudah
tidak bisa dibaca dengan memanggil detailnya kembali:

```powershell
curl.exe -i -H "Accept: application/json" "$baseUrl/api/flights/airlines/$airlineId"
```

Detail setelah delete juga harus menghasilkan `404 Not Found`.

## Body POST kosong, object kosong, atau JSON rusak

Body tanpa isi ditolak dengan HTTP `400` karena body bukan JSON valid:

```powershell
curl.exe -i `
    -X POST "$baseUrl/api/flights/airlines" `
    -H "Accept: application/json" `
    -H "Content-Type: application/json" `
    --data-raw ''
```

Object JSON kosong ditolak dengan HTTP `400` karena field wajib tidak ada:

```powershell
curl.exe -i `
    -X POST "$baseUrl/api/flights/airlines" `
    -H "Accept: application/json" `
    -H "Content-Type: application/json" `
    --data-raw '{}'
```

JSON rusak juga ditolak dengan HTTP `400`:

```powershell
curl.exe -i `
    -X POST "$baseUrl/api/flights/airlines" `
    -H "Accept: application/json" `
    -H "Content-Type: application/json" `
    --data-raw '{'
```
