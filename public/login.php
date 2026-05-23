<?php
/**
 * public/login.php
 * ----------------
 * Formulario de inicio de sesión. Si ya hay sesión, salta directo al dashboard.
 *
 * Flujo:
 *   1. Si el usuario ya está logueado → redirige al dashboard.
 *   2. Si llega un POST → valida credenciales contra la tabla usuarios.
 *   3. Si todo OK → inicia sesión y redirige al dashboard.
 *   4. Si no → muestra el error y vuelve a pintar el formulario.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// --- 1. Ya está logueado → fuera ---
if (current_user_id()) {
    header('Location: dashboard.php');
    exit;
}

$error = null;
$email = '';

// --- 2. Procesar envío del formulario ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim(strtolower($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';

    if (!$email || !$pass) {
        $error = 'Faltan credenciales';
    } else {
        // Buscamos el usuario por email. password_hash se compara con password_verify().
        $stmt = db()->prepare("SELECT id, password_hash FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($pass, $row['password_hash'])) {
            $error = 'Correo o contraseña incorrectos';
        } else {
            // --- 3. Credenciales OK → iniciar sesión y mandar al dashboard ---
            login_user($row['id']);
            header('Location: dashboard.php');
            exit;
        }
    }
}

// --- 4. Render del formulario (con error si lo hay) ---
$pageTitle = 'Iniciar sesión';
$hideNav   = true;
require __DIR__ . '/../includes/header.php';
?>
<div class="min-h-screen bg-cream bg-grain grid md:grid-cols-2">
  <div class="hidden md:flex flex-col justify-between p-12 bg-ink text-cream relative overflow-hidden">
    <div class="flex items-center gap-3 relative z-10">
      <div class="w-10 h-10 rounded-2xl bg-sage-500 flex items-center justify-center shadow-soft">
        <i data-lucide="activity" style="width:18px;height:18px"></i>
      </div>
      <div>
        <div class="font-display text-xl">Bienestar</div>
        <div class="text-[10px] tracking-[0.2em] uppercase text-cream/50">Municipal</div>
      </div>
    </div>

    <div class="relative z-10 max-w-md animate-fade-up">
      <div class="inline-flex items-center gap-2 text-xs uppercase tracking-[0.18em] text-sage-300/80 mb-5">
        <span class="w-6 h-px bg-sage-300/60"></span> Plataforma de bienestar
      </div>
      <h1 class="font-display text-[3.4rem] leading-[1.02] mb-6 text-balance">
        Cuida tu <span class="italic text-sage-300">bienestar</span>,<br>
        sin complicaciones.
      </h1>
      <p class="text-cream/70 leading-relaxed text-lg">
        Una plataforma serena para conocer cómo está tu cuerpo, tu energía y tus hábitos.
        Sin diagnósticos, sin presión — solo seguimiento amable.
      </p>
    </div>

    <div class="absolute -right-40 -bottom-40 w-[480px] h-[480px] rounded-full bg-sage-500/25 blur-3xl"></div>
    <div class="absolute -left-20 top-20 w-[280px] h-[280px] rounded-full bg-clay-400/10 blur-3xl"></div>
    <div class="absolute -right-16 -top-16 w-80 h-80 rounded-full border border-cream/10 animate-slow-spin"></div>

    <div class="relative text-xs text-cream/40">© Plataforma Municipal de Bienestar</div>
  </div>

  <div class="flex items-center justify-center px-6 py-12">
    <div class="w-full max-w-sm animate-fade-up">
      <div class="md:hidden mb-10 flex items-center gap-2.5">
        <div class="w-9 h-9 rounded-2xl bg-sage-500 flex items-center justify-center text-cream shadow-soft">
          <i data-lucide="activity" style="width:18px;height:18px"></i>
        </div>
        <span class="font-display text-lg">Bienestar Municipal</span>
      </div>

      <h2 class="font-display text-4xl mb-1.5 text-balance">Hola de nuevo.</h2>
      <p class="text-mute mb-9">Inicia sesión para continuar.</p>

      <form method="POST" action="login.php" class="space-y-4" novalidate>
        <div>
          <label class="label" for="email">Correo</label>
          <input id="email" name="email" type="email" required class="input"
                 value="<?= e($email) ?>" placeholder="tu@correo.com" autocomplete="email">
        </div>
        <div>
          <label class="label" for="password">Contraseña</label>
          <div class="relative">
            <input id="password" name="password" type="password" required class="input pr-11"
                   placeholder="••••••••" autocomplete="current-password">
            <button type="button" data-toggle-pass="password"
                    class="absolute right-3 top-1/2 -translate-y-1/2 w-8 h-8 grid place-items-center rounded-full text-mute hover:text-ink hover:bg-black/5 transition-colors"
                    aria-label="Mostrar contraseña">
              <i data-lucide="eye" style="width:15px;height:15px"></i>
            </button>
          </div>
        </div>

        <?php if ($error) echo flash_err($error); ?>

        <button type="submit" class="btn btn-primary w-full mt-2">
          <span>Entrar</span>
          <i data-lucide="arrow-right" style="width:16px;height:16px"></i>
        </button>
      </form>

      <p class="mt-7 text-sm text-mute text-center">
        ¿No tienes cuenta?
        <a href="register.php" class="text-sage-700 underline underline-offset-4 hover:no-underline">Crea una</a>
      </p>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
