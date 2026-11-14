<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth_layout.php';
 
guest_only();
 
const SHOW_DB_ERRORS = true;   // set to false once everything works
 
$errors = [];
$old = ['full_name' => '', 'email' => '', 'invite' => ''];
 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $role = 'staff';   // default role, can only be raised by a valid manager code below
    $pdo  = db();
    $old['full_name'] = trim((string)($_POST['full_name'] ?? ''));
    $old['email']     = strtolower(trim((string)($_POST['email'] ?? '')));
    $old['invite']    = trim((string)($_POST['invite'] ?? ''));
    $password         = (string)($_POST['password'] ?? '');
    $confirm          = (string)($_POST['confirm'] ?? '');
 
    if (mb_strlen($old['full_name']) < 2 || mb_strlen($old['full_name']) > 100) {
        $errors[] = 'Full name must be 2 to 100 characters.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 120) {
        $errors[] = 'Enter a valid email address.';
    }
    if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors[] = 'Password must be at least 8 characters and include a letter and a number.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }
 
    // Role is decided on the server. Everyone is staff unless a valid manager code is supplied.
    if ($old['invite'] !== '') {
        if (hash_equals(MANAGER_INVITE_CODE, $old['invite'])) {
            $role = 'manager';
        } else {
            $errors[] = 'The manager access code is not valid. Leave it blank to register as staff.';
        }
    }
 
    if (!$errors) {
        $st = $pdo->prepare('SELECT 1 FROM users WHERE email = ?');
        $st->execute([$old['email']]);
        if ($st->fetchColumn()) {
            $errors[] = 'That email is already registered.';
        }
    }
 
    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $ins = $pdo->prepare('INSERT INTO users (employee_code, full_name, email, password_hash, role)
                                  VALUES (?, ?, ?, ?, ?)');
            $ins->execute(['TMP-' . bin2hex(random_bytes(6)), $old['full_name'], $old['email'],
                           password_hash($password, PASSWORD_DEFAULT), $role]);
            $id = (int)$pdo->lastInsertId();
            $pdo->prepare('UPDATE users SET employee_code = ? WHERE user_id = ?')
                ->execute([sprintf('E-%02d', $id), $id]);
            $pdo->commit();
 
            flash('success', 'Account created as ' . $role . ' (employee ID ' . sprintf('E-%02d', $id) . '). You can sign in now.');
            redirect('login.php');
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Could not create the account. Please try again.';
            // TEMPORARY debugging aid: shows the real database error. Set to false after testing.
            if (SHOW_DB_ERRORS) {
                $errors[] = 'Database says: ' . $ex->getMessage();
            }
        }
    }
}
 
auth_open('Create account');
?>
      <h1>Create your account</h1>
      <p class="lead">Register as a warehouse employee. New accounts start with staff access.</p>
 
      <?php if ($errors): ?>
        <div class="alert alert-error">
          <?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?>
        </div>
      <?php endif; ?>
 
      <form method="post" class="form-grid" novalidate>
        <?= csrf_field() ?>
        <div class="field">
          <label for="full_name">Full name</label>
          <input class="input" id="full_name" name="full_name" value="<?= e($old['full_name']) ?>" required autofocus>
        </div>
        <div class="field">
          <label for="email">Email</label>
          <input class="input" type="email" id="email" name="email" value="<?= e($old['email']) ?>" required>
        </div>
        <div class="form-grid cols-2">
          <div class="field">
            <label for="password">Password</label>
            <input class="input" type="password" id="password" name="password" required>
          </div>
          <div class="field">
            <label for="confirm">Confirm password</label>
            <input class="input" type="password" id="confirm" name="confirm" required>
          </div>
        </div>
        <div class="field">
          <label for="invite">Manager access code <span class="muted">(optional)</span></label>
          <input class="input" id="invite" name="invite" value="<?= e($old['invite']) ?>" autocomplete="off">
          <div class="hint">Leave blank to register as warehouse staff.</div>
        </div>
        <button class="btn btn-primary btn-block" type="submit">Create account</button>
      </form>
 
      <p class="auth-switch">Already registered? <a href="<?= e(url('login.php')) ?>">Sign in</a></p>
<?php auth_close(); ?>