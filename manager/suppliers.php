<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role('manager');

$pdo = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $isEdit = ($_POST['mode'] ?? '') === 'edit';
    $code   = strtoupper(trim((string)($_POST['supplier_code'] ?? '')));
    $name   = trim((string)($_POST['supplier_name'] ?? ''));
    $email  = trim((string)($_POST['contact_email'] ?? ''));
    $phone  = trim((string)($_POST['contact_phone'] ?? ''));

    if (!preg_match('/^[A-Z0-9][A-Z0-9-]{1,9}$/', $code)) $errors[] = 'Supplier code must be 2-10 characters: letters, numbers and dashes (e.g. SUP-03).';
    if ($name === '' || mb_strlen($name) > 100)            $errors[] = 'Supplier name is required (max 100 characters).';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Contact email is not valid.';
    if (mb_strlen($phone) > 30)                            $errors[] = 'Phone number is too long.';

    if (!$errors) {
        try {
            if ($isEdit) {
                $pdo->prepare('UPDATE suppliers SET supplier_name=?, contact_email=?, contact_phone=? WHERE supplier_code=?')
                    ->execute([$name, $email ?: null, $phone ?: null, $code]);
                flash('success', "Supplier $code updated.");
            } else {
                $pdo->prepare('INSERT INTO suppliers (supplier_code, supplier_name, contact_email, contact_phone) VALUES (?,?,?,?)')
                    ->execute([$code, $name, $email ?: null, $phone ?: null]);
                flash('success', "Supplier $code added.");
            }
            redirect('manager/suppliers.php');
        } catch (PDOException $ex) {
            $errors[] = ($ex->getCode() === '23000') ? 'That supplier code or name already exists.' : 'The database rejected this change.';
        }
    }
}

$editing = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM suppliers WHERE supplier_code = ?');
    $st->execute([(string)$_GET['edit']]);
    $editing = $st->fetch() ?: null;
}

$suppliers = $pdo->query(
    'SELECT s.supplier_code, s.supplier_name, s.contact_email, s.contact_phone, COUNT(p.sku) AS product_count
       FROM suppliers s LEFT JOIN products p ON p.supplier_code = s.supplier_code
      GROUP BY s.supplier_code, s.supplier_name, s.contact_email, s.contact_phone
      ORDER BY s.supplier_code'
)->fetchAll();

$f = [
    'code'  => $editing['supplier_code'] ?? ($_POST['supplier_code'] ?? ''),
    'name'  => $editing['supplier_name'] ?? ($_POST['supplier_name'] ?? ''),
    'email' => $editing['contact_email'] ?? ($_POST['contact_email'] ?? ''),
    'phone' => $editing['contact_phone'] ?? ($_POST['contact_phone'] ?? ''),
];

$pageTitle = 'Suppliers';
$pageSub   = 'A supplier is identified by a unique code and may supply many products.';
$active    = 'suppliers';
require __DIR__ . '/../includes/header.php';
?>
<?php if ($errors): ?>
  <div class="alert alert-error"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="grid grid-main">
  <div class="card">
    <div class="card-head"><div class="card-title">Supplier directory</div><div class="card-sub"><?= count($suppliers) ?> suppliers</div></div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Code</th><th>Name</th><th>Email</th><th>Phone</th><th class="num">Products</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($suppliers as $s): ?>
          <tr>
            <td class="mono"><?= e($s['supplier_code']) ?></td>
            <td><strong><?= e($s['supplier_name']) ?></strong></td>
            <td class="muted"><?= e($s['contact_email'] ?? '-') ?></td>
            <td class="muted"><?= e($s['contact_phone'] ?? '-') ?></td>
            <td class="num"><?= (int)$s['product_count'] ?></td>
            <td><a class="btn btn-outline btn-sm" href="<?= e(url('manager/suppliers.php?edit=' . urlencode($s['supplier_code']))) ?>"><?= icon('edit', 14) ?> Edit</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card" style="align-self:start">
    <div class="card-head">
      <div class="card-title"><?= $editing ? 'Edit ' . e($editing['supplier_code']) : 'Add supplier' ?></div>
      <?php if ($editing): ?><a class="btn btn-outline btn-sm" href="<?= e(url('manager/suppliers.php')) ?>">Cancel</a><?php endif; ?>
    </div>
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="mode" value="<?= $editing ? 'edit' : 'add' ?>">
      <div class="field">
        <label for="supplier_code">Supplier code</label>
        <input class="input" id="supplier_code" name="supplier_code" value="<?= e($f['code']) ?>" <?= $editing ? 'readonly' : '' ?> placeholder="SUP-03" required>
      </div>
      <div class="field">
        <label for="supplier_name">Supplier name</label>
        <input class="input" id="supplier_name" name="supplier_name" value="<?= e($f['name']) ?>" required>
      </div>
      <div class="field">
        <label for="contact_email">Contact email <span class="muted">(optional)</span></label>
        <input class="input" type="email" id="contact_email" name="contact_email" value="<?= e($f['email']) ?>">
      </div>
      <div class="field">
        <label for="contact_phone">Contact phone <span class="muted">(optional)</span></label>
        <input class="input" id="contact_phone" name="contact_phone" value="<?= e($f['phone']) ?>">
      </div>
      <button class="btn btn-primary" type="submit"><?= $editing ? 'Save changes' : 'Add supplier' ?></button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
