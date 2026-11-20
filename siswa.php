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
    $nis      = trim($_POST['nis'] ?? '');
    $nama     = trim($_POST['nama'] ?? '');
    $kelas    = trim($_POST['kelas'] ?? '');
    $jk       = (($_POST['jenis_kelamin'] ?? 'L') === 'P') ? 'P' : 'L';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($do === 'tambah') {
        mysqli_begin_transaction($koneksi);
        $ok = false;
        if (dbq($koneksi, "INSERT INTO users (username, password, role) VALUES (?, ?, 'siswa')", 'ss', [$username, hash_pw($password)])) {
            $uid = mysqli_insert_id($koneksi);
            $ok  = (bool)dbq($koneksi, "INSERT INTO siswa (nis, nama, kelas, jenis_kelamin, user_id) VALUES (?, ?, ?, ?, ?)", 'ssssi', [$nis, $nama, $kelas, $jk, $uid]);
        }
        $err = pesan_db($koneksi);
        if ($ok) { mysqli_commit($koneksi); flash_set('success', 'Siswa berhasil ditambahkan.'); }
        else     { mysqli_rollback($koneksi); flash_set('danger', 'Gagal menambah siswa: ' . $err); }
        header("Location: siswa.php"); exit;
    }

    if ($do === 'edit') {
        $gid = (int)($_POST['id'] ?? 0);
        $cur = rows(dbq($koneksi, "SELECT user_id FROM siswa WHERE id = ?", 'i', [$gid]));
        if (!$cur) { flash_set('danger', 'Data siswa tidak ditemukan.'); header("Location: siswa.php"); exit; }
        $uid = (int)$cur[0]['user_id'];

        mysqli_begin_transaction($koneksi);
        $ok = (bool)dbq($koneksi, "UPDATE siswa SET nis = ?, nama = ?, kelas = ?, jenis_kelamin = ? WHERE id = ?", 'ssssi', [$nis, $nama, $kelas, $jk, $gid]);
        if ($ok) {
            if ($password !== '') {
                $ok = (bool)dbq($koneksi, "UPDATE users SET username = ?, password = ? WHERE id = ?", 'ssi', [$username, hash_pw($password), $uid]);
            } else {
                $ok = (bool)dbq($koneksi, "UPDATE users SET username = ? WHERE id = ?", 'si', [$username, $uid]);
            }
        }
        $err = pesan_db($koneksi);
        if ($ok) { mysqli_commit($koneksi); flash_set('success', 'Data siswa berhasil diupdate.'); }
        else     { mysqli_rollback($koneksi); flash_set('danger', 'Gagal update siswa: ' . $err); }
        header("Location: siswa.php"); exit;
    }

    if ($do === 'hapus') {
        $gid = (int)($_POST['id'] ?? 0);
        $cur = rows(dbq($koneksi, "SELECT user_id FROM siswa WHERE id = ?", 'i', [$gid]));
        if (!$cur) { flash_set('danger', 'Data siswa tidak ditemukan.'); header("Location: siswa.php"); exit; }

        mysqli_begin_transaction($koneksi);
        $ok = dbq($koneksi, "DELETE FROM nilai_rapor WHERE siswa_id = ?", 'i', [$gid])
           && dbq($koneksi, "DELETE FROM mengajar WHERE siswa_id = ?", 'i', [$gid])
           && dbq($koneksi, "DELETE FROM siswa WHERE id = ?", 'i', [$gid])
           && dbq($koneksi, "DELETE FROM users WHERE id = ?", 'i', [(int)$cur[0]['user_id']]);
        $err = pesan_db($koneksi);
        if ($ok) { mysqli_commit($koneksi); flash_set('success', 'Siswa berhasil dihapus.'); }
        else     { mysqli_rollback($koneksi); flash_set('danger', 'Gagal hapus siswa: ' . $err); }
        header("Location: siswa.php"); exit;
    }
}

// ================= SIAPKAN DATA TAMPILAN =================
$d = ['id' => 0, 'nis' => '', 'nama' => '', 'kelas' => '', 'jenis_kelamin' => 'L', 'username' => ''];
if ($aksi === 'edit') {
    $r = rows(dbq($koneksi, "SELECT g.id, g.nis, g.nama, g.kelas, g.jenis_kelamin, u.username
                             FROM siswa g JOIN users u ON u.id = g.user_id WHERE g.id = ?", 'i', [$id]));
    if (!$r) { flash_set('danger', 'Data siswa tidak ditemukan.'); header("Location: siswa.php"); exit; }
    $d = $r[0];
}

$cari = trim($_GET['q'] ?? '');
$hal  = max(1, (int)($_GET['hal'] ?? 1));
$per  = 15;
$list = []; $total = 0; $pages = 1;
if ($aksi === 'list') {
    $like  = '%' . $cari . '%';
    $from  = "FROM siswa g JOIN users u ON u.id = g.user_id WHERE (g.nama LIKE ? OR g.nis LIKE ? OR g.kelas LIKE ? OR u.username LIKE ?)";
    $total = hitung($koneksi, "SELECT COUNT(*) $from", 'ssss', [$like, $like, $like, $like]);
    $pages = max(1, (int)ceil($total / $per));
    $hal   = min($hal, $pages);
    $off   = ($hal - 1) * $per;
    $list  = rows(dbq($koneksi, "SELECT g.id, g.nis, g.nama, g.kelas, g.jenis_kelamin, u.username $from
                                 ORDER BY g.kelas ASC, g.nama ASC LIMIT $per OFFSET $off", 'ssss', [$like, $like, $like, $like]));
}

include "navbar.php";
?>

<div class="container mt-4">
  <?php flash_show(); ?>

<?php if ($aksi === 'tambah' || $aksi === 'edit'): ?>

  <div class="row justify-content-center">
    <div class="col-md-8">
      <div class="card shadow-sm">
        <div class="card-header"><?= $aksi === 'edit' ? 'Edit Siswa' : 'Tambah Siswa' ?></div>
        <div class="card-body">
          <form action="siswa.php" method="POST">
            <?= csrf_field() ?><input type="hidden" name="do" value="<?= $aksi ?>">
            <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">

            <h5 class="mb-3">Data Profil</h5>
            <div class="mb-3">
              <label class="form-label" for="nis">NIS</label>
              <input type="text" class="form-control" id="nis" name="nis" maxlength="10" value="<?= e($d['nis']) ?>" placeholder="Nomor Induk Siswa" required>
            </div>
            <div class="mb-3">
              <label class="form-label" for="nama">Nama Lengkap</label>
              <input type="text" class="form-control" id="nama" name="nama" maxlength="100" value="<?= e($d['nama']) ?>" placeholder="Contoh: Ujang Asep" required>
            </div>
            <div class="mb-3">
              <label class="form-label" for="kelas">Kelas</label>
              <input type="text" class="form-control" id="kelas" name="kelas" maxlength="10" value="<?= e($d['kelas']) ?>" placeholder="Contoh: XI-RPL-1" required>
            </div>
            <div class="mb-4">
              <label class="form-label d-block">Jenis Kelamin</label>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="jenis_kelamin" id="jk_l" value="L" <?= $d['jenis_kelamin'] !== 'P' ? 'checked' : '' ?>>
                <label class="form-check-label" for="jk_l">Laki-laki</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="jenis_kelamin" id="jk_p" value="P" <?= $d['jenis_kelamin'] === 'P' ? 'checked' : '' ?>>
                <label class="form-check-label" for="jk_p">Perempuan</label>
              </div>
            </div>

            <hr>
            <h5 class="mb-3">Data Akun</h5>
            <div class="mb-3">
              <label class="form-label" for="username">Username</label>
              <input type="text" class="form-control" id="username" name="username" maxlength="50" value="<?= e($d['username']) ?>" required>
            </div>
            <div class="mb-4">
              <label class="form-label" for="password"><?= $aksi === 'edit' ? 'Password Baru' : 'Password' ?></label>
              <input type="password" class="form-control" id="password" name="password"
                     <?= $aksi === 'edit' ? 'placeholder="Kosongkan jika tidak ingin mengubah"' : 'required' ?>>
            </div>

            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary">Simpan</button>
              <a href="siswa.php" class="btn btn-secondary">Kembali</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

<?php else: ?>

  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
      <span>Daftar Siswa <span class="badge bg-secondary"><?= $total ?></span></span>
      <form method="get" action="siswa.php" class="d-flex gap-2 flex-wrap">
        <input type="text" name="q" value="<?= e($cari) ?>" class="form-control form-control-sm" placeholder="Cari nama / NIS / kelas / username" style="width:230px">
        <button type="submit" class="btn btn-outline-primary btn-sm">Cari</button>
        <a href="siswa.php" class="btn btn-outline-secondary btn-sm">Reset</a>
        <a href="siswa.php?aksi=tambah" class="btn btn-primary btn-sm">+ Tambah Siswa</a>
      </form>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th class="text-center">No</th>
              <th>NIS</th>
              <th>Nama</th>
              <th>Kelas</th>
              <th>JK</th>
              <th>Username</th>
              <th class="text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($list as $i => $r): ?>
            <tr>
              <td class="text-center"><?= ($hal - 1) * $per + $i + 1 ?></td>
              <td><?= e($r['nis']) ?></td>
              <td><?= e($r['nama']) ?></td>
              <td><?= e($r['kelas']) ?></td>
              <td><?= e($r['jenis_kelamin']) ?></td>
              <td><?= e($r['username']) ?></td>
              <td class="text-center">
                <a href="siswa.php?aksi=edit&id=<?= (int)$r['id'] ?>" class="btn btn-warning btn-sm">Edit</a>
                <form action="siswa.php" method="POST" class="d-inline"
                      onsubmit="return confirm('Hapus siswa <?= e(addslashes($r['nama'])) ?>? Akun, data mengajar, dan nilai siswa ini ikut terhapus.')">
                  <?= csrf_field() ?><input type="hidden" name="do" value="hapus">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$list): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">Belum ada data siswa.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?= pager($hal, $pages, ['q' => $cari]) ?>
    </div>
  </div>

<?php endif; ?>
</div>

<?php include "script.php"; ?>
