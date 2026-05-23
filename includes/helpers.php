<?php
/**
 * includes/helpers.php
 * --------------------
 * Funciones compartidas que se usan en varias páginas:
 *   1. Colores / iconos / badges de nivel
 *   2. Formato de fechas en español
 *   3. Mensajes motivacionales por nivel
 *   4. Cálculo de IMC
 *   5. Utilidades pequeñas (nn, flash_ok, flash_err)
 *   6. Render de filas de "resultado"
 */

// ============================================================
// 1) Colores, niveles, iconos
// ============================================================

// Mapa color → clases Tailwind. Si el token no existe usamos sage por defecto.
const COLOR_MAP = [
    'indigo'  => 'bg-indigo-50 text-indigo-700',
    'amber'   => 'bg-amber-50 text-amber-700',
    'sky'     => 'bg-sky-50 text-sky-700',
    'rose'    => 'bg-rose-50 text-rose-700',
    'lime'    => 'bg-lime-50 text-lime-700',
    'emerald' => 'bg-emerald-50 text-emerald-700',
];

function color_classes($token) {
    return COLOR_MAP[$token] ?? 'bg-sage-100 text-sage-700';
}

// Estilos por nivel de bienestar (bajo / medio / alto).
const NIVELES = [
    'bajo'  => ['bg' => 'bg-clay-200/70', 'text' => 'text-clay-600',  'dot' => 'bg-clay-400',  'label' => 'Bajo'],
    'medio' => ['bg' => 'bg-amber-100',   'text' => 'text-amber-700', 'dot' => 'bg-amber-500', 'label' => 'Medio'],
    'alto'  => ['bg' => 'bg-sage-100',    'text' => 'text-sage-700',  'dot' => 'bg-sage-500',  'label' => 'Alto'],
];

/** Devuelve el HTML de la "pildora" (badge) con el color del nivel. */
function nivel_badge($nivel) {
    $n = NIVELES[$nivel] ?? NIVELES['medio'];
    return '<span class="pill ' . $n['bg'] . ' ' . $n['text'] . '">'
         . '<span class="w-1.5 h-1.5 rounded-full ' . $n['dot'] . '"></span>'
         . $n['label'] . '</span>';
}

// Lista blanca de iconos válidos (de Lucide). Cualquier otro nombre cae al icono por defecto.
const ICONOS_OK = ['moon','battery','droplet','activity','zap','leaf','heart-pulse'];

/** Render de un icono Lucide con tamaño configurable. */
function icono_cuestionario($name, $size = 22) {
    $n = in_array($name, ICONOS_OK, true) ? $name : 'clipboard-list';
    return '<i data-lucide="' . e($n) . '" style="width:' . (int)$size . 'px;height:' . (int)$size . 'px"></i>';
}

// ============================================================
// 2) Formato de fechas en español
// ============================================================

/**
 * Convierte una fecha de MySQL ("2026-05-20 14:32:00") en algo legible:
 *   completo → "20 may 2026 · 14:32"
 *   corto    → "20 may 2026"
 *   largo    → "20 mayo 2026 · 14:32"
 */
function formato_fecha($mysqlDatetime, $formato = 'completo') {
    if (!$mysqlDatetime) return '';
    $ts = strtotime($mysqlDatetime);
    if (!$ts) return '';

    $meses       = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    $mesesLargos = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];

    $dia    = date('d', $ts);
    $mesIdx = (int)date('n', $ts) - 1;
    $anio   = date('Y', $ts);
    $hora   = date('H:i', $ts);

    if ($formato === 'corto') return "$dia {$meses[$mesIdx]} $anio";
    if ($formato === 'largo') return "$dia {$mesesLargos[$mesIdx]} $anio · $hora";
    return "$dia {$meses[$mesIdx]} $anio · $hora";
}

// ============================================================
// 3) Mensajes motivacionales y cálculo de IMC
// ============================================================

/** Mensaje corto según el nivel general del cuestionario. */
function mensaje_nivel($nivel) {
    if ($nivel === 'alto')  return '¡Excelente! Sigues cultivando hábitos sanos. Mantén el ritmo.';
    if ($nivel === 'medio') return 'Vas bien, pero hay áreas donde puedes mejorar un poco.';
    if ($nivel === 'bajo')  return 'Tu bienestar necesita atención. Considera pequeños cambios diarios.';
    return '';
}

/**
 * IMC = peso(kg) / altura(m)^2.
 * Devuelve [imc, categoria] o [null, null] si faltan datos.
 */
function calcular_imc($peso, $alturaCm) {                // recibe peso en kg y altura en cm
    if (!$peso || !$alturaCm) return [null, null];       // si falta alguno → no se puede calcular, salimos con nulls
    $m = ((float)$alturaCm) / 100.0;                     // pasar cm a metros (175 cm → 1.75 m). (float) por si llega como texto
    if ($m <= 0) return [null, null];                    // evita dividir entre 0 o entre negativo
    $imc = round(((float)$peso) / ($m * $m), 1);         // FÓRMULA: peso ÷ (altura × altura), redondeado a 1 decimal

    if ($imc < 18.5)   $cat = 'Bajo peso';               // menor a 18.5 → bajo peso
    elseif ($imc < 25) $cat = 'Saludable';               // entre 18.5 y 24.9 → saludable
    elseif ($imc < 30) $cat = 'Sobrepeso';               // entre 25 y 29.9 → sobrepeso
    else               $cat = 'Obesidad';                // 30 o más → obesidad

    return [$imc, $cat];                                 // devuelve un array con dos cosas: el número y la categoría
}

// ============================================================
// 4) Utilidades pequeñas (DRY)
// ============================================================

/** Convierte '' en null. Útil para columnas opcionales que vienen de $_POST. */
function nn($v) { return $v === '' ? null : $v; }

/** Caja roja de error. Se usa en formularios. */
function flash_err($msg, $extra = '') {
    return '<div class="text-sm text-clay-600 bg-clay-50 border border-clay-200/60 px-4 py-2.5 rounded-2xl ' . $extra . '">' . e($msg) . '</div>';
}

/** Caja verde de éxito. */
function flash_ok($msg, $extra = '') {
    return '<div class="text-sm text-sage-700 bg-sage-50 border border-sage-100 px-4 py-2.5 rounded-2xl ' . $extra . '">' . e($msg) . '</div>';
}

// ============================================================
// 5) Render de "item resultado"
// ============================================================

/**
 * Render de una fila (<li>) que enlaza a resultado.php.
 * Se usa en dashboard, historial y ficha → evita repetir el mismo HTML 3 veces.
 *
 * $r     → fila con id, porcentaje, nivel, creado_en, titulo, slug, icono, color
 * $opts  → ['delay' => int, 'animate' => bool, 'formato' => 'completo'|'corto'|'largo']
 */
function item_resultado($r, $opts = []) {
    $delay   = $opts['delay']   ?? null;
    $animate = $opts['animate'] ?? false;
    $formato = $opts['formato'] ?? 'completo';

    $liClass = $animate ? 'animate-fade-up' : '';
    $style   = $delay !== null ? ' style="animation-delay:' . (int)$delay . 'ms"' : '';

    ob_start(); ?>
<li class="<?= $liClass ?>"<?= $style ?>>
  <a href="resultado.php?id=<?= (int)$r['id'] ?>" class="flex items-center gap-4 p-4 hover:bg-black/[0.025] transition-colors">
    <div class="w-10 h-10 rounded-2xl flex items-center justify-center <?= color_classes($r['color']) ?>">
      <?= icono_cuestionario($r['icono'], 18) ?>
    </div>
    <div class="flex-1 min-w-0">
      <div class="font-medium truncate"><?= e($r['titulo']) ?></div>
      <div class="text-xs text-mute"><?= e(formato_fecha($r['creado_en'], $formato)) ?></div>
    </div>
    <div class="font-display text-xl tabular-nums"><?= (int)round((float)$r['porcentaje']) ?>%</div>
    <?= nivel_badge($r['nivel']) ?>
    <i data-lucide="arrow-right" style="width:14px;height:14px" class="text-mute/40"></i>
  </a>
</li>
<?php return ob_get_clean();
}
