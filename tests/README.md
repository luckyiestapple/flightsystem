# Pengujian

Test menggunakan konfigurasi `testing` dari `phpunit.dist.xml` dan database
SQLite in-memory, termasuk untuk feature test API. Test integrasi Place/Pilot
menggunakan mock HTTP client, jadi tidak memerlukan kedua service tersebut
sedang berjalan.

Jalankan semua test dari root repository:

```powershell
php vendor\bin\phpunit --no-coverage
```

Jalankan satu file test saat mengembangkan perubahan:

```powershell
php vendor\bin\phpunit tests\feature\ApiResourceTest.php --no-coverage
```

Output PHPUnit disimpan sementara di `build/`. Direktori ini bukan sumber
aplikasi dan tidak perlu dimasukkan ke commit.
