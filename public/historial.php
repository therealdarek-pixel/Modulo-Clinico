<?php
/**
 * public/historial.php
 * --------------------
 * Lista de TODOS los resultados del usuario (hasta 100), con:
 *   - Chips para filtrar por cuestionario (?slug=...)
 *   - Gráfica de tendencia (si hay 2+ puntos)
 *   - Lista de resultados (cada uno enlaza a resultado.php)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_login();

$idUsuario = current_user_id();
$pdo = db();

$filtro = $_GET['slug'] ?? 'todos';

// --- 1) Chips de filtros: solo aquellos cuestionarios donde HAY datos ---
$stmt = $pdo->prepare(
    "SELECT DISTINCT c.slug, c.titulo
       FROM resultados r JOIN cuestionarios c ON c.id = r.cuestionario_id
      WHERE r.usuario_id = ? ORDER BY c.titulo"
);
$stmt->execute([$idUsuario]);
$titulos = $stmt->fetchAll();

// --- 2) Lista de resultados (misma query, con o sin filtro por slug) ---
$sqlBase = "SELECT r.id, r.porcentaje, r.nivel, r.creado_en, c.titulo, c.slug, c.icono, c.color
              FROM resultados r JOIN cuestionarios c ON c.id = r.cuestionario_id
             WHERE r.usuario_id = ?";
$params = [$idUsuario];
if ($filtro !== 'todos') {
    $sqlBase .= " AND c.slug = ?";
    $params[] = $filtro;
}
$stmt = $pdo->prepare($sqlBase . " ORDER BY r.creado_en DESC LIMIT 100");
$stmt->execute($params);
$items = $stmt->fetchAll();

// --- 3) Serie cronológica (de viejo a nuevo) para la gráfica ---
$serie = array_map(
    fn($it) => ['fecha' => substr($it['creado_en'], 5, 5), 'porcentaje' => (float)$it['porcentaje']],
    array_reverse($items)
);

$pageTitle  = 'Historial';
$activeNav  = 'historial';
$needsChart = count($serie) > 1;   // solo cargamos Chart.js si vamos a dibujar la línea
require __DIR__ . '/../includes/header.php';
?>
<div class="space-y-8">
  <header>
    <p class="text-mute text-[11px] uppercase tracking-[0.18em]">Historial</p>
    <h1 class="font-display text-4xl md:text-5xl mt-2 leading-[1.05] text-balance">
      Tu <span class="italic text-sage-700">evolución</span>.
    </h1>
    <p class="text-mute mt-3 max-w-xl leading-relaxed">
      Todos los cuestionarios que has realizado, en orden cronológico.
    </p>
  </header>

  <div class="flex flex-wrap gap-2">
    <a href="historial.php" class="chip" data-active="<?= $filtro==='todos'?'true':'false' ?>">Todos</a>
    <?php foreach ($titulos as $t): ?>
      <a href="historial.php?slug=<?= urlencode($t['slug']) ?>" class="chip"
         data-active="<?= $filtro===$t['slug']?'true':'false' ?>"><?= e($t['titulo']) ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (count($serie) > 1): ?>
    <div class="card animate-fade-up">
      <div class="flex items-center justify-between mb-5">
        <h3 class="font-display text-xl">Tendencia</h3>
        <span class="text-xs text-mute">% bienestar</span>
      </div>
      <div class="h-56"><canvas id="chart-tendencia"></canvas></div>
    </div>
  <?php endif; ?>

  <?php if (count($items) === 0): ?>
    <div class="card text-center py-12">
      <div class="w-12 h-12 rounded-2xl bg-sage-100 text-sage-700 grid place-items-center mx-auto mb-3">
        <i data-lucide="history" style="width:20px;height:20px"></i>
      </div>
      <h4 class="font-display text-xl mb-1">Aún no tienes resultados</h4>
      <p class="text-mute text-sm mb-5">Cuando completes un cuestionario aparecerá aquí, junto con tu evolución.</p>
      <a href="cuestionarios.php" class="btn btn-primary">Comenzar uno <i data-lucide="arrow-right" style="width:15px;height:15px"></i></a>
    </div>
  <?php else: ?>
    <ul class="card divide-y divide-black/5 p-0 overflow-hidden">
      <?php foreach ($items as $i => $r) echo item_resultado($r, ['animate' => true, 'delay' => $i * 30, 'formato' => 'corto']); ?>
    </ul>
  <?php endif; ?>
</div>

<?php if (count($serie) > 1): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const ctx = document.getElementById('chart-tendencia');
  const grad = ctx.getContext('2d').createLinearGradient(0, 0, 0, 200);
  grad.addColorStop(0, 'rgba(122,160,121,0.32)');
  grad.addColorStop(1, 'rgba(122,160,121,0)');
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: <?= json_encode(array_column($serie, 'fecha')) ?>,
      datasets: [{
        data: <?= json_encode(array_column($serie, 'porcentaje')) ?>,
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
});
</script>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
