<?php
include "koneksi.php";
include "auth.php";
require_role('siswa');
mysqli_report(MYSQLI_REPORT_OFF);

$siswa = siswa_saya($koneksi);
$nilai = $siswa ? rows(dbq($koneksi, "SELECT m.kode_mapel, m.nama_mapel, g.nama AS guru,
              n.nilai_kkm, n.nilai_pengetahuan, n.nilai_keterampilan, n.Keterangan
            FROM mengajar j
            JOIN mapel m ON m.id = j.mapel_id
            JOIN guru g  ON g.id = m.guru_id
            LEFT JOIN nilai_rapor n ON n.mapel_id = j.mapel_id AND n.siswa_id = j.siswa_id
            WHERE j.siswa_id = ? ORDER BY m.nama_mapel", 'i', [(int)$siswa['id']])) : [];

list($ada, $rata, $tuntas) = ringkas_nilai($nilai);

$page_sub = 'Rekap nilai pengetahuan dan keterampilan per mata pelajaran.';
include "navbar.php";
?>

<?php flash_show(); ?>

<?php if (!$siswa): ?>
  <div class="alert alert-warning">Akun ini belum terhubung dengan data siswa. Hubungi admin.</div>
<?php else: ?>

<div class="idbox">
  <div><small>Nama</small><b><?= e($siswa['nama']) ?></b></div>
  <div><small>NIS</small><b><?= e($siswa['nis']) ?></b></div>
  <div><small>Kelas</small><b><?= e($siswa['kelas']) ?></b></div>
  <div><small>Jenis kelamin</small><b><?= $siswa['jenis_kelamin'] === 'P' ? 'Perempuan' : 'Laki-laki' ?></b></div>
</div>

<div class="table-responsive">
  <table class="table align-middle">
    <thead>
      <tr>
        <th class="text-center d-none d-sm-table-cell">No</th><th>Mata pelajaran</th><th>Guru</th>
        <th class="text-center">KKM</th><th class="text-center">Peng.</th><th class="text-center">Ket.</th>
        <th class="text-center">Rata-rata</th><th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($nilai as $i => $r): ?>
      <tr>
        <td class="text-center d-none d-sm-table-cell"><?= $i + 1 ?></td>
        <td><?= e($r['nama_mapel']) ?> <span class="badge text-bg-light"><?= e($r['kode_mapel']) ?></span></td>
        <td><?= e($r['guru']) ?></td>
        <?php if ($r['nilai_pengetahuan'] === null): ?>
          <td class="text-center">-</td><td class="text-center">-</td><td class="text-center">-</td><td class="text-center">-</td>
          <td><?= stamp(null) ?></td>
        <?php else: ?>
          <td class="text-center"><?= (int)$r['nilai_kkm'] ?></td>
          <td class="text-center<?= kelas_nilai($r['nilai_pengetahuan'], $r['nilai_kkm']) ?>"><?= (int)$r['nilai_pengetahuan'] ?></td>
          <td class="text-center<?= kelas_nilai($r['nilai_keterampilan'], $r['nilai_kkm']) ?>"><?= (int)$r['nilai_keterampilan'] ?></td>
          <td class="text-center"><?= skor_sel(rata_nilai($r['nilai_pengetahuan'], $r['nilai_keterampilan']), $r['nilai_kkm']) ?></td>
          <td><?= stamp($r['Keterangan']) ?></td>
        <?php endif; ?>
      </tr>
      <?php endforeach; ?>
      <?php if (!$nilai): ?><tr><td colspan="8" class="text-center text-muted py-4">Kamu belum terdaftar di mata pelajaran manapun.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<div class="ringkas">
  <div>
    <small>Rata-rata keseluruhan</small>
    <div class="besar"><?= $rata !== null ? $rata : '-' ?></div>
  </div>
  <div>
    <small>Ketuntasan</small>
    <b><?= $tuntas ?> dari <?= count($ada) ?></b> mapel bernilai tuntas<br>
    <span class="text-muted small"><?= count($nilai) - count($ada) ?> mapel belum ada nilainya</span>
  </div>
</div>

<?php endif; ?>

<?php include "script.php"; ?>
