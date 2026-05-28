<?php
/**
 * public/evaluacion.php
 * ---------------------
 * Página "Evaluación Preventiva" (frontend).
 *  - GET            → muestra la evaluación más reciente del usuario (o ?id=N para una específica).
 *  - POST __form=nueva → recalcula con los últimos resultados, guarda y redirige (patrón PRG).
 * Esta evaluación es ORIENTATIVA, nunca un diagnóstico médico.
 */
require_once __DIR__ . '/../includes/auth.php';                          // sesión + require_login()
require_once __DIR__ . '/../includes/helpers.php';                       // e(), flash_ok(), formato_fecha(), etc.
require_once __DIR__ . '/../includes/evaluacion_backend.php';            // lógica de la evaluación (cálculo + BD)
require_login();                                                         // si no hay sesión → redirige a login

$idUsuario = current_user_id();                                          // id del usuario logueado
$pdo       = db();                                                       // conexión PDO singleton

// ----------------------------------------------------------------
// POST: el usuario apretó "Generar nueva evaluación"
// ----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['__form']) && $_POST['__form'] === 'nueva') {
    $evaluacion = calcularEvaluacion($pdo, $idUsuario);                  // 1) calcula con los últimos datos
    $nuevoId    = guardarEvaluacion($pdo, $idUsuario, $evaluacion);      // 2) guarda en BD y nos da el id nuevo
    header('Location: evaluacion.php?id=' . $nuevoId . '&ok=1');         // 3) redirige a GET (PRG: evita reenvío al recargar)
    exit;                                                                // 4) corta el script aquí, ya redirigimos
}

// ----------------------------------------------------------------
// GET: decidir qué evaluación cargar para mostrar
// ----------------------------------------------------------------
$verId = null;                                                           // por defecto: no pidió un id específico
if (isset($_GET['id'])) {                                                // ?id=N si el usuario eligió una del historial
    $verId = (int)$_GET['id'];                                           // (int) por seguridad: garantiza que sea número
}
$ok = isset($_GET['ok']);                                                // ?ok=1 → mostramos mensaje verde de éxito

// Buscamos la evaluación a mostrar: la pedida por id, o la más reciente.
if ($verId) {                                                            // rama 1: pidió ver una específica
    $sql = "SELECT id, puntaje, nivel, creado_en
              FROM evaluaciones_preventivas
             WHERE usuario_id = ? AND id = ?";                           // limita por id Y por usuario (seguridad)
    $stmt = $pdo->prepare($sql);                                         // preparamos la query
    $stmt->execute([$idUsuario, $verId]);                                // ejecutamos con los dos parámetros
} else {                                                                 // rama 2: no pidió nada → mostramos la última
    $sql = "SELECT id, puntaje, nivel, creado_en
              FROM evaluaciones_preventivas
             WHERE usuario_id = ?
             ORDER BY creado_en DESC LIMIT 1";                           // la más reciente del usuario
    $stmt = $pdo->prepare($sql);                                         // preparamos la query
    $stmt->execute([$idUsuario]);                                        // ejecutamos solo con el id del usuario
}
$registro = $stmt->fetch();                                              // arreglo con los datos, o false si no hay

// Si encontramos un registro guardado lo usamos; si no, calculamos uno en vivo
// para que la página tenga algo que mostrar antes de la primera evaluación.
if ($registro) {                                                         // CASO A: hay evaluación guardada en BD
    $puntaje            = (int)$registro['puntaje'];                     // puntaje 0-100 ya calculado
    $nivel              = $registro['nivel'];                            // bajo / moderado / elevado
    $evaluacionIdActual = (int)$registro['id'];                          // id de la evaluación → para resaltar fila en el historial
    $factores           = cargarFactoresEvaluacion($pdo, $evaluacionIdActual); // factores + detalles desde tablas relacionales
    $creadoEn           = $registro['creado_en'];                        // fecha de cuando se guardó

    // Solo necesitamos los faltantes para el aviso amarillo (los % no se usan acá).
    $datos     = obtenerUltimosPorcentajes($pdo, $idUsuario);            // releemos los slugs sin datos
    $faltantes = $datos['faltantes'];                                    // lista de cuestionarios sin contestar
} else {                                                                 // CASO B: no hay nada guardado → calculamos en vivo
    $evaluacion         = calcularEvaluacion($pdo, $idUsuario);          // corre las reglas con los datos actuales
    $puntaje            = $evaluacion['puntaje'];                        // mismo puntaje pero sin pasar por BD
    $nivel              = $evaluacion['nivel'];                          // mismo nivel
    $factores           = $evaluacion['factores'];                       // factores detectados en memoria
    $faltantes          = $evaluacion['cuestionarios_faltantes'];        // cuestionarios sin datos
    $creadoEn           = null;                                          // no hay fecha porque no está guardado
    $evaluacionIdActual = null;                                          // no hay id porque no está en BD
}

$analizados = count(EVAL_SLUGS) - count($faltantes);                     // cuántos de los 7 sí tienen datos

// Historial: las últimas 5 evaluaciones guardadas del usuario.
$stmt = $pdo->prepare(
    "SELECT id, puntaje, nivel, creado_en
       FROM evaluaciones_preventivas
      WHERE usuario_id = ?
      ORDER BY creado_en DESC LIMIT 5"
);
$stmt->execute([$idUsuario]);                                            // pasamos el id del usuario
$historial = $stmt->fetchAll();                                          // trae todas las filas como arreglo

$pageTitle = 'Evaluación Preventiva';                                    // título de la pestaña del navegador
$activeNav = 'evaluacion';                                               // marca el item activo en el menú lateral
require __DIR__ . '/../includes/header.php';                             // pinta <head>, sidebar y abre <main>
?>
<div class="space-y-8">                                                  <!-- contenedor principal con separación vertical -->
  <!-- ENCABEZADO: título de la página + botón para generar nueva evaluación -->
  <header class="flex flex-wrap items-end justify-between gap-4">
    <div>
      <p class="text-mute text-[11px] uppercase tracking-[0.18em]">Evaluación Preventiva</p>
      <h1 class="font-display text-4xl md:text-5xl mt-2 leading-[1.05] text-balance">
        Análisis <span class="italic text-sage-700">integral</span> de tus cuestionarios.
      </h1>
      <p class="text-mute mt-3 max-w-xl leading-relaxed">
        Cruzamos el último resultado de cada cuestionario para detectar posibles factores de riesgo.
      </p>
    </div>
    <form method="POST" action="evaluacion.php">                         <!-- formulario que dispara una nueva evaluación -->
      <input type="hidden" name="__form" value="nueva">                  <!-- bandera que el PHP de arriba revisa -->
      <button type="submit" class="btn btn-primary">
        <i data-lucide="refresh-cw" style="width:16px;height:16px"></i>
        Generar nueva evaluación
      </button>
    </form>
  </header>

  <?php if ($ok) echo flash_ok('Evaluación generada y guardada.'); ?>    <!-- mensaje verde tras crear una nueva (?ok=1 en la URL) -->

  <?php if (!empty($faltantes)): // AVISO AMARILLO: solo si faltan cuestionarios ?>
    <div class="card border-l-4 border-amber-400 animate-fade-up">       <!-- card con borde ámbar -->
      <div class="flex items-start gap-3">
        <span class="w-9 h-9 rounded-2xl bg-amber-100 text-amber-700 grid place-items-center shrink-0">
          <i data-lucide="alert-circle" style="width:16px;height:16px"></i>
        </span>
        <div class="flex-1">
          <h3 class="font-display text-xl">Faltan cuestionarios</h3>
          <p class="text-mute text-sm mt-1">
            La evaluación es más precisa con los 7 cuestionarios. Te faltan
            <?= count($faltantes) ?> de <?= count(EVAL_SLUGS) ?>:        <!-- cuántos faltan / total -->
          </p>
          <ul class="mt-3 flex flex-wrap gap-2">
            <?php foreach ($faltantes as $slug): // un chip por cada cuestionario faltante ?>
              <li>
                <a href="cuestionario.php?slug=<?= urlencode($slug) ?>"  <!-- link directo a contestarlo -->
                   class="chip inline-flex items-center gap-1.5">
                  <?= e(tituloCuestionario($slug)) ?>                    <!-- nombre bonito del cuestionario -->
                  <i data-lucide="arrow-right" style="width:12px;height:12px"></i>
                </a>
              </li>
            <?php endforeach; // fin loop de faltantes ?>
          </ul>
        </div>
      </div>
    </div>
  <?php endif; // fin aviso amarillo ?>

  <!-- CARD DE RESUMEN: puntaje grande + badge de nivel + cuántos cuestionarios entraron -->
  <section class="card animate-fade-up">
    <div class="flex flex-wrap items-start justify-between gap-6">
      <div>
        <p class="text-[11px] uppercase tracking-[0.14em] text-mute">Puntaje de riesgo</p>
        <div class="flex items-baseline gap-2 mt-2">
          <span class="font-display text-6xl tabular-nums leading-none"><?= (int)$puntaje ?></span> <!-- número grande 0-100 -->
          <span class="text-mute text-lg">/100</span>
        </div>
        <div class="mt-3 flex items-center gap-3">
          <span class="pill <?= badgeNivelEval($nivel) ?>">              <!-- badge con color según nivel -->
            <span class="w-1.5 h-1.5 rounded-full bg-current opacity-70"></span>
            Nivel <?= e(ucfirst($nivel)) ?>                              <!-- "Bajo" / "Moderado" / "Elevado" -->
          </span>
          <?php if ($creadoEn): // si la evaluación está guardada, muestra su fecha ?>
            <span class="text-xs text-mute"><?= e(formato_fecha($creadoEn)) ?></span>
          <?php else: // si es cálculo en vivo, lo aclaramos ?>
            <span class="text-xs text-mute italic">Vista previa (sin guardar)</span>
          <?php endif; ?>
        </div>
      </div>

      <div class="text-right">
        <p class="text-[11px] uppercase tracking-[0.14em] text-mute">Cuestionarios analizados</p>
        <div class="font-display text-4xl mt-2 tabular-nums">
          <?= (int)$analizados ?>                                        <!-- ej. 5 -->
          <span class="text-mute text-2xl">/ <?= count(EVAL_SLUGS) ?></span> <!-- / 7 -->
        </div>
      </div>
    </div>
    <p class="text-mute mt-6 leading-relaxed max-w-2xl"><?= e(textoNivelEval($nivel)) ?></p> <!-- texto interpretativo del nivel -->
  </section>

  <!-- LISTA DE FACTORES: si está vacía, card verde de felicitación. Si no, una card por factor. -->
  <?php if (empty($factores)): // caso 1: cero factores detectados ?>
    <section class="card border-l-4 border-sage-500 animate-fade-up" style="animation-delay:60ms">
      <div class="flex items-start gap-3">
        <span class="w-9 h-9 rounded-2xl bg-sage-100 text-sage-700 grid place-items-center shrink-0">
          <i data-lucide="check-circle-2" style="width:16px;height:16px"></i>
        </span>
        <div>
          <h3 class="font-display text-xl">Sin factores de riesgo detectados</h3>
          <p class="text-mute text-sm mt-1">
            Tus hábitos y antecedentes no presentan indicadores de riesgo en este momento. ¡Sigue así!
          </p>
        </div>
      </div>
    </section>
  <?php else: // caso 2: hay 1 o más factores ?>
    <section class="space-y-4">
      <h2 class="font-display text-2xl">Factores detectados (<?= count($factores) ?>)</h2> <!-- ej. "Factores detectados (3)" -->
      <?php foreach ($factores as $i => $factor): // una card por factor; $i para escalonar la animación ?>
        <article class="card <?= bordeFactor((int)$factor['puntaje']) ?> animate-fade-up"
                 style="animation-delay:<?= 60 + $i * 40 ?>ms">         <!-- delay creciente para efecto cascada -->
          <!-- cabecera del factor: ícono + título + puntos -->
          <div class="flex items-start justify-between gap-4 mb-4">
            <div class="flex items-start gap-3">
              <span class="w-9 h-9 rounded-2xl bg-clay-50 text-clay-600 grid place-items-center shrink-0">
                <i data-lucide="alert-triangle" style="width:16px;height:16px"></i>
              </span>
              <h3 class="font-display text-xl leading-snug"><?= e($factor['titulo']) ?></h3> <!-- ej. "Posible riesgo de Diabetes tipo 2" -->
            </div>
            <span class="text-xs text-mute whitespace-nowrap">+<?= (int)$factor['puntaje'] ?> pts</span> <!-- puntos que aporta -->
          </div>

          <!-- 3 secciones por factor: por qué se detectó, qué significa, qué hacer -->
          <div class="space-y-4 text-sm leading-relaxed">
            <div>
              <p class="text-[11px] uppercase tracking-[0.14em] text-mute mb-2">Se detectó porque:</p>
              <ul class="list-disc pl-5 space-y-1 text-ink/80">
                <?php foreach ($factor['detectado_por'] as $razon): // razones específicas por las que el factor se activó ?>
                  <li><?= e($razon) ?></li>                              <!-- una razón por bullet -->
                <?php endforeach; ?>
              </ul>
            </div>
            <div>
              <p class="text-[11px] uppercase tracking-[0.14em] text-mute mb-2">Qué significa:</p>
              <p class="text-ink/80"><?= e($factor['que_significa']) ?></p> <!-- explicación clínica del factor -->
            </div>
            <div>
              <p class="text-[11px] uppercase tracking-[0.14em] text-mute mb-2">Recomendaciones:</p>
              <ul class="list-disc pl-5 space-y-1 text-ink/80">
                <?php foreach ($factor['recomendaciones'] as $recomendacion): // bullets de qué hacer al respecto ?>
                  <li><?= e($recomendacion) ?></li>                      <!-- una recomendación por bullet -->
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
        </article>
      <?php endforeach; // fin loop de factores ?>
    </section>
  <?php endif; // fin if hay/no hay factores ?>

  <!-- DISCLAIMER FIJO: SIEMPRE visible, recordando que esto NO es diagnóstico médico -->
  <section class="card bg-bone border border-clay-200/40 animate-fade-up" style="animation-delay:120ms">
    <div class="flex items-start gap-3">
      <span class="w-9 h-9 rounded-2xl bg-clay-50 text-clay-600 grid place-items-center shrink-0">
        <i data-lucide="info" style="width:16px;height:16px"></i>
      </span>
      <div class="text-sm text-ink/80 leading-relaxed space-y-2">
        <p class="font-display text-base text-ink">Aviso importante</p>
        <p>
          Esta evaluación es <strong>orientativa</strong> y <strong>NO constituye un diagnóstico médico</strong>.
          Los factores detectados son indicadores preventivos basados en tus hábitos y antecedentes
          reportados en los cuestionarios.
        </p>
        <p>
          Consulta a un médico para una evaluación clínica real. Los resultados no deben usarse para
          automedicarse ni para retrasar la atención médica profesional.
        </p>
      </div>
    </div>
  </section>

  <!-- HISTORIAL: últimas 5 evaluaciones guardadas, clickeables para ver el detalle. Solo se muestra si hay alguna. -->
  <?php if (!empty($historial)): ?>
    <section class="animate-fade-up" style="animation-delay:180ms">
      <div class="flex items-center gap-2 mb-5">
        <span class="w-8 h-8 rounded-2xl bg-sage-100 text-sage-700 grid place-items-center">
          <i data-lucide="history" style="width:15px;height:15px"></i>
        </span>
        <h3 class="font-display text-2xl">Historial de evaluaciones</h3>
      </div>

      <ul class="card divide-y divide-black/5 p-0 overflow-hidden">
        <?php foreach ($historial as $item): // una fila por evaluación guardada ?>
          <?php $esLaActual = ($evaluacionIdActual === (int)$item['id']); ?> <!-- true si es la que se está mostrando arriba -->
          <li>
            <a href="evaluacion.php?id=<?= (int)$item['id'] ?>"          <!-- click → recarga la página con esa evaluación -->
               class="flex items-center gap-4 p-4 hover:bg-black/[0.025] transition-colors <?php if ($esLaActual) echo 'bg-sage-50/60'; ?>"> <!-- resalta si es la actual -->
              <div class="w-10 h-10 rounded-2xl bg-sage-100 text-sage-700 grid place-items-center">
                <i data-lucide="shield-check" style="width:18px;height:18px"></i>
              </div>
              <div class="flex-1 min-w-0">
                <div class="font-medium truncate">Evaluación preventiva</div>
                <div class="text-xs text-mute"><?= e(formato_fecha($item['creado_en'])) ?></div> <!-- fecha de esa evaluación -->
              </div>
              <div class="font-display text-xl tabular-nums"><?= (int)$item['puntaje'] ?>/100</div> <!-- puntaje de esa evaluación -->
              <span class="pill <?= badgeNivelEval($item['nivel']) ?>">  <!-- badge color por nivel -->
                <span class="w-1.5 h-1.5 rounded-full bg-current opacity-70"></span>
                <?= e(ucfirst($item['nivel'])) ?>                        <!-- "Bajo" / "Moderado" / "Elevado" -->
              </span>
              <i data-lucide="arrow-right" style="width:14px;height:14px" class="text-mute/40"></i>
            </a>
          </li>
        <?php endforeach; // fin loop historial ?>
      </ul>
    </section>
  <?php endif; // fin if hay historial ?>
</div>
<?php require __DIR__ . '/../includes/footer.php';                       // cierra </main>, carga iconos Lucide ?>
