# Sistem Informasi Pengelolaan Kas  
CV. Karya Wahana Sentosa

## Gambaran Umum

Aplikasi web berbasis PHP untuk mengelola arus kas, inventaris, dan operasional di CV. Karya Wahana Sentosa. Sistem ini menyediakan fitur manajemen pengguna, bahan baku, produk, supplier, produksi, biaya, pembelian, penjualan, dan laporan. Terdapat tiga peran pengguna dengan kontrol akses berbasis peran: **Admin**, **Pegawai**, dan **Pemilik**.

---

## Fitur Utama

- **Manajemen Pengguna:** CRUD akun pengguna (khusus Admin)
- **Manajemen Bahan Baku & Produk:** Monitoring stok bahan baku dan produk jadi
- **Manajemen Supplier:** Data supplier
- **Manajemen Produksi:** Catatan aktivitas produksi
- **Manajemen Biaya:** Pengeluaran operasional
- **Manajemen Pembelian & Penjualan:** Transaksi pembelian dan penjualan
- **Laporan:** Laporan keuangan & inventaris
- **Dasbor:** Metrik utama (produk, penjualan, pengeluaran, saldo kas)
- **Akses Berbasis Peran:**  
  - **Admin:** Akses penuh  
  - **Pegawai:** Biaya, pembelian, penjualan, laporan  
  - **Pemilik:** Dasbor & laporan

---

## Teknologi

- **Backend:** PHP 7.4+, MySQL (PDO)
- **Frontend:** HTML, Tailwind CSS, JavaScript, Chart.js
- **Dependensi:**  
  - Tailwind CSS (CDN)  
  - Font Awesome (CDN)  
  - Chart.js (CDN)

---

## Struktur Direktori

```
project/
├── config/
│   └── db_connect.php
├── includes/
│   ├── header.php
│   ├── sidebar.php
│   ├── footer.php
│   └── function.php
├── assets/
│   ├── css/styles.css
│   └── js/scripts.js
├── login.php
├── dashboard.php
├── user_management.php
├── material_management.php
├── product_management.php
├── supplier_management.php
├── production_management.php
├── cost_management.php
├── purchase_management.php
├── sales_management.php
├── reports.php
├── logout.php
└── schema.sql
```

---

## Prasyarat

- PHP 7.4 atau lebih tinggi
- MySQL 5.7+
- Web server (Apache/Nginx)
- Composer (opsional)
- Node.js (opsional, untuk Tailwind CSS lokal)

---

## Instalasi

1. **Kloning Repositori**
    ```bash
    git clone <url-repositori>
    cd project
    ```

2. **Siapkan Database**
    - Buat database MySQL: `karya_wahana_sentosa`
    - Import skema:
      ```bash
      mysql -u <username> -p karya_wahana_sentosa < schema.sql
      ```

3. **Konfigurasi Koneksi Database**
    - Edit `config/db_connect.php`:
      ```php
      $host = 'localhost';
      $dbname = 'karya_wahana_sentosa';
      $username = 'nama_pengguna_anda';
      $password = 'kata_sandi_anda';
      ```

4. **Siapkan Server Web**
    - Tempatkan folder di root server web (misal: `/Applications/XAMPP/xamppfiles/htdocs/`)
    - Pastikan izin file:
      ```bash
      chmod -R 775 .
      ```

5. **Akses Aplikasi**
    - Buka browser: `http://localhost/project`
    - Akun default:
      - **Admin:** admin / admin
      - **Pegawai:** pegawai / pegawai
      - **Pemilik:** pemilik / pemilik

---

## Penggunaan

- **Login:** Masuk melalui `login.php` sesuai peran.
- **Dasbor:** Lihat metrik utama & grafik.
- **Modul Manajemen:** Navigasi via sidebar, tambah/edit/hapus data.
- **Laporan:** Hasilkan laporan keuangan & inventaris.
- **Logout:** Klik "Logout" di header.

---

## Catatan Keamanan

- **Kata Sandi:** Saat ini menggunakan MD5 (hanya untuk demo). Untuk produksi, gunakan `password_hash()` dan `password_verify()`.
- **Validasi Input:** Selalu sanitasi input untuk mencegah SQL Injection & XSS.
- **Keamanan Sesi:** Gunakan cookie sesi yang aman dan pertimbangkan CSRF token.

---

## Pengembangan

- Tambahkan utilitas di `function.php`
- Modifikasi gaya di `assets/css/styles.css`
- Tambah logika UI di `assets/js/scripts.js`
- Update `schema.sql` & file PHP terkait jika ada perubahan database

---

## Pemecahan Masalah

- **Koneksi Database:** Periksa kredensial di `config/db_connect.php` & status MySQL.
- **Izin File:** Pastikan izin (`chmod 775`) & konfigurasi server web.
- **CDN Tidak Muncul:** Pastikan koneksi internet untuk CDN.

---

## Lisensi

MIT License. Lihat file LICENSE untuk detail.

---

## Kontak

Pertanyaan/fitur: [email-anda@contoh.com]