<?php
/**
 * public/ficha.php
 * ----------------
 * Una pantalla con TODA la información del usuario:
 *   1. Datos personales (nombre, edad, sexo, peso, altura) ← editables
 *   2. Ficha clínica (sangre, alergias, medicamentos, contacto, notas) ← editables
 *   3. Resumen automático (total de cuestionarios, promedio, IMC, último por área)
 *   4. Historial reciente (las 6 últimas entradas)
 *
 * La página maneja DOS formularios POST distintos, identificados por un campo oculto:
 *   __form=clinica → guarda la ficha clínica (tabla `ficha`)
 *   (sin __form)   → guarda datos personales (tabla `usuarios`)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_login();

$idUsuario = current_user_id();
$pdo = db();

// Flags de éxito/error para cada formulario (se usan al pintar los alerts).
$ok = false;            $error = null;
$okClinica = false;     $errorClinica = null;

$sangresValidas = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];

// ============================================================
// FORM A — Ficha clínica  (INSERT ... ON DUPLICATE KEY UPDATE)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['__form'] ?? '') === 'clinica') {
    $sangre       = $_POST['sangre']       ?? '';
    $alergias     = trim($_POST['alergias']     ?? '');
    $medicamentos = trim($_POST['medicamentos'] ?? '');
    $contacto     = trim($_POST['contacto']     ?? '');
    $notas        = trim($_POST['notas']        ?? '');

    if ($sangre !== '' && !in_array($sangre, $sangresValidas, true)) {
        $errorClinica = 'Tipo de sangre inválido';
    } elseif (mb_strlen($contacto) > 200) {
        $errorClinica = 'El contacto es demasiado largo (máx. 200 caracteres)';
    } else {
        $up = $pdo->prepare(
            "INSERT INTO ficha (usuario_id, sangre, alergias, medicamentos, contacto, notas)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               sangre = VALUES(sangre),
               alergias = VALUES(alergias),
               medicamentos = VALUES(medicamentos),
               contacto = VALUES(contacto),
               notas = VALUES(notas)"
        );
        $up->execute([$idUsuario, nn($sangre), nn($alergias), nn($medicamentos), nn($contacto), nn($notas)]);
        $okClinica = true;
    }
}
// ============================================================
// FORM B — Datos personales (UPDATE en tabla `usuarios`)
// ============================================================
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $edad   = $_POST['edad']   !== '' ? (int)$_POST['edad']     : null;
    $sexo   = nn($_POST['sexo'] ?? '');
    $peso   = $_POST['peso']   !== '' ? (float)$_POST['peso']   : null;
    $altura = $_POST['altura'] !== '' ? (float)$_POST['altura'] : null;

    if (!$nombre) {
        $error = 'El nombre es obligatorio';
    } elseif ($sexo !== null && !in_array($sexo, ['M','F','Otro'], true)) {
        $error = 'Sexo inválido';
    } else {
        $upd = $pdo->prepare(
            "UPDATE usuarios SET nombre = ?, edad = ?, sexo = ?, peso = ?, altura = ? WHERE id = ?"
        );
        $upd->execute([$nombre, $edad, $sexo, $peso, $altura, $idUsuario]);
        $ok = true;
    }
}

// ============================================================
// LECTURA: cargamos todo lo que necesita la vista
// ============================================================

// Datos personales actualizados (después del posible UPDATE).
$stmt = $pdo->prepare(
    "SELECT id, nombre, email, edad, sexo, peso, altura, creado_en FROM usuarios WHERE id = ?"
);
$stmt->execute([$idUsuario]);
$user = $stmt->fetch();

// Resumen general: total cuestionarios + promedio.
$stmt = $pdo->prepare("SELECT COUNT(*) c, AVG(porcentaje) p FROM resultados WHERE usuario_id = ?");
$stmt->execute([$idUsuario]);
$resumen = $stmt->fetch();
$totalResultados = (int)$resumen['c'];
$promedio = round((float)($resumen['p'] ?? 0), 2);

// Último resultado por cada cuestionario activo (puede ser null si nunca lo hizo).
$stmt = $pdo->prepare(
    "SELECT c.titulo, c.slug, c.icono, c.color,
            (SELECT porcentaje FROM resultados r WHERE r.usuario_id = ? AND r.cuestionario_id = c.id ORDER BY r.creado_en DESC LIMIT 1) ultimo_porcentaje,
            (SELECT nivel      FROM resultados r WHERE r.usuario_id = ? AND r.cuestionario_id = c.id ORDER BY r.creado_en DESC LIMIT 1) ultimo_nivel
       FROM cuestionarios c WHERE c.activo = 1 ORDER BY c.id"
);
$stmt->execute([$idUsuario, $idUsuario]);
$resumenPorCuestionario = $stmt->fetchAll();

// Últimas 6 actividades.
$stmt = $pdo->prepare(
    "SELECT r.id, r.porcentaje, r.nivel, r.creado_en, c.titulo, c.slug, c.icono, c.color
       FROM resultados r JOIN cuestionarios c ON c.id = r.cuestionario_id
      WHERE r.usuario_id = ? ORDER BY r.creado_en DESC LIMIT 6"
);
$stmt->execute([$idUsuario]);
$historialReciente = $stmt->fetchAll();

// IMC (puede salir null si no hay peso/altura).
[$imc, $imcCat] = calcular_imc($user['peso'], $user['altura']);

// Ficha clínica del usuario (puede no existir todavía → null).
$stmt = $pdo->prepare(
    "SELECT sangre, alergias, medicamentos, contacto, notas, actualizado_en
       FROM ficha WHERE usuario_id = ?"
);
$stmt->execute([$idUsuario]);
$ficha = $stmt->fetch() ?: null;

// ¿Pintamos el formulario de la ficha clínica o solo la vista?
// Tras un guardado exitoso volvemos a modo vista; con ?edit=clinica forzamos edición.
$editClinica = !$okClinica && (($_GET['edit'] ?? '') === 'clinica');

$pageTitle = 'Mi ficha';
$activeNav = 'ficha';
require __DIR__ . '/../includes/header.php';
?>
<div class="space-y-10">
  <header>
    <p class="text-mute text-[11px] uppercase tracking-[0.18em]">Mi ficha</p>
    <h1 class="font-display text-4xl md:text-5xl mt-2 leading-[1.05] text-balance">
      Tu información <span class="italic text-sage-700">en un vistazo</span>.
    </h1>
    <p class="text-mute mt-3 max-w-xl leading-relaxed">
      Datos personales, resumen automático y un vistazo a tu historial reciente.
    </p>
  </header>

  <!-- Sección 1: datos personales editables -->
  <section class="card animate-fade-up">
    <div class="flex items-center gap-2 mb-5">
      <span class="w-8 h-8 rounded-2xl bg-sage-100 text-sage-700 grid place-items-center">
        <i data-lucide="user-cog" style="width:15px;height:15px"></i>
      </span>
      <h3 class="font-display text-2xl">Datos personales</h3>
    </div>

    <?php if ($ok)    echo flash_ok('Cambios guardados correctamente.', 'mb-4'); ?>
    <?php if ($error) echo flash_err($error, 'mb-4'); ?>

    <form method="POST" action="ficha.php" class="space-y-5">
      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label class="label" for="nombre">Nombre completo</label>
          <input id="nombre" name="nombre" type="text" required class="input" value="<?= e($user['nombre']) ?>">
        </div>
        <div>
          <label class="label">Correo</label>
          <input type="email" class="input" value="<?= e($user['email']) ?>" disabled>
        </div>
      </div>

      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div>
          <label class="label" for="edad">Edad</label>
          <input id="edad" name="edad" type="number" min="1" max="120" class="input" value="<?= e($user['edad']) ?>">
        </div>
        <div>
          <label class="label" for="sexo">Sexo</label>
          <select id="sexo" name="sexo" class="input">
            <option value="">—</option>
            <option value="M"    <?= $user['sexo']==='M'?'selected':'' ?>>Masculino</option>
            <option value="F"    <?= $user['sexo']==='F'?'selected':'' ?>>Femenino</option>
            <option value="Otro" <?= $user['sexo']==='Otro'?'selected':'' ?>>Otro</option>
          </select>
        </div>
        <div>
          <label class="label" for="peso">Peso (kg)</label>
          <input id="peso" name="peso" type="number" step="0.1" class="input" value="<?= e($user['peso']) ?>">
        </div>
        <div>
          <label class="label" for="altura">Altura (cm)</label>
          <input id="altura" name="altura" type="number" step="0.1" class="input" value="<?= e($user['altura']) ?>">
        </div>
      </div>

      <button type="submit" class="btn btn-primary">
        Guardar cambios <i data-lucide="check" style="width:16px;height:16px"></i>
      </button>
    </form>
  </section>

  <!-- Sección 1.5: ficha clínica (tabla ficha) -->
  <section class="card animate-fade-up" style="animation-delay:60ms">
    <div class="flex items-center justify-between mb-5">
      <div class="flex items-center gap-2">
        <span class="w-8 h-8 rounded-2xl bg-clay-50 text-clay-600 grid place-items-center">
          <i data-lucide="heart-pulse" style="width:15px;height:15px"></i>
        </span>
        <h3 class="font-display text-2xl">Ficha clínica</h3>
      </div>
      <?php if (!$editClinica): ?>
        <a href="ficha.php?edit=clinica#clinica" class="text-sm text-sage-700 hover:text-sage-900 inline-flex items-center gap-1">
          <i data-lucide="<?= $ficha ? 'pencil' : 'plus' ?>" style="width:14px;height:14px"></i>
          <?= $ficha ? 'Editar' : 'Llenar' ?>
        </a>
      <?php endif; ?>
    </div>
    <span id="clinica"></span>

    <?php if ($okClinica)    echo flash_ok('Ficha clínica guardada correctamente.', 'mb-4'); ?>
    <?php if ($errorClinica) echo flash_err($errorClinica, 'mb-4'); ?>

    <?php if ($editClinica): ?>
      <form method="POST" action="ficha.php" class="space-y-5">
        <input type="hidden" name="__form" value="clinica">

        <div class="grid md:grid-cols-2 gap-4">
          <div>
            <label class="label" for="sangre">Tipo de sangre</label>
            <select id="sangre" name="sangre" class="input">
              <option value="">No especificado</option>
              <?php foreach ($sangresValidas as $s): ?>
                <option value="<?= e($s) ?>" <?= ($ficha['sangre'] ?? '') === $s ? 'selected' : '' ?>><?= e($s) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="label" for="contacto">Contacto de emergencia</label>
            <input id="contacto" name="contacto" type="text" maxlength="200" class="input"
                   placeholder="Nombre y teléfono"
                   value="<?= e($ficha['contacto'] ?? '') ?>">
          </div>
        </div>

        <div>
          <label class="label" for="alergias">Alergias</label>
          <textarea id="alergias" name="alergias" rows="2" class="input"
                    placeholder="Ej. penicilina, mariscos…"><?= e($ficha['alergias'] ?? '') ?></textarea>
        </div>

        <div>
          <label class="label" for="medicamentos">Medicamentos actuales</label>
          <textarea id="medicamentos" name="medicamentos" rows="2" class="input"
                    placeholder="Nombre y dosis"><?= e($ficha['medicamentos'] ?? '') ?></textarea>
        </div>

        <div>
          <label class="label" for="notas">Notas adicionales</label>
          <textarea id="notas" name="notas" rows="3" class="input"
                    placeholder="Cualquier información que quieras tener a la mano"><?= e($ficha['notas'] ?? '') ?></textarea>
        </div>

        <div class="flex gap-3">
          <button type="submit" class="btn btn-primary">
            Guardar <i data-lucide="check" style="width:16px;height:16px"></i>
          </button>
          <a href="ficha.php" class="btn btn-outline">Cancelar</a>
        </div>
      </form>
    <?php elseif (!$ficha): ?>
      <div class="text-center py-6">
        <p class="text-mute text-sm mb-4">Aún no has llenado tu ficha clínica.</p>
        <a href="ficha.php?edit=clinica#clinica" class="btn btn-primary">
          <i data-lucide="plus" style="width:16px;height:16px"></i> Llenar ficha
        </a>
      </div>
    <?php else: ?>
      <?php
        $filas = [
          ['sangre',       'Tipo de sangre',         'font-medium',      '<span class="text-mute font-normal">No especificado</span>'],
          ['contacto',     'Contacto de emergencia', '',                 '<span class="text-mute">—</span>'],
          ['alergias',     'Alergias',               'whitespace-pre-wrap', '<span class="text-mute">—</span>'],
          ['medicamentos', 'Medicamentos',           'whitespace-pre-wrap', '<span class="text-mute">—</span>'],
          ['notas',        'Notas',                  'whitespace-pre-wrap', '<span class="text-mute">—</span>'],
        ];
      ?>
      <dl class="divide-y divide-black/5">
        <?php foreach ($filas as [$k, $label, $cls, $vacio]): ?>
          <div class="flex items-start gap-4 py-3">
            <dt class="w-44 shrink-0 text-[11px] uppercase tracking-[0.14em] text-mute pt-0.5"><?= $label ?></dt>
            <dd class="flex-1 <?= $cls ?>"><?= $ficha[$k] ? e($ficha[$k]) : $vacio ?></dd>
          </div>
        <?php endforeach; ?>
      </dl>
      <?php if (!empty($ficha['actualizado_en'])): ?>
        <p class="text-xs text-mute mt-4">Última actualización: <?= e(formato_fecha($ficha['actualizado_en'])) ?></p>
      <?php endif; ?>
    <?php endif; ?>
  </section>

  <!-- Sección 2: resumen automático -->
  <section class="space-y-4 animate-fade-up" style="animation-delay:120ms">
    <div class="flex items-center gap-2">
      <span class="w-8 h-8 rounded-2xl bg-sage-100 text-sage-700 grid place-items-center">
        <i data-lucide="sparkles" style="width:15px;height:15px"></i>
      </span>
      <h3 class="font-display text-2xl">Resumen automático</h3>
    </div>

    <div class="grid sm:grid-cols-3 gap-4">
      <div class="card">
        <p class="text-[11px] uppercase tracking-[0.14em] text-mute">Cuestionarios realizados</p>
        <div class="font-display text-4xl mt-2 tabular-nums"><?= $totalResultados ?></div>
      </div>
      <div class="card">
        <p class="text-[11px] uppercase tracking-[0.14em] text-mute">Bienestar promedio</p>
        <div class="font-display text-4xl mt-2 tabular-nums"><?= $promedio ?>%</div>
      </div>
      <div class="card">
        <p class="text-[11px] uppercase tracking-[0.14em] text-mute">IMC</p>
        <div class="font-display text-4xl mt-2 tabular-nums"><?= $imc ?? '—' ?></div>
        <p class="text-xs text-mute mt-1"><?= e($imcCat ?? 'Sin datos') ?></p>
      </div>
    </div>

    <div class="card">
      <p class="text-[11px] uppercase tracking-[0.14em] text-mute mb-4">Último por área</p>
      <ul class="divide-y divide-black/5">
        <?php foreach ($resumenPorCuestionario as $c): ?>
          <li class="flex items-center gap-4 py-3">
            <div class="w-9 h-9 rounded-2xl flex items-center justify-center <?= color_classes($c['color']) ?>">
              <?= icono_cuestionario($c['icono'], 16) ?>
            </div>
            <div class="flex-1 min-w-0">
              <div class="font-medium truncate"><?= e($c['titulo']) ?></div>
            </div>
            <?php if ($c['ultimo_porcentaje'] === null): ?>
              <span class="text-xs text-mute">Sin datos</span>
            <?php else: ?>
              <span class="font-display text-lg tabular-nums"><?= (int)round((float)$c['ultimo_porcentaje']) ?>%</span>
              <?= nivel_badge($c['ultimo_nivel']) ?>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <!-- Sección 3: historial reciente -->
  <section class="animate-fade-up" style="animation-delay:180ms">
    <div class="flex items-center justify-between mb-5">
      <div class="flex items-center gap-2">
        <span class="w-8 h-8 rounded-2xl bg-sage-100 text-sage-700 grid place-items-center">
          <i data-lucide="history" style="width:15px;height:15px"></i>
        </span>
        <h3 class="font-display text-2xl">Historial reciente</h3>
      </div>
      <a href="historial.php" class="text-sm text-sage-700 hover:text-sage-900 inline-flex items-center gap-1">
        Ver todo <i data-lucide="arrow-right" style="width:14px;height:14px"></i>
      </a>
    </div>

    <?php if (count($historialReciente) === 0): ?>
      <div class="card text-center py-10">
        <p class="text-mute text-sm mb-4">Aún no tienes resultados.</p>
        <a href="cuestionarios.php" class="btn btn-primary">Comenzar uno</a>
      </div>
    <?php else: ?>
      <ul class="card divide-y divide-black/5 p-0 overflow-hidden">
        <?php foreach ($historialReciente as $r) echo item_resultado($r); ?>
      </ul>
    <?php endif; ?>
  </section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
