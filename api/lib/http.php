<?php
declare(strict_types=1);

/** HTTP / JSON helpers. */

function send_json($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function send_error(string $message, int $status = 400, $extra = null): void
{
    $payload = ['detail' => $message];
    if ($extra !== null) {
        $payload['extra'] = $extra;
    }
    send_json($payload, $status);
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) return [];
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        send_error('Invalid JSON body', 400);
    }
    return $data;
}

function param(array $data, string $key, $default = null)
{
    return array_key_exists($key, $data) ? $data[$key] : $default;
}

function require_param(array $data, string $key)
{
    if (!isset($data[$key]) || $data[$key] === '') {
        send_error("Missing required field: $key", 400);
    }
    return $data[$key];
}

function apply_cors(string $origin): void
{
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Max-Age: 86400');
    header('Vary: Origin');
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}
