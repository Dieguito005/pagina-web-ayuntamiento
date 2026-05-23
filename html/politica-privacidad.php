<?php
require_once __DIR__ . '/../config.php';
$policyVersion = defined('POLICY_VERSION') ? POLICY_VERSION : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Politica de privacidad del portal de reservas deportivas del Ayuntamiento de Tarancon (RGPD / LOPDGDD).">
    <link rel="preload" href="../assets/imagenes/LOGO-AYUNTAMIENTO-BLANCO.png" as="image">
    <link rel="stylesheet" href="../css/home.css">
    <link rel="stylesheet" href="../css/politica.css">
    <link rel="stylesheet" href="../css/cookies.css">
    <script src="../js/cookies.js" defer></script>
    <title>Politica de Privacidad | Alquileres Tarancon</title>
</head>
<body>

    <?php include '../includes/header.php'; ?>

    <main id="contenido-principal" class="main-content legal-content">
        <section class="legal-section fade-in-up">
            <div class="legal-card">
                <h2>Politica de Privacidad</h2>
                <p style="font-size:0.85rem; color:#777;">Versión <?= htmlspecialchars($policyVersion) ?> · Última revisión: <?= htmlspecialchars($policyVersion) ?></p>

                <div class="legal-text">
                    <p>El presente documento describe el tratamiento de datos personales que se realiza a través del portal <em>"Alquileres Tarancón"</em>, de conformidad con el Reglamento (UE) 2016/679 (RGPD), la Ley Orgánica 3/2018 de Protección de Datos Personales y garantía de los derechos digitales (LOPDGDD), y la Ley 7/1985 reguladora de las Bases del Régimen Local.</p>

                    <h3>1. Responsable del tratamiento</h3>
                    <ul>
                        <li><strong>Identidad:</strong> Excelentísimo Ayuntamiento de Tarancón</li>
                        <li><strong>NIF:</strong> P1621500F</li>
                        <li><strong>Domicilio:</strong> Plaza del Ayuntamiento, 1, 16400 Tarancón (Cuenca), España</li>
                        <li><strong>Teléfono:</strong> +34 969 32 10 16</li>
                        <li><strong>Correo de contacto:</strong> info@tarancon.es</li>
                        <li><strong>Sede electrónica:</strong> <a href="https://sede.tarancon.es" target="_blank" rel="noopener">sede.tarancon.es</a></li>
                    </ul>

                    <h3>2. Delegado de Protección de Datos (DPD)</h3>
                    <p>De conformidad con el art. 37.1.a) RGPD y art. 34 LOPDGDD, el Ayuntamiento de Tarancón cuenta con un Delegado de Protección de Datos. Puede contactarse con el DPD:</p>
                    <ul>
                        <li><strong>Correo:</strong> dpd@tarancon.es <span style="color:#777;font-size:0.85rem;">[REVISAR DPO: confirmar buzón institucional habilitado]</span></li>
                        <li><strong>Dirección postal:</strong> Plaza del Ayuntamiento, 1, 16400 Tarancón (Cuenca) - A la atención del Delegado de Protección de Datos</li>
                    </ul>

                    <h3>3. Tratamientos realizados a través del portal</h3>

                    <h4>3.1. Gestión de cuentas de usuario</h4>
                    <ul>
                        <li><strong>Finalidad:</strong> permitir el alta y gestión de una cuenta para reservar instalaciones deportivas.</li>
                        <li><strong>Base jurídica:</strong> art. 6.1.b) RGPD - ejecución de la relación jurídica con el interesado (prestación del servicio).</li>
                        <li><strong>Datos tratados:</strong> nombre, apellidos, correo electrónico, contraseña (cifrada), DNI/NIE (opcional), teléfono (opcional).</li>
                        <li><strong>Conservación:</strong> mientras la cuenta esté activa y hasta 12 meses tras la última actividad. Tras dicho plazo, los datos se anonimizan o eliminan, salvo obligación legal de conservación.</li>
                    </ul>

                    <h4>3.2. Reserva y pago de instalaciones deportivas</h4>
                    <ul>
                        <li><strong>Finalidad:</strong> gestionar la reserva de pistas, generar el código de acceso, cobrar la tasa correspondiente y emitir justificante.</li>
                        <li><strong>Base jurídica:</strong> art. 6.1.e) RGPD - cumplimiento de una misión en interés público (gestión de instalaciones deportivas municipales, art. 25.2.l Ley 7/1985 LBRL); art. 6.1.c) RGPD para las obligaciones tributarias y de facturación derivadas de la ordenanza fiscal aplicable.</li>
                        <li><strong>Datos tratados:</strong> nombre, apellidos, DNI/NIE, email, teléfono, pista, fecha y hora reservada, importe abonado, código de acceso, identificador de la transacción de pago.</li>
                        <li><strong>Conservación:</strong> 4 años a contar desde la fecha de la reserva, en cumplimiento del art. 66 de la Ley 58/2003 General Tributaria (plazo de prescripción tributaria). La contabilidad asociada se conserva 6 años (art. 30 Código de Comercio).</li>
                    </ul>

                    <h4>3.3. Atención de consultas y comunicaciones</h4>
                    <ul>
                        <li><strong>Finalidad:</strong> resolver las preguntas, sugerencias o quejas remitidas a través del formulario de contacto.</li>
                        <li><strong>Base jurídica:</strong> art. 6.1.a) RGPD - consentimiento del interesado, manifestado al pulsar "Enviar".</li>
                        <li><strong>Datos tratados:</strong> nombre, apellidos, email, teléfono, descripción de la consulta. El DNI es opcional.</li>
                        <li><strong>Conservación:</strong> hasta 12 meses tras la resolución de la consulta. Si genera expediente administrativo, se conserva conforme al calendario municipal aplicable.</li>
                    </ul>

                    <h4>3.4. Cookies y análisis web</h4>
                    <ul>
                        <li><strong>Finalidad:</strong> permitir la navegación, recordar preferencias y, en su caso, medir el uso del portal.</li>
                        <li><strong>Base jurídica:</strong> interés legítimo para las cookies técnicas estrictamente necesarias; consentimiento (art. 6.1.a RGPD y art. 22.2 LSSI-CE) para cookies analíticas u otras no esenciales.</li>
                        <li><strong>Detalle:</strong> véase la <a href="politica-cookies.php">Política de Cookies</a>.</li>
                    </ul>

                    <h3>4. Categorías de datos y origen</h3>
                    <p>Únicamente se tratan datos identificativos, de contacto, identificación oficial (DNI/NIE) y económico-financieros relacionados con el pago. Todos los datos son facilitados directamente por la persona interesada. El portal no realiza categorías especiales de datos (art. 9 RGPD).</p>

                    <h3>5. Destinatarios y encargados del tratamiento</h3>
                    <p>Para la prestación del servicio se contrata a los siguientes encargados del tratamiento, con quienes se ha suscrito o se suscribirá el correspondiente Acuerdo de Encargo conforme al art. 28 RGPD:</p>
                    <ul>
                        <li><strong>Stripe Payments Europe, Limited</strong> (Irlanda, UE) - pasarela de pago. Algunas operaciones técnicas pueden realizarse desde Stripe Inc. (EE. UU.) bajo cláusulas tipo aprobadas por la Comisión Europea y el Marco UE-EE. UU. de Privacidad de Datos.</li>
                        <li><strong>Resend, Inc.</strong> (EE. UU., adherido al Data Privacy Framework UE-EE. UU.) - envío de emails transaccionales (confirmaciones de reserva). Posibles alternativas equivalentes en territorio UE en caso de cambio de proveedor.</li>
                        <li><strong>Proveedor de alojamiento web</strong> contratado por el Ayuntamiento <span style="color:#777;font-size:0.85rem;">[REVISAR DPO: indicar proveedor final una vez se migre a hosting certificado ENS]</span>.</li>
                        <li><strong>Personal autorizado del Ayuntamiento</strong> (Concejalía de Deportes y conserjes municipales) que accede únicamente al código de acceso y datos mínimos para validar la entrada del usuario a la instalación.</li>
                    </ul>
                    <p>Adicionalmente, los datos podrán comunicarse a la Administración Tributaria, Tribunal de Cuentas o Juzgados y Tribunales cuando exista obligación legal.</p>

                    <h3>6. Transferencias internacionales</h3>
                    <p>Stripe y Resend pueden tratar datos personales fuera del Espacio Económico Europeo (EE. UU.). Estas transferencias se realizan al amparo de las cláusulas contractuales tipo aprobadas por la Comisión Europea y, en su caso, de la adhesión del proveedor al Marco UE-EE. UU. de Privacidad de Datos. Puede obtenerse copia de las garantías escribiendo al DPD.</p>

                    <h3>7. Plazo de conservación</h3>
                    <p>Los plazos concretos se indican en cada tratamiento del apartado 3. Con carácter general, los datos se conservan únicamente durante el tiempo necesario para el fin que motivó su recogida y, posteriormente, durante los plazos legales que resulten aplicables (fiscalidad, contabilidad, contencioso-administrativo). Transcurridos esos plazos, los datos serán suprimidos o anonimizados.</p>

                    <h3>8. Derechos de las personas interesadas</h3>
                    <p>Puede ejercer los siguientes derechos en los términos previstos por el RGPD:</p>
                    <ul>
                        <li>Derecho de <strong>acceso</strong> a sus datos personales.</li>
                        <li>Derecho de <strong>rectificación</strong> de datos inexactos o incompletos.</li>
                        <li>Derecho de <strong>supresión</strong> ("derecho al olvido"), cuando no exista obligación legal de conservación.</li>
                        <li>Derecho a la <strong>limitación</strong> del tratamiento.</li>
                        <li>Derecho a la <strong>portabilidad</strong> de los datos.</li>
                        <li>Derecho de <strong>oposición</strong> al tratamiento.</li>
                        <li>Derecho a <strong>no ser objeto de decisiones automatizadas</strong> (no se realizan).</li>
                        <li>Derecho a <strong>retirar el consentimiento</strong> en cualquier momento (en aquellos tratamientos basados en él), sin afectar a la licitud del tratamiento previo.</li>
                    </ul>
                    <p><strong>Cómo ejercerlos:</strong></p>
                    <ol>
                        <li>Telemáticamente a través de la Sede Electrónica del Ayuntamiento de Tarancón con identificación mediante Cl@ve o certificado digital.</li>
                        <li>Por correo electrónico al DPD: <strong>dpd@tarancon.es</strong>, adjuntando copia del DNI/NIE o documento equivalente.</li>
                        <li>Presencialmente en el Registro General del Ayuntamiento (Plaza del Ayuntamiento, 1) en horario de atención al público.</li>
                    </ol>
                    <p>El Ayuntamiento dispondrá de un plazo máximo de un mes para responder, ampliable a dos meses adicionales en casos de especial complejidad.</p>

                    <h3>9. Derecho a reclamar ante la autoridad de control</h3>
                    <p>Si considera que el tratamiento de sus datos vulnera la normativa de protección de datos o no se ha atendido adecuadamente al ejercicio de sus derechos, puede presentar una reclamación ante la Agencia Española de Protección de Datos (<a href="https://www.aepd.es" target="_blank" rel="noopener">www.aepd.es</a>), C/ Jorge Juan 6, 28001 Madrid, sin perjuicio de cualquier otro recurso administrativo o judicial.</p>

                    <h3>10. Medidas de seguridad</h3>
                    <p>El Ayuntamiento de Tarancón aplica medidas técnicas y organizativas adecuadas al riesgo del tratamiento, en línea con el Esquema Nacional de Seguridad (Real Decreto 311/2022) y el art. 32 RGPD: cifrado de comunicaciones (HTTPS/TLS), almacenamiento seguro de contraseñas, control de accesos, registro de actividad, copias de seguridad y procedimiento de notificación de brechas en 72 horas a la AEPD (art. 33 RGPD).</p>

                    <h3>11. Decisiones automatizadas y elaboración de perfiles</h3>
                    <p>No se adoptan decisiones individuales basadas únicamente en el tratamiento automatizado de datos, ni se elaboran perfiles que produzcan efectos jurídicos.</p>

                    <h3>12. Datos de menores</h3>
                    <p>El servicio está dirigido a personas mayores de 14 años. Los menores de 14 años solo pueden reservar instalaciones con consentimiento de quienes ejerzan la patria potestad o tutela (art. 7 LOPDGDD).</p>

                    <h3>13. Cambios en la política</h3>
                    <p>El Ayuntamiento podrá actualizar esta política para adaptarla a cambios normativos o a nuevos tratamientos. Se notificará a los usuarios con cuenta activa cualquier modificación sustancial.</p>
                </div>

                <div class="legal-action-container">
                    <a href="home.php" class="btn-legal btn-volver">Volver al Inicio</a>
                </div>
            </div>
        </section>
    </main>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
