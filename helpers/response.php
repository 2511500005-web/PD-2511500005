<?php
/**
 * Response Helper (Pertemuan 2)
 * Standarisasi format JSON untuk semua endpoint.
 *
 * Format: { "status": bool, "message": string, "data": array|object|null }
 */
function sendResponse($status, $message, $data = null, $httpCode = 200)
{
    http_response_code($httpCode);
    header("Content-Type: application/json; charset=utf-8");

    echo json_encode([
        "status"  => $status,
        "message" => $message,
        "data"    => $data
    ], JSON_UNESCAPED_UNICODE);

    exit();
}
