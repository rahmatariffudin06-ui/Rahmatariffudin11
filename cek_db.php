<?php
// Halaman diagnosis: membandingkan database yang terhubung dengan skema di soal.
// Hanya admin. HAPUS file ini sebelum dikumpulkan.
include "koneksi.php";
include "auth.php";
require_admin();
mysqli_report(MYSQLI_REPORT_OFF);

$skema = [
    'users'       => ['id', 'username', 'password', 'role', 'created_at'],
    'siswa'       => ['id', 'nis', 'nama', 'kelas', 'jenis_kelamin', 'user_id'],
    'guru'        => ['id', 'nip', 'nama', 'jenis_kelamin', 'user_id'],
    'mapel'       => ['id', 'kode_mapel', 'nama_mapel', 'guru_id'],
    'mengajar'    => ['id', 'mapel_id', 'siswa_id'],
    'nilai_rapor' => ['id', 'siswa_id', 'mapel_id', 'nilai_kkm', 'nilai_pengetahuan', 'nilai_keterampilan', 'keterangan'],
];

$db  = rows(dbq($koneksi, "SELECT DATABASE() AS d, VERSION() AS v"));
$info = $db ? $db[0] : ['d' => '?', 'v' => '?'];

$hasil = [];
$semua_ok = true;
foreach ($skema as $tabel => $harus) {
    $r = rows(dbq($koneksi, "SELECT COLUMN_NAME AS kolom FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?", 's', [$tabel]));
    $ada   = array_map('strtolower', array_column($r, 'kolom'));
    $ada_t = count($ada) > 0;
    $kurang = $ada_t ? array_values(array_diff($harus, $ada)) : $harus;
    $lebih  = $ada_t ? array_values(array_diff($ada, $harus)) : [];
    $baris  = $ada_t ? hitung($koneksi, "SELECT COUNT(*) FROM `$tabel`") : null;
    if (!$ada_t || $kurang || $lebih) $semua_ok = false;
    $hasil[] = compact('tabel', 'ada_t', 'kurang', 'lebih', 'baris');
}

// Query-query yang dipakai halaman Daftar User, dijalankan langsung supaya error aslinya kelihatan
$tes = [
    'Hitung user'                 => "SELECT COUNT(*) FROM users",
    'Ambil daftar user'           => "SELECT u.id, u.username, u.role FROM users u ORDER BY u.id ASC LIMIT 15",
    'Ambil kolom created_at'      => "SELECT u.created_at FROM users u LIMIT 1",
    'Ambil user_id dari guru'     => "SELECT user_id FROM guru",
    'Ambil user_id dari siswa'    => "SELECT user_id FROM siswa",
    'Join mengajar-mapel-siswa-guru' => "SELECT j.id FROM mengajar j JOIN mapel m ON m.id = j.mapel_id JOIN siswa s ON s.id = j.siswa_id JOIN guru g ON g.id = m.guru_id LIMIT 1",
    'Join nilai_rapor'            => "SELECT n.id, n.Keterangan FROM nilai_rapor n JOIN mapel m ON m.id = n.mapel_id JOIN siswa s ON s.id = n.siswa_id LIMIT 1",
];
$hasil_tes = [];
foreach ($tes as $nama => $sql) {
    $q = mysqli_query($koneksi, $sql);
    $hasil_tes[] = ['nama' => $nama, 'ok' => (bool)$q, 'err' => $q ? '' : mysqli_error($koneksi), 'n' => $q ? mysqli_num_rows($q) : 0];
}
$daftar_user = rows(dbq($koneksi, "SELECT id, username, role FROM users ORDER BY id LIMIT 20"));

include "navbar.php";
?>

<div>
  <div class="text-muted mb-4">
    Database: <strong><?= e($info['d']) ?></strong> &middot; Server: <?= e($info['v']) ?> &middot; PHP <?= e(PHP_VERSION) ?>
  </div>

  <div class="alert alert-<?= $semua_ok ? 'success' : 'danger' ?>">
    <?= $semua_ok ? 'Struktur database sesuai skema soal.' : 'Struktur database TIDAK sesuai skema soal. Lihat tabel di bawah. Cara termudah menyamakan: import sipn.sql.' ?>
  </div>

  <div class="card mb-4">
    <div class="card-header">Perbandingan dengan skema soal</div>
    <div class="card-body p-0"><div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th>Tabel</th><th>Status</th><th>Kolom kurang</th><th>Kolom lebih</th><th class="text-end">Jumlah baris</th></tr></thead>
        <tbody>
          <?php foreach ($hasil as $h): $ok = $h['ada_t'] && !$h['kurang'] && !$h['lebih']; ?>
          <tr>
            <td><code><?= e($h['tabel']) ?></code></td>
            <td>
              <?php if (!$h['ada_t']): ?><span class="badge text-bg-danger">Tabel tidak ada</span>
              <?php elseif ($ok): ?><span class="badge text-bg-success">Sesuai</span>
              <?php else: ?><span class="badge text-bg-warning">Beda</span><?php endif; ?>
            </td>
            <td class="text-danger"><?= $h['ada_t'] ? e(implode(', ', $h['kurang'])) : '-' ?></td>
            <td class="text-warning-emphasis"><?= e(implode(', ', $h['lebih'])) ?></td>
            <td class="text-end <?= ($h['baris'] !== null && $h['baris'] < 10) ? 'text-danger fw-semibold' : '' ?>"><?= $h['baris'] === null ? '-' : (int)$h['baris'] ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div></div>
    <div class="card-footer bg-white small text-muted">Soal mewajibkan tiap tabel minimal 10 data (angka merah = kurang dari 10).</div>
  </div>

  <div class="card mb-4">
    <div class="card-header">Uji query yang dipakai aplikasi</div>
    <div class="card-body p-0"><div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th>Query</th><th>Hasil</th><th>Pesan error MySQL</th></tr></thead>
        <tbody>
          <?php foreach ($hasil_tes as $t): ?>
          <tr>
            <td><?= e($t['nama']) ?></td>
            <td><?= $t['ok'] ? '<span class="badge text-bg-success">OK (' . (int)$t['n'] . ' baris)</span>' : '<span class="badge text-bg-danger">GAGAL</span>' ?></td>
            <td class="small text-danger"><?= e($t['err']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div></div>
  </div>

  <div class="card">
    <div class="card-header">Isi tabel users (maks. 20)</div>
    <div class="card-body p-0">
      <table class="table mb-0">
        <thead><tr><th>ID</th><th>Username</th><th>Role</th></tr></thead>
        <tbody>
          <?php foreach ($daftar_user as $u): ?>
            <tr><td><?= (int)$u['id'] ?></td><td><?= e($u['username']) ?></td><td><?= e($u['role']) ?></td></tr>
          <?php endforeach; ?>
          <?php if (!$daftar_user): ?><tr><td colspan="3" class="text-center text-muted py-3">Tidak ada baris yang terbaca.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include "script.php"; ?>
