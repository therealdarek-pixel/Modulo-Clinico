<?php
/**
 * includes/footer.php
 * -------------------
 * Pie común de página: cierra <main>, carga los iconos Lucide y el JS global.
 * Si $hideNav está activo (login/register) no abrimos <main> en header → tampoco lo cerramos aquí.
 */
$hideNav = $hideNav ?? false;
?>
<?php if (!$hideNav): ?>
</main>
<?php endif; ?>
  <script src="https://unpkg.com/lucide@latest"></script>
  <script src="assets/app.js"></script>
  <script>if (window.lucide) lucide.createIcons();</script>
</body>
</html>
