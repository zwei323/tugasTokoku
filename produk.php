<?php
require_once "fungsi.php";

$q      = trim($_GET['q'] ?? '');
$kat    = (int)($_GET['kat'] ?? 0);
$urut   = $_GET['urut'] ?? 'nama';
$hal    = max(1, (int)($_GET['hal'] ?? 1));
$per    = 12;

$orderMap = [
    'terbaru'  => 'p.id_produk DESC',
    'termurah' => 'p.harga ASC',
    'termahal' => 'p.harga DESC',
    'nama'     => 'p.nama_produk ASC',
];
if (!isset($orderMap[$urut])) $urut = 'nama';

$where = []; $types = ''; $params = [];
if ($q !== '') {
    $where[] = "(p.nama_produk LIKE ? OR k.nama_kategori LIKE ?)";
    $like = '%' . $q . '%';
    $types .= 'ss'; $params[] = $like; $params[] = $like;
}
if ($kat > 0) { $where[] = "p.id_kategori = ?"; $types .= 'i'; $params[] = $kat; }
$w = $where ? "WHERE " . implode(" AND ", $where) : "";

$from = "FROM tbl_produk p LEFT JOIN tbl_kategori k ON k.id_kategori = p.id_kategori $w";

$stmt = $conn->prepare("SELECT COUNT(*) c $from");
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total = (int)$stmt->get_result()->fetch_assoc()['c'];
$halaman = max(1, (int)ceil($total / $per));
$hal = min($hal, $halaman);
$offset = ($hal - 1) * $per;

$stmt = $conn->prepare("SELECT p.*, k.nama_kategori $from ORDER BY {$orderMap[$urut]} LIMIT $per OFFSET $offset");
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$produk = $stmt->get_result();

$daftarKat = $conn->query("SELECT * FROM tbl_kategori ORDER BY nama_kategori");

if ($urut === 'terbaru' && $q === '' && $kat === 0) $pageTitle = "Produk Terbaru";
elseif ($q !== '') $pageTitle = "Hasil pencarian: " . $q;
else $pageTitle = "Semua Produk";
require "header.php";

function link_hal($ubah) {
    $p = array_merge($_GET, $ubah);
    return 'produk.php?' . http_build_query($p);
}
?>
<h4 class="mb-3"><?= e($pageTitle) ?> <small class="text-muted fs-6">(<?= $total ?> produk)</small></h4>

<form method="get" class="row g-2 mb-4">
  <div class="col-12 col-md-5"><input type="search" name="q" class="form-control" placeholder="Cari produk..." value="<?= e($q) ?>"></div>
  <div class="col-6 col-md-3">
    <select name="kat" class="form-select">
      <option value="0">Semua kategori</option>
      <?php while ($k = $daftarKat->fetch_assoc()): ?>
        <option value="<?= (int)$k['id_kategori'] ?>" <?= $kat === (int)$k['id_kategori'] ? 'selected' : '' ?>><?= e($k['nama_kategori']) ?></option>
      <?php endwhile; ?>
    </select>
  </div>
  <div class="col-6 col-md-2">
    <select name="urut" class="form-select">
      <option value="nama" <?= $urut==='nama'?'selected':'' ?>>Nama A-Z</option>
      <option value="terbaru" <?= $urut==='terbaru'?'selected':'' ?>>Terbaru</option>
      <option value="termurah" <?= $urut==='termurah'?'selected':'' ?>>Termurah</option>
      <option value="termahal" <?= $urut==='termahal'?'selected':'' ?>>Termahal</option>
    </select>
  </div>
  <div class="col-12 col-md-2 d-grid"><button class="btn btn-primary"><i class="bi bi-funnel"></i> Terapkan</button></div>
</form>

<?php if ($total === 0): ?>
  <div class="text-center text-muted py-5">
    <i class="bi bi-search fs-1"></i>
    <p class="mt-2">Produk tidak ditemukan. Coba kata kunci lain.</p>
    <a href="produk.php" class="btn btn-outline-primary">Lihat semua produk</a>
  </div>
<?php else: ?>
  <div class="row g-3"><?php while ($p = $produk->fetch_assoc()) echo kartu_produk($p); ?></div>
  <?php if ($halaman > 1): ?>
  <nav class="mt-4"><ul class="pagination justify-content-center">
    <li class="page-item <?= $hal<=1?'disabled':'' ?>"><a class="page-link" href="<?= e(link_hal(['hal'=>$hal-1])) ?>">&laquo;</a></li>
    <?php for ($i = 1; $i <= $halaman; $i++): ?>
      <li class="page-item <?= $i===$hal?'active':'' ?>"><a class="page-link" href="<?= e(link_hal(['hal'=>$i])) ?>"><?= $i ?></a></li>
    <?php endfor; ?>
    <li class="page-item <?= $hal>=$halaman?'disabled':'' ?>"><a class="page-link" href="<?= e(link_hal(['hal'=>$hal+1])) ?>">&raquo;</a></li>
  </ul></nav>
  <?php endif; ?>
<?php endif; ?>
<?php require "footer.php"; ?>
