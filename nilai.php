<?php
include "koneksi.php";
include "auth.php";
require_role('guru');
mysqli_report(MYSQLI_REPORT_OFF);

$guru = guru_saya($koneksi);
if (!$guru) {
    flash_set('warning', 'Akun Anda belum terhubung dengan data guru. Hubungi admin.');
    header("Location: dashboard.php"); exit;
}
$gid  = (int)$guru['id'];
$aksi = $_GET['aksi'] ?? 'list';
$id   = (int)($_GET['id'] ?? 0);

function skor_valid($v) {
    return $v !== '' && is_numeric($v) && (int)$v == $v && $v >= 0 && $v <= 100;
}

// ================= PROSES POST =================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do = $_POST['do'] ?? '';

    if ($do === 'tambah' || $do === 'edit') {
        $kkm = $_POST['nilai_kkm'] ?? '';
        $np  = $_POST['nilai_pengetahuan'] ?? '';
        $nk  = $_POST['nilai_keterampilan'] ?? '';
        $mapel_id = (int)($_POST['mapel_id'] ?? 0);
        $siswa_id = (int)($_POST['siswa_id'] ?? 0);
        $balik = "nilai.php?" . ($do === 'tambah' ? "aksi=tambah&mapel_id=$mapel_id" : "aksi=edit&id=" . (int)($_POST['id'] ?? 0));

        if (!skor_valid($kkm) || !skor_valid($np) || !skor_valid($nk)) {
            flash_set('danger', 'KKM, nilai pengetahuan, dan nilai keterampilan harus angka bulat 0 - 100.');
            header("Location: $balik"); exit;
        }
        $kkm = (int)$kkm; $np = (int)$np; $nk = (int)$nk;
        $ket = keterangan_nilai($np, $nk, $kkm);

        if ($do === 'tambah') {
            // mapel harus milik guru ini, dan siswa harus terdaftar di mapel itu
            if (!hitung($koneksi, "SELECT COUNT(*) FROM mapel WHERE id = ? AND guru_id = ?", 'ii', [$mapel_id, $gid])) {
                flash_set('danger', 'Anda tidak mengampu mapel tersebut.'); header("Location: nilai.php"); exit;
            }
            if (!hitung($koneksi, "SELECT COUNT(*) FROM mengajar WHERE mapel_id = ? AND siswa_id = ?", 'ii', [$mapel_id, $siswa_id])) {
                flash_set('danger', 'Siswa tidak terdaftar di mapel tersebut.'); header("Location: $balik"); exit;
            }
            if (hitung($koneksi, "SELECT COUNT(*) FROM nilai_rapor WHERE mapel_id = ? AND siswa_id = ?", 'ii', [$mapel_id, $siswa_id])) {
                flash_set('danger', 'Nilai siswa ini di mapel tersebut sudah ada. Gunakan Edit.'); header("Location: $balik"); exit;
            }
            if (dbq($koneksi, "INSERT INTO nilai_rapor (siswa_id, mapel_id, nilai_kkm, nilai_pengetahuan, nilai_keterampilan, Keterangan) VALUES (?, ?, ?, ?, ?, ?)",
                    'iiiiis', [$siswa_id, $mapel_id, $kkm, $np, $nk, $ket])) {
                flash_set('success', "Nilai berhasil disimpan ($ket).");
            } else {
                flash_set('danger', 'Gagal menyimpan nilai: ' . pesan_db($koneksi));
            }
            header("Location: nilai.php?mapel_id=$mapel_id"); exit;
        }

        // edit
        $nid = (int)($_POST['id'] ?? 0);
        if (!hitung($koneksi, "SELECT COUNT(*) FROM nilai_rapor n JOIN mapel m ON m.id = n.mapel_id WHERE n.id = ? AND m.guru_id = ?", 'ii', [$nid, $gid])) {
            flash_set('danger', 'Data nilai tidak ditemukan atau bukan mapel Anda.'); header("Location: nilai.php"); exit;
        }
        if (dbq($koneksi, "UPDATE nilai_rapor SET nilai_kkm = ?, nilai_pengetahuan = ?, nilai_keterampilan = ?, Keterangan = ? WHERE id = ?",
                'iiisi', [$kkm, $np, $nk, $ket, $nid])) {
            flash_set('success', "Nilai berhasil diupdate ($ket).");
        } else {
            flash_set('danger', 'Gagal update nilai: ' . pesan_db($koneksi));
        }
        header("Location: nilai.php"); exit;
    }

    if ($do === 'hapus') {
        $nid = (int)($_POST['id'] ?? 0);
        if (!hitung($koneksi, "SELECT COUNT(*) FROM nilai_rapor n JOIN mapel m ON m.id = n.mapel_id WHERE n.id = ? AND m.guru_id = ?", 'ii', [$nid, $gid])) {
            flash_set('danger', 'Data nilai tidak ditemukan atau bukan mapel Anda.');
        } elseif (dbq($koneksi, "DELETE FROM nilai_rapor WHERE id = ?", 'i', [$nid])) {
            flash_set('success', 'Nilai berhasil dihapus.');
        } else {
            flash_set('danger', 'Gagal hapus nilai: ' . pesan_db($koneksi));
        }
        header("Location: nilai.php"); exit;
    }
}

// ================= SIAPKAN DATA TAMPILAN =================
$mapel_guru = rows(dbq($koneksi, "SELECT id, kode_mapel, nama_mapel FROM mapel WHERE guru_id = ? ORDER BY nama_mapel", 'i', [$gid]));
$ids_mapel  = array_map('intval', array_column($mapel_guru, 'id'));

$d = ['id' => 0, 'nilai_kkm' => 75, 'nilai_pengetahuan' => '', 'nilai_keterampilan' => ''];
$sel_mapel = (int)($_GET['mapel_id'] ?? 0);
if (!in_array($sel_mapel, $ids_mapel, true)) $sel_mapel = 0;
$opt_siswa = [];
$edit = null;

if ($aksi === 'tambah' && $sel_mapel) {
    $opt_siswa = rows(dbq($koneksi, "SELECT s.id, s.nis, s.nama, s.kelas
                       FROM mengajar j JOIN siswa s ON s.id = j.siswa_id
                       WHERE j.mapel_id = ?
                         AND NOT EXISTS (SELECT 1 FROM nilai_rapor n WHERE n.mapel_id = j.mapel_id AND n.siswa_id = j.siswa_id)
                       ORDER BY s.kelas, s.nama", 'i', [$sel_mapel]));
}
if ($aksi === 'edit') {
    $r = rows(dbq($koneksi, "SELECT n.*, m.nama_mapel, m.kode_mapel, s.nama AS siswa, s.nis, s.kelas
                       FROM nilai_rapor n JOIN mapel m ON m.id = n.mapel_id JOIN siswa s ON s.id = n.siswa_id
                       WHERE n.id = ? AND m.guru_id = ?", 'ii', [$id, $gid]));
    if (!$r) { flash_set('danger', 'Data nilai tidak ditemukan.'); header("Location: nilai.php"); exit; }
    $edit = $r[0];
    $d = $edit;
}

$cari = trim($_GET['q'] ?? '');
$hal  = max(1, (int)($_GET['hal'] ?? 1));
$per  = 15;
$list = []; $total = 0; $pages = 1;
if ($aksi === 'list') {
    $like  = '%' . $cari . '%';
    $where = "m.guru_id = ? AND (s.nama LIKE ? OR s.nis LIKE ? OR s.kelas LIKE ?)";
    $types = 'isss'; $args = [$gid, $like, $like, $like];
    if ($sel_mapel) { $where .= " AND m.id = ?"; $types .= 'i'; $args[] = $sel_mapel; }
    $from  = "FROM nilai_rapor n JOIN mapel m ON m.id = n.mapel_id JOIN siswa s ON s.id = n.siswa_id";
    $total = hitung($koneksi, "SELECT COUNT(*) $from WHERE $where", $types, $args);
    $pages = max(1, (int)ceil($total / $per));
    $hal   = min($hal, $pages);
    $off   = ($hal - 1) * $per;
    $list  = rows(dbq($koneksi, "SELECT n.*, m.nama_mapel, s.nama AS siswa, s.nis, s.kelas
                       $from WHERE $where ORDER BY m.nama_mapel, s.kelas, s.nama LIMIT $per OFFSET $off", $types, $args));
}

include "navbar.php";
?>

<div class="container py-4">
  <?php flash_show(); ?>

<?php if ($aksi === 'tambah' || $aksi === 'edit'): ?>

  <div class="row justify-content-center">
    <div class="col-md-8">
      <div class="card">
        <div class="card-header"><?= $aksi === 'edit' ? 'Edit Nilai' : 'Input Nilai' ?></div>
        <div class="card-body">

<?php if ($aksi === 'tambah'): ?>
          <?php if (!$mapel_guru): ?>
            <div class="alert alert-warning mb-0">Anda belum ditugaskan mengampu mapel. Hubungi admin.</div>
          <?php else: ?>
            <!-- langkah 1: pilih mapel -->
            <form method="get" action="nilai.php" class="mb-3">
              <input type="hidden" name="aksi" value="tambah">
              <label class="form-label" for="pilih_mapel">Mapel</label>
              <select class="form-select" id="pilih_mapel" name="mapel_id" onchange="this.form.submit()">
                <option value="" disabled <?= !$sel_mapel ? 'selected' : '' ?>>-- Pilih Mapel --</option>
                <?php foreach ($mapel_guru as $m): ?>
                  <option value="<?= (int)$m['id'] ?>" <?= $sel_mapel === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['nama_mapel']) ?> (<?= e($m['kode_mapel']) ?>)</option>
                <?php endforeach; ?>
              </select>
            </form>
          <?php endif; ?>

          <?php if ($sel_mapel && !$opt_siswa): ?>
            <div class="alert alert-info">Semua siswa di mapel ini sudah punya nilai (atau belum ada siswa terdaftar). Ubah nilai lewat tombol Edit di daftar nilai.</div>
            <a href="nilai.php" class="btn btn-secondary">Kembali</a>
          <?php endif; ?>
<?php endif; ?>

<?php if ($aksi === 'edit' || ($aksi === 'tambah' && $sel_mapel && $opt_siswa)): ?>
          <?php if ($aksi === 'tambah'): ?><hr><?php endif; ?>
          <!-- langkah 2 (atau edit): isi nilai -->
          <form action="nilai.php" method="POST">
            <?= csrf_field() ?><input type="hidden" name="do" value="<?= $aksi ?>">
            <?php if ($aksi === 'edit'): ?>
              <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
              <input type="hidden" name="mapel_id" value="<?= (int)$edit['mapel_id'] ?>">
              <input type="hidden" name="siswa_id" value="<?= (int)$edit['siswa_id'] ?>">
              <div class="row g-3 mb-3">
                <div class="col-md-6"><label class="form-label">Mapel</label><input class="form-control" value="<?= e($edit['nama_mapel']) ?>" disabled></div>
                <div class="col-md-6"><label class="form-label">Siswa</label><input class="form-control" value="<?= e($edit['siswa']) ?> (<?= e($edit['kelas']) ?>)" disabled></div>
              </div>
            <?php else: ?>
              <input type="hidden" name="mapel_id" value="<?= $sel_mapel ?>">
              <div class="mb-3">
                <label class="form-label" for="siswa_id">Siswa</label>
                <select class="form-select" id="siswa_id" name="siswa_id" required>
                  <option value="" disabled selected>-- Pilih Siswa (<?= count($opt_siswa) ?> belum dinilai) --</option>
                  <?php foreach ($opt_siswa as $s): ?>
                    <option value="<?= (int)$s['id'] ?>"><?= e($s['nama']) ?> (<?= e($s['nis']) ?> &middot; <?= e($s['kelas']) ?>)</option>
                  <?php endforeach; ?>
                </select>
              </div>
            <?php endif; ?>

            <div class="row g-3 mb-3">
              <div class="col-md-4">
                <label class="form-label" for="nilai_kkm">KKM</label>
                <input type="number" min="0" max="100" class="form-control" id="nilai_kkm" name="nilai_kkm" value="<?= e($d['nilai_kkm']) ?>" required>
              </div>
              <div class="col-md-4">
                <label class="form-label" for="nilai_pengetahuan">Nilai Pengetahuan</label>
                <input type="number" min="0" max="100" class="form-control" id="nilai_pengetahuan" name="nilai_pengetahuan" value="<?= e($d['nilai_pengetahuan']) ?>" required>
              </div>
              <div class="col-md-4">
                <label class="form-label" for="nilai_keterampilan">Nilai Keterampilan</label>
                <input type="number" min="0" max="100" class="form-control" id="nilai_keterampilan" name="nilai_keterampilan" value="<?= e($d['nilai_keterampilan']) ?>" required>
              </div>
            </div>
            <div class="alert alert-light border mb-4">
              Rata-rata &amp; keterangan (otomatis): <strong id="preview">-</strong>
            </div>
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary">Simpan</button>
              <a href="nilai.php" class="btn btn-secondary">Kembali</a>
            </div>
          </form>
          <script>
            (function () {
              var kk = document.getElementById('nilai_kkm'), p = document.getElementById('nilai_pengetahuan'),
                  k = document.getElementById('nilai_keterampilan'), out = document.getElementById('preview');
              function hit() {
                if (p.value === '' || k.value === '' || kk.value === '') { out.textContent = '-'; return; }
                var r = (Number(p.value) + Number(k.value)) / 2;
                out.textContent = r.toFixed(1) + ' → ' + (r >= Number(kk.value) ? 'Tuntas' : 'Belum Tuntas');
              }
              [kk, p, k].forEach(function (el) { el.addEventListener('input', hit); });
              hit();
            })();
          </script>
<?php endif; ?>

        </div>
      </div>
    </div>
  </div>

<?php else: ?>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
      <span>Daftar Nilai Siswa <span class="badge bg-secondary"><?= $total ?></span></span>
      <form method="get" action="nilai.php" class="d-flex gap-2 flex-wrap">
        <select name="mapel_id" class="form-select form-select-sm" style="width:auto;max-width:220px">
          <option value="0">Semua mapel saya</option>
          <?php foreach ($mapel_guru as $m): ?>
            <option value="<?= (int)$m['id'] ?>" <?= $sel_mapel === (int)$m['id'] ? 'selected' : '' ?>><?= e($m['nama_mapel']) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="q" value="<?= e($cari) ?>" class="form-control form-control-sm" placeholder="Cari siswa / NIS / kelas" style="width:200px">
        <button type="submit" class="btn btn-outline-primary btn-sm">Cari</button>
        <a href="nilai.php" class="btn btn-outline-secondary btn-sm">Reset</a>
        <a href="nilai.php?aksi=tambah<?= $sel_mapel ? '&mapel_id=' . $sel_mapel : '' ?>" class="btn btn-primary btn-sm">+ Input Nilai</a>
      </form>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th class="text-center">No</th><th>Mapel</th><th>NIS</th><th>Siswa</th><th>Kelas</th>
              <th class="text-center">KKM</th><th class="text-center">Peng.</th><th class="text-center">Ket.</th>
              <th class="text-center">Rata-rata</th><th class="text-center">Keterangan</th><th class="text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($list as $i => $r): ?>
            <tr>
              <td class="text-center"><?= ($hal - 1) * $per + $i + 1 ?></td>
              <td><?= e($r['nama_mapel']) ?></td>
              <td><?= e($r['nis']) ?></td>
              <td><?= e($r['siswa']) ?></td>
              <td><?= e($r['kelas']) ?></td>
              <td class="text-center"><?= (int)$r['nilai_kkm'] ?></td>
              <td class="text-center<?= kelas_nilai($r['nilai_pengetahuan'], $r['nilai_kkm']) ?>"><?= (int)$r['nilai_pengetahuan'] ?></td>
              <td class="text-center<?= kelas_nilai($r['nilai_keterampilan'], $r['nilai_kkm']) ?>"><?= (int)$r['nilai_keterampilan'] ?></td>
              <td class="text-center"><?= skor_sel(rata_nilai($r['nilai_pengetahuan'], $r['nilai_keterampilan']), $r['nilai_kkm']) ?></td>
              <td class="text-center"><?= stamp($r['Keterangan']) ?></td>
              <td class="text-center text-nowrap">
                <a href="nilai.php?aksi=edit&id=<?= (int)$r['id'] ?>" class="btn btn-warning btn-sm">Edit</a>
                <form action="nilai.php" method="POST" class="d-inline"
                      onsubmit="return confirm('Hapus nilai <?= e(addslashes($r['siswa'])) ?> di mapel <?= e(addslashes($r['nama_mapel'])) ?>?')">
                  <?= csrf_field() ?><input type="hidden" name="do" value="hapus">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$list): ?><tr><td colspan="11" class="text-center text-muted py-4">Belum ada nilai.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <?= pager($hal, $pages, ['q' => $cari, 'mapel_id' => $sel_mapel]) ?>
    </div>
  </div>

<?php endif; ?>
</div>

<?php include "script.php"; ?>
