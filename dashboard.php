<?php
include "koneksi.php";
include "auth.php";
require_login();
mysqli_report(MYSQLI_REPORT_OFF);

$role = $_SESSION['role'];
$guru = null; $siswa = null;
$mapel = []; $nilai = []; $ada = [];

// ---------- data per role ----------
if ($role === 'admin') {
    $n_user     = hitung($koneksi, "SELECT COUNT(*) FROM users");
    $n_guru     = hitung($koneksi, "SELECT COUNT(*) FROM guru");
    $n_siswa    = hitung($koneksi, "SELECT COUNT(*) FROM siswa");
    $n_mapel    = hitung($koneksi, "SELECT COUNT(*) FROM mapel");
    $n_mengajar = hitung($koneksi, "SELECT COUNT(*) FROM mengajar");
    $n_nilai    = hitung($koneksi, "SELECT COUNT(*) FROM nilai_rapor");
    $per_kelas  = rows(dbq($koneksi, "SELECT kelas, COUNT(*) AS jml FROM siswa GROUP BY kelas ORDER BY kelas"));
    $maks_kelas = 1;
    foreach ($per_kelas as $pk) { if ((int)$pk['jml'] > $maks_kelas) $maks_kelas = (int)$pk['jml']; }
    $cc         = kolom_dibuat($koneksi);
    $user_baru  = rows(dbq($koneksi, "SELECT u.username, u.role, $cc AS created_at FROM users u ORDER BY u.id DESC LIMIT 5"));
}

if ($role === 'guru') {
    $guru = guru_saya($koneksi);
    if ($guru) {
        $gid = (int)$guru['id'];
        $mapel = rows(dbq($koneksi, "SELECT m.id, m.kode_mapel, m.nama_mapel,
                (SELECT COUNT(*) FROM mengajar j WHERE j.mapel_id = m.id) AS jml_siswa,
                (SELECT COUNT(*) FROM mengajar j2 WHERE j2.mapel_id = m.id
                    AND EXISTS (SELECT 1 FROM nilai_rapor n WHERE n.mapel_id = j2.mapel_id AND n.siswa_id = j2.siswa_id)) AS jml_nilai
              FROM mapel m WHERE m.guru_id = ? ORDER BY m.nama_mapel", 'i', [$gid]));
        $siswa_unik = hitung($koneksi, "SELECT COUNT(DISTINCT j.siswa_id) FROM mengajar j JOIN mapel m ON m.id = j.mapel_id WHERE m.guru_id = ?", 'i', [$gid]);
        $sudah = array_sum(array_column($mapel, 'jml_nilai'));
        $total = array_sum(array_column($mapel, 'jml_siswa'));
    }
}

if ($role === 'siswa') {
    $siswa = siswa_saya($koneksi);
    if ($siswa) {
        $nilai = rows(dbq($koneksi, "SELECT m.nama_mapel, n.nilai_kkm, n.nilai_pengetahuan, n.nilai_keterampilan, n.Keterangan
                FROM mengajar j
                JOIN mapel m ON m.id = j.mapel_id
                LEFT JOIN nilai_rapor n ON n.mapel_id = j.mapel_id AND n.siswa_id = j.siswa_id
                WHERE j.siswa_id = ? ORDER BY m.nama_mapel", 'i', [(int)$siswa['id']]));
        list($ada, $rata, $tuntas) = ringkas_nilai($nilai);
    }
}

$nama_tampil = $_SESSION['username'];
$sub = '';
if ($guru)  { $nama_tampil = $guru['nama'];  $sub = 'NIP ' . $guru['nip']; }
if ($siswa) { $nama_tampil = $siswa['nama']; $sub = 'NIS ' . $siswa['nis'] . ' · Kelas ' . $siswa['kelas']; }
$page_judul = 'Beranda';
$page_sub   = 'Selamat datang, ' . $nama_tampil . ($sub !== '' ? '  ·  ' . $sub : '');

include "navbar.php";
?>

<?php flash_show(); ?>

<?php if (($role === 'guru' && !$guru) || ($role === 'siswa' && !$siswa)): ?>
  <div class="alert alert-warning">Akun ini belum terhubung dengan data <?= $role ?>. Hubungi admin.</div>
<?php endif; ?>

<?php if ($role === 'admin'): ?>
<div class="row g-4">
  <div class="col-lg-5">
    <section class="sec">
      <h2 class="sec-title">Ringkasan data</h2>
      <ul class="ledger">
        <?= ledger_row('User', $n_user, 'user.php') ?>
        <?= ledger_row('Guru', $n_guru, 'guru.php') ?>
        <?= ledger_row('Siswa', $n_siswa, 'siswa.php') ?>
        <?= ledger_row('Mata pelajaran', $n_mapel, 'mapel.php') ?>
        <?= ledger_row('Data mengajar', $n_mengajar, 'mengajar.php') ?>
        <?= ledger_row('Nilai rapor', $n_nilai) ?>
      </ul>
    </section>
    <section class="sec">
      <h2 class="sec-title">Pintasan</h2>
      <ul class="pintas">
        <li><a href="guru.php?aksi=tambah">+ Tambah guru</a></li>
        <li><a href="siswa.php?aksi=tambah">+ Tambah siswa</a></li>
        <li><a href="mapel.php?aksi=tambah">+ Tambah mata pelajaran</a></li>
        <li><a href="mengajar.php?aksi=tambah">+ Tambah data mengajar</a></li>
      </ul>
    </section>
  </div>

  <div class="col-lg-7">
    <section class="sec">
      <h2 class="sec-title">Siswa per kelas</h2>
      <table class="table">
        <thead><tr><th>Kelas</th><th class="text-end">Jumlah siswa</th></tr></thead>
        <tbody>
          <?php foreach ($per_kelas as $r): ?>
            <tr><td><?= e($r['kelas']) ?></td><td class="text-end text-nowrap"><span class="bar"><i style="width:<?= (int)round($r['jml'] / $maks_kelas * 100) ?>%"></i></span> <?= (int)$r['jml'] ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$per_kelas): ?><tr><td colspan="2" class="text-muted text-center py-3">Belum ada data.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </section>
    <section class="sec">
      <h2 class="sec-title">User terbaru <small><a href="user.php">Lihat semua</a></small></h2>
      <table class="table">
        <thead><tr><th>Username</th><th>Role</th><th>Dibuat</th></tr></thead>
        <tbody>
          <?php foreach ($user_baru as $r): ?>
            <tr><td><?= e($r['username']) ?></td><td><span class="badge text-bg-secondary"><?= e($r['role']) ?></span></td><td class="text-muted small"><?= tgl($r['created_at']) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$user_baru): ?><tr><td colspan="3" class="text-muted text-center py-3">Belum ada data.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </section>
  </div>
</div>
<?php endif; ?>

<?php if ($role === 'guru' && $guru): ?>
<div class="row g-4">
  <div class="col-lg-4">
    <section class="sec">
      <h2 class="sec-title">Ringkasan</h2>
      <ul class="ledger">
        <?= ledger_row('Mapel diampu', count($mapel), 'nilai.php') ?>
        <?= ledger_row('Siswa diajar', $siswa_unik) ?>
        <?= ledger_row('Sudah dinilai', $sudah, 'nilai.php') ?>
        <?= ledger_row('Belum dinilai', $total - $sudah, 'nilai.php?aksi=tambah') ?>
      </ul>
    </section>
  </div>
  <div class="col-lg-8">
    <section class="sec">
      <h2 class="sec-title">Mapel yang saya ampu <small><a href="nilai.php?aksi=tambah">+ Input nilai</a></small></h2>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead><tr><th>Kode</th><th>Mata pelajaran</th><th class="text-center">Siswa</th><th>Sudah dinilai</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($mapel as $m):
                $pct = $m['jml_siswa'] > 0 ? round($m['jml_nilai'] / $m['jml_siswa'] * 100) : 0; ?>
            <tr>
              <td><span class="badge text-bg-light"><?= e($m['kode_mapel']) ?></span></td>
              <td><?= e($m['nama_mapel']) ?></td>
              <td class="text-center"><?= (int)$m['jml_siswa'] ?></td>
              <td class="text-nowrap"><?= (int)$m['jml_nilai'] ?> / <?= (int)$m['jml_siswa'] ?><span class="bar"><i style="width:<?= (int)$pct ?>%"></i></span></td>
              <td class="text-end"><a class="btn btn-outline-primary btn-sm" href="nilai.php?mapel_id=<?= (int)$m['id'] ?>">Lihat</a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$mapel): ?><tr><td colspan="5" class="text-center text-muted py-4">Anda belum ditugaskan mengampu mapel.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</div>
<?php endif; ?>

<?php if ($role === 'siswa' && $siswa): ?>
<div class="row g-4">
  <div class="col-lg-4">
    <section class="sec">
      <h2 class="sec-title">Ringkasan</h2>
      <ul class="ledger">
        <?= ledger_row('Mapel diikuti', count($nilai), 'nilai_saya.php') ?>
        <?= ledger_row('Nilai sudah masuk', count($ada)) ?>
        <?= ledger_row('Rata-rata', $rata !== null ? $rata : '-') ?>
        <?= ledger_row('Mapel tuntas', $tuntas . '/' . count($ada)) ?>
      </ul>
    </section>
  </div>
  <div class="col-lg-8">
    <section class="sec">
      <h2 class="sec-title">Nilai terbaru <small><a href="nilai_saya.php">Rapor lengkap</a></small></h2>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead><tr><th>Mata pelajaran</th><th class="text-center">Rata-rata</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($nilai as $r): ?>
            <tr>
              <td><?= e($r['nama_mapel']) ?></td>
              <td class="text-center"><?= $r['nilai_pengetahuan'] === null ? '-' : rata_nilai($r['nilai_pengetahuan'], $r['nilai_keterampilan']) ?></td>
              <td><?= stamp($r['nilai_pengetahuan'] === null ? null : $r['Keterangan']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$nilai): ?><tr><td colspan="3" class="text-center text-muted py-4">Kamu belum terdaftar di mapel manapun.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</div>
<?php endif; ?>

<?php include "script.php"; ?>
