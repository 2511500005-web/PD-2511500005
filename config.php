<?php
/**
 * Koneksi database (Pertemuan 6 - kredensial dari file .env)
 */
require_once __DIR__ . "/helpers/response.php";

function bacaEnv($file)
{
    $hasil = [];
    if (!is_readable($file)) {
        return $hasil;
    }

    $isi = file_get_contents($file);
    $isi = preg_replace('/^\xEF\xBB\xBF/', '', $isi);

    foreach (preg_split('/\r\n|\r|\n/', $isi) as $baris) {
        $baris = trim($baris);
        if ($baris === '' || $baris[0] === '#' || strpos($baris, '=') === false) {
            continue;
        }
        list($kunci, $nilai) = explode('=', $baris, 2);
        $hasil[trim($kunci)] = trim($nilai);
    }
    return $hasil;
}

$env = bacaEnv(__DIR__ . "/.env");

define("APP_DEBUG", isset($env['APP_DEBUG']) && strtolower($env['APP_DEBUG']) === 'true');

$host = isset($env['DB_HOST']) ? $env['DB_HOST'] : "localhost";
$user = isset($env['DB_USER']) ? $env['DB_USER'] : "app_pdcs";
$pass = isset($env['DB_PASS']) ? $env['DB_PASS'] : "";
$db   = isset($env['DB_NAME']) ? $env['DB_NAME'] : "pdcs_db";

mysqli_report(MYSQLI_REPORT_OFF);

$koneksi = @mysqli_connect($host, $user, $pass, $db);

if (!$koneksi) {
    $detail = mysqli_connect_error();
    error_log("Koneksi database gagal: " . $detail);
    $pesan = APP_DEBUG ? "Koneksi database gagal: " . $detail : "terjadi kesalahan pada server";
    sendResponse(false, $pesan, null, 500);
}

mysqli_set_charset($koneksi, "utf8mb4");