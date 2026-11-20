<?php
include "koneksi.php";
include "auth.php";
mysqli_report(MYSQLI_REPORT_OFF);

// Sudah login? tidak perlu register
if (isset($_SESSION['user_id'])) { header("Location: dashboard.php"); exit; }

$error = '';
$v = ['nis' => '', 'nama' => '', 'kelas' => '', 'jenis_kelamin' => 'L', 'username' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $v['nis']           = trim($_POST['nis'] ?? '');
    $v['nama']          = trim($_POST['nama'] ?? '');
    $v['kelas']         = trim($_POST['kelas'] ?? '');
    $v['jenis_kelamin'] = (($_POST['jenis_kelamin'] ?? 'L') === 'P') ? 'P' : 'L';
    $v['username']      = trim($_POST['username'] ?? '');
    $password           = $_POST['password'] ?? '';
    $konfirmasi         = $_POST['konfirmasi'] ?? '';

    // Role SENGAJA tidak diambil dari form: register publik selalu 'siswa'.
    if (in_array('', [$v['nis'], $v['nama'], $v['kelas'], $v['username'], $password], true)) {
        $error = 'Semua kolom wajib diisi.';
    } elseif ($password !== $konfirmasi) {
        $error = 'Konfirmasi password tidak sama.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif (rows(dbq($koneksi, "SELECT id FROM users WHERE username = ? LIMIT 1", 's', [$v['username']]))) {
        $error = 'Username sudah dipakai.';
    } elseif (rows(dbq($koneksi, "SELECT id FROM siswa WHERE nis = ? LIMIT 1", 's', [$v['nis']]))) {
        $error = 'NIS sudah terdaftar.';
    } else {
        mysqli_begin_transaction($koneksi);
        $ok = false;
        if (dbq($koneksi, "INSERT INTO users (username, password, role) VALUES (?, ?, 'siswa')", 'ss', [$v['username'], hash_pw($password)])) {
            $uid = mysqli_insert_id($koneksi);
            $ok  = (bool)dbq($koneksi, "INSERT INTO siswa (nis, nama, kelas, jenis_kelamin, user_id) VALUES (?, ?, ?, ?, ?)",
                             'ssssi', [$v['nis'], $v['nama'], $v['kelas'], $v['jenis_kelamin'], $uid]);
        }
        $err = pesan_db($koneksi);
        if ($ok) {
            mysqli_commit($koneksi);
            flash_set('success', 'Registrasi berhasil. Silakan login.');
            header("Location: login.php"); exit;
        }
        mysqli_rollback($koneksi);
        $error = 'Registrasi gagal: ' . $err;
    }
}

$page_judul = 'Daftar Siswa';
$sheet_kelas = 'sheet-lebar';
include "navbar.php";
?>
<h1 class="h4 mb-1">Pendaftaran akun siswa</h1>
<p class="text-muted mb-4">Isi data diri dengan benar. Akun guru dan admin tidak dibuat lewat halaman ini.</p>

<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

<form method="POST">
  <?= csrf_field() ?>
  <h2 class="h6 mb-3 pb-1 border-bottom border-dark">Data siswa</h2>
            <div class="mb-3">
              <label class="form-label" for="nis">NIS</label>
              <input type="text" class="form-control" id="nis" name="nis" maxlength="10" value="<?= e($v['nis']) ?>" placeholder="Nomor Induk Siswa" required>
            </div>
            <div class="mb-3">
              <label class="form-label" for="nama">Nama Lengkap</label>
              <input type="text" class="form-control" id="nama" name="nama" maxlength="100" value="<?= e($v['nama']) ?>" required>
            </div>
            <div class="mb-3">
              <label class="form-label" for="kelas">Kelas</label>
              <input type="text" class="form-control" id="kelas" name="kelas" maxlength="10" value="<?= e($v['kelas']) ?>" placeholder="Contoh: XI-RPL-1" required>
            </div>
            <div class="mb-4">
              <label class="form-label d-block">Jenis Kelamin</label>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="jenis_kelamin" id="jk_l" value="L" <?= $v['jenis_kelamin'] !== 'P' ? 'checked' : '' ?>>
                <label class="form-check-label" for="jk_l">Laki-laki</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="jenis_kelamin" id="jk_p" value="P" <?= $v['jenis_kelamin'] === 'P' ? 'checked' : '' ?>>
                <label class="form-check-label" for="jk_p">Perempuan</label>
              </div>
            </div>

            <hr>
            <h2 class="h6 mb-3 pb-1 border-bottom border-dark">Data akun</h2>
            <div class="mb-3">
              <label class="form-label" for="username">Username</label>
              <input type="text" class="form-control" id="username" name="username" maxlength="50" value="<?= e($v['username']) ?>" required>
            </div>
            <div class="row g-3 mb-4">
              <div class="col-md-6">
                <label class="form-label" for="password">Password</label>
                <input type="password" class="form-control" id="password" name="password" minlength="6" required>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="konfirmasi">Konfirmasi Password</label>
                <input type="password" class="form-control" id="konfirmasi" name="konfirmasi" minlength="6" required>
              </div>
            </div>

            <div class="d-flex gap-2 align-items-center">
    <button type="submit" class="btn btn-primary">Daftar</button>
    <a href="login.php" class="btn btn-link">Sudah punya akun? Masuk</a>
  </div>
</form>

<?php include "script.php"; ?>
