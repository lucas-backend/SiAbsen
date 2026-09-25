# Tugas: Buat Dokumen `docs/RANCANGAN.md` — Proyek SiAbsen

Buat dokumen `RANCANGAN.md` di folder `docs/` untuk proyek berikut, menggunakan **data nyata di bawah ini** (jangan gunakan placeholder — semua data sudah tersedia). Struktur dokumen mengikuti 7 bagian berikut.

## 0. Identitas Kelompok
- **Nama Kelompok:** Kelompok SiAbsen
- **Anggota:**
  | Nama | NPM |
  |---|---|
  | I Made Dipa Rama Artike | 2415061001 |
  | Rifki Yudika Perdana | 2415061090 |
  | Riffa Yudika Perdana | 2415061091 |
  | Arqan Purusa Eryan | 2415061055 |
  | Yostiar Aminudin | 2415061016 |
- **Repositori Git:** https://github.com/lucas-backend/SiAbsen

## 1. Gambaran Umum Proyek
- **Tema:** Pendidikan — sistem absensi berbasis QR code untuk sekolah.
- **Masalah yang dijawab** (tulis ulang dalam narasi yang mengalir, jangan hanya menyalin poin mentah, tapi pastikan tiga poin ini tercakup):
  1. Absensi konvensional memakan waktu lama → mengurangi waktu belajar efektif.
  2. Rekapitulasi data manual rawan human error dan sulit dilakukan untuk periode tertentu.
  3. Siswa bisa membolos di tengah jam pelajaran meski sudah absen pagi, karena tidak ada pemantauan lokasi saat pergantian mata pelajaran.
- **Entitas utama:** User (Siswa & Guru), Kelas, Absensi — jelaskan masing-masing dalam 1–2 kalimat, termasuk atribut kunci yang membedakan Siswa dan Guru sebagai sub-tipe/role dari User.
- **Peran pengguna:**
  | Peran | Kewenangan |
  |---|---|
  | Guru | Generate QR code absensi, kelola data siswa/kelas/jadwal, merekap absensi, memantau status kehadiran siswa secara realtime |
  | Siswa | Melakukan absensi via scan QR code, melihat riwayat absen sendiri |
- **Relasi antar entitas:**
  | Entitas A | Entitas B | Jenis Relasi |
  |---|---|---|
  | User (Siswa) | Kelas | Many to Many |
  | User (Guru) | Kelas | One to Many |
  | User (Siswa) | Absensi | One to Many |
  Jelaskan alasan desain tiap relasi (mis. kenapa Siswa–Kelas many-to-many, apakah ada tabel pivot dengan atribut tambahan seperti tahun ajaran).
- **Alur interaksi yang dipilih** (jelaskan keduanya secara rinci, langkah per langkah):
  1. **Pencarian bertingkat & alur persetujuan** — jelaskan konteks pemakaiannya dalam SiAbsen (mis. guru mencari data siswa/kelas secara bertingkat — sekolah → kelas → siswa — dan alur persetujuan apa yang terlibat, misalnya persetujuan koreksi absensi atau pengajuan izin).
  2. **Halaman rekap** — jelaskan alur guru/pihak sekolah mengakses rekap, memfilter periode, dan meninjau kecocokan lokasi.

## 2. Rancangan Skema Basis Data
Untuk entitas **User, Kelas, Absensi** (dan tabel pivot/pendukung jika ada, mis. `kelas_siswa`, `jadwal`), buat tabel:
| Atribut | Tipe Data | Kunci (PK/FK/Unique) | Nullable | Alasan Pemilihan Tipe Data | Batasan Integritas |
|---|---|---|---|---|---|

- Untuk entitas **Absensi**, pastikan mencakup atribut status (Hadir/Alpa/dsb.), waktu absen, dan data lokasi verifikasi (koordinat/geofence) yang disebutkan di bagian rekap.
- Sertakan diagram ERD sederhana (boleh dalam format teks atau Mermaid).

## 3. Migrasi & Seeder (Verifikasi, Bukan Membuat Baru)
> Catatan: migrasi dan seeder **sudah dibuat** di repositori.
- Jalankan migrasi dari kondisi bersih dan pastikan tidak ada error di PostgreSQL. Cantumkan perintah persisnya (mis. `php artisan migrate:fresh --seed`).
- Verifikasi seeder/factory menghasilkan data contoh yang cukup untuk didemokan (jumlah realistis: beberapa guru, beberapa kelas, siswa per kelas, dan riwayat absensi untuk beberapa hari).
- Jika ditemukan ketidaksesuaian antara skema di seeder dan rancangan pada Bagian 2, catat sebagai catatan perbaikan di dokumen ini.

## 4. Pembagian Peran Tim
| Peran | Anggota |
|---|---|
| Basis Data & Model | Arqan, Dipa, Riffa, Rifki |
| Antarmuka & Komponen | Dipa, Rifki, Yostiar |
| Autentikasi & Otorisasi | Arqan, Yostiar |
| Uji Kebergunaan | Arqan, Dipa, Riffa, Rifki, Yostiar |
| Dokumentasi | Yostiar, Rifki |

(Catatan: beberapa anggota memegang lebih dari satu peran — ini boleh dipertahankan apa adanya sesuai data yang diberikan, tapi tandai secara eksplisit siapa peran utamanya jika memungkinkan, agar tetap selaras dengan prinsip "satu peran utama per orang".)

## 5. Calon Pengguna untuk Uji Kebergunaan (Pertemuan 13)
Karena nama individu belum ditentukan secara personal, gunakan sebutan peran berikut sebagai identitas sementara calon peserta yang **sudah bersedia**:
- Bapak Guru (perwakilan guru laki-laki)
- Ibu Guru (perwakilan guru perempuan)
- Perwakilan Siswa Kelas X
- Perwakilan Siswa Kelas XI
- Perwakilan Siswa Kelas XII

Tambahkan kolom rencana konfirmasi nama asli sebelum pertemuan 13:
| Sebutan | Nama Asli (isi sebelum pertemuan 13) | Tanggal Konfirmasi |
|---|---|---|
| Bapak Guru | `[ISI_NAMA]` | |
| Ibu Guru | `[ISI_NAMA]` | |
| Perwakilan Siswa X | `[ISI_NAMA]` | |
| Perwakilan Siswa XI | `[ISI_NAMA]` | |
| Perwakilan Siswa XII | `[ISI_NAMA]` | |

## 6. Isi Halaman Rekap
Jelaskan secara rinci komponen halaman rekap yang akan dibangun, mencakup:
- Tabel riwayat dan rekapitulasi status kehadiran siswa (Hadir, Alpa — tambahkan status lain jika relevan, mis. Izin/Sakit).
- Informasi kecocokan data lokasi verifikasi (mis. status "sesuai lokasi kelas" vs "di luar radius") per entri absensi.
- Filter yang tersedia (per kelas, per periode/tanggal, per siswa).
- Siapa saja yang dapat mengakses halaman ini (Guru, pihak sekolah/admin).

## 7. Keputusan Arsitektur
Tuliskan sebagai satu halaman ringkas:
- Bentuk arsitektur yang **dipilih** (pilih satu: monolit Blade, API terpisah + SPA, atau monolit Inertia) — sesuaikan dengan stack yang sudah dipakai di repositori (cek `composer.json`/`package.json` di repo sebelum menentukan, jangan mengasumsikan).
- **Dua alasan teknis** memilih arsitektur tersebut, dikaitkan langsung dengan kebutuhan SiAbsen (mis. kebutuhan realtime monitoring kehadiran, kompleksitas UI scan QR, kecepatan pengembangan tim beranggota 5 orang, dsb).
- **Satu hal yang dikorbankan** akibat pilihan ini dan alasan mengapa itu dapat diterima untuk skala proyek ini.

## Ketentuan Tambahan
- Tulis dalam Bahasa Indonesia yang jelas dan profesional, siap dipakai sebagai acuan kerja tim (bukan draf kasar).
- Gunakan data yang sudah diberikan apa adanya; jangan mengarang detail teknis (nama kolom, tipe data spesifik, dsb.) — jika perlu detail teknis lebih lanjut, periksa langsung isi migrasi/seeder di repositori sebelum menulis Bagian 2 dan 3.
- Format akhir: Markdown dengan heading terstruktur, tabel di mana relevan, dan diagram ERD/alur bila memungkinkan.