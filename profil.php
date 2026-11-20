<?php
include "koneksi.php";
include "auth.php";
require_login();
mysqli_report(MYSQLI_REPORT_OFF);

$uid   = (int)$_SESSION['user_id'];
$role  = $_SESSION['role'];
$aksi  = ($_GET['aksi'] ?? 'lihat') === 'edit' ? 'edit' : 'lihat';
$tabel = $role === 'guru' ? 'guru' : ($role === 'siswa' ? 'siswa' : null);

$cc = kolom_dibuat($koneksi);
$u = rows(dbq($koneksi, "SELECT u.id, u.username, u.password, u.role, $cc AS created_at FROM users u WHERE u.id = ?", 'i', [$uid]));
if (!$u) { header("Location: logout.php"); exit; }
$u = $u[0];

$p = null;
if ($tabel) {
    $r = rows(dbq($koneksi, "SELECT * FROM $tabel WHERE user_id = ?", 'i', [$uid]));
    $p = $r ? $r[0] : null;
}

// nilai awal form
$f = [
    'username' => $u['username'],
    'nama'     => $p['nama'] ?? '',
    'kelas'    => $p['kelas'] ?? '',
    'jk'       => $p['jenis_kelamin'] ?? 'L',
];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = 'edit';
    $f['username'] = trim($_POST['username'] ?? '');
    $f['nama']     = trim($_POST['nama'] ?? $f['nama']);
    $f['kelas']    = trim($_POST['kelas'] ?? $f['kelas']);
    $f['jk']       = (($_POST['jenis_kelamin'] ?? 'L') === 'P') ? 'P' : 'L';
    $pw_lama = $_POST['password_lama'] ?? '';
    $pw_baru = $_POST['password_baru'] ?? '';
    $pw_konf = $_POST['password_konfirmasi'] ?? '';

    if ($f['username'] === '') {
        $error = 'Username wajib diisi.';
    } elseif ($p && $f['nama'] === '') {
        $error = 'Nama wajib diisi.';
    } elseif ($role === 'siswa' && $p && $f['kelas'] === '') {
        $error = 'Kelas wajib diisi.';
    } elseif (rows(dbq($koneksi, "SELECT id FROM users WHERE username = ? AND id <> ?", 'si', [$f['username'], $uid]))) {
        $error = 'Username sudah dipakai user lain.';
    } elseif ($pw_baru !== '' && !cek_pw($pw_lama, $u['password'])) {
        $error = 'Password lama salah.';
    } elseif ($pw_baru !== '' && strlen($pw_baru) < 6) {
        $error = 'Password baru minimal 6 karakter.';
    } elseif ($pw_baru !== $pw_konf) {
        $error = 'Konfirmasi password baru tidak sama.';
    } else {
        mysqli_begin_transaction($koneksi);
        $ok = true;
        if ($p && $role === 'guru') {
            $ok = (bool)dbq($koneksi, "UPDATE guru SET nama = ?, jenis_kelamin = ? WHERE id = ?", 'ssi', [$f['nama'], $f['jk'], (int)$p['id']]);
        } elseif ($p && $role === 'siswa') {
            $ok = (bool)dbq($koneksi, "UPDATE siswa SET nama = ?, kelas = ?, jenis_kelamin = ? WHERE id = ?", 'sssi', [$f['nama'], $f['kelas'], $f['jk'], (int)$p['id']]);
        }
        if ($ok) {
            $ok = $pw_baru !== ''
                ? (bool)dbq($koneksi, "UPDATE users SET username = ?, password = ? WHERE id = ?", 'ssi', [$f['username'], hash_pw($pw_baru), $uid])
                : (bool)dbq($koneksi, "UPDATE users SET username = ? WHERE id = ?", 'si', [$f['username'], $uid]);
        }
        $err = pesan_db($koneksi);
        if ($ok) {
            mysqli_commit($koneksi);
            $_SESSION['username'] = $f['username'];
            flash_set('success', 'Profil berhasil diperbarui.');
            header("Location: profil.php"); exit;
        }
        mysqli_rollback($koneksi);
        $error = 'Gagal memperbarui profil: ' . $err;
    }
}

include "navbar.php";
$jk_txt = function ($x) { return $x === 'P' ? 'Perempuan' : 'Laki-laki'; };
?>

<?php flash_show(); ?>
<div class="row">
    <div class="col-lg-8">

<?php if ($aksi === 'lihat'): ?>

      <div class="d-flex justify-content-between align-items-baseline mb-2">
        <h2 class="h5 mb-0"><?= e($p['nama'] ?? $u['username']) ?></h2>
        <a href="profil.php?aksi=edit" class="btn btn-outline-primary btn-sm">Ubah profil</a>
      </div>
      <table class="table">
        <tbody>
          <tr><th class="text-muted fw-normal" style="width:11rem">Username</th><td><?= e($u['username']) ?></td></tr>
          <tr><th class="text-muted fw-normal">Role</th><td><?= e(ucfirst($role)) ?></td></tr>
          <?php if ($role === 'siswa' && $p): ?>
            <tr><th class="text-muted fw-normal">NIS</th><td><?= e($p['nis']) ?></td></tr>
            <tr><th class="text-muted fw-normal">Kelas</th><td><?= e($p['kelas']) ?></td></tr>
          <?php elseif ($role === 'guru' && $p): ?>
            <tr><th class="text-muted fw-normal">NIP</th><td><?= e($p['nip']) ?></td></tr>
          <?php endif; ?>
          <?php if ($p): ?>
            <tr><th class="text-muted fw-normal">Jenis kelamin</th><td><?= $jk_txt($p['jenis_kelamin']) ?></td></tr>
          <?php endif; ?>
          <tr><th class="text-muted fw-normal">Terdaftar sejak</th><td><?= tgl($u['created_at']) ?></td></tr>
        </tbody>
      </table>

<?php else: ?>

      <div class="card">
        <div class="card-header">Edit Profil</div>
        <div class="card-body">
          <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
          <form method="POST" action="profil.php?aksi=edit">
            <?= csrf_field() ?>

            <?php if ($p): ?>
            <h5 class="mb-3">Data Diri</h5>
            <div class="mb-3">
              <label class="form-label"><?= $role === 'guru' ? 'NIP' : 'NIS' ?></label>
              <input type="text" class="form-control" value="<?= e($role === 'guru' ? $p['nip'] : $p['nis']) ?>" disabled>
              <div class="form-text">Nomor induk hanya bisa diubah oleh admin.</div>
            </div>
            <div class="mb-3">
              <label class="form-label" for="nama">Nama Lengkap</label>
              <input type="text" class="form-control" id="nama" name="nama" maxlength="100" value="<?= e($f['nama']) ?>" required>
            </div>
            <?php if ($role === 'siswa'): ?>
            <div class="mb-3">
              <label class="form-label" for="kelas">Kelas</label>
              <input type="text" class="form-control" id="kelas" name="kelas" maxlength="10" value="<?= e($f['kelas']) ?>" required>
            </div>
            <?php endif; ?>
            <div class="mb-4">
              <label class="form-label d-block">Jenis Kelamin</label>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="jenis_kelamin" id="jk_l" value="L" <?= $f['jk'] !== 'P' ? 'checked' : '' ?>>
                <label class="form-check-label" for="jk_l">Laki-laki</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="jenis_kelamin" id="jk_p" value="P" <?= $f['jk'] === 'P' ? 'checked' : '' ?>>
                <label class="form-check-label" for="jk_p">Perempuan</label>
              </div>
            </div>
            <hr>
            <?php endif; ?>

            <h5 class="mb-3">Akun</h5>
            <div class="mb-3">
              <label class="form-label" for="username">Username</label>
              <input type="text" class="form-control" id="username" name="username" maxlength="50" value="<?= e($f['username']) ?>" required>
            </div>
            <div class="mb-2 text-muted small">Ganti password (kosongkan jika tidak ingin mengubah)</div>
            <div class="mb-3">
              <label class="form-label" for="password_lama">Password Lama</label>
              <input type="password" class="form-control" id="password_lama" name="password_lama" autocomplete="current-password">
            </div>
            <div class="row g-3 mb-4">
              <div class="col-md-6">
                <label class="form-label" for="password_baru">Password Baru</label>
                <input type="password" class="form-control" id="password_baru" name="password_baru" minlength="6" autocomplete="new-password">
              </div>
              <div class="col-md-6">
                <label class="form-label" for="password_konfirmasi">Konfirmasi Password Baru</label>
                <input type="password" class="form-control" id="password_konfirmasi" name="password_konfirmasi" minlength="6" autocomplete="new-password">
              </div>
            </div>

            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
              <a href="profil.php" class="btn btn-secondary">Batal</a>
            </div>
          </form>
        </div>
      </div>

<?php endif; ?>
    </div>
</div>

<?php include "script.php"; ?>
