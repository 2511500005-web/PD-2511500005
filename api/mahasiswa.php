<?php
/**
 * Endpoint: /api/mahasiswa.php
 *
 * GET    /api/mahasiswa.php                    -> semua mahasiswa (urut id DESC)
 * GET    /api/mahasiswa.php?id=1               -> satu mahasiswa (404 jika tidak ada)
 * GET    /api/mahasiswa.php?search=budi        -> cari berdasarkan nama / NIM
 * GET    /api/mahasiswa.php?page=2&limit=5     -> pagination
 * POST   /api/mahasiswa.php  (body JSON)       -> tambah mahasiswa (201 / 400 / 404 / 409)
 * PUT    /api/mahasiswa.php?id=1 (body JSON)   -> ubah mahasiswa (200 / 400 / 404 / 409)
 * DELETE /api/mahasiswa.php?id=1               -> hapus mahasiswa (200 / 400 / 404)
 */

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
// GET (Pertemuan 2, 3)
// ============================================================
function handleGet($koneksi)
{
    $select = "SELECT m.id, m.nama, m.nim, j.nama_jurusan AS jurusan
               FROM mahasiswa m
               LEFT JOIN jurusan j ON m.jurusan_id = j.id";

    // 1. GET by ID
    if (isset($_GET['id'])) {
        if (!ctype_digit((string) $_GET['id'])) {
            sendResponse(false, "Parameter id harus berupa angka", null, 400);
        }
        $id = (int) $_GET['id'];

        $r = mysqli_query($koneksi, "$select WHERE m.id = $id");
        if (!$r) {
            sendResponse(false, "query gagal: " . mysqli_error($koneksi), null, 500);
        }

        $row = mysqli_fetch_assoc($r);
        if (!$row) {
            sendResponse(false, "Mahasiswa tidak ditemukan", null, 404);
        }

        sendResponse(true, "berhasil", $row, 200);
    }

    // 2. SEARCH berdasarkan nama atau NIM
    if (isset($_GET['search'])) {
        $keyword = trim($_GET['search']);
        if ($keyword === '') {
            sendResponse(false, "Parameter search tidak boleh kosong", null, 400);
        }
        $kw = mysqli_real_escape_string($koneksi, $keyword);

        $r = mysqli_query($koneksi,
            "$select WHERE m.nama LIKE '%$kw%' OR m.nim LIKE '%$kw%' ORDER BY m.id DESC"
        );
        if (!$r) {
            sendResponse(false, "query gagal: " . mysqli_error($koneksi), null, 500);
        }

        sendResponse(true, "berhasil", fetchAll($r), 200);
    }

    // 3. PAGINATION (jika page / limit dikirim)
    if (isset($_GET['page']) || isset($_GET['limit'])) {
        $page  = isset($_GET['page'])  ? (int) $_GET['page']  : 1;
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;

        if ($page < 1)  $page  = 1;
        if ($limit < 1) $limit = 10;

        $offset = ($page - 1) * $limit;

        $r = mysqli_query($koneksi,
            "$select ORDER BY m.id DESC LIMIT $limit OFFSET $offset"
        );
        if (!$r) {
            sendResponse(false, "query gagal: " . mysqli_error($koneksi), null, 500);
        }

        sendResponse(true, "berhasil", fetchAll($r), 200);
    }

    // 4. Tanpa parameter: semua data
    $r = mysqli_query($koneksi, "$select ORDER BY m.id DESC");
    if (!$r) {
        sendResponse(false, "query gagal: " . mysqli_error($koneksi), null, 500);
    }

    sendResponse(true, "berhasil", fetchAll($r), 200);
}

// ============================================================
// POST (Pertemuan 4 - create dengan validasi berlapis)
// ============================================================
function handlePost($koneksi)
{
    // Lapis 0: baca body JSON
    $input = json_decode(file_get_contents("php://input"), true);
    if (!is_array($input)) {
        sendResponse(false, "Body harus berupa JSON yang valid", null, 400);
    }

    $nama       = isset($input['nama'])       ? trim((string) $input['nama'])  : '';
    $nim        = isset($input['nim'])        ? trim((string) $input['nim'])   : '';
    $jurusan_id = isset($input['jurusan_id']) ? (int) $input['jurusan_id']     : 0;

    // Lapis 1: field wajib tidak boleh kosong
    if ($nama === '' || $nim === '' || $jurusan_id <= 0) {
        sendResponse(false, "Field nama, nim, dan jurusan_id wajib diisi", null, 400);
    }

    // Lapis 2: jurusan_id harus ada di tabel jurusan
    $r = mysqli_query($koneksi, "SELECT id FROM jurusan WHERE id = $jurusan_id");
    if (!$r) {
        sendResponse(false, "query gagal: " . mysqli_error($koneksi), null, 500);
    }
    if (mysqli_num_rows($r) === 0) {
        sendResponse(false, "Jurusan tidak ditemukan", null, 404);
    }

    // Lapis 3: NIM tidak boleh ganda
    $nimEsc = mysqli_real_escape_string($koneksi, $nim);
    $r = mysqli_query($koneksi, "SELECT id FROM mahasiswa WHERE nim = '$nimEsc'");
    if (!$r) {
        sendResponse(false, "query gagal: " . mysqli_error($koneksi), null, 500);
    }
    if (mysqli_num_rows($r) > 0) {
        sendResponse(false, "NIM sudah terdaftar", null, 409);
    }

    // Lapis 4: INSERT memakai prepared statement (input dari user)
    $stmt = mysqli_prepare($koneksi,
        "INSERT INTO mahasiswa (nama, nim, jurusan_id, created_at) VALUES (?, ?, ?, CURDATE())"
    );
    if (!$stmt) {
        sendResponse(false, "query gagal: " . mysqli_error($koneksi), null, 500);
    }

    mysqli_stmt_bind_param($stmt, "ssi", $nama, $nim, $jurusan_id);

    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        sendResponse(false, "query gagal: " . $err, null, 500);
    }

    // Lapis 5: ambil id baris yang baru lahir sebagai bukti
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
// PUT (Pertemuan 5 - update dengan id di URL, perubahan di body)
// ============================================================
function handlePut($koneksi)
{
    // Lapis 1: parameter id wajib ada
    $id = ambilId();

    // Lapis 2: baca dan validasi body JSON
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

    // Lapis 3: record target harus ada
    $r = mysqli_query($koneksi, "SELECT id FROM mahasiswa WHERE id = $id");
    if (!$r) {
        sendResponse(false, "query gagal: " . mysqli_error($koneksi), null, 500);
    }
    if (mysqli_num_rows($r) === 0) {
        sendResponse(false, "Mahasiswa tidak ditemukan", null, 404);
    }

    // Lapis 4: jurusan_id harus ada di tabel jurusan
    $r = mysqli_query($koneksi, "SELECT id FROM jurusan WHERE id = $jurusan_id");
    if (!$r) {
        sendResponse(false, "query gagal: " . mysqli_error($koneksi), null, 500);
    }
    if (mysqli_num_rows($r) === 0) {
        sendResponse(false, "Jurusan tidak ditemukan", null, 404);
    }

    // Lapis 5: NIM tidak boleh dipakai mahasiswa lain
    $nimEsc = mysqli_real_escape_string($koneksi, $nim);
    $r = mysqli_query($koneksi, "SELECT id FROM mahasiswa WHERE nim = '$nimEsc' AND id <> $id");
    if (!$r) {
        sendResponse(false, "query gagal: " . mysqli_error($koneksi), null, 500);
    }
    if (mysqli_num_rows($r) > 0) {
        sendResponse(false, "NIM sudah terdaftar", null, 409);
    }

    // Lapis 6: jalankan UPDATE pada record dengan id tersebut
    $stmt = mysqli_prepare($koneksi,
        "UPDATE mahasiswa SET nama = ?, nim = ?, jurusan_id = ? WHERE id = ?"
    );
    if (!$stmt) {
        sendResponse(false, "query gagal: " . mysqli_error($koneksi), null, 500);
    }

    mysqli_stmt_bind_param($stmt, "ssii", $nama, $nim, $jurusan_id, $id);

    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        sendResponse(false, "query gagal: " . $err, null, 500);
    }

    // Lapis 7: periksa affected_rows lalu kirim status code
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
// DELETE (Pertemuan 5 - hapus satu target lalu verifikasi)
// ============================================================
function handleDelete($koneksi)
{
    // Lapis 1: parameter id wajib ada
    $id = ambilId();

    // Lapis 2: jalankan DELETE pada record dengan id tersebut
    $stmt = mysqli_prepare($koneksi, "DELETE FROM mahasiswa WHERE id = ?");
    if (!$stmt) {
        sendResponse(false, "query gagal: " . mysqli_error($koneksi), null, 500);
    }

    mysqli_stmt_bind_param($stmt, "i", $id);

    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        sendResponse(false, "query gagal: " . $err, null, 500);
    }

    // Lapis 3: jika tidak ada baris terhapus, target memang tidak ada
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

function fetchAll($r)
{
    $data = [];
    while ($row = mysqli_fetch_assoc($r)) {
        $data[] = $row;
    }
    return $data;
}