<?php
/**
 * public/register.php
 * -------------------
 * Crear una cuenta nueva. Datos demográficos (edad, sexo, peso, altura) son opcionales.
 *
 * Flujo:
 *   1. Si ya hay sesión → dashboard.
 *   2. Si llega POST →
 *      a. Leer y normalizar los campos del formulario.
 *      b. Validar (requeridos, email, contraseña ≥ 6, sexo válido).
 *      c. Comprobar que el email no exista.
 *      d. Hashear contraseña, INSERT, iniciar sesión y redirigir.
 *   3. Si no → pintar el formulario (con el error si lo hay).
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// --- 1. Ya logueado → fuera ---
if (current_user_id()) {
    header('Location: dashboard.php');
    exit;
}

$error = null;
// $f guarda lo que el usuario escribió, para repintarlo si falla la validación.
$f = ['nombre' => '', 'email' => '', 'edad' => '', 'sexo' => '', 'peso' => '', 'altura' => ''];

// --- 2. Procesar POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 2.a — leer y normalizar
    $f['nombre'] = trim($_POST['nombre'] ?? '');
    $f['email']  = trim(strtolower($_POST['email'] ?? ''));
    $pass        = $_POST['password'] ?? '';
    $f['edad']   = $_POST['edad']   ?? '';
    $f['sexo']   = $_POST['sexo']   ?? '';
    $f['peso']   = $_POST['peso']   ?? '';
    $f['altura'] = $_POST['altura'] ?? '';

    // Casts a los tipos que espera la BD (o null si el campo está vacío).
    $edad   = $f['edad']   !== '' ? (int)$f['edad']     : null;
    $sexo   = nn($f['sexo']);
    $peso   = $f['peso']   !== '' ? (float)$f['peso']   : null;
    $altura = $f['altura'] !== '' ? (float)$f['altura'] : null;

    // 2.b — validaciones (de la más obvia a la más específica)
    if (!$f['nombre'] || !$f['email'] || !$pass) {
        $error = 'Faltan campos requeridos';
    } elseif (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Email inválido';
    } elseif (strlen($pass) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres';
    } elseif ($sexo !== null && !in_array($sexo, ['M', 'F', 'Otro'], true)) {
        $error = 'Sexo inválido';
    } else {
        // 2.c — email único
        $check = db()->prepare("SELECT id FROM usuarios WHERE email = ?");
        $check->execute([$f['email']]);
        if ($check->fetch()) {
            $error = 'Este correo ya está registrado';
        } else {
            // 2.d — la contraseña SIEMPRE va hasheada (nunca en texto plano)
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $ins = db()->prepare(
                "INSERT INTO usuarios (nombre, email, password_hash, edad, sexo, peso, altura)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $ins->execute([$f['nombre'], $f['email'], $hash, $edad, $sexo, $peso, $altura]);
            login_user(db()->lastInsertId());
            header('Location: dashboard.php');
            exit;
        }
    }
}

// --- 3. Render del formulario ---
$pageTitle = 'Crear cuenta';
$hideNav   = true;
require __DIR__ . '/../includes/header.php';
?>
<div class="min-h-screen bg-cream bg-grain px-6 py-10">
  <div class="max-w-xl mx-auto animate-fade-up">
    <a href="login.php" class="inline-flex items-center gap-1.5 text-sm text-mute hover:text-ink mb-8 transition-colors">
      <i data-lucide="arrow-left" style="width:15px;height:15px"></i> Volver
    </a>

    <div class="flex items-center gap-3 mb-9">
      <div class="w-11 h-11 rounded-2xl bg-sage-500 flex items-center justify-center text-cream shadow-soft">
        <i data-lucide="activity" style="width:18px;height:18px"></i>
      </div>
      <div>
        <h1 class="font-display text-3xl text-balance leading-tight">Crea tu cuenta</h1>
        <p class="text-mute text-sm">Solo unos datos para personalizar tu seguimiento.</p>
      </div>
    </div>

    <form method="POST" action="register.php" class="card space-y-5" novalidate>
      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label class="label" for="nombre">Nombre completo</label>
          <input id="nombre" name="nombre" type="text" required class="input" value="<?= e($f['nombre']) ?>">
        </div>
        <div>
          <label class="label" for="email">Correo</label>
          <input id="email" name="email" type="email" required class="input" value="<?= e($f['email']) ?>">
        </div>
      </div>

      <div>
        <label class="label" for="password">Contraseña</label>
        <input id="password" name="password" type="password" required minlength="6" class="input" placeholder="Mínimo 6 caracteres">
      </div>

      <div class="pt-3 border-t border-black/[0.06]">
        <p class="text-[11px] uppercase tracking-[0.16em] text-mute mb-4">Datos de bienestar (opcional)</p>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div>
            <label class="label" for="edad">Edad</label>
            <input id="edad" name="edad" type="number" min="1" max="120" class="input" value="<?= e($f['edad']) ?>">
          </div>
          <div>
            <label class="label" for="sexo">Sexo</label>
            <select id="sexo" name="sexo" class="input">
              <option value="">—</option>
              <option value="M"   <?= $f['sexo']==='M'?'selected':'' ?>>Masculino</option>
              <option value="F"   <?= $f['sexo']==='F'?'selected':'' ?>>Femenino</option>
              <option value="Otro"<?= $f['sexo']==='Otro'?'selected':'' ?>>Otro</option>
            </select>
          </div>
          <div>
            <label class="label" for="peso">Peso (kg)</label>
            <input id="peso" name="peso" type="number" step="0.1" class="input" value="<?= e($f['peso']) ?>">
          </div>
          <div>
            <label class="label" for="altura">Altura (cm)</label>
            <input id="altura" name="altura" type="number" step="0.1" class="input" value="<?= e($f['altura']) ?>">
          </div>
        </div>
      </div>

      <?php if ($error) echo flash_err($error); ?>

      <button type="submit" class="btn btn-primary w-full">
        <span>Crear cuenta</span>
        <i data-lucide="arrow-right" style="width:16px;height:16px"></i>
      </button>
    </form>

    <p class="mt-6 text-sm text-mute text-center">
      ¿Ya tienes cuenta?
      <a href="login.php" class="text-sage-700 underline underline-offset-4 hover:no-underline">Inicia sesión</a>
    </p>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
