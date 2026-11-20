<?php
include "koneksi.php";
include "auth.php";
require_admin();
mysqli_report(MYSQLI_REPORT_OFF);

$aksi = $_GET['aksi'] ?? 'list';
$id   = (int)($_GET['id'] ?? 0);
$me   = (int)$_SESSION['user_id'];
$roles = ['admin', 'guru', 'siswa'];

// apakah user punya profil guru/siswa?
function punya_profil($koneksi, $uid) {
    return hitung($koneksi, "SELECT (SELECT COUNT(*) FROM guru WHERE user_id = ?) + (SELECT COUNT(*) FROM siswa WHERE user_id = ?)", 'ii', [$uid, $uid]) > 0;
}

// ================= PROSES POST =================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do       = $_POST['do'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role_in  = in_array($_POST['role'] ?? '', $roles, true) ? $_POST['role'] : 'admin';

    if ($do === 'tambah') {
        if (dbq($koneksi, "INSERT INTO users (username, password, role) VALUES (?, ?, ?)", 'sss', [$username, hash_pw($password), $role_in])) {
            flash_set('success', 'User berhasil ditambahkan.' . ($role_in !== 'admin' ? ' Lengkapi profilnya lewat menu ' . ucfirst($role_in) . '.' : ''));
        } else {
            flash_set('danger', 'Gagal menambah user: ' . pesan_db($koneksi));
        }
        header("Location: user.php"); exit;
    }

    if ($do === 'edit') {
        $uid = (int)($_POST['id'] ?? 0);
        $cur = rows(dbq($koneksi, "SELECT role FROM users WHERE id = ?", 'i', [$uid]));
        if (!$cur) { flash_set('danger', 'User tidak ditemukan.'); header("Location: user.php"); exit; }
        // role dikunci kalau sudah punya profil guru/siswa
        if (punya_profil($koneksi, $uid) || $uid === $me) { $role_in = $cur[0]['role']; }

        $ok = $password !== ''
            ? dbq($koneksi, "UPDATE users SET username = ?, password = ?, role = ? WHERE id = ?", 'sssi', [$username, hash_pw($password), $role_in, $uid])
            : dbq($koneksi, "UPDATE users SET username = ?, role = ? WHERE id = ?", 'ssi', [$username, $role_in, $uid]);
        if ($ok) {
            if ($uid === $me) $_SESSION['username'] = $username;
            flash_set('success', 'User berhasil diupdate.');
        } else {
            flash_set('danger', 'Gagal update user: ' . pesan_db($koneksi));
        }
        header("Location: user.php"); exit;
    }

    if ($do === 'hapus') {
        $uid = (int)($_POST['id'] ?? 0);
        if ($uid === $me) {
            flash_set('danger', 'Anda tidak bisa menghapus akun yang sedang dipakai.');
        } elseif (punya_profil($koneksi, $uid)) {
            flash_set('danger', 'User ini punya profil guru/siswa. Hapus lewat menu Guru atau Siswa.');
        } elseif (dbq($koneksi, "DELETE FROM users WHERE id = ?", 'i', [$uid])) {
            flash_set('success', 'User berhasil dihapus.');
        } else {
            flash_set('danger', 'Gagal hapus user: ' . pesan_db($koneksi));
        }
        header("Location: user.php"); exit;
    }
}

// ================= SIAPKAN DATA TAMPILAN =================
$d = ['id' => 0, 'username' => '', 'role' => 'admin'];
$role_terkunci = false;
if ($aksi === 'edit') {
    $r = rows(dbq($koneksi, "SELECT id, username, role FROM users WHERE id = ?", 'i', [$id]));
    if (!$r) { flash_set('danger', 'User tidak ditemukan.'); header("Location: user.php"); exit; }
    $d = $r[0];
    $role_terkunci = punya_profil($koneksi, (int)$d['id']) || (int)$d['id'] === $me;
}

$cari   = trim($_GET['q'] ?? '');
$f_role = in_array($_GET['role'] ?? '', $roles, true) ? $_GET['role'] : '';
$hal    = max(1, (int)($_GET['hal'] ?? 1));
$per    = 15;
$list   = []; $total = 0;
if ($aksi === 'list') {
    $like  = '%' . $cari . '%';
    $where = "u.username LIKE ?"; $types = 's'; $args = [$like];
    if ($f_role !== '') { $where .= " AND u.role = ?"; $types .= 's'; $args[] = $f_role; }
    $total = hitung($koneksi, "SELECT COUNT(*) FROM users u WHERE $where", $types, $args);
    $pages = max(1, (int)ceil($total / $per));
    $hal   = min($hal, $pages);
    $off   = ($hal - 1) * $per;
    $cc    = kolom_dibuat($koneksi);
    $list  = rows(dbq($koneksi, "SELECT u.id, u.username, u.role, $cc AS created_at
                                 FROM users u WHERE $where ORDER BY u.id ASC LIMIT $per OFFSET $off", $types, $args));
    // Penanda profil dicari terpisah: kalau tabel guru/siswa belum ada, daftar user tetap tampil
    $id_guru  = array_map('intval', array_column(rows(dbq($koneksi, "SELECT user_id FROM guru")), 'user_id'));
    $id_siswa = array_map('intval', array_column(rows(dbq($koneksi, "SELECT user_id FROM siswa")), 'user_id'));
}

include "navbar.php";
$warna = ['admin' => 'danger', 'guru' => 'success', 'siswa' => 'info'];
?>

<div class="container py-4">
  <?php flash_show(); ?>

<?php if ($aksi === 'tambah' || $aksi === 'edit'): ?>

  <div class="row justify-content-center">
    <div class="col-md-7">
      <div class="card">
        <div class="card-header"><?= $aksi === 'edit' ? 'Edit User' : 'Tambah User' ?></div>
        <div class="card-body">
          <form action="user.php" method="POST">
            <?= csrf_field() ?><input type="hidden" name="do" value="<?= $aksi ?>">
            <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">

            <div class="mb-3">
              <label class="form-label" for="username">Username</label>
              <input type="text" class="form-control" id="username" name="username" maxlength="50" value="<?= e($d['username']) ?>" required>
            </div>
            <div class="mb-3">
              <label class="form-label" for="password"><?= $aksi === 'edit' ? 'Password Baru' : 'Password' ?></label>
              <input type="password" class="form-control" id="password" name="password"
                     <?= $aksi === 'edit' ? 'placeholder="Kosongkan jika tidak ingin mengubah"' : 'required minlength="6"' ?>>
            </div>
            <div class="mb-4">
              <label class="form-label" for="role">Role</label>
              <select class="form-select" id="role" name="role" <?= $role_terkunci ? 'disabled' : '' ?>>
                <?php foreach ($roles as $rl): ?>
                  <option value="<?= $rl ?>" <?= $d['role'] === $rl ? 'selected' : '' ?>><?= ucfirst($rl) ?></option>
                <?php endforeach; ?>
              </select>
              <?php if ($role_terkunci): ?>
                <div class="form-text">Role dikunci karena user ini punya profil guru/siswa atau sedang dipakai login.</div>
              <?php elseif ($aksi === 'tambah'): ?>
                <div class="form-text">User bertipe guru/siswa perlu dilengkapi profilnya lewat menu Guru / Siswa.</div>
              <?php endif; ?>
            </div>

            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary">Simpan</button>
              <a href="user.php" class="btn btn-secondary">Kembali</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

<?php else: ?>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
      <span>Daftar User <span class="badge bg-secondary"><?= $total ?></span></span>
      <form method="get" action="user.php" class="d-flex gap-2 flex-wrap">
        <select name="role" class="form-select form-select-sm" style="width:auto">
          <option value="">Semua role</option>
          <?php foreach ($roles as $rl): ?>
            <option value="<?= $rl ?>" <?= $f_role === $rl ? 'selected' : '' ?>><?= ucfirst($rl) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="q" value="<?= e($cari) ?>" class="form-control form-control-sm" placeholder="Cari username" style="width:200px">
        <button type="submit" class="btn btn-outline-primary btn-sm">Cari</button>
        <a href="user.php" class="btn btn-outline-secondary btn-sm">Reset</a>
        <a href="user.php?aksi=tambah" class="btn btn-primary btn-sm">+ Tambah User</a>
      </form>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th class="text-center">No</th><th>Username</th><th>Role</th><th>Profil</th><th>Dibuat</th><th class="text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($list as $i => $r):
                $profil = in_array((int)$r['id'], $id_guru, true) || in_array((int)$r['id'], $id_siswa, true);
                $milikku = (int)$r['id'] === $me; ?>
            <tr>
              <td class="text-center"><?= ($hal - 1) * $per + $i + 1 ?></td>
              <td><?= e($r['username']) ?> <?php if ($milikku): ?><span class="badge text-bg-light border">Anda</span><?php endif; ?></td>
              <td><span class="badge text-bg-<?= $warna[$r['role']] ?>"><?= ucfirst($r['role']) ?></span></td>
              <td>
                <?php if ($r['role'] === 'admin'): ?><span class="text-muted">-</span>
                <?php elseif ($profil): ?><span class="badge text-bg-success-subtle text-success-emphasis">Terhubung</span>
                <?php else: ?><span class="badge text-bg-warning">Belum ada</span><?php endif; ?>
              </td>
              <td class="text-muted small"><?= tgl($r['created_at']) ?></td>
              <td class="text-center">
                <a href="user.php?aksi=edit&id=<?= (int)$r['id'] ?>" class="btn btn-warning btn-sm">Edit</a>
                <?php if (!$milikku && !$profil): ?>
                <form action="user.php" method="POST" class="d-inline"
                      onsubmit="return confirm('Hapus user <?= e(addslashes($r['username'])) ?>?')">
                  <?= csrf_field() ?><input type="hidden" name="do" value="hapus">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$list): ?><tr><td colspan="6" class="text-center text-muted py-4">Tidak ada data.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <?= pager($hal, $pages, ['q' => $cari, 'role' => $f_role]) ?>
    </div>
  </div>

<?php endif; ?>
</div>

<?php include "script.php"; ?>
