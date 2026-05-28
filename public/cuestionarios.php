<?php
/**
 * public/cuestionarios.php
 * ------------------------
 * Lista todos los cuestionarios activos. Cada tarjeta enlaza a cuestionario.php?slug=...
 */
require_once __DIR__ . '/../includes/auth.php';      // carga sesión y la función require_login()
require_once __DIR__ . '/../includes/helpers.php';   // carga funciones: e(), color_classes(), icono_cuestionario()
require_login();                                     // si no hay sesión → redirige a login.php

$lista = db()->query(                                // db() devuelve la conexión PDO; query() ejecuta el SELECT
    "SELECT id, slug, titulo, descripcion, icono, color -- selecciona solo las columnas necesarias
       FROM cuestionarios WHERE activo = 1 ORDER BY id"  // activo = 1 → solo los habilitados
)->fetchAll();                                       // fetchAll() → array con todos los cuestionarios

$pageTitle = 'Cuestionarios';                        // título que verá el navegador en la pestaña
$activeNav = 'cuestionarios';                        // marca este item como activo en el menú lateral
require __DIR__ . '/../includes/header.php';         // pinta <head>, sidebar y abre <main>
?>
<div class="space-y-10">
  <header>                                                                          <!-- Encabezado de la página -->
    <p class="text-mute text-[11px] uppercase tracking-[0.18em]">Cuestionarios</p>
    <h1 class="font-display text-4xl md:text-5xl mt-2 text-balance leading-[1.05]">
      Escoge un área <span class="italic text-sage-700">para evaluar</span>.
    </h1>
    <p class="text-mute mt-3 max-w-xl leading-relaxed">
      Cada cuestionario toma 1-2 minutos. No hay respuestas correctas — solo se trata de conocerte mejor.
    </p>
  </header>

  <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">                              <!-- Grid: 1 col móvil, 2 tablet, 3 escritorio -->
    <?php foreach ($lista as $i => $c): ?>                                            <!-- $i = índice (para delay), $c = cuestionario -->
      <!-- enlace al wizard, pasando el slug en la URL; aparece 50ms después que la tarjeta anterior -->
      <a href="cuestionario.php?slug=<?= urlencode($c['slug']) ?>"
         class="card-hover group flex flex-col h-full animate-fade-up relative overflow-hidden"
         style="animation-delay:<?= $i * 50 ?>ms">
        <div class="w-12 h-12 rounded-2xl flex items-center justify-center mb-4 <?= color_classes($c['color']) ?>"><!-- icono con su color -->
          <?= icono_cuestionario($c['icono']) ?>                                      <!-- pinta el icono Lucide guardado en BD -->
        </div>
        <h3 class="font-display text-xl mb-1.5"><?= e($c['titulo']) ?></h3>           <!-- e() escapa HTML para evitar XSS -->
        <p class="text-sm text-mute leading-relaxed flex-1"><?= e($c['descripcion']) ?></p>
        <div class="mt-5 flex items-center justify-between">                          <!-- Pie de la tarjeta -->
          <span class="inline-flex items-center gap-1.5 text-[11px] text-mute">
            <i data-lucide="clock" style="width:12px;height:12px"></i> 1-2 min        <!-- tiempo estimado fijo -->
          </span>
          <span class="text-sm font-medium text-sage-700 inline-flex items-center gap-1.5">
            Comenzar <i data-lucide="arrow-right" style="width:14px;height:14px"></i> <!-- call-to-action visual -->
          </span>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; // cierra </main>, carga iconos Lucide y el JS global ?>
