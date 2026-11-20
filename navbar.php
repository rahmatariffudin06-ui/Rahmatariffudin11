<?php
include_once "auth.php";
$cur       = basename($_SERVER['PHP_SELF']);
$role      = isset($_SESSION['role']) ? $_SESSION['role'] : '';
$logged    = isset($_SESSION['user_id']);
$nama_user = isset($_SESSION['username']) ? $_SESSION['username'] : '';
$aksi_q    = isset($_GET['aksi']) ? $_GET['aksi'] : '';

// Judul dan keterangan tiap halaman. Halaman boleh menimpa lewat $page_judul / $page_sub sebelum include.
$label_role = array('admin' => 'Administrator', 'guru' => 'Guru', 'siswa' => 'Siswa');
$halaman = array(
    'dashboard.php' => array('Beranda', '', ''),
    'user.php'      => array('Kelola User', 'Akun login: admin, guru, dan siswa.', 'Data master'),
    'guru.php'      => array('Kelola Guru', 'Guru pengampu mata pelajaran.', 'Data master'),
    'siswa.php'     => array('Kelola Siswa', 'Siswa terdaftar beserta kelasnya.', 'Data master'),
    'mapel.php'     => array('Kelola Mapel', 'Mata pelajaran dan guru pengampunya.', 'Data master'),
    'mengajar.php'  => array('Kelola Mengajar', 'Siswa yang mengikuti tiap mata pelajaran.', 'Data master'),
    'nilai.php'     => array('Nilai Siswa', 'Input dan ubah nilai rapor untuk mapel yang Anda ampu.', 'Penilaian'),
    'nilai_saya.php'=> array('Rapor Saya', '', 'Akademik'),
    'profil.php'    => array('Profil', '', 'Akun'),
    'cek_db.php'    => array('Cek Struktur Database', 'Membandingkan database dengan skema di soal.', 'Peralatan'),
);
$info  = isset($halaman[$cur]) ? $halaman[$cur] : array('SIPN', '', '');
$judul = isset($page_judul) ? $page_judul : $info[0];
$sub   = isset($page_sub)   ? $page_sub   : $info[1];
$eyebrow = $info[2] !== '' ? $info[2] : (isset($label_role[$role]) ? $label_role[$role] : '');
$kelas_sheet = isset($sheet_kelas) ? $sheet_kelas : '';
$ver = @filemtime(__DIR__ . '/style.css');

// Menu admin: tiap section satu file halaman, isinya "Daftar X" dan "+ Tambah X"
$grup_admin = array(
    array('User',     'user.php',     'users'),
    array('Guru',     'guru.php',     'user'),
    array('Siswa',    'siswa.php',    'cap'),
    array('Mapel',    'mapel.php',    'book'),
    array('Mengajar', 'mengajar.php', 'link'),
);

// Ikon garis sederhana (inline SVG, tanpa file tambahan)
if (!function_exists('ikon')) {
    function ikon($n) {
        $p = array(
            'home'   => '<path d="M3 11l9-8 9 8v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/>',
            'users'  => '<circle cx="9" cy="8" r="4"/><path d="M1 21v-1a6 6 0 0 1 12 0v1"/><path d="M16 4a4 4 0 0 1 0 8M17 14a6 6 0 0 1 6 6v1"/>',
            'user'   => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
            'cap'    => '<path d="M2 9l10-5 10 5-10 5z"/><path d="M6 11v5c0 1.5 3 3 6 3s6-1.5 6-3v-5"/>',
            'book'   => '<path d="M4 4h12a4 4 0 0 1 4 4v12H8a4 4 0 0 1-4-4z"/><path d="M4 16a4 4 0 0 1 4-4h12"/>',
            'link'   => '<path d="M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/>',
            'star'   => '<path d="M12 3l2.8 5.7 6.2.9-4.5 4.4 1 6.2L12 17.3 6.5 20.2l1-6.2L3 9.6l6.2-.9z"/>',
            'edit'   => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
            'out'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
            'menu'   => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        );
        return '<svg class="ik" viewBox="0 0 24 24" aria-hidden="true">' . (isset($p[$n]) ? $p[$n] : '') . '</svg>';
    }
}

// Satu grup menu: tombol + dua tautan (Daftar X / + Tambah X). Dibuka lewat script.php (kelas "buka").
if (!function_exists('menu_grup')) {
    function menu_grup($label, $file, $ik, $daftar, $tambah, $cur, $aksi) {
        $di_sini  = ($cur === $file);
        $a_daftar = ($di_sini && $aksi !== 'tambah') ? ' on' : '';
        $a_tambah = ($di_sini && $aksi === 'tambah') ? ' on' : '';
        return '<div class="grp' . ($di_sini ? ' ini' : '') . '">'
             . '<button class="grp-btn' . ($di_sini ? ' on' : '') . '" type="button" aria-expanded="false">'
             . '<span>' . e($label) . '</span><i class="chev"></i></button>'
             . '<div class="sub">'
             . '<a class="' . trim('sub-link' . $a_daftar) . '" href="' . $file . '">' . e($daftar) . '</a>'
             . '<a class="' . trim('sub-link tambah' . $a_tambah) . '" href="' . $file . '?aksi=tambah">+ ' . e($tambah) . '</a>'
             . '</div></div>';
    }
}
if (!function_exists('menu_link')) {
    function menu_link($label, $href, $ik, $aktif) {
        return '<a class="nav-it' . ($aktif ? ' on' : '') . '" href="' . $href . '">' . ikon($ik) . '<span>' . e($label) . '</span></a>';
    }
}
$inisial = strtoupper(substr($nama_user !== '' ? $nama_user : 'S', 0, 1));
?>
<!doctype html>
<html lang="id">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($judul) ?> &middot; SIPN</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link href="style.css?v=<?= (int)$ver ?>" rel="stylesheet">
  </head>

  <body class="<?= $logged ? 'app' : 'guest' ?>">

<?php if ($logged): ?>
  <div class="shell">

    <!-- start navbar (sidebar) -->
    <aside class="side" id="side">
      <a class="brand" href="dashboard.php">
        <span class="mark">SIPN</span>
        <span class="brand-t"><small>SMK Sangkuriang 1 Cimahi</small><b>Pengelolaan Nilai</b></span>
      </a>

      <nav class="side-nav" aria-label="Menu utama">
        <div class="cap-t">Menu</div>
        <?= menu_link('Beranda', 'dashboard.php', 'home', $cur === 'dashboard.php') ?>

        <?php if ($role === 'admin'): foreach ($grup_admin as $g):
            echo menu_grup($g[0], $g[1], $g[2], 'Daftar ' . $g[0], 'Tambah ' . $g[0], $cur, $aksi_q);
        endforeach; endif; ?>

        <?php if ($role === 'guru'): echo menu_grup('Nilai', 'nilai.php', 'star', 'Daftar Nilai', 'Input Nilai', $cur, $aksi_q); endif; ?>

        <?php if ($role === 'siswa'): echo menu_link('Rapor Saya', 'nilai_saya.php', 'star', $cur === 'nilai_saya.php'); endif; ?>

        <div class="cap-t">Akun</div>
        <?= menu_link('Profil', 'profil.php', 'user', ($cur === 'profil.php' && $aksi_q !== 'edit')) ?>
        <?= menu_link('Ubah Profil', 'profil.php?aksi=edit', 'edit', ($cur === 'profil.php' && $aksi_q === 'edit')) ?>
      </nav>

      <div class="side-user">
        <span class="avatar"><?= e($inisial) ?></span>
        <span class="who"><b><?= e($nama_user) ?></b><small><?= e(isset($label_role[$role]) ? $label_role[$role] : $role) ?></small></span>
        <a class="keluar" href="logout.php" title="Keluar" aria-label="Keluar"><?= ikon('out') ?></a>
      </div>
    </aside>
    <!-- end navbar -->
    <div class="side-back" id="sideBack"></div>

    <div class="main-col">
      <header class="topbar">
        <button class="top-btn" id="sideBtn" type="button" aria-label="Buka menu" aria-controls="side"><?= ikon('menu') ?></button>
        <span class="top-t"><?= e($judul) ?></span>
        <a class="keluar" href="logout.php" aria-label="Keluar"><?= ikon('out') ?></a>
      </header>

      <main class="page">
        <div class="head">
          <h1><?= e($judul) ?></h1>
          <?php if ($sub !== ''): ?><p><?= e($sub) ?></p><?php endif; ?>
        </div>
<?php else: ?>
  <div class="sheet sheet-kecil <?= e($kelas_sheet) ?>">
    <header class="kop-tengah">
      <div class="mark">SIPN</div>
      <div class="kop-inst">SMK Sangkuriang 1 Cimahi</div>
      <div class="kop-app">Sistem Informasi Pengelolaan Nilai</div>
    </header>
    <main class="page">
<?php endif; ?>
