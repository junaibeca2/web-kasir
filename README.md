# Kasir Toko (PHP + PhpMyAdmin)

Aplikasi kasir lokal untuk XAMPP. Fitur: login super admin/karyawan, katalog menu, input dan edit pesanan, cetak struk 80 mm, unggah logo toko, laporan penjualan terbatas untuk super admin, dan pengelolaan akun karyawan.

## Pasang di XAMPP

1. Salin folder ini ke `F:\xampp\htdocs\web-kasir`.
2. Buka XAMPP Control Panel, lalu jalankan **Apache** dan **MySQL**.
3. Buka `http://localhost/web-kasir/setup.php`.
4. Buat akun super admin dengan username dan kata sandi sendiri. Setup membuat database `web_kasir` dan tabelnya otomatis. Tidak ada kata sandi bawaan.
5. Masuk melalui `http://localhost/web-kasir/`.
6. Dari akun super admin, buka **Pengaturan** untuk mengisi nama/alamat/nomor HP dan mengunggah logo. Tambahkan produk di **Menu**, lalu buat akun di **Karyawan**.

Jika MySQL root Anda memakai kata sandi atau port yang berbeda, sesuaikan `DB_PORT`, `DB_USER`, dan `DB_PASS` di `config.php` sebelum membuka setup. Skema yang dapat diimpor lewat phpMyAdmin ada di `database/schema.sql`.

## Hak akses

- **Super admin**: kelola menu, karyawan, identitas toko/logo, pesanan, laporan penjualan, dan cetak struk.
- **Karyawan**: buat/edit pesanan, pilih menu aktif, dan cetak struk. Halaman laporan, katalog menu, akun, dan pengaturan toko ditolak di server, bukan hanya disembunyikan dari menu.

Menu yang dinonaktifkan tidak dihapus agar riwayat transaksi tetap utuh. Nama dan harga item pesanan disimpan sebagai snapshot, jadi struk lama tidak berubah saat harga menu diperbarui.

## Cetak struk

Tekan **Cetak struk**, pilih printer thermal, dan gunakan lebar kertas 80 mm. Browser mengatur dialog printer; matikan header/footer browser bila muncul. Logo, alamat, nomor telepon, kasir, item, total, dan catatan toko tampil di struk.

## Uji aturan akses dan total

Dengan PHP XAMPP:

```text
F:\xampp\php\php.exe F:\xampp\htdocs\web-kasir\tests\domain_test.php
```

## Catatan keamanan

Aplikasi ini ditujukan untuk jaringan lokal/pengembangan XAMPP, bukan langsung dipublikasikan ke internet. Gunakan kata sandi kuat untuk akun, ubah kredensial root MySQL dari bawaan XAMPP bila diperlukan, dan lindungi `config.php` serta folder database. Kata sandi pengguna disimpan menggunakan `password_hash`; formulir memakai token CSRF dan query database memakai prepared statements.
