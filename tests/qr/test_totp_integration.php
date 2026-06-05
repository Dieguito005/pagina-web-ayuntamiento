<?php
require __DIR__ . '/_assert.php';
require __DIR__ . '/../../includes/totp.php';

$uri = totp_provisioning_uri('Tarancon Admin', 'admin@tarancon.es', str_repeat('A', 32));
$qr = totp_qr_url($uri);
check_true('es data URI SVG', str_starts_with($qr, 'data:image/svg+xml;base64,'));
check_true('no menciona quickchart', !str_contains($qr, 'quickchart'));
$svg = base64_decode(substr($qr, strlen('data:image/svg+xml;base64,')));
check_true('contiene <svg', str_starts_with($svg, '<svg'));
done();
