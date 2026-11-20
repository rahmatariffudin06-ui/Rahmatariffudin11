<?php
// ===== Session, keamanan, hak akses, dan fungsi bantu =====
date_default_timezone_set('Asia/Jakarta');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    @ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// ----- CSRF: semua POST wajib membawa token milik sesi -----
function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field() {
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $t = (string)($_POST['_csrf'] ?? '');
    if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $t)) {
        http_response_code(403);
        die('Sesi formulir tidak valid atau kedaluwarsa. Kembali, muat ulang halaman, lalu coba lagi.');
    }
}

// ----- hak akses -----
function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Semua fungsi require_* dipanggil SEBELUM include navbar.php (sebelum ada output)
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}

function require_role($roles) {
    require_login();
    if (!in_array($_SESSION['role'], (array)$roles, true)) {
        flash_set('danger', 'Anda tidak punya akses ke halaman itu.');
        header("Location: dashboard.php");
        exit;
    }
}

function require_admin() {
    require_role('admin');
}

// ----- password -----
function hash_pw($p) {
    return password_hash($p, PASSWORD_DEFAULT);
}

// Cocokkan hash bcrypt (utama) atau plaintext (data lama)
function cek_pw($input, $stored) {
    return password_verify($input, (string)$stored) || hash_equals((string)$stored, (string)$input);
}

// ----- flash message -----
function flash_set($type, $msg) {
    $_SESSION['flash'] = ['t' => $type, 'm' => $msg];
}

function flash_show() {
    // Error query pada request ini ditampilkan, tidak ditelan diam-diam
    if (!empty($GLOBALS['db_errors'])) {
        echo '<div class="alert alert-warning" role="alert"><strong>Query database gagal:</strong> '
           . e(implode(' | ', array_unique($GLOBALS['db_errors'])))
           . '<br><small>Struktur database kemungkinan belum sesuai skema soal. Import <code>sipn.sql</code>, atau buka <code>cek_db.php</code>.</small></div>';
    }
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        echo '<div class="alert alert-' . e($f['t']) . ' alert-dismissible fade show" role="alert">'
           . e($f['m'])
           . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }
}

// ----- database -----
// Parameter '?' diganti nilai yang sudah di-escape (i = integer, d = desimal, selain itu string).
// Tidak memakai prepared statement/get_result, jadi jalan di semua build PHP/mysqli.
function sql_bind($koneksi, $sql, $types, $params) {
    $parts = explode('?', $sql);
    if (count($parts) - 1 !== count($params)) return false;
    $out = $parts[0];
    foreach (array_values($params) as $i => $v) {
        $t = isset($types[$i]) ? $types[$i] : 's';
        if ($t === 'i')      $lit = (string)(int)$v;
        elseif ($t === 'd')  $lit = (string)(float)$v;
        else                 $lit = "'" . mysqli_real_escape_string($koneksi, (string)$v) . "'";
        $out .= $lit . $parts[$i + 1];
    }
    return $out;
}

// Return: mysqli_result (SELECT) / true (INSERT, UPDATE, DELETE) / false kalau gagal.
// Error terakhir ada di $GLOBALS['dbq_err'] dan dicatat ke $GLOBALS['db_errors'] untuk banner.
function dbq($koneksi, $sql, $types = '', $params = []) {
    $GLOBALS['dbq_err'] = [0, ''];
    if ($types !== '' || $params) {
        $sql = sql_bind($koneksi, $sql, $types, $params);
        if ($sql === false) {
            $GLOBALS['dbq_err'] = [0, 'Jumlah parameter query tidak cocok'];
            $GLOBALS['db_errors'][] = 'Jumlah parameter query tidak cocok';
            return false;
        }
    }
    $r = mysqli_query($koneksi, $sql);
    if ($r === false) {
        $GLOBALS['dbq_err'] = [mysqli_errno($koneksi), mysqli_error($koneksi)];
        $GLOBALS['db_errors'][] = mysqli_error($koneksi);
        return false;
    }
    return $r;
}

// Ambil semua baris hasil SELECT
function rows($r) {
    $out = array();
    if (!($r instanceof mysqli_result)) return $out;
    while ($row = mysqli_fetch_assoc($r)) {
        $out[] = $row;
    }
    mysqli_free_result($r);
    return $out;
}

// Ambil satu angka (COUNT dsb)
function hitung($koneksi, $sql, $types = '', $params = []) {
    $r = rows(dbq($koneksi, $sql, $types, $params));
    if (!$r) return 0;
    $v = array_values($r[0]);
    return (int)$v[0];
}

// Apakah kolom ada di tabel? (di-cache). Agar halaman tetap jalan walau kolom opsional belum ada.
function punya_kolom($koneksi, $tabel, $kolom) {
    static $cache = array();
    $k = $tabel . '.' . $kolom;
    if (!isset($cache[$k])) {
        $cache[$k] = hitung($koneksi, "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?", 'ss', array($tabel, $kolom)) > 0;
    }
    return $cache[$k];
}

// Ekspresi SELECT untuk users.created_at (alias tabel: u); NULL kalau kolomnya tidak ada
function kolom_dibuat($koneksi) {
    return punya_kolom($koneksi, 'users', 'created_at') ? 'u.created_at' : 'NULL';
}

// Tanggal untuk ditampilkan, "-" kalau kosong
function tgl($v) {
    return ($v === null || $v === '') ? '-' : e($v);
}

// Pesan error DB yang ramah (1062 = data duplikat)
function pesan_db($koneksi) {
    $er  = isset($GLOBALS['dbq_err']) ? $GLOBALS['dbq_err'] : array(0, '');
    $no  = $er[0] ? $er[0] : mysqli_errno($koneksi);
    $msg = $er[1] !== '' ? $er[1] : mysqli_error($koneksi);
    return $no === 1062 ? 'Data duplikat: username / NIS / NIP sudah dipakai.' : $msg;
}

// ----- profil user login -----
function guru_saya($koneksi) {
    $r = rows(dbq($koneksi, "SELECT * FROM guru WHERE user_id = ? LIMIT 1", 'i', array((int)(isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0))));
    return $r ? $r[0] : null;
}

function siswa_saya($koneksi) {
    $r = rows(dbq($koneksi, "SELECT * FROM siswa WHERE user_id = ? LIMIT 1", 'i', array((int)(isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0))));
    return $r ? $r[0] : null;
}

// ----- nilai -----
function rata_nilai($pengetahuan, $keterampilan) {
    return round(($pengetahuan + $keterampilan) / 2, 1);
}

function keterangan_nilai($pengetahuan, $keterampilan, $kkm) {
    return rata_nilai($pengetahuan, $keterampilan) >= $kkm ? 'Tuntas' : 'Belum Tuntas';
}

// Ringkasan nilai siswa: [daftar yang sudah bernilai, rata-rata keseluruhan atau null, jumlah tuntas]
function ringkas_nilai($nilai) {
    $ada = array();
    $total = 0;
    $tuntas = 0;
    foreach ($nilai as $r) {
        if ($r['nilai_pengetahuan'] === null) continue;
        $ada[] = $r;
        $total += rata_nilai($r['nilai_pengetahuan'], $r['nilai_keterampilan']);
        if ($r['Keterangan'] === 'Tuntas') $tuntas++;
    }
    $rata = count($ada) ? round($total / count($ada), 1) : null;
    return array($ada, $rata, $tuntas);
}

// ----- komponen tampilan -----
// Angka rata-rata + batang 0-100 dengan garis penanda KKM. Hijau kalau tuntas, merah kalau belum.
function skor_sel($rata, $kkm) {
    $r = max(0, min(100, (float)$rata));
    $k = max(0, min(100, (float)$kkm));
    $kelas = ($r >= $k) ? 'ok' : 'no';
    return '<span class="skor-sel"><b>' . e($rata) . '</b>'
         . '<span class="skor ' . $kelas . '" title="KKM ' . e((int)$kkm) . '"><i style="width:' . round($r) . '%"></i><u style="left:' . round($k) . '%"></u></span></span>';
}

// Kelas CSS untuk nilai yang masih di bawah KKM (ditandai merah di tabel)
function kelas_nilai($nilai, $kkm) {
    return ((int)$nilai < (int)$kkm) ? ' rendah' : '';
}

// Stempel status nilai. $ket: 'Tuntas' | 'Belum Tuntas' | null (belum ada nilai)
function stamp($ket) {
    if ($ket === null || $ket === '') return '<span class="stamp stamp-nil">Belum ada nilai</span>';
    return '<span class="stamp ' . ($ket === 'Tuntas' ? 'stamp-ok' : 'stamp-no') . '">' . e($ket) . '</span>';
}

// Satu baris buku besar: label ..... angka. Kalau $link diisi, labelnya jadi tautan.
function ledger_row($label, $angka, $link = null) {
    $l = $link ? '<a href="' . e($link) . '">' . e($label) . '</a>' : e($label);
    return '<li><span>' . $l . '</span><span class="dots"></span><span class="n">' . e($angka) . '</span></li>';
}

// ----- pagination -----
function pager($page, $pages, $params = array()) {
    if ($pages <= 1) return '';
    $h = '<nav><ul class="pagination pagination-sm justify-content-center mb-0 py-3">';
    for ($i = 1; $i <= $pages; $i++) {
        $params['hal'] = $i;
        $h .= '<li class="page-item' . ($i === $page ? ' active' : '') . '">'
            . '<a class="page-link" href="?' . e(http_build_query($params)) . '">' . $i . '</a></li>';
    }
    return $h . '</ul></nav>';
}
