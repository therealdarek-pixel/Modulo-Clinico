<?php
/**
 * public/dashboard.php
 * --------------------
 * Pantalla de inicio del usuario. Resume toda su actividad.
 *
 * Datos que prepara para la vista:
 *   1. Total de cuestionarios hechos + promedio general
 *   2. Distribución por nivel (bajo / medio / alto)
 *   3. Último resultado de cada cuestionario (con conteo de veces)
 *   4. Evolución diaria de los últimos 30 días (para la gráfica)
 *   5. Actividad reciente (las 5 últimas entradas del historial)
 *   6. IMC
 *   7. Saludo + nombre + flag de "hay datos para pie chart"
 */
require_once __DIR__ . '/../includes/auth.php';                                       // sesión + require_login()
require_once __DIR__ . '/../includes/helpers.php';                                    // calcular_imc(), e(), color_classes()...
require_login();                                                                      // sin sesión → manda a login.php

$idUsuario = current_user_id();                                                       // id del usuario logueado
$user      = get_user();                                                              // datos del usuario (nombre, peso, altura, ...)
$pdo       = db();                                                                    // conexión PDO a la base de datos

// --- 1) Total de cuestionarios + promedio general (una sola query con COUNT y AVG) ---
$stmt = $pdo->prepare("SELECT COUNT(*) c, AVG(porcentaje) p FROM resultados WHERE usuario_id = ?"); // c = cuántos resultados; p = promedio
$stmt->execute([$idUsuario]);                                                         // ejecuta con el id del usuario
$row = $stmt->fetch();                                                                // una sola fila con {c, p}
$totalCuestionarios = (int)$row['c'];                                                 // forzamos a entero
$promedio = round((float)($row['p'] ?? 0), 2);                                        // si no hay datos AVG es null → usamos 0; redondeamos a 2 decimales

// --- 2) Distribución por nivel (cuántas veces sacó bajo/medio/alto) ---
$distribucion = ['bajo' => 0, 'medio' => 0, 'alto' => 0];                             // arrancamos en 0 para que SIEMPRE existan las 3 llaves
$stmt = $pdo->prepare("SELECT nivel, COUNT(*) c FROM resultados WHERE usuario_id = ? GROUP BY nivel"); // agrupa por nivel y cuenta
$stmt->execute([$idUsuario]);                                                                          // cada fila tiene ['nivel' => 'bajo'|'medio'|'alto', 'c' => conteo]; pisamos el 0 inicial con el conteo real
foreach ($stmt->fetchAll() as $r) $distribucion[$r['nivel']] = (int)$r['c'];          // pisamos los 0s con los conteos reales

// --- 3) Por cuestionario: último porcentaje, último nivel, # de veces ---
// Las subqueries traen el ÚLTIMO resultado por cada cuestionario activo.
$stmt = $pdo->prepare(
    "SELECT c.slug, c.titulo, c.icono, c.color,
            (SELECT porcentaje FROM resultados r WHERE r.usuario_id = ? AND r.cuestionario_id = c.id ORDER BY r.creado_en DESC LIMIT 1) ultimo_porcentaje, -- subquery para traer el último porcentaje de ese cuestionario
            (SELECT nivel      FROM resultados r WHERE r.usuario_id = ? AND r.cuestionario_id = c.id ORDER BY r.creado_en DESC LIMIT 1) ultimo_nivel,       -- subquery para traer el último nivel de ese cuestionario
            (SELECT COUNT(*)   FROM resultados r WHERE r.usuario_id = ? AND r.cuestionario_id = c.id) veces -- subquery para contar cuántas veces ha hecho ese cuestionario
       FROM cuestionarios c WHERE c.activo = 1 ORDER BY c.id"
);
$stmt->execute([$idUsuario, $idUsuario, $idUsuario]);                                 // mandamos el id 3 veces (uno por cada subquery)
$porCuestionario = $stmt->fetchAll();                                                 // array con un objeto por cada cuestionario activo

// --- 4) Evolución diaria (últimos 30 días) para la gráfica de línea ---
// DATE(creado_en) agrupa por día sin hora; AVG saca el promedio de ese día.
// Filtramos a los últimos 30 días y ordenamos de viejo a nuevo (para la gráfica).
$stmt = $pdo->prepare(
    "SELECT DATE(creado_en) fecha, AVG(porcentaje) promedio
       FROM resultados
      WHERE usuario_id = ? AND creado_en >= (NOW() - INTERVAL 30 DAY)
   GROUP BY DATE(creado_en) ORDER BY fecha ASC"
);
$stmt->execute([$idUsuario]);
$evolucion = array_map(                                                               // limpiamos: cada fila se queda con fecha + promedio redondeado
    fn($r) => ['fecha' => $r['fecha'], 'promedio' => round((float)$r['promedio'], 2)],
    $stmt->fetchAll()
);

// --- 5) Actividad reciente: las 5 últimas filas del historial ---
// JOIN con cuestionarios para traer título/icono/color (que están en la otra tabla).
// ORDER BY creado_en DESC + LIMIT 5 → solo las 5 entradas más recientes.
$stmt = $pdo->prepare(
    "SELECT r.id, r.porcentaje, r.nivel, r.creado_en, c.titulo, c.slug, c.icono, c.color
       FROM resultados r JOIN cuestionarios c ON c.id = r.cuestionario_id
      WHERE r.usuario_id = ? ORDER BY r.creado_en DESC LIMIT 5"
);
$stmt->execute([$idUsuario]);
$reciente = $stmt->fetchAll();

// --- 6) IMC (peso/altura). Si faltan datos, devuelve [null, null]. ---
[$imc, $imcCat] = calcular_imc($user['peso'] ?? null, $user['altura'] ?? null);       // destructuring → $imc = número, $imcCat = "Saludable" etc.

// --- 7) Variables para el saludo y el encabezado ---
$firstName = explode(' ', $user['nombre'])[0];                                        // toma el primer nombre ("Juan Pérez" → "Juan")
$hora      = (int)date('H');                                                          // hora actual (0-23)
$saludo    = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches'); // saludo según hora
$tienePie  = array_sum($distribucion) > 0;                                            // ¿hay al menos 1 resultado? → si no, no pintamos la dona

$pageTitle  = 'Inicio';                                                               // título de la pestaña
$activeNav  = 'dashboard';                                                            // marca "Inicio" como activo en el menú
$needsChart = true;                                                                   // esta página sí necesita Chart.js (se carga desde header)
require __DIR__ . '/../includes/header.php';                                          // pinta <head>, sidebar y abre <main>
?>


<div class="space-y-12">
  <header class="relative bg-mesh rounded-[2rem] border border-black/[0.04] px-7 md:px-10 py-9 md:py-12 overflow-hidden animate-fade-up">
    <div class="relative flex flex-col md:flex-row md:items-end md:justify-between gap-5">
      <div>
        <div class="inline-flex items-center gap-2 text-[11px] uppercase tracking-[0.18em] text-sage-700 mb-2">
          <span class="w-1.5 h-1.5 rounded-full bg-sage-500"></span><?= e($saludo) ?>
        </div>
        <h1 class="font-display text-[2.6rem] md:text-5xl leading-[1.02] text-balance">
          <?= e($firstName) ?>,<br class="md:hidden">
          <span class="italic text-sage-700"> ¿cómo te sientes hoy?</span>
        </h1>
      </div>
      <a href="cuestionarios.php" class="btn btn-primary self-start md:self-end">
        Nuevo cuestionario <i data-lucide="arrow-right" style="width:16px;height:16px"></i>
      </a>
    </div>
  </header>

  <section class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    <?php
    $kpis = [
        ['sparkles',   'Cuestionarios', $totalCuestionarios,      'completados'],
        ['trending-up','Bienestar',     $promedio . '%',          'promedio'],
        ['heart',      'Nivel alto',    $distribucion['alto'],    'veces'],
        ['activity',   'IMC',           $imc ?? '—',              $imcCat ?? 'Sin datos'],
    ];
    foreach ($kpis as $i => [$icon, $label, $val, $suf]): ?>
      <div class="card relative overflow-hidden animate-fade-up" style="animation-delay:<?= $i * 50 ?>ms">
        <div class="flex items-center gap-2 text-mute mb-3">
          <span class="w-7 h-7 rounded-xl bg-sage-100 text-sage-700 flex items-center justify-center">
            <i data-lucide="<?= e($icon) ?>" style="width:16px;height:16px"></i>
          </span>
          <span class="text-[11px] uppercase tracking-[0.14em]"><?= e($label) ?></span>
        </div>
        <div class="font-display text-3xl tabular-nums leading-none"><?= e((string)$val) ?></div>
        <div class="text-xs text-mute mt-1.5"><?= e($suf) ?></div>
      </div>
    <?php endforeach; ?>
  </section>

  <section class="grid lg:grid-cols-3 gap-4 animate-fade-up" style="animation-delay:120ms">
    <div class="card lg:col-span-2">
      <div class="flex items-center justify-between mb-5">
        <div>
          <h3 class="font-display text-xl leading-none">Evolución</h3>
          <p class="text-xs text-mute mt-1">Promedio diario · últimos 30 días</p>
        </div>
        <span class="pill bg-sage-100 text-sage-700"><span class="w-1.5 h-1.5 rounded-full bg-sage-500"></span> Bienestar</span>
      </div>
      <div class="h-64">
        <?php if (count($evolucion) > 0): ?>
          <canvas id="chart-evolucion"></canvas>
        <?php else: ?>
          <div class="h-full flex items-center justify-center text-mute text-sm">Aún no hay registros este mes</div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="mb-4">
        <h3 class="font-display text-xl leading-none">Distribución</h3>
        <p class="text-xs text-mute mt-1">Por nivel de bienestar</p>
      </div>
      <div class="h-44">
        <?php if ($tienePie): ?>
          <canvas id="chart-distribucion"></canvas>
        <?php else: ?>
          <div class="h-full flex items-center justify-center text-mute text-sm">Aún sin datos</div>
        <?php endif; ?>
      </div>
      <div class="flex justify-center gap-4 text-xs mt-2">
        <?php
        $pieColors = ['bajo' => '#D89A82', 'medio' => '#E0B770', 'alto' => '#7AA079'];
        foreach ($distribucion as $nivel => $n): ?>
          <div class="flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full" style="background:<?= $pieColors[$nivel] ?>"></span>
            <span class="capitalize text-mute"><?= e($nivel) ?> <b class="text-ink ml-0.5"><?= (int)$n ?></b></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="animate-fade-up" style="animation-delay:180ms">
    <div class="flex items-end justify-between mb-5">
      <div>
        <h3 class="font-display text-2xl leading-none">Tus áreas de bienestar</h3>
        <p class="text-sm text-mute mt-1.5">Toca una para volverla a evaluar.</p>
      </div>
    </div>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <?php foreach ($porCuestionario as $i => $c):
        $sinDatos = $c['ultimo_porcentaje'] === null;
        $pct = $sinDatos ? 0 : (float)$c['ultimo_porcentaje'];
      ?>
        <a href="cuestionario.php?slug=<?= urlencode($c['slug']) ?>"
           class="card-hover group animate-fade-up relative overflow-hidden block"
           style="animation-delay:<?= $i * 50 ?>ms">
          <i data-lucide="arrow-up-right" style="width:16px;height:16px"
             class="absolute top-5 right-5 text-mute/40 transition-all group-hover:text-ink"></i>
          <div class="flex items-start justify-between mb-4">
            <div class="w-11 h-11 rounded-2xl flex items-center justify-center <?= color_classes($c['color']) ?>">
              <?= icono_cuestionario($c['icono']) ?>
            </div>
            <?= $c['ultimo_nivel'] ? nivel_badge($c['ultimo_nivel']) : '' ?>
          </div>
          <h4 class="font-display text-lg mb-1"><?= e($c['titulo']) ?></h4>
          <?php if ($sinDatos): ?>
            <p class="text-sm text-mute py-3">Aún no lo has hecho — comienza cuando quieras.</p>
          <?php else: ?>
            <div class="flex items-baseline gap-1.5 mb-3">
              <span class="font-display text-3xl tabular-nums"><?= (int)round($pct) ?></span>
              <span class="text-mute text-sm">%</span>
            </div>
            <div class="h-1.5 bg-black/5 rounded-full overflow-hidden">
              <div class="h-full bg-sage-500 rounded-full" style="width:<?= $pct ?>%"></div>
            </div>
            <p class="text-xs text-mute mt-3"><?= (int)$c['veces'] ?> <?= $c['veces']==1 ? 'realizado':'realizados' ?></p>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="animate-fade-up" style="animation-delay:240ms">
    <div class="flex items-center justify-between mb-5">
      <h3 class="font-display text-2xl leading-none">Actividad reciente</h3>
      <a href="historial.php" class="text-sm text-sage-700 hover:text-sage-900 inline-flex items-center gap-1">
        Ver todo <i data-lucide="arrow-right" style="width:14px;height:14px"></i>
      </a>
    </div>
    <?php if (count($reciente) === 0): ?>
      <div class="card text-center py-12">
        <div class="w-12 h-12 rounded-2xl bg-sage-100 text-sage-700 grid place-items-center mx-auto mb-3">
          <i data-lucide="leaf" style="width:20px;height:20px"></i>
        </div>
        <h4 class="font-display text-xl mb-1">Aún no hay actividad</h4>
        <p class="text-mute text-sm mb-5">Empieza con tu primer cuestionario y aquí verás tu progreso.</p>
        <a href="cuestionarios.php" class="btn btn-primary">Empezar ahora <i data-lucide="arrow-right" style="width:15px;height:15px"></i></a>
      </div>
    <?php else: ?>
      <ul class="card divide-y divide-black/5 p-0 overflow-hidden">
        <?php foreach ($reciente as $i => $r) echo item_resultado($r, ['animate' => true, 'delay' => $i * 40]); ?>
      </ul>
    <?php endif; ?>
  </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  <?php if (count($evolucion) > 0): ?>
  const evCtx = document.getElementById('chart-evolucion');
  const grad = evCtx.getContext('2d').createLinearGradient(0, 0, 0, 200);
  grad.addColorStop(0, 'rgba(122,160,121,0.35)');
  grad.addColorStop(1, 'rgba(122,160,121,0)');
  new Chart(evCtx, {
    type: 'line',
    data: {
      labels: <?= json_encode(array_column($evolucion, 'fecha')) ?>,
      datasets: [{
        data: <?= json_encode(array_column($evolucion, 'promedio')) ?>,
        borderColor: '#3F5F44', backgroundColor: grad, fill: true, tension: 0.35,
        pointBackgroundColor: '#7AA079', pointRadius: 3, pointHoverRadius: 6, borderWidth: 2.5
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        y: { min:0, max:100, ticks:{ color:'#6B7570', font:{ size:11 } }, grid:{ color:'rgba(31,36,33,0.06)' } },
        x: { ticks:{ color:'#6B7570', font:{ size:11 }, maxRotation:0 }, grid:{ display:false } }
      }
    }
  });
  <?php endif; ?>

  <?php if ($tienePie): ?>
  new Chart(document.getElementById('chart-distribucion'), {
    type: 'doughnut',
    data: {
      labels: <?= json_encode(array_keys($distribucion)) ?>,
      datasets: [{
        data: <?= json_encode(array_values($distribucion)) ?>,
        backgroundColor: ['#D89A82','#E0B770','#7AA079'],
        borderWidth: 0
      }]
    },
    options: { responsive:true, maintainAspectRatio:false, cutout:'60%', plugins:{ legend:{ display:false } } }
  });
  <?php endif; ?>
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
