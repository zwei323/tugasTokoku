
<?php
// ======================================================
// CHECKOUT TOKOKU
// Pembayaran: Tunai dan QR DANA
// ======================================================

require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../functions.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// ------------------------------------------------------
// AMBIL DATA KERANJANG
// ------------------------------------------------------

$cartKey = null;

if (isset($_SESSION["keranjang"]) && is_array($_SESSION["keranjang"])) {
    $cartKey = "keranjang";
} elseif (isset($_SESSION["cart"]) && is_array($_SESSION["cart"])) {
    $cartKey = "cart";
}

$cart = $cartKey ? $_SESSION[$cartKey] : [];

// ------------------------------------------------------
// NORMALISASI KERANJANG
// Mendukung beberapa format session keranjang
// ------------------------------------------------------

$items = [];

foreach ($cart as $key => $item) {

    if (is_array($item)) {
        $id_produk = (int)($item["id_produk"] ?? $item["id"] ?? $key);
        $jumlah = (int)($item["jumlah"] ?? $item["qty"] ?? $item["quantity"] ?? 1);
    } else {
        $id_produk = (int)$key;
        $jumlah = (int)$item;
    }

    if ($id_produk > 0 && $jumlah > 0) {
        $items[$id_produk] = ($items[$id_produk] ?? 0) + $jumlah;
    }
}

// ------------------------------------------------------
// AMBIL DATA PRODUK DARI DATABASE
// Harga dari database, bukan dari browser
// ------------------------------------------------------

$produkCheckout = [];
$totalBelanja = 0;
$errorCheckout = "";

if (!empty($items)) {

    $stmtProduk = $conn->prepare("
        SELECT id_produk, nama_produk, harga, stok
        FROM tbl_produk
        WHERE id_produk = ?
    ");

    foreach ($items as $id_produk => $jumlah) {

        $stmtProduk->bind_param("i", $id_produk);
        $stmtProduk->execute();

        $hasil = $stmtProduk->get_result();
        $produk = $hasil->fetch_assoc();

        if (!$produk) {
            $errorCheckout = "Ada produk yang tidak ditemukan.";
            break;
        }

        $harga = (float)$produk["harga"];
        $stok = (int)$produk["stok"];
        $subtotal = $harga * $jumlah;

        $produkCheckout[] = [
            "id_produk" => (int)$produk["id_produk"],
            "nama_produk" => $produk["nama_produk"],
            "harga" => $harga,
            "stok" => $stok,
            "jumlah" => $jumlah,
            "subtotal" => $subtotal
        ];

        $totalBelanja += $subtotal;

        if ($jumlah > $stok) {
            $errorCheckout = "Stok {$produk['nama_produk']} tidak mencukupi. Stok tersedia: {$stok}.";
        }
    }

    $stmtProduk->close();
}

// ------------------------------------------------------
// PROSES CHECKOUT
// ------------------------------------------------------

if (isset($_POST["checkout"])) {

    $namaPenerima = trim($_POST["nama_penerima"] ?? "");
    $noHp = trim($_POST["no_hp"] ?? "");
    $alamat = trim($_POST["alamat"] ?? "");
    $pengiriman = trim($_POST["pengiriman"] ?? "Ambil di toko");
    $metodePembayaran = trim($_POST["pembayaran"] ?? "");

    $ongkir = 0;
    $diskon = 0;
    $kodeVoucher = null;

    $metodeValid = ["Tunai", "QRIS DANA"];

    if ($namaPenerima === "") {
        $errorCheckout = "Nama penerima wajib diisi.";
    } elseif ($noHp === "") {
        $errorCheckout = "Nomor HP wajib diisi.";
    } elseif ($alamat === "") {
        $errorCheckout = "Alamat wajib diisi.";
    } elseif (!in_array($metodePembayaran, $metodeValid, true)) {
        $errorCheckout = "Pilih metode pembayaran yang valid.";
    } elseif (empty($produkCheckout)) {
        $errorCheckout = "Keranjang belanja kosong.";
    }

    // QRIS hanya bisa dikonfirmasi secara manual.
    // Tidak ada verifikasi otomatis dari DANA.

    if ($errorCheckout === "") {

        try {

            $conn->begin_transaction();

            // Cek ulang stok saat transaksi akan disimpan
            $stmtCekStok = $conn->prepare("
                SELECT stok, nama_produk, harga
                FROM tbl_produk
                WHERE id_produk = ?
                FOR UPDATE
            ");

            foreach ($produkCheckout as $p) {

                $idProduk = (int)$p["id_produk"];

                $stmtCekStok->bind_param("i", $idProduk);
                $stmtCekStok->execute();

                $cek = $stmtCekStok->get_result()->fetch_assoc();

                if (!$cek) {
                    throw new Exception("Produk {$p['nama_produk']} tidak ditemukan.");
                }

                if ((int)$cek["stok"] < (int)$p["jumlah"]) {
                    throw new Exception(
                        "Stok {$cek['nama_produk']} tidak mencukupi."
                    );
                }
            }

            $stmtCekStok->close();

            // --------------------------------------------------
            // SIMPAN TRANSAKSI
            // --------------------------------------------------

            $stmtTransaksi = $conn->prepare("
                INSERT INTO tbl_transaksi
                (
                    tanggal,
                    id_pelanggan,
                    total_belanja,
                    status,
                    nama_penerima,
                    no_hp,
                    alamat,
                    pengiriman,
                    ongkir,
                    pembayaran,
                    kode_voucher,
                    diskon
                )
                VALUES
                (
                    CURDATE(),
                    NULL,
                    ?,
                    'Menunggu',
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");

            $stmtTransaksi->bind_param(
                "dssssdssd",
                $totalBelanja,
                $namaPenerima,
                $noHp,
                $alamat,
                $pengiriman,
                $ongkir,
                $metodePembayaran,
                $kodeVoucher,
                $diskon
            );

            $stmtTransaksi->execute();

            $idTransaksi = $conn->insert_id;
            $stmtTransaksi->close();

            // --------------------------------------------------
            // SIMPAN DETAIL TRANSAKSI
            // --------------------------------------------------

            $stmtDetail = $conn->prepare("
                INSERT INTO tbl_detail_transaksi
                (
                    id_transaksi,
                    id_produk,
                    jumlah,
                    harga,
                    subtotal
                )
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmtUpdateStok = $conn->prepare("
                UPDATE tbl_produk
                SET stok = stok - ?
                WHERE id_produk = ?
            ");

            foreach ($produkCheckout as $p) {

                $idProduk = (int)$p["id_produk"];
                $jumlah = (int)$p["jumlah"];
                $harga = (float)$p["harga"];
                $subtotal = (float)$p["subtotal"];

                $stmtDetail->bind_param(
                    "iiidd",
                    $idTransaksi,
                    $idProduk,
                    $jumlah,
                    $harga,
                    $subtotal
                );

                $stmtDetail->execute();

                // Kurangi stok
                $stmtUpdateStok->bind_param(
                    "ii",
                    $jumlah,
                    $idProduk
                );

                $stmtUpdateStok->execute();

                if ($stmtUpdateStok->affected_rows !== 1) {
                    throw new Exception("Gagal mengurangi stok produk.");
                }
            }

            $stmtDetail->close();
            $stmtUpdateStok->close();

            // --------------------------------------------------
            // COMMIT TRANSAKSI
            // --------------------------------------------------

            $conn->commit();

            // Kosongkan keranjang
            if ($cartKey !== null) {
                $_SESSION[$cartKey] = [];
            }

            flash(
                "Checkout berhasil! Nomor transaksi: #" . $idTransaksi,
                "success"
            );

            redirect("checkout.php?success=" . $idTransaksi);

        } catch (Throwable $e) {

            $conn->rollback();

            error_log("Checkout error: " . $e->getMessage());

            $errorCheckout = "Checkout gagal: " . $e->getMessage();
        }
    }
}

// ------------------------------------------------------
// HEADER
// ------------------------------------------------------

$pageTitle = "Checkout";
require "header.php";

// ------------------------------------------------------
// CEK TRANSAKSI SUKSES
// ------------------------------------------------------

if (isset($_GET["success"])) {
    $idSukses = (int)$_GET["success"];
?>

<div class="container py-5">
    <div class="card shadow-sm border-0 mx-auto text-center"
         style="max-width:650px">

        <div class="card-body p-4 p-md-5">

            <div class="text-success mb-3">
                <i class="bi bi-check-circle-fill"
                   style="font-size:65px"></i>
            </div>

            <h3 class="fw-bold">Checkout Berhasil!</h3>

            <p class="text-muted">
                Transaksi telah berhasil disimpan ke sistem Tokoku.
            </p>

            <div class="alert alert-success">
                Nomor transaksi:
                <strong>#<?=e($idSukses)?></strong>
            </div>

            <p>
                Silakan simpan nomor transaksi untuk keperluan pengecekan.
            </p>

            <a href="index.php" class="btn btn-primary">
                <i class="bi bi-shop"></i>
                Kembali Belanja
            </a>

        </div>
    </div>
</div>

<?php
require "footer.php";
exit;
}
?>

<div class="container py-4">

    <div class="mb-4">
        <h3 class="fw-bold">
            <i class="bi bi-cart-check text-primary"></i>
            Checkout
        </h3>
        <p class="text-muted">
            Periksa pesanan dan pilih metode pembayaran.
        </p>
    </div>

    <?php if($errorCheckout !== ""): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?=e($errorCheckout)?>
            <button type="button" class="btn-close"
                    data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if(empty($produkCheckout)): ?>

        <div class="card border-0 shadow-sm">
            <div class="card-body text-center p-5">
                <i class="bi bi-cart-x text-secondary"
                   style="font-size:55px"></i>

                <h5 class="mt-3">Keranjang masih kosong</h5>

                <p class="text-muted">
                    Silakan tambahkan produk sebelum checkout.
                </p>

                <a href="index.php" class="btn btn-primary">
                    Belanja Sekarang
                </a>
            </div>
        </div>

    <?php else: ?>

    <form method="POST" id="formCheckout">

        <div class="row g-4">

            <!-- ==========================================
                 DATA PENERIMA
            ========================================== -->

            <div class="col-lg-7">

                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">
                            <i class="bi bi-person-circle text-primary"></i>
                            Data Penerima
                        </h5>
                    </div>

                    <div class="card-body">

                        <div class="mb-3">
                            <label class="form-label">
                                Nama Penerima
                            </label>

                            <input type="text"
                                   name="nama_penerima"
                                   class="form-control"
                                   maxlength="100"
                                   required
                                   value="<?=e($_POST['nama_penerima'] ?? '')?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                Nomor HP
                            </label>

                            <input type="tel"
                                   name="no_hp"
                                   class="form-control"
                                   maxlength="20"
                                   required
                                   value="<?=e($_POST['no_hp'] ?? '')?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                Alamat
                            </label>

                            <textarea name="alamat"
                                      class="form-control"
                                      rows="3"
                                      required><?=e($_POST['alamat'] ?? '')?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                Metode Pengiriman
                            </label>

                            <select name="pengiriman"
                                    class="form-select"
                                    required>

                                <option value="Ambil di toko">
                                    Ambil di toko
                                </option>

                                <option value="Kurir">
                                    Kurir
                                </option>

                            </select>
                        </div>

                    </div>
                </div>

                <!-- ==========================================
                     METODE PEMBAYARAN
                ========================================== -->

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">
                            <i class="bi bi-wallet2 text-primary"></i>
                            Metode Pembayaran
                        </h5>
                    </div>

                    <div class="card-body">

                        <!-- TUNAI -->
                        <label class="payment-option mb-3"
                               for="tunai">

                            <input type="radio"
                                   name="pembayaran"
                                   value="Tunai"
                                   id="tunai"
                                   required
                                   <?=($_POST["pembayaran"] ?? "") === "Tunai" ? "checked" : ""?>>

                            <div class="payment-info">
                                <div class="payment-icon cash-icon">
                                    <i class="bi bi-cash-stack"></i>
                                </div>

                                <div>
                                    <strong>Bayar Tunai</strong>
                                    <div class="text-muted small">
                                        Pembayaran langsung
                                    </div>
                                </div>
                            </div>
                        </label>

                        <!-- QR DANA -->
                        <label class="payment-option"
                               for="qris">

                            <input type="radio"
                                   name="pembayaran"
                                   value="QRIS DANA"
                                   id="qris"
                                   required
                                   <?=($_POST["pembayaran"] ?? "") === "QRIS DANA" ? "checked" : ""?>>

                            <div class="payment-info">
                                <div class="payment-icon dana-icon">
                                    <i class="bi bi-qr-code"></i>
                                </div>

                                <div>
                                    <strong>QRIS / DANA</strong>
                                    <div class="text-muted small">
                                        Scan QR menggunakan aplikasi pembayaran
                                    </div>
                                </div>
                            </div>
                        </label>

                        <!-- QR DISPLAY -->
                        <div id="qrisContainer" class="mt-4" style="display:none">

                            <div class="qris-box text-center">

                                <h5 class="fw-bold">
                                    Scan QR DANA
                                </h5>

                                <p class="text-muted small">
                                    Scan kode QR berikut menggunakan aplikasi DANA.
                                </p>

                                <div class="qris-image-wrapper">
                                    <img src="../assets/qris-dana.jpg"
                                         alt="QR pembayaran DANA"
                                         id="qrisImage"
                                         class="qris-image">
                                </div>

                                <div class="qris-total mt-3">
                                    <div class="text-muted">
                                        Total pembayaran
                                    </div>

                                    <h2 class="text-primary fw-bold">
                                        Rp <?=number_format($totalBelanja,0,',','.')?>
                                    </h2>
                                </div>

                                <div class="alert alert-info text-start small mt-3 mb-0">
                                    <i class="bi bi-info-circle"></i>
                                    Pastikan nama penerima dan nominal sesuai
                                    sebelum membayar. QR ini bersifat statis,
                                    jadi masukkan nominal secara manual.
                                    Pembayaran akan diperiksa oleh kasir.
                                </div>

                            </div>

                        </div>

                    </div>
                </div>

            </div>

            <!-- ==========================================
                 RINGKASAN BELANJA
            ========================================== -->

            <div class="col-lg-5">

                <div class="card shadow-sm border-0 sticky-summary">

                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">
                            <i class="bi bi-receipt text-primary"></i>
                            Ringkasan Belanja
                        </h5>
                    </div>

                    <div class="card-body">

                        <?php foreach($produkCheckout as $p): ?>

                            <div class="d-flex justify-content-between gap-3 mb-3">

                                <div>
                                    <div class="fw-semibold">
                                        <?=e($p["nama_produk"])?>
                                    </div>

                                    <small class="text-muted">
                                        <?=number_format($p["jumlah"])?> x
                                        <?=rupiah($p["harga"])?>
                                    </small>
                                </div>

                                <div class="fw-semibold text-nowrap">
                                    <?=rupiah($p["subtotal"])?>
                                </div>

                            </div>

                        <?php endforeach; ?>

                        <hr>

                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal</span>
                            <span><?=rupiah($totalBelanja)?></span>
                        </div>

                        <div class="d-flex justify-content-between mb-2">
                            <span>Ongkir</span>
                            <span><?=rupiah(0)?></span>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <strong>Total Pembayaran</strong>

                            <h4 class="text-primary fw-bold mb-0">
                                <?=rupiah($totalBelanja)?>
                            </h4>
                        </div>

                        <button type="submit"
                                name="checkout"
                                value="1"
                                class="btn btn-primary w-100 btn-lg"
                                id="btnCheckout">

                            <i class="bi bi-check-circle"></i>
                            Konfirmasi Checkout
                        </button>

                        <a href="index.php"
                           class="btn btn-outline-secondary w-100 mt-2">
                            <i class="bi bi-arrow-left"></i>
                            Kembali Belanja
                        </a>

                        <div class="text-center text-muted small mt-3">
                            <i class="bi bi-shield-check text-success"></i>
                            Transaksi disimpan dengan aman
                        </div>

                    </div>
                </div>

            </div>

        </div>

    </form>

    <?php endif; ?>

</div>

<style>
.payment-option {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 16px;
    border: 1px solid #dee2e6;
    border-radius: 12px;
    cursor: pointer;
    transition: .2s;
}

.payment-option:hover {
    border-color: #0d6efd;
    background: #f8fbff;
}

.payment-option:has(input:checked) {
    border: 2px solid #0d6efd;
    background: #f0f7ff;
}

.payment-option input[type="radio"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.payment-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.payment-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 23px;
}

.cash-icon {
    background: #e8f8ef;
    color: #198754;
}

.dana-icon {
    background: #e7f1ff;
    color: #087bff;
}

.qris-box {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 15px;
    padding: 20px;
}

.qris-image-wrapper {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px;
    background: #fff;
    border: 1px solid #eee;
    border-radius: 12px;
    max-width: 100%;
}

.qris-image {
    display: block;
    width: 100%;
    max-width: 320px;
    height: auto;
    object-fit: contain;
}

.sticky-summary {
    position: sticky;
    top: 20px;
}

@media(max-width: 991px) {
    .sticky-summary {
        position: static;
    }
}
</style>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const radioTunai = document.getElementById("tunai");
    const radioQris = document.getElementById("qris");
    const qrisContainer = document.getElementById("qrisContainer");
    const qrisImage = document.getElementById("qrisImage");

    function updatePayment() {

        if (radioQris && radioQris.checked) {
            qrisContainer.style.display = "block";
        } else {
            qrisContainer.style.display = "none";
        }
    }

    if (radioTunai) {
        radioTunai.addEventListener("change", updatePayment);
    }

    if (radioQris) {
        radioQris.addEventListener("change", updatePayment);
    }

    // Tampilkan QR jika QRIS sudah dipilih sebelumnya
    updatePayment();

    // Periksa apakah gambar QR berhasil dimuat
    if (qrisImage) {
        qrisImage.addEventListener("error", function () {
            this.style.display = "none";

            const pesan = document.createElement("div");
            pesan.className = "alert alert-danger mt-2";
            pesan.textContent =
                "Gambar QR DANA tidak ditemukan. Periksa folder assets/qris-dana.jpeg.";

            this.parentElement.appendChild(pesan);
        });
    }

});
</script>

<?php require "footer.php"; ?>
