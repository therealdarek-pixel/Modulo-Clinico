<?php
/**
 * public/resultado.php
 * --------------------
 * Detalle del resultado de un intento concreto de cuestionario.
 *
 * URL: resultado.php?id=...
 *
 * Pinta:
 *   - Anillo SVG con el porcentaje
 *   - Nivel (bajo/medio/alto) y mensaje motivacional
 *   - 3 recomendaciones según el nivel
 *   - Lista detallada de las respuestas (pregunta + opción + valor 1-5)
 */
require_once __DIR__ . '/../includes/auth.php';                                      // sesión + require_login()
require_once __DIR__ . '/../includes/helpers.php';                                   // nivel_badge(), icono_cuestionario(), formato_fecha()...
require_login();                                                                     // sin sesión → manda a login.php

$idUsuario = current_user_id();                                                      // id del usuario logueado
$id = (int)($_GET['id'] ?? 0);                                                       // id del resultado de la URL (?id=42). (int) por seguridad

// --- 1) Sin id → al historial ---
if (!$id) {
    header('Location: historial.php');                                               // si no llegó id válido, no hay nada que mostrar
    exit;
}

$pdo = db();                                                                         // conexión PDO

// --- 2) Cargar el resultado (filtrando por usuario para que nadie vea resultados de otros) ---
// JOIN con cuestionarios para traer también titulo/slug/icono/color (datos del cuestionario).
$stmt = $pdo->prepare(
    "SELECT r.*, c.titulo, c.slug, c.icono, c.color
       FROM resultados r JOIN cuestionarios c ON c.id = r.cuestionario_id   
      WHERE r.id = ? AND r.usuario_id = ?"                                            // dos condiciones: id + dueño → evita ver resultados ajenos
);
$stmt->execute([$id, $idUsuario]);                                                   // pasamos los dos parámetros en orden
$resultado = $stmt->fetch();                                                         // 1 sola fila esperada

if (!$resultado) {                                                                   // si no existe o es de otro usuario...
    http_response_code(404);                                                         // ...respondemos 404
    echo 'Resultado no encontrado';
    exit;
}

// --- 3) Detalle de respuestas para la sección "Tus respuestas" ---
// JOIN con preguntas y opciones para traer el texto de cada una (en vez de solo el id).
// Cada fila tendrá: valor (1-5), texto de la pregunta y texto de la opción elegida.
// ORDER BY p.orden → pintamos en el orden original del cuestionario.
$stmt = $pdo->prepare(
    "SELECT rs.valor, p.texto AS pregunta, o.texto AS respuesta
       FROM respuestas rs
       JOIN preguntas p ON p.id = rs.pregunta_id
       JOIN opciones  o ON o.id = rs.opcion_id
      WHERE rs.resultado_id = ?
      ORDER BY p.orden ASC"
);
$stmt->execute([$id]);
$detalle = $stmt->fetchAll();                                                        // array: [{valor, pregunta, respuesta}, ...]

// --- 4) Consejos y color del anillo según el nivel ---
$CONSEJOS = [                                                                        // diccionario: nivel → 3 recomendaciones
    'alto'  => ['Mantén tu rutina actual', 'Comparte tus hábitos con otros', 'Sigue celebrando tus pequeños logros'],
    'medio' => ['Identifica un hábito a mejorar', 'Establece metas pequeñas', 'Sé constante esta semana'],
    'bajo'  => ['Empieza con un solo cambio', 'Habla con alguien cercano', 'Recuerda: pequeños pasos importan'],
];
$RING_COLOR = ['alto' => '#7AA079', 'medio' => '#E0B770', 'bajo' => '#D89A82'];      // color hex del anillo SVG por nivel

$nivel          = $resultado['nivel'];                                               // 'alto' | 'medio' | 'bajo'
$color          = $RING_COLOR[$nivel] ?? '#7AA079';                                  // color del anillo (verde si llega algo raro)
$tips           = $CONSEJOS[$nivel] ?? [];                                           // los 3 consejos correspondientes
$nivelTextClass = NIVELES[$nivel]['text'] ?? 'text-mute';                            // clase Tailwind para colorear la palabra del nivel

// --- 5) Cálculos para el anillo SVG (stroke-dashoffset) ---
// El anillo se dibuja con stroke-dasharray (circunferencia) y stroke-dashoffset (cuánto se oculta).
$radio  = 56;                                                                        // radio del círculo en px
$circ   = 2 * M_PI * $radio;                                                         // circunferencia = 2·π·r (≈ 351.86)
$offset = $circ - ((float)$resultado['porcentaje'] / 100) * $circ;                   // parte oculta = total - parte pintada (proporcional al %)
$pct    = (int)round((float)$resultado['porcentaje']);                               // % entero para mostrar el número grande en el centro

$pageTitle = 'Resultado';
$activeNav = 'historial';
require __DIR__ . '/../includes/header.php';
?>
<div class="max-w-3xl mx-auto space-y-6 animate-fade-up">
  <a href="historial.php" class="inline-flex items-center gap-1.5 text-sm text-mute hover:text-ink transition-colors">
    <i data-lucide="arrow-left" style="width:15px;height:15px"></i> Historial
  </a>

  <div class="card relative overflow-hidden">
    <div class="absolute inset-x-0 top-0 h-1" style="background:<?= $color ?>"></div>
    <div class="absolute -right-24 -top-24 w-72 h-72 rounded-full blur-3xl opacity-40 pointer-events-none" style="background:<?= $color ?>"></div>

    <div class="relative flex flex-col md:flex-row md:items-center md:justify-between gap-8">
      <div class="flex items-start gap-4">
        <div class="w-12 h-12 rounded-2xl bg-sage-100 text-sage-700 flex items-center justify-center shrink-0">
          <?= icono_cuestionario($resultado['icono']) ?>
        </div>
        <div>
          <p class="text-mute text-[11px] uppercase tracking-[0.16em]">Resultado</p>
          <h1 class="font-display text-4xl mt-1 leading-tight text-balance"><?= e($resultado['titulo']) ?></h1>
          <p class="text-mute text-sm mt-1"><?= e(formato_fecha($resultado['creado_en'], 'largo')) ?></p>
          <div class="mt-3"><?= nivel_badge($nivel) ?></div>
        </div>
      </div>

      <div class="relative w-36 h-36 self-center">
        <svg viewBox="0 0 140 140" class="w-full h-full -rotate-90">
          <circle cx="70" cy="70" r="<?= $radio ?>" fill="none" stroke="rgba(31,36,33,0.06)" stroke-width="10"/>
          <circle cx="70" cy="70" r="<?= $radio ?>" fill="none"
                  stroke="<?= $color ?>" stroke-width="10" stroke-linecap="round"
                  stroke-dasharray="<?= $circ ?>" stroke-dashoffset="<?= $offset ?>"/>
        </svg>
        <div class="absolute inset-0 flex flex-col items-center justify-center">
          <div class="font-display text-4xl tabular-nums leading-none">
            <span><?= $pct ?></span><span class="text-lg text-mute">%</span>
          </div>
          <div class="text-[10px] uppercase tracking-[0.16em] text-mute mt-1">Bienestar</div>
        </div>
      </div>
    </div>

    <p class="relative mt-7 text-mute leading-relaxed">
      Tu puntaje fue de <b class="text-ink tabular-nums"><?= (int)$resultado['puntaje_total'] ?></b>
      sobre <b class="text-ink tabular-nums"><?= (int)$resultado['puntaje_max'] ?></b>.
      Esto se interpreta como un nivel <b class="<?= $nivelTextClass ?>"><?= e($nivel) ?></b> de bienestar en esta área.
    </p>
    <p class="relative mt-3 text-sm text-ink/80"><?= e(mensaje_nivel($nivel)) ?></p>
  </div>

  <div class="card animate-fade-up" style="animation-delay:120ms">
    <div class="flex items-center gap-2 mb-5">
      <span class="w-8 h-8 rounded-2xl bg-sage-100 text-sage-700 grid place-items-center">
        <i data-lucide="sparkles" style="width:15px;height:15px"></i>
      </span>
      <h3 class="font-display text-2xl">Recomendaciones</h3>
    </div>
    <ul class="space-y-3">
      <?php foreach ($tips as $i => $tip): ?>
        <li class="flex gap-3 items-start animate-fade-up p-3 -mx-3 rounded-2xl hover:bg-sage-50/60 transition-colors" style="animation-delay:<?= $i * 50 ?>ms">
          <span class="w-6 h-6 rounded-full bg-sage-100 text-sage-700 grid place-items-center text-xs font-semibold flex-shrink-0"><?= $i + 1 ?></span>
          <span class="text-ink/80 leading-relaxed"><?= e($tip) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>

  <div class="card animate-fade-up" style="animation-delay:180ms">
    <h3 class="font-display text-2xl mb-4">Tus respuestas</h3>
    <ul class="divide-y divide-black/5">
      <?php foreach ($detalle as $i => $r): ?>
        <li class="py-3.5 flex justify-between gap-4 animate-fade-up" style="animation-delay:<?= $i * 40 ?>ms">
          <div class="flex-1 min-w-0">
            <div class="text-xs text-mute"><?= e($r['pregunta']) ?></div>
            <div class="font-medium mt-0.5"><?= e($r['respuesta']) ?></div>
          </div>
          <div class="flex items-center gap-1.5 shrink-0">
            <?php for ($n = 0; $n < 5; $n++): ?>
              <span class="w-1.5 h-5 rounded-full <?= $n < (int)$r['valor'] ? 'bg-sage-500' : 'bg-black/[0.06]' ?>"></span>
            <?php endfor; ?>
            <span class="ml-1 font-display text-sm text-mute tabular-nums"><?= (int)$r['valor'] ?>/5</span>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>

  <div class="flex flex-wrap gap-3">
    <a href="cuestionario.php?slug=<?= urlencode($resultado['slug']) ?>" class="btn btn-primary">
      <i data-lucide="rotate-cw" style="width:15px;height:15px"></i> Hacerlo de nuevo
    </a>
    <a href="cuestionarios.php" class="btn btn-outline">Otro cuestionario</a>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
