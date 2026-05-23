<?php
/**
 * helpers.php — Utilidades comunes.
 */

require_once __DIR__ . '/SecurityHandler.php';

/** Escape HTML seguro para incrustar texto del usuario en plantillas. */
function esc_html(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Inyecta meta CSRF + variable global JS en la cabecera HTML. Llamar en <head>. */
function csrf_meta_tag(): string
{
    $token = SecurityHandler::generateCSRFToken();
    return '<meta name="csrf-token" content="' . esc_html($token) . '">';
}

/** Devuelve un input hidden con el token CSRF para incrustar en formularios. */
function csrf_input(): string
{
    $token = SecurityHandler::generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . esc_html($token) . '">';
}

/**
 * Lee el cuerpo JSON de la petición. Cachea para evitar leer php://input dos veces.
 * Aborta con 413 si el body supera el cap configurado (64 KB por defecto — generoso
 * para nuestros endpoints; subir solo si se prevé un payload mayor).
 */
function read_json_body(int $maxBytes = 65536): array
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $raw = file_get_contents('php://input');
    if ($raw !== false && strlen($raw) > $maxBytes) {
        SecurityHandler::jsonError(413, 'Payload demasiado grande.');
    }
    $data = $raw ? json_decode($raw, true) : null;
    $cache = is_array($data) ? $data : [];
    return $cache;
}

/** Escapa los wildcards `%` y `_` de SQL LIKE para que sólo el atacante no controle el patrón. */
function like_escape(string $s): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
}

/**
 * Trunca un password a una longitud sensata antes de pasarlo a password_verify/hash.
 * password_hash con bcrypt es O(2^cost) y un payload de 1MB puede generar DoS de CPU.
 * Bcrypt sólo usa los primeros 72 bytes, así que truncar a 256 es seguro.
 */
function clamp_password(string $p, int $maxBytes = 256): string
{
    return strlen($p) > $maxBytes ? substr($p, 0, $maxBytes) : $p;
}
