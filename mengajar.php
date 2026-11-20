<?php
include "koneksi.php";
include "auth.php";
require_admin();
mysqli_report(MYSQLI_REPORT_OFF);

$aksi = $_GET['aksi'] ?? 'list';
$id   = (int)($_GET['id'] ?? 0);

// ================= PROSES POST =================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do       = $_POST['do'] ?? '';
    $mapel_id = (int)($_POST['mapel_id'] ?? 0);

    if (($do === 'tambah' || $do === 'edit') && !rows(dbq($koneksi, "SELECT id FROM mapel WHERE id = ?", 'i', [$mapel_id]))) {
        flash_set('danger', 'Mapel tidak valid.');
        header("Location: mengajar.php"); exit;
    }

    // Tambah: 1 mapel + banyak siswa sekaligus (duplikat dilewati)
    if ($do === 'tambah') {
        $ids = array_unique(array_map('intval', (array)($_POST['siswa_id'] ?? [])));
        if (!$ids) {
            flash_set('danger', 'Pilih minimal 1 siswa.');
            header("Location: mengajar.php?aksi=tambah"); exit;
        }
        $baru = 0; $lewat = 0; $gagal = 0;
        foreach ($ids as $sid) {
            if (!hitung($koneksi, "SELECT COUNT(*) FROM siswa WHERE id = ?", 'i', [$sid])) { $gagal++; continue; }
            if (hitung($koneksi, "SELECT COUNT(*) FROM mengajar WHERE mapel_id = ? AND siswa_id = ?", 'ii', [$mapel_id, $sid])) { $lewat++; continue; }
            dbq($koneksi, "INSERT INTO mengajar (mapel_id, siswa_id) VALUES (?, ?)", 'ii', [$mapel_id, $sid]) ? $baru++ : $gagal++;
        }
        $msg = "$baru siswa ditambahkan ke mapel.";
        if ($lewat) $msg .= " $lewat sudah terdaftar (dilewati).";
        if ($gagal) $msg .= " $gagal gagal.";
        flash_set($baru > 0 ? 'success' : 'warning', $msg);
        header("Location: mengajar.php"); exit;
    }

    if ($do === 'edit') {
        $mid = (int)($_POST['id'] ?? 0);
        $sid = (int)($_POST['siswa_id'] ?? 0);
        if (hitung($koneksi, "SELECT COUNT(*) FROM mengajar WHERE mapel_id = ? AND siswa_id = ? AND id <> ?", 'iii', [$mapel_id, $sid, $mid])) {
            flash_set('danger', 'Siswa tersebut sudah terdaftar di mapel itu.');
        } elseif (!hitung($koneksi, "SELECT COUNT(*) FROM siswa WHERE id = ?", 'i', [$sid])) {
            flash_set('danger', 'Siswa tidak valid.');
        } elseif (dbq($koneksi, "UPDATE mengajar SET mapel_id = ?, siswa_id = ? WHERE id = ?", 'iii', [$mapel_id, $sid, $mid])) {
            flash_set('success', 'Data mengajar berhasil diupdate.');
        } else {
            flash_set('danger', 'Gagal update: ' . pesan_db($koneksi));
        }
        header("Location: mengajar.php"); exit;
    }

    if ($do === 'hapus') {
        $mid = (int)($_POST['id'] ?? 0);
        $row = rows(dbq($koneksi, "SELECT mapel_id, siswa_id FROM mengajar WHERE id = ?", 'i', [$mid]));
        if ($row && hitung($koneksi, "SELECT COUNT(*) FROM nilai_rapor WHERE mapel_id = ? AND siswa_id = ?", 'ii', [$row[0]['mapel_id'], $row[0]['siswa_id']])) {
            flash_set('danger', 'Tidak bisa dihapus: siswa ini sudah punya nilai di mapel tersebut.');
        } elseif (dbq($koneksi, "DELETE FROM mengajar WHERE id = ?", 'i', [$mid])) {
            flash_set('success', 'Data mengajar berhasil dihapus.');
        } else {
            flash_set('danger', 'Gagal hapus: ' . pesan_db($koneksi));
        }
        header("Location: mengajar.php"); exit;
    }
}

// ================= SIAPKAN DATA TAMPILAN =================
$d = ['id' => 0, 'mapel_id' => (int)($_GET['mapel_id'] ?? 0), 'siswa_id' => 0];
$opt_mapel = $opt_siswa = [];
if ($aksi === 'tambah' || $aksi === 'edit') {
    $opt_mapel = rows(dbq($koneksi, "SELECT m.id, m.kode_mapel, m.nama_mapel, g.nama AS guru FROM mapel m JOIN guru g ON g.id = m.guru_id ORDER BY m.nama_mapel ASC"));
    $opt_siswa = rows(dbq($koneksi, "SELECT id, nis, nama, kelas FROM siswa ORDER BY kelas ASC, nama ASC"));
}
if ($aksi === 'edit') {
    $r = rows(dbq($koneksi, "SELECT id, mapel_id, siswa_id FROM mengajar WHERE id = ?", 'i', [$id]));
    if (!$r) { flash_set('danger', 'Data mengajar tidak ditemukan.'); header("Location: mengajar.php"); exit; }
    $d = $r[0];
}

$cari   = trim($_GET['q'] ?? '');
$f_mapel = (int)($_GET['f_mapel'] ?? 0);
$hal    = max(1, (int)($_GET['hal'] ?? 1));
$per    = 15;
$list = []; $total = 0; $pages = 1; $filter_mapel = [];
if ($aksi === 'list') {
    $filter_mapel = rows(dbq($koneksi, "SELECT id, kode_mapel, nama_mapel FROM mapel ORDER BY nama_mapel"));
    $like  = '%' . $cari . '%';
    $where = "(s.nama LIKE ? OR s.nis LIKE ? OR s.kelas LIKE ? OR m.nama_mapel LIKE ? OR g.nama LIKE ?)";
    $types = 'sssss'; $args = [$like, $like, $like, $like, $like];
    if ($f_mapel > 0) { $where .= " AND m.id = ?"; $types .= 'i'; $args[] = $f_mapel; }
    $from  = "FROM mengajar j JOIN mapel m ON m.id = j.mapel_id JOIN siswa s ON s.id = j.siswa_id JOIN guru g ON g.id = m.guru_id";
    $total = hitung($koneksi, "SELECT COUNT(*) $from WHERE $where", $types, $args);
    $pages = max(1, (int)ceil($total / $per));
    $hal   = min($hal, $pages);
    $off   = ($hal - 1) * $per;
    $list  = rows(dbq($koneksi, "SELECT j.id, m.kode_mapel, m.nama_mapel, g.nama AS guru, s.nis, s.nama AS siswa, s.kelas
                                 $from WHERE $where ORDER BY m.nama_mapel ASC, s.kelas ASC, s.nama ASC LIMIT $per OFFSET $off", $types, $args));
}

include "navbar.php";
?>

<div class="container py-4">
  <?php flash_show(); ?>

<?php if ($aksi === 'tambah' || $aksi === 'edit'): ?>

  <div class="row justify-content-center">
    <div class="col-md-8">
      <div class="card">
        <div class="card-header"><?= $aksi === 'edit' ? 'Edit Data Mengajar' : 'Tambah Data Mengajar' ?></div>
        <div class="card-body">

          <?php if (!$opt_mapel || !$opt_siswa): ?>
            <div class="alert alert-warning">
              Butuh minimal 1 mapel dan 1 siswa.
              <?php if (!$opt_mapel): ?><a href="mapel.php?aksi=tambah">Tambah mapel</a>.<?php endif; ?>
              <?php if (!$opt_siswa): ?><a href="siswa.php?aksi=tambah">Tambah siswa</a>.<?php endif; ?>
            </div>
          <?php endif; ?>

          <form action="mengajar.php" method="POST">
            <?= csrf_field() ?><input type="hidden" name="do" value="<?= $aksi ?>">
            <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">

            <div class="mb-3">
              <label class="form-label" for="mapel_id">Mapel</label>
              <select class="form-select" id="mapel_id" name="mapel_id" required>
                <option value="" disabled <?= !$d['mapel_id'] ? 'selected' : '' ?>>-- Pilih Mapel --</option>
                <?php foreach ($opt_mapel as $m): ?>
                  <option value="<?= (int)$m['id'] ?>" <?= (int)$d['mapel_id'] === (int)$m['id'] ? 'selected' : '' ?>>
                    <?= e($m['nama_mapel']) ?> (<?= e($m['kode_mapel']) ?>) &mdash; <?= e($m['guru']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <?php if ($aksi === 'edit'): ?>
              <div class="mb-4">
                <label class="form-label" for="siswa_id">Siswa</label>
                <select class="form-select" id="siswa_id" name="siswa_id" required>
                  <?php foreach ($opt_siswa as $s): ?>
                    <option value="<?= (int)$s['id'] ?>" <?= (int)$d['siswa_id'] === (int)$s['id'] ? 'selected' : '' ?>>
                      <?= e($s['nama']) ?> (<?= e($s['nis']) ?> &middot; <?= e($s['kelas']) ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            <?php else: ?>
              <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <label class="form-label mb-0">Siswa (bisa pilih banyak)</label>
                  <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" id="pilih_semua">
                    <label class="form-check-label small" for="pilih_semua">Pilih semua</label>
                  </div>
                </div>
                <div class="border rounded p-2" style="max-height:320px;overflow:auto">
                  <?php foreach ($opt_siswa as $s): ?>
                    <div class="form-check">
                      <input class="form-check-input cb-siswa" type="checkbox" name="siswa_id[]" id="s<?= (int)$s['id'] ?>" value="<?= (int)$s['id'] ?>">
                      <label class="form-check-label" for="s<?= (int)$s['id'] ?>">
                        <?= e($s['nama']) ?> <span class="text-muted small">&middot; <?= e($s['nis']) ?> &middot; <?= e($s['kelas']) ?></span>
                      </label>
                    </div>
                  <?php endforeach; ?>
                </div>
                <div class="form-text">Siswa yang sudah terdaftar di mapel ini otomatis dilewati.</div>
              </div>
            <?php endif; ?>

            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary" <?= (!$opt_mapel || !$opt_siswa) ? 'disabled' : '' ?>>Simpan</button>
              <a href="mengajar.php" class="btn btn-secondary">Kembali</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
  <script>
    var ps = document.getElementById('pilih_semua');
    if (ps) ps.addEventListener('change', function () {
      document.querySelectorAll('.cb-siswa').forEach(function (c) { c.checked = ps.checked; });
    });
  </script>

<?php else: ?>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
      <span>Daftar Mengajar <span class="badge bg-secondary"><?= $total ?></span></span>
      <form method="get" action="mengajar.php" class="d-flex gap-2 flex-wrap">
        <select name="f_mapel" class="form-select form-select-sm" style="width:auto;max-width:200px">
          <option value="0">Semua mapel</option>
          <?php foreach ($filter_mapel as $m): ?>
            <option value="<?= (int)$m['id'] ?>" <?= $f_mapel === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['nama_mapel']) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="q" value="<?= e($cari) ?>" class="form-control form-control-sm" placeholder="Cari siswa / kelas / guru" style="width:200px">
        <button type="submit" class="btn btn-outline-primary btn-sm">Cari</button>
        <a href="mengajar.php" class="btn btn-outline-secondary btn-sm">Reset</a>
        <a href="mengajar.php?aksi=tambah" class="btn btn-primary btn-sm">+ Tambah Mengajar</a>
      </form>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr><th class="text-center">No</th><th>Mapel</th><th>Guru</th><th>NIS</th><th>Siswa</th><th>Kelas</th><th class="text-center">Aksi</th></tr>
          </thead>
          <tbody>
            <?php foreach ($list as $i => $r): ?>
            <tr>
              <td class="text-center"><?= ($hal - 1) * $per + $i + 1 ?></td>
              <td><?= e($r['nama_mapel']) ?> <span class="badge text-bg-light border"><?= e($r['kode_mapel']) ?></span></td>
              <td><?= e($r['guru']) ?></td>
              <td><?= e($r['nis']) ?></td>
              <td><?= e($r['siswa']) ?></td>
              <td><?= e($r['kelas']) ?></td>
              <td class="text-center">
                <a href="mengajar.php?aksi=edit&id=<?= (int)$r['id'] ?>" class="btn btn-warning btn-sm">Edit</a>
                <form action="mengajar.php" method="POST" class="d-inline"
                      onsubmit="return confirm('Hapus <?= e(addslashes($r['siswa'])) ?> dari mapel <?= e(addslashes($r['nama_mapel'])) ?>?')">
                  <?= csrf_field() ?><input type="hidden" name="do" value="hapus">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$list): ?><tr><td colspan="7" class="text-center text-muted py-4">Belum ada data mengajar.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <?= pager($hal, $pages, ['q' => $cari, 'f_mapel' => $f_mapel]) ?>
    </div>
  </div>

<?php endif; ?>
</div>

<?php include "script.php"; ?>
