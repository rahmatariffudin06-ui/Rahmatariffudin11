<?php
// Nama database mengikuti skema di soal tugas besar: db_siacad_smk_2
$koneksi = mysqli_connect("127.0.0.1", "root", "root", "db_siacad_smk_2", 3306);
if (!$koneksi) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
mysqli_set_charset($koneksi, "utf8mb4");
