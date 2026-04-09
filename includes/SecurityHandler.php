<?php
/**
 * ARCHIVO: SecurityHandler.php
 * Descripción: Middleware central de seguridad (CSRF, Rate Limiting, Redirects)
 */

class SecurityHandler {
    
    // Generación de Token CSRF Seguro [7, 8]
    public static function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    // Validación de Token CSRF con comparación segura [8]
    public static function validateCSRFToken($token) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    // Prevención de Open Redirect (Validación de Whitelist) [9, 10]
    public static function safeRedirect($url) {
        // En un entorno dinámico podríamos leer esto de config.php
        $allowed_host = $_SERVER['HTTP_HOST'] ?? 'localhost'; 
        $parsed_url = parse_url($url);
        
        if (isset($parsed_url['host']) && $parsed_url['host'] !== $allowed_host) {
            header("Location: /admin/admin-dashboard.php");
        } else {
            header("Location: " . $url);
        }
        exit;
    }

    // Implementación de Cabeceras de Seguridad HSTS y CSP [1, 11]
    public static function sendSecurityHeaders() {
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
        header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com;");
        header("X-Frame-Options: DENY");
        header("X-Content-Type-Options: nosniff");
    }
}
