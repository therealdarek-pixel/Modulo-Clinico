<?php
/**
 * public/cuestionario.php
 * -----------------------
 * Página del cuestionario en sí (un wizard pregunta-a-pregunta).
 *
 * GET  → Muestra el formulario con las preguntas y sus opciones.
 * POST → Procesa todas las respuestas, calcula puntaje/nivel y guarda en BD.
 *
 * Tablas que toca:
 *   cuestionarios  → meta del cuestionario (titulo, color, icono…)
 *   preguntas      → preguntas de ese cuestionario
 *   opciones       → opciones de cada pregunta (con su valor 1-5)
 *   resultados     → fila resumen del intento (puntaje, %, nivel)
 *   respuestas     → fila por cada pregunta respondida (detalle)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_login();

$idUsuario = current_user_id();
$slug = $_GET['slug'] ?? '';

// --- 1) Si no hay slug, volvemos a la lista de cuestionarios ---
if (!$slug) {
    header('Location: cuestionarios.php');
    exit;
}

// --- 2) Buscar el cuestionario por slug (si no existe o está inactivo → 404) ---
$pdo = db();
$stmt = $pdo->prepare("SELECT * FROM cuestionarios WHERE slug = ? AND activo = 1"); // el slug es único, así que esperamos máximo 1 resultado
$stmt->execute([$slug]);                                                            // Si no encontramos el cuestionario, mostramos un error 404.
$cuestionario = $stmt->fetch(); // $cuestionario tendrá los campos: id, slug, titulo, descripcion, color, icono, activo
if (!$cuestionario) {  
    http_response_code(404);
    echo 'Cuestionario no encontrado';
    exit;
}

// --- 3) Cargar preguntas y, para cada una, sus opciones ---

// 3.a) Traer todas las PREGUNTAS de este cuestionario en orden.
$stmt = $pdo->prepare("SELECT * FROM preguntas WHERE cuestionario_id = ? ORDER BY orden ASC"); // ? = id del cuestionario (evita inyección SQL)
$stmt->execute([$cuestionario['id']]);                                                          // ejecuta con el id real
$preguntas = $stmt->fetchAll();                                                                 // array con todas las preguntas

// 3.b) Para CADA pregunta, traer sus opciones y guardarlas dentro de la misma pregunta.
//      Así en el HTML basta con escribir $p['opciones'] para pintar los radios.
foreach ($preguntas as &$p) {                                                                   // & = referencia → poder modificar $p dentro del array
    $st = $pdo->prepare("SELECT id, texto, valor FROM opciones WHERE pregunta_id = ? ORDER BY orden ASC"); // opciones de esta pregunta
    $st->execute([$p['id']]);                                                                   // ejecuta con el id de la pregunta actual
    $p['opciones'] = $st->fetchAll();                                                           // guardamos el array de opciones dentro de la pregunta
}
unset($p);                                                                                       // soltar la referencia para evitar bugs después

$error = null;

//   ┌─ CÁLCULOS QUE HACE ESTE ARCHIVO ─────────────────────────────────────────────┐
//   │ ESCALA DE OPCIONES                                                           │
//   │   cada opción vale 1..5, donde 5 = mejor (más bienestar)                     │
//   │                                                                              │
//   │ FÓRMULAS (cuando el usuario envía el cuestionario por POST)                  │
//   │   puntajeTotal = suma de los valores 1..5 de las opciones elegidas           │
//   │   puntajeMax   = (número de preguntas) × 5                                   │
//   │   porcentaje   = (puntajeTotal / puntajeMax) × 100   (redondeado a 2 dec.)   │
//   │                                                                              │
//   │ NIVEL (a partir del porcentaje, escala donde MÁS ALTO = MEJOR)               │
//   │   porcentaje ≥ 75 → alto   (verde,  buen bienestar)                          │
//   │   porcentaje ≥ 45 → medio  (ámbar,  bienestar regular)                       │
//   │   porcentaje < 45 → bajo   (coral,  necesita atención)                       │
//   │                                                                              │
//   │ EJEMPLO con 7 preguntas y 23 puntos sumados                                  │
//   │   puntajeMax = 7 × 5 = 35                                                    │
//   │   porcentaje = 23/35 × 100 = 65.71  →  nivel "medio"                         │
//   └──────────────────────────────────────────────────────────────────────────────┘

// --- 4) Procesar el POST cuando el usuario envía sus respuestas ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resp = $_POST['respuestas'] ?? [];

    // Validación rápida: deben llegar tantas respuestas como preguntas.
    if (!is_array($resp) || count($resp) !== count($preguntas)) {
        $error = 'Responde todas las preguntas antes de continuar.';
    } else {
        try {
            // Transacción: o se guarda todo (resultado + respuestas), o nada.
            $pdo->beginTransaction();

            $puntajeTotal = 0;
            $detalles = [];

            // Recorremos pregunta por pregunta y buscamos la opción elegida dentro de sus opciones.
            foreach ($preguntas as $p) {
                $opcionId = (int)($resp[$p['id']] ?? 0);                // id de la opción que marcó el usuario
                if (!$opcionId) throw new Exception('Falta una respuesta');

                // Buscamos esa opción entre las opciones válidas de esta pregunta.
                $valor = null;
                foreach ($p['opciones'] as $o) {
                    if ((int)$o['id'] === $opcionId) { $valor = (int)$o['valor']; break; }
                }
                if ($valor === null) throw new Exception('Opción inválida'); // no pertenece a esta pregunta

                $puntajeTotal += $valor;                                // sumamos el valor (1-5) al puntaje total
                $detalles[] = ['pregunta_id' => $p['id'], 'opcion_id' => $opcionId, 'valor' => $valor];
            }

            $puntajeMax = count($detalles) * 5; // sirve para calcular el porcentaje y nivel general del resultado

            //FORMULA PARA CALCULAR EL PORCENTAJE Y NIVEL:
            $porcentaje = round(($puntajeTotal / $puntajeMax) * 100, 2); //sirve para mostrar el porcentaje obtenido y también para determinar el nivel (bajo, medio, alto) según rangos predefinidos
            $nivel = $porcentaje >= 75 ? 'alto' : ($porcentaje >= 45 ? 'medio' : 'bajo'); // sirve para categorizar el resultado general del cuestionario en niveles de bienestar, energía, etc., según el porcentaje obtenido

            $ins = $pdo->prepare(
                "INSERT INTO resultados (usuario_id, cuestionario_id, puntaje_total, puntaje_max, porcentaje, nivel) -- guardamos el resultado general del cuestionario (una fila resumen con el puntaje total, porcentaje y nivel)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $ins->execute([$idUsuario, $cuestionario['id'], $puntajeTotal, $puntajeMax, $porcentaje, $nivel]); 
            $idResultado = (int)$pdo->lastInsertId(); 

            // Guardamos el detalle: una fila por pregunta respondida.
            $insR = $pdo->prepare(
                "INSERT INTO respuestas (resultado_id, pregunta_id, opcion_id, valor) VALUES (?, ?, ?, ?)" // cada fila de esta tabla representa la respuesta a una pregunta específica dentro de un intento de cuestionario, con su valor asociado (1-5)
            );
            foreach ($detalles as $d) {
                $insR->execute([$idResultado, $d['pregunta_id'], $d['opcion_id'], $d['valor']]);
            }
            $pdo->commit();

            // Mandamos al usuario directo a su página de resultado.
            header('Location: resultado.php?id=' . $idResultado); 
            exit;
        } catch (Exception $e) {
            // Si algo falla, deshacemos la transacción y mostramos el error.
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'No se pudo guardar: ' . $e->getMessage();
        }
    }
}

// --- 5) Render del wizard (el JS al final navega entre pasos) ---
$pageTitle = $cuestionario['titulo'];
$activeNav = 'cuestionarios';
require __DIR__ . '/../includes/header.php';
?>
<div class="max-w-3xl mx-auto space-y-8 animate-fade-up">
  <a href="cuestionarios.php" class="inline-flex items-center gap-1.5 text-sm text-mute hover:text-ink transition-colors">
    <i data-lucide="arrow-left" style="width:15px;height:15px"></i> Cuestionarios
  </a>

  <header class="flex items-start gap-4">
    <div class="w-12 h-12 rounded-2xl flex items-center justify-center shrink-0 <?= color_classes($cuestionario['color']) ?>">
      <?= icono_cuestionario($cuestionario['icono']) ?>
    </div>
    <div>
      <p class="text-mute text-[11px] uppercase tracking-[0.16em]">Cuestionario</p>
      <h1 class="font-display text-4xl mt-1 leading-tight text-balance"><?= e($cuestionario['titulo']) ?></h1>
      <p class="text-mute mt-1 max-w-xl"><?= e($cuestionario['descripcion']) ?></p>
    </div>
  </header>

  <?php if ($error) echo flash_err($error); ?>

  <?php $total = count($preguntas); ?>

  <!-- Barra de progreso -->
  <div class="space-y-2">
    <div class="flex items-center justify-between text-xs text-mute">
      <span id="wiz-step-label">Pregunta 1 de <?= $total ?></span>
      <span id="wiz-step-pct" class="tabular-nums">0%</span>
    </div>
    <div class="h-1.5 bg-black/[0.06] rounded-full overflow-hidden">
      <div id="wiz-progress" class="h-full bg-sage-500 rounded-full transition-all duration-300" style="width:0%"></div>
    </div>
  </div>

  <form id="wiz-form" method="POST" action="cuestionario.php?slug=<?= urlencode($slug) ?>" class="space-y-6">
    <?php foreach ($preguntas as $idx => $p): ?>
      <div class="wiz-step card <?= $idx === 0 ? 'animate-fade-up' : 'hidden' ?>" data-step="<?= $idx ?>">
        <div class="flex items-baseline gap-3 mb-4">
          <span class="font-display text-sm text-sage-700 tabular-nums"><?= $idx + 1 ?>/<?= $total ?></span>
          <h3 class="font-display text-xl text-balance leading-snug"><?= e($p['texto']) ?></h3>
        </div>
        <div class="space-y-2">
          <?php foreach ($p['opciones'] as $o):
            $checked = isset($_POST['respuestas'][$p['id']]) && (int)$_POST['respuestas'][$p['id']] === (int)$o['id'];
          ?>
            <label class="option-tile flex items-center gap-3" data-selected="<?= $checked ? 'true' : 'false' ?>">
              <input type="radio" name="respuestas[<?= (int)$p['id'] ?>]" value="<?= (int)$o['id'] ?>"
                     class="sr-only peer" required <?= $checked ? 'checked' : '' ?>>
              <span class="flex-1"><?= e($o['texto']) ?></span>
              <span class="text-xs text-mute tabular-nums"><?= (int)$o['valor'] ?>/5</span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <div class="flex flex-wrap gap-3 pt-2">
      <button type="button" id="wiz-prev" class="btn btn-ghost" disabled>
        <i data-lucide="arrow-left" style="width:16px;height:16px"></i> Anterior
      </button>
      <button type="button" id="wiz-next" class="btn btn-primary" disabled>
        Siguiente <i data-lucide="arrow-right" style="width:16px;height:16px"></i>
      </button>
      <button type="submit" id="wiz-submit" class="btn btn-primary hidden" disabled>
        Enviar respuestas <i data-lucide="check" style="width:16px;height:16px"></i>
      </button>
      <a href="cuestionarios.php" class="btn btn-ghost">Cancelar</a>
    </div>
  </form>
</div>

<script>
(function(){
  const steps   = Array.from(document.querySelectorAll('.wiz-step'));
  const total   = steps.length;
  const prevBtn = document.getElementById('wiz-prev');
  const nextBtn = document.getElementById('wiz-next');
  const subBtn  = document.getElementById('wiz-submit');
  const label   = document.getElementById('wiz-step-label');
  const pct     = document.getElementById('wiz-step-pct');
  const bar     = document.getElementById('wiz-progress');
  let current = 0;

  function answered(i){
    return !!steps[i].querySelector('input[type="radio"]:checked');
  }
  function render(){
    steps.forEach((s, i) => {
      s.classList.toggle('hidden', i !== current);
      if (i === current) {
        s.classList.remove('animate-fade-up');
        // re-trigger animation
        void s.offsetWidth;
        s.classList.add('animate-fade-up');
      }
    });
    label.textContent = 'Pregunta ' + (current + 1) + ' de ' + total;
    const progress = Math.round(((current) / total) * 100);
    pct.textContent = progress + '%';
    bar.style.width = progress + '%';

    prevBtn.disabled = current === 0;
    const isLast = current === total - 1;
    nextBtn.classList.toggle('hidden', isLast);
    subBtn.classList.toggle('hidden', !isLast);
    nextBtn.disabled = !answered(current);
    subBtn.disabled  = !answered(current);

    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  // Selección de opción: marca el tile, habilita botones y avanza automáticamente
  document.querySelectorAll('.wiz-step').forEach(step => {
    step.addEventListener('change', (e) => {
      if (e.target.matches('input[type="radio"]')) {
        step.querySelectorAll('.option-tile').forEach(el => el.dataset.selected = 'false');
        e.target.closest('.option-tile').dataset.selected = 'true';
        nextBtn.disabled = false;
        subBtn.disabled  = false;
        // Avance automático: si no es la última pregunta, pasa a la siguiente
        if (current < total - 1) {
          setTimeout(() => { current++; render(); }, 280);
        }
      }
    });
  });

  nextBtn.addEventListener('click', () => {
    if (!answered(current)) return;
    if (current < total - 1) { current++; render(); }
  });
  prevBtn.addEventListener('click', () => {
    if (current > 0) { current--; render(); }
  });

  // Si el POST anterior trajo respuestas marcadas, avanzar al primer paso sin responder
  for (let i = 0; i < total; i++) {
    if (!answered(i)) { current = i; break; }
    if (i === total - 1) current = i;
  }
  render();
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
