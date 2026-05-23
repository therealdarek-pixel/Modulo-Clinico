<?php
/**
 * includes/header.php
 * -------------------
 * Cabecera HTML compartida por TODAS las páginas: <head>, sidebar y apertura de <main>.
 *
 * Cada página define ANTES de hacer `require __DIR__ . '/../includes/header.php':
 *   $pageTitle  → string  Título que va en <title>.
 *   $activeNav  → string  Clave del menú activo: dashboard|cuestionarios|historial|ficha|perfil.
 *   $hideNav    → bool    true en login/register → no se pinta la barra lateral.
 *   $needsChart → bool    true si la página usa Chart.js (se carga solo si hace falta).
 */

// Valores por defecto por si una página olvida definirlos.
$pageTitle  = $pageTitle  ?? 'Bienestar Municipal';
$activeNav  = $activeNav  ?? '';
$hideNav    = $hideNav    ?? false;
$needsChart = $needsChart ?? false;

// Cargamos el usuario (solo si vamos a pintar sidebar) y sacamos la inicial.
$user    = $hideNav ? null : get_user();
$inicial = $user ? mb_strtoupper(mb_substr($user['nombre'], 0, 1)) : '·';

// Items del menú lateral.
$nav = [
    ['href' => 'dashboard.php',     'label' => 'Inicio',        'icon' => 'home',           'key' => 'dashboard'],
    ['href' => 'cuestionarios.php', 'label' => 'Cuestionarios', 'icon' => 'clipboard-list', 'key' => 'cuestionarios'],
    ['href' => 'historial.php',     'label' => 'Historial',     'icon' => 'history',        'key' => 'historial'],
    ['href' => 'ficha.php',         'label' => 'Mi ficha',      'icon' => 'file-text',      'key' => 'ficha'],
    ['href' => 'perfil.php',        'label' => 'Perfil',        'icon' => 'user',           'key' => 'perfil'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> · Bienestar Municipal</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
  window.tailwind = window.tailwind || {};
  tailwind.config = {
    theme: { extend: {
      fontFamily: {
        display: ['"Fraunces"', 'serif'],
        sans:    ['"Outfit"', 'system-ui', 'sans-serif']
      },
      colors: {
        cream: '#F8F4EC', bone: '#FBF8F2',
        sage:  { 50:'#F1F5F0', 100:'#E2EBE0', 300:'#B6CFB1', 500:'#7AA079', 700:'#3F5F44', 900:'#243A28' },
        clay:  { 50:'#FBF1EB', 200:'#F2D6C5', 400:'#D89A82', 600:'#A65D44' },
        ink:   '#1F2421', mute: '#6B7570'
      },
      boxShadow: {
        soft: '0 1px 2px rgba(31,36,33,0.04), 0 8px 24px rgba(31,36,33,0.06)',
        lift: '0 1px 2px rgba(31,36,33,0.05), 0 16px 40px -8px rgba(31,36,33,0.12)',
        ring: '0 0 0 1px rgba(31,36,33,0.06)'
      },
      borderRadius: { '4xl': '2rem' },
      animation: {
        'fade-up': 'fadeUp 0.5s cubic-bezier(0.23, 1, 0.32, 1) both',
        'fade-in': 'fadeIn 0.4s cubic-bezier(0.23, 1, 0.32, 1) both',
        'scale-in':'scaleIn 0.3s cubic-bezier(0.23, 1, 0.32, 1) both',
        'pop-in':  'popIn 0.45s cubic-bezier(0.34, 1.56, 0.64, 1) both',
        'slow-spin':'spin 22s linear infinite'
      },
      keyframes: {
        fadeUp:{'0%':{opacity:0,transform:'translateY(8px)'},'100%':{opacity:1,transform:'translateY(0)'}},
        fadeIn:{'0%':{opacity:0},'100%':{opacity:1}},
        scaleIn:{'0%':{opacity:0,transform:'scale(0.96)'},'100%':{opacity:1,transform:'scale(1)'}},
        popIn:{'0%':{opacity:0,transform:'scale(0.9)'},'60%':{opacity:1,transform:'scale(1.02)'},'100%':{opacity:1,transform:'scale(1)'}}
      }
    }}
  };
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@400;500;600;700&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/styles.css">
  <?php if ($needsChart): ?>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <?php endif; ?>
</head>
<body class="<?= $hideNav ? 'bg-cream' : 'min-h-screen bg-cream bg-grain flex flex-col sm:block' ?>">
<?php if (!$hideNav && $user): ?>
<aside class="sm:fixed sm:top-0 sm:left-0 sm:w-64 sm:h-screen
              bg-bone/85 backdrop-blur-md border-b sm:border-b-0 sm:border-r border-black/[0.05]
              flex sm:flex-col px-5 py-3.5 sm:py-8 sticky top-0 z-30">
  <div class="flex items-center gap-2.5 sm:mb-10">
    <div class="relative w-9 h-9 rounded-2xl bg-sage-500 flex items-center justify-center text-cream shadow-soft">
      <i data-lucide="activity" style="width:18px;height:18px"></i>
    </div>
    <div class="leading-tight">
      <div class="font-display text-lg font-semibold">Bienestar</div>
      <div class="text-[10px] text-mute tracking-[0.18em] uppercase">Municipal</div>
    </div>
  </div>

  <nav class="flex sm:flex-col gap-1 sm:gap-0.5 ml-auto sm:ml-0 sm:flex-1
              overflow-x-auto -mx-2 sm:mx-0 px-2 sm:px-0 gradient-mask-r
              sm:[-webkit-mask-image:none] sm:[mask-image:none]">
    <?php foreach ($nav as $item): $activo = ($item['key'] === $activeNav); ?>
      <a href="<?= e($item['href']) ?>" data-active="<?= $activo ? 'true' : 'false' ?>"
         class="relative flex items-center gap-3 px-3.5 py-2.5 rounded-2xl whitespace-nowrap
                text-ink/70 hover:text-ink hover:bg-black/[0.04]
                data-[active=true]:text-cream transition-colors duration-200 ease-out">
        <?php if ($activo): ?>
          <span class="absolute inset-0 rounded-2xl bg-ink shadow-soft animate-scale-in"></span>
        <?php endif; ?>
        <i data-lucide="<?= e($item['icon']) ?>" class="relative z-10" style="width:18px;height:18px"></i>
        <span class="text-sm font-medium relative z-10"><?= e($item['label']) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="hidden sm:flex items-center gap-3 mt-auto pt-6 border-t border-black/[0.06]">
    <div class="w-9 h-9 rounded-full bg-sage-100 text-sage-700 flex items-center justify-center font-medium text-sm"><?= e($inicial) ?></div>
    <div class="flex-1 min-w-0">
      <div class="text-sm font-medium truncate leading-tight"><?= e($user['nombre']) ?></div>
      <a href="logout.php" class="mt-0.5 inline-flex items-center gap-1 text-[11px] text-mute hover:text-clay-600 transition-colors">
        <i data-lucide="log-out" style="width:11px;height:11px"></i> Cerrar sesión
      </a>
    </div>
  </div>

  <a href="perfil.php" class="sm:hidden ml-2 w-9 h-9 rounded-full bg-sage-100 text-sage-700 flex items-center justify-center font-medium text-sm transition-transform active:scale-95"><?= e($inicial) ?></a>
</aside>

<main class="sm:ml-64 px-5 sm:px-12 py-8 sm:py-12 max-w-6xl mx-auto w-full">
<?php endif; ?>
