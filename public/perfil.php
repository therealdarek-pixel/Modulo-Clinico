<?php
/**
 * public/perfil.php
 * -----------------
 * Vista del perfil del usuario: muestra sus datos en formato lista + IMC.
 * Los datos NO se editan aquí (la edición vive en ficha.php).
 */
require_once __DIR__ . '/../includes/auth.php';                                                // sesión + require_login()
require_once __DIR__ . '/../includes/helpers.php';                                             // calcular_imc(), e()
require_login();                                                                               // sin sesión → manda a login.php

$user = get_user();                                                                            // trae los datos del usuario logueado (cacheado en auth.php)
[$imc, $imcCat] = calcular_imc($user['peso'] ?? null, $user['altura'] ?? null);                // destructuring: $imc = número, $imcCat = "Saludable" / "Sobrepeso"...

// Color del texto bajo el IMC, según la categoría (mapa simple en vez de if/elseif).
$tonoClase = [
    'Bajo peso' => 'text-clay-600',                                                            // rojo apagado para extremos
    'Obesidad'  => 'text-clay-600',                                                            // mismo color para obesidad
    'Sobrepeso' => 'text-amber-700',                                                           // ámbar como aviso intermedio
][$imcCat] ?? 'text-sage-700';                                                                 // si es "Saludable" o null → verde por defecto

$inicial = mb_strtoupper(mb_substr($user['nombre'], 0, 1));                                    // primera letra del nombre en MAYÚSCULA (para el avatar circular)

// Filas de la lista. Cada elemento: [icono, etiqueta, valor a mostrar].
// Los campos vacíos caen a "—" o "No especificado".
$filas = [
    ['mail',     'Correo', $user['email']],                                                    // el correo siempre existe (es obligatorio al registrarse)
    ['user',     'Sexo',   $user['sexo']   ?: 'No especificado'],                             // ?: → si está vacío/null muestra "No especificado"
    ['calendar', 'Edad',   $user['edad']   ? $user['edad']   . ' años' : '—'],                 // si hay edad la pega con " años", si no muestra "—"
    ['scale',    'Peso',   $user['peso']   ? $user['peso']   . ' kg'   : '—'],                 // mismo patrón con la unidad "kg"
    ['ruler',    'Altura', $user['altura'] ? $user['altura'] . ' cm'   : '—'],                 // mismo patrón con la unidad "cm"
];

$pageTitle = 'Perfil';                                                                         // título de la pestaña del navegador
$activeNav = 'perfil';                                                                         // marca "Perfil" como activo en el menú lateral
require __DIR__ . '/../includes/header.php';                                                   // pinta <head>, sidebar y abre <main>
?>
<div class="space-y-8">
  <header class="flex items-center gap-5">
    <div class="w-16 h-16 rounded-3xl bg-sage-500 text-cream flex items-center justify-center font-display text-2xl shadow-soft"><?= e($inicial) ?></div>
    <div>
      <p class="text-mute text-[11px] uppercase tracking-[0.18em]">Tu perfil</p>
      <h1 class="font-display text-3xl md:text-4xl leading-tight"><?= e($user['nombre']) ?></h1>
    </div>
  </header>

  <div class="card divide-y divide-black/5 p-0 overflow-hidden">
    <?php foreach ($filas as $i => [$icon, $label, $value]): ?>
      <div class="flex items-center gap-4 px-5 py-4 animate-fade-up" style="animation-delay:<?= $i * 40 ?>ms">
        <span class="w-9 h-9 rounded-2xl bg-sage-100 text-sage-700 grid place-items-center">
          <i data-lucide="<?= e($icon) ?>" style="width:15px;height:15px"></i>
        </span>
        <div class="flex-1 min-w-0">
          <div class="text-[11px] uppercase tracking-[0.14em] text-mute"><?= e($label) ?></div>
          <div class="font-medium truncate"><?= e($value) ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($imc): ?>
  <div class="card flex items-start justify-between gap-6 animate-fade-up" style="animation-delay:120ms">
    <div>
      <p class="text-[11px] uppercase tracking-[0.16em] text-mute">Índice de masa corporal</p>
      <div class="font-display text-4xl mt-1.5 tabular-nums"><?= e((string)$imc) ?></div>
      <p class="text-sm mt-1 <?= $tonoClase ?>"><?= e($imcCat) ?></p>
    </div>
    <div class="text-xs text-mute text-right max-w-[58%] leading-relaxed">
      El IMC es solo una referencia general y no sustituye la evaluación de un profesional.
    </div>
  </div>
  <?php endif; ?>

  <div class="flex gap-3">
    <a href="ficha.php" class="btn btn-outline flex-1">
      <i data-lucide="file-text" style="width:16px;height:16px"></i> Editar mi ficha
    </a>
    <a href="logout.php" class="btn btn-outline flex-1 text-clay-600 hover:bg-clay-50 hover:border-clay-200">
      <i data-lucide="log-out" style="width:16px;height:16px"></i> Cerrar sesión
    </a>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
