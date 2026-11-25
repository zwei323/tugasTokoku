
<?php
require_once "koneksi.php";

// Ambil ID transaksi
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    die("ID transaksi tidak valid.");
}

// Ambil data transaksi
$stmt = $conn->prepare("
    SELECT
        t.id_transaksi,
        t.tanggal,
        t.total_belanja,
        p.nama_pelanggan
    FROM tbl_transaksi t
    LEFT JOIN tbl_pelanggan p
        ON p.id_pelanggan = t.id_pelanggan
    WHERE t.id_transaksi = ?
");

$stmt->bind_param("i", $id);
$stmt->execute();

$transaksi = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$transaksi) {
    die("Transaksi tidak ditemukan.");
}

// Ambil detail transaksi
$stmt = $conn->prepare("
    SELECT
        d.*,
        p.nama_produk
    FROM tbl_detail_transaksi d
    LEFT JOIN tbl_produk p
        ON p.id_produk = d.id_produk
    WHERE d.id_transaksi = ?
");

$stmt->bind_param("i", $id);
$stmt->execute();

$detail = $stmt->get_result();

function rupiahStruk($angka) {
    return "Rp " . number_format((float)$angka, 0, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Struk Transaksi</title>

<style>
* {
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    background: #eee;
    margin: 0;
    padding: 25px;
}

.struk {
    width: 380px;
    max-width: 100%;
    margin: auto;
    background: white;
    padding: 25px;
    color: #222;
}

.center {
    text-align: center;
}

h2 {
    margin-bottom: 5px;
    color: #1679bd;
}

hr {
    border: 0;
    border-top: 1px dashed #888;
    margin: 15px 0;
}

.info {
    font-size: 13px;
    line-height: 1.8;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}

th, td {
    padding: 8px 3px;
    border-bottom: 1px dashed #ddd;
    text-align: left;
}

.right {
    text-align: right;
}

.total {
    font-size: 17px;
    font-weight: bold;
}

.footer {
    text-align: center;
    font-size: 12px;
    margin-top: 25px;
}

.actions {
    text-align: center;
    margin: 20px;
}

button, a {
    padding: 10px 18px;
    border: 0;
    border-radius: 6px;
    background: #268bd2;
    color: white;
    text-decoration: none;
    cursor: pointer;
}

@media print {
    body {
        background: white;
        padding: 0;
    }

    .struk {
        width: 100%;
        box-shadow: none;
    }

    .actions {
        display: none;
    }
}
</style>
</head>

<body>

<div class="struk">

    <div class="center">
        <h2>TOKO KITA</h2>
        <p style="font-size:12px">
            Sistem Informasi Penjualan
        </p>
    </div>

    <hr>

    <div class="info">
        <b>ID Transaksi:</b>
        #<?= htmlspecialchars($transaksi['id_transaksi']) ?><br>

        <b>Tanggal:</b>
        <?= htmlspecialchars($transaksi['tanggal']) ?><br>

        <b>Pelanggan:</b>
        <?= htmlspecialchars($transaksi['nama_pelanggan'] ?? 'Umum') ?>
    </div>

    <hr>

    <table>
        <thead>
            <tr>
                <th>Produk</th>
                <th>Qty</th>
                <th class="right">Subtotal</th>
            </tr>
        </thead>

        <tbody>
        <?php while ($d = $detail->fetch_assoc()): ?>
            <tr>
                <td>
                    <?= htmlspecialchars($d['nama_produk'] ?? '-') ?>
                </td>

                <td>
                    <?= (int)$d['jumlah'] ?>
                </td>

                <td class="right">
                    <?= rupiahStruk($d['subtotal']) ?>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <hr>

    <div class="total">
        <div style="display:flex;justify-content:space-between">
            <span>TOTAL</span>
            <span><?= rupiahStruk($transaksi['total_belanja']) ?></span>
        </div>
    </div>

    <div class="footer">
        <hr>
        <b>Terima Kasih Telah Berbelanja!</b>
        <p>Barang yang sudah dibeli tidak dapat dikembalikan.</p>
    </div>

</div>

<div class="actions">
    <button onclick="window.print()">
        Cetak Struk
    </button>

    <a href="transaksi.php">
        Kembali
    </a>
</div>

</body>
</html>
