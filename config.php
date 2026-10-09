<?php
/**
 * Koneksi database (Pertemuan 2 - Koneksi Safe PHP)
 * Default Laragon: user root, password kosong.
 */
require_once __DIR__ . "/helpers/response.php";

mysqli_report(MYSQLI_REPORT_OFF);

$host = "localhost";
$user = "root";
$pass = "";
$db   = "pdcs_db";

$koneksi = @mysqli_connect($host, $user, $pass, $db);

if (!$koneksi) {
    sendResponse(false, "Koneksi database gagal", null, 500);
}

mysqli_set_charset($koneksi, "utf8mb4");
