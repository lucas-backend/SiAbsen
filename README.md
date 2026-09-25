# SiAbsen

SiAbsen adalah aplikasi absensi sekolah berbasis web. Aplikasi ini mengelola data akademik (jenjang, kelas, mata pelajaran, siswa, guru) dan mencatat kehadiran siswa melalui sesi absensi.

## Teknologi

- **Backend:** Laravel 13 (PHP 8.3+), Eloquent, Laravel Fortify (autentikasi), Inertia.js 3
- **Frontend:** React 19, TypeScript, Tailwind CSS 4, Vite (vite-plus)
- **Database:** PostgreSQL (runtime), SQLite in-memory (test)
- **Testing & Quality:** Pest/PHPUnit, PHPStan (Larastan), Pint, ESLint/Prettier (via `vp check`)

## Prasyarat

Pastikan perangkat sudah memiliki:

- **PHP >= 8.3** beserta ekstensi umum Laravel (`pdo`, `pdo_pgsql`, `mbstring`, `openssl`, `fileinfo`, `tokenizer`, `xml`, `ctype`, `curl`).
- **Composer 2**
- **Node.js >= 22** dan **npm**
- **PostgreSQL** (mis. versi 13 ke atas). Bisa juga memakai SQLite, lihat bagian [Alternatif SQLite](#alternatif-sqlite).
- **Git**

Cek versi:

```bash
php -v
composer -V
node -v
npm -v
psql --version
```

## Setup dari 0

### 1. Clone repository

```bash
git clone https://github.com/lucas-backend/SiAbsen
cd siabsen
```

### 2. Install dependency backend

```bash
composer install
```

### 3. Konfigurasi environment

Salin file environment contoh lalu buat application key.

Windows PowerShell:

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

Linux/macOS:

```bash
cp .env.example .env
php artisan key:generate
```

Buka `.env` dan sesuaikan koneksi database sesuai PostgreSQL lokal:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=siabsen
DB_USERNAME=postgres
DB_PASSWORD=1234
```

### 4. Siapkan database PostgreSQL

Buat database kosong. Laravel migration akan membuat seluruh tabel.

```bash
psql -U postgres -c "CREATE DATABASE siabsen;"
```

Atau lewat shell `psql`:

```sql
CREATE DATABASE siabsen;
```

Pastikan nilai `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD` pada `.env` cocok dengan database yang dibuat.

### 5. Jalankan migrasi dan seeding

```bash
php artisan migrate
php artisan db:seed
```

`migrate` membuat tabel domain dan tabel internal Laravel. `db:seed` mengisi data contoh (jenjang, kelas, mata pelajaran, akun guru, akun siswa, penugasan, dan sesi absensi).

### 6. Install dependency frontend dan build aset

```bash
npm install
npm run build
```

`npm run build` menghasilkan aset produksi di `public/build`. Untuk pengembangan, aset bisa dibangun otomatis oleh Vite (lihat bagian [Menjalankan project](#menjalankan-project)).

## Menjalankan project

Cara paling praktis adalah menjalankan seluruh service pengembangan sekaligus:

```bash
composer dev
```

Perintah ini menjalankan server PHP, queue listener, log viewer, dan Vite dev server secara bersamaan.

Alternatif manual dapat dijalankan pada dua terminal terpisah:

```bash
# Terminal 1 - backend
php artisan serve

# Terminal 2 - frontend (hot reload)
npm run dev
```

Buka `http://localhost:8000` di browser.

> Catatan: `composer dev` memakai `php artisan dev`. Di Windows, sub-command `pail` (log viewer) tidak berjalan karena butuh ekstensi `pcntl`; service lain tetap normal.

## Akun default (hasil seeding)

Semua akun memakai password `password`.

| Peran  | Email                                                             |
| ------ | ----------------------------------------------------------------- |
| Admin  | `admin@siabsen.com`                                               |
| Guru   | `guru1@siabsen.com` s/d `guru10@siabsen.com`                      |
| Siswa  | `siswa1@siabsen.com` s/d `siswa120@siabsen.com`                   |

## Perintah umum

| Perintah                | Keterangan                                        |
| ----------------------- | ------------------------------------------------- |
| `composer dev`          | Menjalankan seluruh service pengembangan           |
| `npm run dev`           | Vite dev server (hot reload)                       |
| `npm run build`         | Build aset produksi                                |
| `npm run build:ssr`     | Build aset termasuk SSR                            |
| `php artisan test`      | Menjalankan test Pest/PHPUnit                      |
| `composer test`         | Lint check, PHPStan, lalu test                     |
| `composer lint`         | Auto-format backend dengan Pint                   |
| `composer lint:check`   | Cek format backend tanpa mengubah file            |
| `composer types:check`  | Analisis statis PHP dengan PHPStan                 |
| `npm run check`         | Lint & format frontend (`vp check`)                |
| `npm run check:fix`     | Perbaiki lint & format frontend                    |
| `npm run types:check`   | Type-check TypeScript (`tsc --noEmit`)             |
| `composer ci:check`     | Rangkaian pemeriksaan CI (check, types, test)      |

## Alternatif SQLite

Untuk menjalankan tanpa PostgreSQL, ubah `.env` menjadi:

```dotenv
DB_CONNECTION=sqlite
# DB_DATABASE kosongkan atau arahkan ke file sqlite
```

Buat file database lalu jalankan migrasi:

```powershell
New-Item -ItemType File -Path database/database.sqlite -Force
php artisan migrate
php artisan db:seed
```

Suite test otomatis sudah memakai SQLite in-memory (lihat `phpunit.xml`) dan tidak membutuhkan PostgreSQL.

## Struktur proyek

```
app/               Kode aplikasi (Actions, Http, Models, Providers)
bootstrap/         Bootstrap Laravel
config/            Konfigurasi aplikasi
database/          Migration, factory, dan seeder
resources/js/      Sumber frontend React + TypeScript
resources/css/     Sumber CSS Tailwind
routes/            Definisi route (web, settings, console)
tests/             Test Unit dan Feature
.docs/             Dokumentasi database
```

## Dokumentasi tambahan

- [`.docs/DATABASE.md`](.docs/DATABASE.md) - spesifikasi skema PostgreSQL, aturan integritas absensi, dan konvensi database.
- [`.docs/QUERY.sql`](.docs/QUERY.sql) - DDL transaksional untuk 10 tabel domain (referensi integrasi PostgreSQL).
