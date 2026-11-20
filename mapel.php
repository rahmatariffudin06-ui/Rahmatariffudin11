<?php
include "koneksi.php";
include "auth.php";
require_admin();
mysqli_report(MYSQLI_REPORT_OFF);

$aksi = $_GET['aksi'] ?? 'list';
$id   = (int)($_GET['id'] ?? 0);

// ================= PROSES POST =================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do      = $_POST['do'] ?? '';
    $kode    = trim($_POST['kode_mapel'] ?? '');
    $nama    = trim($_POST['nama_mapel'] ?? '');
    $guru_id = (int)($_POST['guru_id'] ?? 0);

    if (($do === 'tambah' || $do === 'edit') && !rows(dbq($koneksi, "SELECT id FROM guru WHERE id = ?", 'i', [$guru_id]))) {
        flash_set('danger', 'Guru pengampu tidak valid.');
        header("Location: mapel.php"); exit;
    }

    if ($do === 'tambah') {
        if (dbq($koneksi, "INSERT INTO mapel (kode_mapel, nama_mapel, guru_id) VALUES (?, ?, ?)", 'ssi', [$kode, $nama, $guru_id])) {
            flash_set('success', 'Mapel berhasil ditambahkan.');
        } else {
            flash_set('danger', 'Gagal menambah mapel: ' . pesan_db($koneksi));
        }
        header("Location: mapel.php"); exit;
    }

    if ($do === 'edit') {
        $mid = (int)($_POST['id'] ?? 0);
        if (dbq($koneksi, "UPDATE mapel SET kode_mapel = ?, nama_mapel = ?, guru_id = ? WHERE id = ?", 'ssii', [$kode, $nama, $guru_id, $mid])) {
            flash_set('success', 'Data mapel berhasil diupdate.');
        } else {
            flash_set('danger', 'Gagal update mapel: ' . pesan_db($koneksi));
        }
        header("Location: mapel.php"); exit;
    }

    if ($do === 'hapus') {
        $mid = (int)($_POST['id'] ?? 0);
        $n_mengajar = hitung($koneksi, "SELECT COUNT(*) FROM mengajar WHERE mapel_id = ?", 'i', [$mid]);
        $n_nilai    = hitung($koneksi, "SELECT COUNT(*) FROM nilai_rapor WHERE mapel_id = ?", 'i', [$mid]);
        if ($n_mengajar > 0 || $n_nilai > 0) {
            flash_set('danger', "Mapel tidak bisa dihapus: masih dipakai di $n_mengajar data Mengajar dan $n_nilai data Nilai. Hapus dulu data tersebut.");
        } elseif (dbq($koneksi, "DELETE FROM mapel WHERE id = ?", 'i', [$mid])) {
            flash_set('success', 'Mapel berhasil dihapus.');
        } else {
            flash_set('danger', 'Gagal hapus mapel: ' . pesan_db($koneksi));
        }
        header("Location: mapel.php"); exit;
    }
}

// ================= SIAPKAN DATA TAMPILAN =================
$d = ['id' => 0, 'kode_mapel' => '', 'nama_mapel' => '', 'guru_id' => 0];
$opt_guru = [];
if ($aksi === 'tambah' || $aksi === 'edit') {
    $opt_guru = rows(dbq($koneksi, "SELECT id, nip, nama FROM guru ORDER BY nama ASC"));
}
if ($aksi === 'edit') {
    $r = rows(dbq($koneksi, "SELECT id, kode_mapel, nama_mapel, guru_id FROM mapel WHERE id = ?", 'i', [$id]));
    if (!$r) { flash_set('danger', 'Data mapel tidak ditemukan.'); header("Location: mapel.php"); exit; }
    $d = $r[0];
}

$cari = trim($_GET['q'] ?? '');
$hal  = max(1, (int)($_GET['hal'] ?? 1));
$per  = 15;
$list = []; $total = 0; $pages = 1;
if ($aksi === 'list') {
    $like  = '%' . $cari . '%';
    $where = "(m.kode_mapel LIKE ? OR m.nama_mapel LIKE ? OR g.nama LIKE ?)";
    $total = hitung($koneksi, "SELECT COUNT(*) FROM mapel m JOIN guru g ON g.id = m.guru_id WHERE $where", 'sss', [$like, $like, $like]);
    $pages = max(1, (int)ceil($total / $per));
    $hal   = min($hal, $pages);
    $off   = ($hal - 1) * $per;
    $list  = rows(dbq($koneksi, "SELECT m.id, m.kode_mapel, m.nama_mapel, g.nama AS guru,
                  (SELECT COUNT(*) FROM mengajar j WHERE j.mapel_id = m.id) AS jml_siswa
                FROM mapel m JOIN guru g ON g.id = m.guru_id
                WHERE $where ORDER BY m.nama_mapel ASC LIMIT $per OFFSET $off", 'sss', [$like, $like, $like]));
}

include "navbar.php";
?>

<div class="container py-4">
  <?php flash_show(); ?>

<?php if ($aksi === 'tambah' || $aksi === 'edit'): ?>

  <div class="row justify-content-center">
    <div class="col-md-7">
      <div class="card">
        <div class="card-header"><?= $aksi === 'edit' ? 'Edit Mapel' : 'Tambah Mapel' ?></div>
        <div class="card-body">

          <?php if (!$opt_guru): ?>
            <div class="alert alert-warning">Mapel butuh guru pengampu. <a href="guru.php?aksi=tambah">Tambah guru</a> dulu.</div>
          <?php endif; ?>

          <form action="mapel.php" method="POST">
            <?= csrf_field() ?><input type="hidden" name="do" value="<?= $aksi ?>">
            <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">

            <div class="mb-3">
              <label class="form-label" for="kode_mapel">Kode Mapel</label>
              <input type="text" class="form-control" id="kode_mapel" name="kode_mapel" maxlength="10" value="<?= e($d['kode_mapel']) ?>" placeholder="Contoh: PW" required>
            </div>
            <div class="mb-3">
              <label class="form-label" for="nama_mapel">Nama Mapel</label>
              <input type="text" class="form-control" id="nama_mapel" name="nama_mapel" maxlength="100" value="<?= e($d['nama_mapel']) ?>" placeholder="Contoh: Pemrograman Web" required>
            </div>
            <div class="mb-4">
              <label class="form-label" for="guru_id">Guru Pengampu</label>
              <select class="form-select" id="guru_id" name="guru_id" required>
                <option value="" disabled <?= !$d['guru_id'] ? 'selected' : '' ?>>-- Pilih Guru --</option>
                <?php foreach ($opt_guru as $g): ?>
                  <option value="<?= (int)$g['id'] ?>" <?= (int)$d['guru_id'] === (int)$g['id'] ? 'selected' : '' ?>><?= e($g['nama']) ?> (<?= e($g['nip']) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary" <?= !$opt_guru ? 'disabled' : '' ?>>Simpan</button>
              <a href="mapel.php" class="btn btn-secondary">Kembali</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

<?php else: ?>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
      <span>Daftar Mapel <span class="badge bg-secondary"><?= $total ?></span></span>
      <form method="get" action="mapel.php" class="d-flex gap-2 flex-wrap">
        <input type="text" name="q" value="<?= e($cari) ?>" class="form-control form-control-sm" placeholder="Cari kode / mapel / guru" style="width:230px">
        <button type="submit" class="btn btn-outline-primary btn-sm">Cari</button>
        <a href="mapel.php" class="btn btn-outline-secondary btn-sm">Reset</a>
        <a href="mapel.php?aksi=tambah" class="btn btn-primary btn-sm">+ Tambah Mapel</a>
      </form>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr><th class="text-center">No</th><th>Kode</th><th>Nama Mapel</th><th>Guru Pengampu</th><th class="text-center">Siswa</th><th class="text-center">Aksi</th></tr>
          </thead>
          <tbody>
            <?php foreach ($list as $i => $r): ?>
            <tr>
              <td class="text-center"><?= ($hal - 1) * $per + $i + 1 ?></td>
              <td><span class="badge text-bg-light border"><?= e($r['kode_mapel']) ?></span></td>
              <td><?= e($r['nama_mapel']) ?></td>
              <td><?= e($r['guru']) ?></td>
              <td class="text-center"><?= (int)$r['jml_siswa'] ?></td>
              <td class="text-center">
                <a href="mapel.php?aksi=edit&id=<?= (int)$r['id'] ?>" class="btn btn-warning btn-sm">Edit</a>
                <form action="mapel.php" method="POST" class="d-inline"
                      onsubmit="return confirm('Hapus mapel <?= e(addslashes($r['nama_mapel'])) ?>?')">
                  <?= csrf_field() ?><input type="hidden" name="do" value="hapus">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$list): ?><tr><td colspan="6" class="text-center text-muted py-4">Belum ada data mapel.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <?= pager($hal, $pages, ['q' => $cari]) ?>
    </div>
  </div>

<?php endif; ?>
</div>

<?php include "script.php"; ?>
