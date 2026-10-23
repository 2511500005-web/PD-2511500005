<?php
/**
 * Endpoint: /api/mahasiswa.php (versi keamanan - Pertemuan 6)
 *
 * GET    /api/mahasiswa.php                    -> semua mahasiswa
 * GET    /api/mahasiswa.php?id=1               -> satu mahasiswa
 * GET    /api/mahasiswa.php?search=budi        -> cari nama / NIM
 * GET    /api/mahasiswa.php?page=2&limit=5     -> pagination
 * POST   /api/mahasiswa.php  (body JSON)       -> tambah mahasiswa
 * PUT    /api/mahasiswa.php?id=1 (body JSON)   -> ubah mahasiswa
 * DELETE /api/mahasiswa.php?id=1               -> hapus mahasiswa
 * OPTIONS (preflight CORS)                     -> 204
 */

// CORS (Pertemuan 6): dibutuhkan agar Android / WebView bisa memanggil API
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Preflight request dijawab 204 lalu berhenti di sini
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../helpers/response.php";

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGet($koneksi);
        break;

    case 'POST':
        handlePost($koneksi);
        break;

    case 'PUT':
        handlePut($koneksi);
        break;

    case 'DELETE':
        handleDelete($koneksi);
        break;

    default:
        sendResponse(false, "Method $method tidak diizinkan", null, 405);
}

// ============================================================
// GET
// ============================================================
function handleGet($koneksi)
{
    $select = "SELECT m.id, m.nama, m.nim, j.nama_jurusan AS jurusan
               FROM mahasiswa m
               LEFT JOIN jurusan j ON m.jurusan_id = j.id";

    // 1. GET by ID (prepared statement)
    if (isset($_GET['id'])) {
        if (!ctype_digit((string) $_GET['id'])) {
            sendResponse(false, "Parameter id harus berupa angka", null, 400);
        }
        $id = (int) $_GET['id'];

        $stmt = siapkan($koneksi, "$select WHERE m.id = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        jalankan($stmt);
        $data = ambilBarisStmt($stmt);
        mysqli_stmt_close($stmt);

        if (count($data) === 0) {
            sendResponse(false, "Mahasiswa tidak ditemukan", null, 404);
        }

        sendResponse(true, "berhasil", $data[0], 200);
    }

    // 2. SEARCH nama atau NIM (prepared statement, wildcard dibuat di PHP)
    if (isset($_GET['search'])) {
        $keyword = trim($_GET['search']);
        if ($keyword === '') {
            sendResponse(false, "Parameter search tidak boleh kosong", null, 400);
        }
        $term = "%" . $keyword . "%";

        $stmt = siapkan($koneksi, "$select WHERE m.nama LIKE ? OR m.nim LIKE ? ORDER BY m.id DESC");
        mysqli_stmt_bind_param($stmt, "ss", $term, $term);
        jalankan($stmt);
        $data = ambilBarisStmt($stmt);
        mysqli_stmt_close($stmt);

        sendResponse(true, "berhasil", $data, 200);
    }

    // 3. PAGINATION (page dan limit sudah di-cast ke integer)
    if (isset($_GET['page']) || isset($_GET['limit'])) {
        $page  = isset($_GET['page'])  ? (int) $_GET['page']  : 1;
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;

        if ($page < 1)  $page  = 1;
        if ($limit < 1) $limit = 10;

        $offset = ($page - 1) * $limit;

        $r = mysqli_query($koneksi, "$select ORDER BY m.id DESC LIMIT $limit OFFSET $offset");
        if (!$r) {
            kesalahanServer(mysqli_error($koneksi));
        }

        sendResponse(true, "berhasil", fetchAll($r), 200);
    }

    // 4. Tanpa parameter: semua data
    $r = mysqli_query($koneksi, "$select ORDER BY m.id DESC");
    if (!$r) {
        kesalahanServer(mysqli_error($koneksi));
    }

    sendResponse(true, "berhasil", fetchAll($r), 200);
}

// ============================================================
// POST
// ============================================================
function handlePost($koneksi)
{
    $input = json_decode(file_get_contents("php://input"), true);
    if (!is_array($input)) {
        sendResponse(false, "Body harus berupa JSON yang valid", null, 400);
    }

    $nama       = isset($input['nama'])       ? trim((string) $input['nama'])  : '';
    $nim        = isset($input['nim'])        ? trim((string) $input['nim'])   : '';
    $jurusan_id = isset($input['jurusan_id']) ? (int) $input['jurusan_id']     : 0;

    if ($nama === '' || $nim === '' || $jurusan_id <= 0) {
        sendResponse(false, "Field nama, nim, dan jurusan_id wajib diisi", null, 400);
    }

    if (jumlahBaris($koneksi, "SELECT id FROM jurusan WHERE id = ?", "i", $jurusan_id) === 0) {
        sendResponse(false, "Jurusan tidak ditemukan", null, 404);
    }

    if (jumlahBaris($koneksi, "SELECT id FROM mahasiswa WHERE nim = ?", "s", $nim) > 0) {
        sendResponse(false, "NIM sudah terdaftar", null, 409);
    }

    $stmt = siapkan($koneksi,
        "INSERT INTO mahasiswa (nama, nim, jurusan_id, created_at) VALUES (?, ?, ?, CURDATE())"
    );
    mysqli_stmt_bind_param($stmt, "ssi", $nama, $nim, $jurusan_id);
    jalankan($stmt);

    $newId = mysqli_insert_id($koneksi);
    mysqli_stmt_close($stmt);

    sendResponse(true, "Data mahasiswa berhasil ditambahkan", [
        "id"         => $newId,
        "nama"       => $nama,
        "nim"        => $nim,
        "jurusan_id" => $jurusan_id
    ], 201);
}

// ============================================================
// PUT
// ============================================================
function handlePut($koneksi)
{
    $id = ambilId();

    $input = json_decode(file_get_contents("php://input"), true);
    if (!is_array($input)) {
        sendResponse(false, "Body harus berupa JSON yang valid", null, 400);
    }

    $nama       = isset($input['nama'])       ? trim((string) $input['nama'])  : '';
    $nim        = isset($input['nim'])        ? trim((string) $input['nim'])   : '';
    $jurusan_id = isset($input['jurusan_id']) ? (int) $input['jurusan_id']     : 0;

    if ($nama === '' || $nim === '' || $jurusan_id <= 0) {
        sendResponse(false, "Field nama, nim, dan jurusan_id wajib diisi", null, 400);
    }

    if (jumlahBaris($koneksi, "SELECT id FROM mahasiswa WHERE id = ?", "i", $id) === 0) {
        sendResponse(false, "Mahasiswa tidak ditemukan", null, 404);
    }

    if (jumlahBaris($koneksi, "SELECT id FROM jurusan WHERE id = ?", "i", $jurusan_id) === 0) {
        sendResponse(false, "Jurusan tidak ditemukan", null, 404);
    }

    // NIM tidak boleh dipakai mahasiswa lain
    $stmt = siapkan($koneksi, "SELECT id FROM mahasiswa WHERE nim = ? AND id <> ?");
    mysqli_stmt_bind_param($stmt, "si", $nim, $id);
    jalankan($stmt);
    mysqli_stmt_store_result($stmt);
    $bentrok = mysqli_stmt_num_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($bentrok > 0) {
        sendResponse(false, "NIM sudah terdaftar", null, 409);
    }

    $stmt = siapkan($koneksi, "UPDATE mahasiswa SET nama = ?, nim = ?, jurusan_id = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "ssii", $nama, $nim, $jurusan_id, $id);
    jalankan($stmt);

    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    $pesan = $affected > 0
        ? "Data mahasiswa berhasil diperbarui"
        : "Tidak ada perubahan, data sudah sama dengan yang tersimpan";

    sendResponse(true, $pesan, [
        "id"         => $id,
        "nama"       => $nama,
        "nim"        => $nim,
        "jurusan_id" => $jurusan_id
    ], 200);
}

// ============================================================
// DELETE
// ============================================================
function handleDelete($koneksi)
{
    $id = ambilId();

    $stmt = siapkan($koneksi, "DELETE FROM mahasiswa WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    jalankan($stmt);

    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($affected === 0) {
        sendResponse(false, "Mahasiswa tidak ditemukan", null, 404);
    }

    sendResponse(true, "Data mahasiswa berhasil dihapus", ["id" => $id], 200);
}

// ============================================================
// Helper
// ============================================================
function ambilId()
{
    if (!isset($_GET['id']) || !ctype_digit((string) $_GET['id'])) {
        sendResponse(false, "Parameter id wajib diisi dan harus berupa angka", null, 400);
    }
    return (int) $_GET['id'];
}

function siapkan($koneksi, $sql)
{
    $stmt = mysqli_prepare($koneksi, $sql);
    if (!$stmt) {
        kesalahanServer(mysqli_error($koneksi));
    }
    return $stmt;
}

function jalankan($stmt)
{
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        kesalahanServer($err);
    }
}

// Mengambil hasil prepared statement tanpa mysqli_stmt_get_result()
function ambilBarisStmt($stmt)
{
    mysqli_stmt_store_result($stmt);
    mysqli_stmt_bind_result($stmt, $id, $nama, $nim, $jurusan);

    $data = [];
    while (mysqli_stmt_fetch($stmt)) {
        $data[] = [
            "id"      => $id,
            "nama"    => $nama,
            "nim"     => $nim,
            "jurusan" => $jurusan
        ];
    }
    return $data;
}

// Menghitung baris hasil query prepared dengan satu parameter
function jumlahBaris($koneksi, $sql, $tipe, $nilai)
{
    $stmt = siapkan($koneksi, $sql);
    mysqli_stmt_bind_param($stmt, $tipe, $nilai);
    jalankan($stmt);
    mysqli_stmt_store_result($stmt);
    $jumlah = mysqli_stmt_num_rows($stmt);
    mysqli_stmt_close($stmt);
    return $jumlah;
}

// Error detail hanya tampil jika APP_DEBUG=true; selalu dicatat di log server
function kesalahanServer($detail)
{
    error_log("[mahasiswa.php] " . $detail);
    $pesan = APP_DEBUG ? "Error: " . $detail : "terjadi kesalahan pada server";
    sendResponse(false, $pesan, null, 500);
}

function fetchAll($r)
{
    $data = [];
    while ($row = mysqli_fetch_assoc($r)) {
        $data[] = $row;
    }
    return $data;
}