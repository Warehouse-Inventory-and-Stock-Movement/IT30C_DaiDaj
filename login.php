<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth_layout.php';

guest_only();

$error = '';
$email = '';
$MAX_ATTEMPTS = 5;
$LOCK_SECONDS = 60;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email    = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');

    // Basic brute-force throttle (per session)
    $lockedUntil = $_SESSION['login_locked_until'] ?? 0;
    if ($lockedUntil > time()) {
        $error = 'Too many failed attempts. Please wait ' . ($lockedUntil - time()) . ' seconds.';
    } else {
        $st = db()->prepare('SELECT user_id, employee_code, full_name, email, password_hash, role, is_active
                               FROM users WHERE email = ? LIMIT 1');
        $st->execute([$email]);
        $row = $st->fetch();

        
        $hash  = $row['password_hash'] ?? '$2y$10$usesomesillystringforsalt.invalidhashvalueXXXXXXXXXXXXXXXXXXXX';
        $valid = password_verify($password, $hash) && $row;

        if ($valid && (int)$row['is_active'] === 1) {
            session_regenerate_id(true);
            unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
            $_SESSION['user'] = [
                'id'    => (int)$row['user_id'],
                'code'  => $row['employee_code'],
                'name'  => $row['full_name'],
                'email' => $row['email'],
                'role'  => $row['role'],
            ];
            if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $row['user_id']]);
            }
            flash('success', 'Welcome back, ' . $row['full_name'] . '.');
            redirect(dashboard_path($row['role']));
        }

        if ($valid && (int)$row['is_active'] !== 1) {
            $error = 'This account has been deactivated. Contact your manager.';
        } else {
            $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
            if ($_SESSION['login_attempts'] >= $MAX_ATTEMPTS) {
                $_SESSION['login_locked_until'] = time() + $LOCK_SECONDS;
                $_SESSION['login_attempts'] = 0;
            }
            $error = 'Incorrect email or password.';
        }
    }
}

auth_open('Sign in');
?>
      <h1>Sign in</h1>
      <p class="lead">Use your employee account to access the warehouse system.</p>

      <?php foreach (pull_flashes() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
      <?php endforeach; ?>
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

      <form method="post" class="form-grid" autocomplete="on" novalidate>
        <?= csrf_field() ?>
        <div class="field">
          <label for="email">Email</label>
          <input class="input" type="email" id="email" name="email" value="<?= e($email) ?>" placeholder="you@company.com" required autofocus>
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input class="input" type="password" id="password" name="password" placeholder="Your password" required>
        </div>
        <button class="btn btn-primary btn-block" type="submit">Sign in</button>
      </form>

      <p class="auth-switch">New employee? <a href="<?= e(url('register.php')) ?>">Create an account</a></p>

      <div class="demo-box">
        <strong>Demo accounts</strong> (password <code>Password@123</code>)<br>
        Manager: <code>ana@warehouse.test</code><br>
        Staff: <code>maria@warehouse.test</code> &middot; <code>jose@warehouse.test</code>
      </div>
<?php auth_close(); ?>
