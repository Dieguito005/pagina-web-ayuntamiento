<?php
require_once '../config.php';
require_once '../includes/user-auth-check.php';
require_once '../api/conexion.php';

// Obtener bonos activos del usuario para mostrar los que ya tiene
$misBonos = [];
try {
    // Caducar bonos expirados
    $pdo->prepare("UPDATE bonos SET estado = 'caducado' WHERE usuario_id = ? AND estado = 'activo' AND fecha_caducidad IS NOT NULL AND fecha_caducidad < CURDATE()")
        ->execute([$_SESSION['user_id']]);

    $stmt = $pdo->prepare("SELECT * FROM bonos WHERE usuario_id = ? AND estado = 'activo' ORDER BY fecha_compra ASC");
    $stmt->execute([$_SESSION['user_id']]);
    $misBonos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) { $misBonos = []; }

$totalUsosDisponibles = array_sum(array_column($misBonos, 'usos_restantes'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Compra bonos de sesiones deportivas y ahorra en tus reservas de pádel y tenis en Tarancón.">
    <link rel="preload" href="../assets/imagenes/LOGO-AYUNTAMIENTO-BLANCO.png" as="image">
    <link rel="stylesheet" href="../css/home.css?v=2.0.0">
    <link rel="stylesheet" href="../css/bonos.css?v=1.0.0">
    <link rel="stylesheet" href="../css/cookies.css">
    <script src="../js/cookies.js" defer></script>
    <script>
        window.USE_STRIPE = <?= defined('USE_STRIPE') && USE_STRIPE ? 'true' : 'false' ?>;
    </script>
    <title>Bonos de Sesiones - Alquileres Tarancón</title>
</head>
<body>

    <?php include '../includes/header.php'; ?>

    <main class="bonos-wrapper">
        <div class="bonos-container">

            <!-- Hero -->
            <div class="bonos-hero">
                <h2>🎟️ Bonos de Sesiones</h2>
                <p>Ahorra comprando packs de sesiones. Un bono te permite reservar cualquier pista sin coste adicional.</p>
            </div>

            <!-- Alerta pago cancelado -->
            <div id="bonos-cancel-alert" style="display:none; background:#fff3e0; border:1px solid #ff9800; color:#e65100; padding:1rem; border-radius:8px; margin-bottom:1.5rem; text-align:center; font-weight:500;">
                ⚠️ Has cancelado el proceso de pago. Puedes volver a intentarlo.
            </div>

            <?php if (!empty($misBonos)): ?>
            <!-- Bonos activos del usuario -->
            <div style="background: #e8f5e9; border: 1px solid #a5d6a7; border-radius: 10px; padding: 1rem 1.3rem; margin-bottom: 2rem; display: flex; align-items: center; gap: 0.8rem; flex-wrap: wrap;">
                <span style="font-size: 1.3rem;">🎟️</span>
                <div style="flex: 1;">
                    <strong style="color: #2e7d32;">Tienes <?= $totalUsosDisponibles ?> uso(s) disponible(s)</strong>
                    <p style="margin: 0.2rem 0 0; font-size: 0.88rem; color: #555;">
                        en <?= count($misBonos) ?> bono(s) activo(s).
                        <a href="perfil.php" style="color: #1565c0; font-weight: 600;">Ver mis bonos →</a>
                    </p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Packs dinámicos -->
            <div class="bonos-packs-grid" id="bonos-packs-container">
                <div style="text-align: center; padding: 3rem; color: #888;">Cargando packs disponibles...</div>
            </div>

            <!-- Info adicional -->
            <div style="background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); padding: 2rem; margin-top: 1rem;">
                <h3 style="color: #0b1c3c; margin-bottom: 1rem; font-size: 1.1rem;">❓ Preguntas Frecuentes</h3>
                <div style="margin-bottom: 1rem;">
                    <p style="font-weight: 600; color: #333; margin-bottom: 0.3rem;">¿Para qué pistas sirve el bono?</p>
                    <p style="color: #666; font-size: 0.92rem;">El bono es universal: puedes usarlo para Tenis, Pádel Cubierta o Pádel Descubierta, incluyendo el suplemento de luz nocturna.</p>
                </div>
                <div style="margin-bottom: 1rem;">
                    <p style="font-weight: 600; color: #333; margin-bottom: 0.3rem;">¿Cómo uso mi bono?</p>
                    <p style="color: #666; font-size: 0.92rem;">Al reservar una pista, aparecerá la opción "Usar bono" en el checkout. Actívala y tu reserva se confirmará sin pago adicional.</p>
                </div>
                <div>
                    <p style="font-weight: 600; color: #333; margin-bottom: 0.3rem;">¿Los bonos caducan?</p>
                    <p style="color: #666; font-size: 0.92rem;">Sí, cada bono tiene una fecha de validez indicada en el momento de la compra. Puedes consultarla en tu perfil.</p>
                </div>
            </div>

            <div style="text-align: center; margin-top: 2rem;">
                <a href="home.php" class="btn-secondary" style="text-decoration: none;">← Volver al Inicio</a>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>

    <script>
    document.addEventListener('DOMContentLoaded', async () => {
        const csrfToken = window.CSRF_TOKEN || document.querySelector('meta[name="csrf-token"]')?.content || '';

        const params = new URLSearchParams(window.location.search);
        if (params.get('pago') === 'cancelado') {
            document.getElementById('bonos-cancel-alert').style.display = 'block';
        }

        const buttonLabel = () => window.USE_STRIPE ? '💳 Comprar con Stripe' : '🛒 Comprar Bono';
        const container = document.getElementById('bonos-packs-container');

        function renderEmpty(msg, color) {
            while (container.firstChild) container.removeChild(container.firstChild);
            const div = document.createElement('div');
            div.style.cssText = 'text-align:center;padding:2rem;color:' + color + ';';
            div.textContent = msg;
            container.appendChild(div);
        }

        function renderPack(pack, isPopular) {
            const card = document.createElement('div');
            card.className = 'bono-pack-card' + (isPopular ? ' popular' : '');

            if (isPopular) {
                const badge = document.createElement('span');
                badge.className = 'bono-popular-badge';
                badge.textContent = '⭐ Más Popular';
                card.appendChild(badge);
            }

            const header = document.createElement('div');
            header.className = 'bono-pack-header';
            const iconDiv = document.createElement('div');
            iconDiv.className = 'pack-icon';
            iconDiv.textContent = '🎟️';
            const h3 = document.createElement('h3');
            h3.textContent = pack.nombre;
            const usosDiv = document.createElement('div');
            usosDiv.className = 'pack-usos';
            usosDiv.textContent = pack.usos + ' sesiones deportivas';
            header.append(iconDiv, h3, usosDiv);
            card.appendChild(header);

            const body = document.createElement('div');
            body.className = 'bono-pack-body';

            const precioDiv = document.createElement('div');
            precioDiv.className = 'bono-pack-precio';
            precioDiv.textContent = parseFloat(pack.precio).toFixed(2).replace('.', ',') + ' ';
            const eur = document.createElement('small');
            eur.textContent = '€';
            precioDiv.appendChild(eur);
            body.appendChild(precioDiv);

            const perSesion = (pack.precio / pack.usos).toFixed(2).replace('.', ',');
            const perDiv = document.createElement('div');
            perDiv.className = 'bono-pack-per-sesion';
            perDiv.textContent = perSesion + ' € por sesión';
            body.appendChild(perDiv);

            const featuresUl = document.createElement('ul');
            featuresUl.className = 'bono-pack-features';
            ['Válido para cualquier pista', 'Incluye suplemento de luz', 'Canjeable en el checkout', 'Válido ' + pack.caducidad_meses + ' meses desde la compra']
                .forEach(t => { const li = document.createElement('li'); li.textContent = t; featuresUl.appendChild(li); });
            body.appendChild(featuresUl);

            const cadP = document.createElement('p');
            cadP.className = 'bono-pack-caducidad';
            cadP.textContent = '📅 Caduca a los ' + pack.caducidad_meses + ' meses';
            body.appendChild(cadP);

            const btn = document.createElement('button');
            btn.className = 'btn-comprar-bono';
            btn.type = 'button';
            btn.dataset.tipo = pack.tipo;
            btn.textContent = buttonLabel();
            body.appendChild(btn);

            card.appendChild(body);
            return card;
        }

        try {
            const resp = await fetch('../api/bonos.php?action=get_config', { credentials: 'same-origin' });
            const result = await resp.json();

            if (result.status === 'success' && result.data.length > 0) {
                while (container.firstChild) container.removeChild(container.firstChild);
                result.data.forEach((pack, i) => {
                    container.appendChild(renderPack(pack, i === result.data.length - 1));
                });
            } else {
                renderEmpty('No hay packs disponibles en este momento.', '#888');
            }
        } catch (err) {
            console.error('Error cargando packs:', err);
            renderEmpty('Error al cargar los packs. Recarga la página.', '#c62828');
        }

        // Event delegation sobre el contenedor (sustituye al onclick inline)
        container.addEventListener('click', async (e) => {
            const btn = e.target.closest('.btn-comprar-bono');
            if (!btn) return;
            const tipo = btn.dataset.tipo;
            if (!tipo) return;

            btn.disabled = true;
            btn.textContent = 'Procesando...';
            try {
                const resp = await fetch('../api/bonos-checkout.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                    credentials: 'same-origin',
                    body: JSON.stringify({ tipo, csrf_token: csrfToken })
                });
                const result = await resp.json();
                if (result.status === 'success') {
                    if (result.checkout_url) {
                        window.location.href = result.checkout_url;
                    } else if (result.redirect) {
                        window.location.href = result.redirect;
                    }
                } else {
                    alert('Error: ' + (result.message || 'No se pudo procesar la compra.'));
                    btn.disabled = false;
                    btn.textContent = buttonLabel();
                }
            } catch (err) {
                console.error('Error comprando bono:', err);
                alert('Error de conexión. Inténtalo de nuevo.');
                btn.disabled = false;
                btn.textContent = buttonLabel();
            }
        });
    });
    </script>
</body>
</html>
