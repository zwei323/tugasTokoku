<?php
require_once "fungsi.php";

// Hanya hapus sesi pelanggan; sesi admin/kasir (jika ada) tidak ikut keluar
unset(
    $_SESSION['user_id'],
    $_SESSION['user_nama'],
    $_SESSION['user_pelanggan'],
    $_SESSION['pesanan_saya'],
    $_SESSION['voucher'],
    $_SESSION['gagal_login']
);
session_regenerate_id(true);

flash('success', 'Anda berhasil keluar.');
header("Location: index.php");
exit;
