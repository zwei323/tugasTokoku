<?php
require_once __DIR__ . "/fungsi.php";
$pageTitle = $pageTitle ?? "Tokoku";
$hdrHal = basename($_SERVER['PHP_SELF']);
$hdrUrut = $_GET['urut'] ?? '';
function aktif($cond) { return $cond ? 'active' : ''; }
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> - Tokoku</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
  body{background:#f5f7fb}
  .produk-card{border:0;box-shadow:0 1px 4px rgba(0,0,0,.08);transition:.15s}
  .produk-card:hover{transform:translateY(-3px);box-shadow:0 6px 16px rgba(0,0,0,.12)}
  .produk-img{overflow:hidden;height:120px;display:flex;align-items:center;justify-content:center;font-size:3rem;
              color:#3b82f6;background:linear-gradient(135deg,#e0ecff,#f0f6ff);border-radius:.375rem .375rem 0 0}
  .produk-img img{width:100%;height:100%;object-fit:cover}
  .hero{background:linear-gradient(135deg,#1e3a8a,#3b82f6);color:#fff;border-radius:16px}
  .kat-card{border:0;box-shadow:0 1px 4px rgba(0,0,0,.08);text-decoration:none;color:inherit;transition:.15s}
  .kat-card:hover{transform:translateY(-3px);box-shadow:0 6px 16px rgba(0,0,0,.12)}
  .kat-icon{font-size:2.4rem;color:#3b82f6}
  .steps{display:flex;justify-content:space-between;position:relative;margin:28px 0}
  .steps:before{content:"";position:absolute;top:18px;left:8%;right:8%;height:4px;background:#dee2e6}
  .step{position:relative;text-align:center;flex:1;font-size:.85rem;color:#6c757d}
  .step .dot{width:40px;height:40px;border-radius:50%;background:#dee2e6;color:#fff;margin:0 auto 6px;
             display:flex;align-items:center;justify-content:center;position:relative;z-index:1}
  .step.done{color:#198754;font-weight:600}.step.done .dot{background:#198754}
  .step.now .dot{box-shadow:0 0 0 5px rgba(25,135,84,.25)}
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
  <div class="container">
    <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-shop-window"></i> Tokoku</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="menu">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link <?= aktif($hdrHal==='index.php') ?>" href="index.php">Beranda</a></li>
        <li class="nav-item"><a class="nav-link <?= aktif($hdrHal==='kategori.php') ?>" href="kategori.php">Semua Kategori</a></li>
        <li class="nav-item"><a class="nav-link <?= aktif($hdrHal==='produk.php' && $hdrUrut==='terbaru') ?>" href="produk.php?urut=terbaru">Terbaru</a></li>
        <li class="nav-item"><a class="nav-link <?= aktif($hdrHal==='produk.php' && $hdrUrut!=='terbaru') ?>" href="produk.php">Semua Produk</a></li>
        <li class="nav-item"><a class="nav-link <?= aktif($hdrHal==='lacak.php') ?>" href="lacak.php">Lacak Pesanan</a></li>
      </ul>
      <form class="d-flex me-lg-3 my-2 my-lg-0" action="produk.php" method="get">
        <input class="form-control form-control-sm me-2" type="search" name="q" placeholder="Cari produk..." value="<?= e($_GET['q'] ?? '') ?>">
        <button class="btn btn-light btn-sm"><i class="bi bi-search"></i></button>
      </form>
      <?php if (user_login_check()): ?>
      <div class="dropdown me-lg-2 my-2 my-lg-0">
        <button class="btn btn-outline-light btn-sm dropdown-toggle" data-bs-toggle="dropdown">
          <i class="bi bi-person-circle"></i> <?= e($_SESSION['user_nama']) ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="pesanan.php"><i class="bi bi-receipt"></i> Pesanan Saya</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right"></i> Keluar</a></li>
        </ul>
      </div>
      <?php else: ?>
      <div class="me-lg-2 my-2 my-lg-0">
        <a href="masuk.php" class="btn btn-outline-light btn-sm">Masuk</a>
        <a href="daftar.php" class="btn btn-light btn-sm">Daftar</a>
      </div>
      <?php endif; ?>
      <a class="btn btn-warning btn-sm position-relative" href="keranjang.php">
        <i class="bi bi-cart3"></i> Keranjang
        <?php if (cart_count() > 0): ?>
          <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= cart_count() ?></span>
        <?php endif; ?>
      </a>
    </div>
  </div>
</nav>
<div class="container py-4">
<?php if (!empty($_SESSION['flash'])): $hdrFlash = $_SESSION['flash']; unset($_SESSION['flash']); ?>
  <div class="alert alert-<?= e($hdrFlash['type']) ?> alert-dismissible fade show" role="alert">
    <?= e($hdrFlash['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>
