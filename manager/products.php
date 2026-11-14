<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role('manager');

$pdo = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'add_category') {
            $name = trim((string)($_POST['category_name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 60) {
                $errors[] = 'Category name is required (max 60 characters).';
            } else {
                $pdo->prepare('INSERT INTO categories (category_name) VALUES (?)')->execute([$name]);
                flash('success', 'Category "' . $name . '" added.');
                redirect('manager/products.php');
            }
        }

        if ($action === 'save_product') {
            $isEdit  = ($_POST['mode'] ?? '') === 'edit';
            $sku     = strtoupper(trim((string)($_POST['sku'] ?? '')));
            $name    = trim((string)($_POST['product_name'] ?? ''));
            $catId   = (int)($_POST['category_id'] ?? 0);
            $supCode = trim((string)($_POST['supplier_code'] ?? ''));
            $reorder = filter_var($_POST['reorder_level'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1000000]]);

            if (!preg_match('/^[A-Z0-9][A-Z0-9-]{1,19}$/', $sku)) $errors[] = 'SKU must be 2-20 characters: letters, numbers and dashes (e.g. SKU-04).';
            if ($name === '' || mb_strlen($name) > 100)            $errors[] = 'Product name is required (max 100 characters).';
            if ($reorder === false)                                $errors[] = 'Reorder level must be a whole number of 0 or more.';

            $chk = $pdo->prepare('SELECT 1 FROM categories WHERE category_id = ?'); $chk->execute([$catId]);
            if (!$chk->fetchColumn()) $errors[] = 'Choose a valid category.';
            $chk = $pdo->prepare('SELECT 1 FROM suppliers WHERE supplier_code = ?'); $chk->execute([$supCode]);
            if (!$chk->fetchColumn()) $errors[] = 'Choose a valid supplier.';

            if (!$errors) {
                if ($isEdit) {
                    $isActive = isset($_POST['is_active']) ? 1 : 0;
                    $pdo->prepare('UPDATE products SET product_name=?, category_id=?, supplier_code=?, reorder_level=?, is_active=? WHERE sku=?')
                        ->execute([$name, $catId, $supCode, $reorder, $isActive, $sku]);
                    flash('success', "Product $sku updated.");
                } else {
                    $dup = $pdo->prepare('SELECT 1 FROM products WHERE sku = ?'); $dup->execute([$sku]);
                    if ($dup->fetchColumn()) {
                        $errors[] = "SKU $sku already exists.";
                    } else {
                        $pdo->prepare('INSERT INTO products (sku, product_name, category_id, supplier_code, reorder_level) VALUES (?,?,?,?,?)')
                            ->execute([$sku, $name, $catId, $supCode, $reorder]);
                        flash('success', "Product $sku added.");
                    }
                }
                if (!$errors) redirect('manager/products.php');
            }
        }
    } catch (PDOException $ex) {
        $errors[] = ($ex->getCode() === '23000') ? 'That value already exists.' : 'The database rejected this change.';
    }
}

$categories = $pdo->query('SELECT category_id, category_name FROM categories ORDER BY category_name')->fetchAll();
$suppliers  = $pdo->query('SELECT supplier_code, supplier_name FROM suppliers ORDER BY supplier_name')->fetchAll();

$editing = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM products WHERE sku = ?');
    $st->execute([(string)$_GET['edit']]);
    $editing = $st->fetch() ?: null;
}

$products = $pdo->query(
    'SELECT p.sku, p.product_name, p.reorder_level, p.is_active, c.category_name, s.supplier_name, s.supplier_code,
            COALESCE((SELECT SUM(CASE m.movement_type WHEN \'OUT\' THEN -m.quantity ELSE m.quantity END)
                        FROM stock_movements m WHERE m.sku = p.sku), 0) AS total
       FROM products p
       JOIN categories c ON c.category_id = p.category_id
       JOIN suppliers s  ON s.supplier_code = p.supplier_code
      ORDER BY p.sku'
)->fetchAll();

$f = [
    'sku'  => $editing['sku'] ?? ($_POST['sku'] ?? ''),
    'name' => $editing['product_name'] ?? ($_POST['product_name'] ?? ''),
    'cat'  => (int)($editing['category_id'] ?? ($_POST['category_id'] ?? 0)),
    'sup'  => $editing['supplier_code'] ?? ($_POST['supplier_code'] ?? ''),
    're'   => $editing['reorder_level'] ?? ($_POST['reorder_level'] ?? 10),
    'act'  => $editing ? (int)$editing['is_active'] : 1,
];

$pageTitle = 'Products';
$pageSub   = 'Each product is identified by its SKU and belongs to one category and one supplier.';
$active    = 'products';
require __DIR__ . '/../includes/header.php';
?>
<?php if ($errors): ?>
  <div class="alert alert-error"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="grid grid-main">
  <div class="card">
    <div class="card-head"><div class="card-title">Product catalog</div><div class="card-sub"><?= count($products) ?> products</div></div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>SKU</th><th>Product</th><th>Category</th><th>Supplier</th><th class="num">On hand</th><th class="num">Reorder at</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($products as $p): ?>
          <tr>
            <td class="mono"><?= e($p['sku']) ?></td>
            <td><strong><?= e($p['product_name']) ?></strong></td>
            <td><?= e($p['category_name']) ?></td>
            <td class="muted"><?= e($p['supplier_name']) ?></td>
            <td class="num"><?= fmt_qty($p['total']) ?></td>
            <td class="num"><?= fmt_qty($p['reorder_level']) ?></td>
            <td><?= (int)$p['is_active'] ? '<span class="badge badge-ok">Active</span>' : '<span class="badge badge-off">Inactive</span>' ?></td>
            <td><a class="btn btn-outline btn-sm" href="<?= e(url('manager/products.php?edit=' . urlencode($p['sku']))) ?>"><?= icon('edit', 14) ?> Edit</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="muted" style="margin:12px 0 0">Products that have movement history cannot be deleted. Mark them inactive instead.</p>
  </div>

  <div class="grid" style="align-content:start">
    <div class="card">
      <div class="card-head">
        <div class="card-title"><?= $editing ? 'Edit ' . e($editing['sku']) : 'Add product' ?></div>
        <?php if ($editing): ?><a class="btn btn-outline btn-sm" href="<?= e(url('manager/products.php')) ?>">Cancel</a><?php endif; ?>
      </div>
      <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_product">
        <input type="hidden" name="mode" value="<?= $editing ? 'edit' : 'add' ?>">
        <div class="field">
          <label for="sku">SKU</label>
          <input class="input" id="sku" name="sku" value="<?= e($f['sku']) ?>" <?= $editing ? 'readonly' : '' ?> placeholder="SKU-04" required>
        </div>
        <div class="field">
          <label for="product_name">Product name</label>
          <input class="input" id="product_name" name="product_name" value="<?= e($f['name']) ?>" required>
        </div>
        <div class="field">
          <label for="category_id">Category</label>
          <select class="select" id="category_id" name="category_id" required>
            <option value="">Select category</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int)$c['category_id'] ?>" <?= $f['cat'] === (int)$c['category_id'] ? 'selected' : '' ?>><?= e($c['category_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="supplier_code">Supplier</label>
          <select class="select" id="supplier_code" name="supplier_code" required>
            <option value="">Select supplier</option>
            <?php foreach ($suppliers as $s): ?>
              <option value="<?= e($s['supplier_code']) ?>" <?= $f['sup'] === $s['supplier_code'] ? 'selected' : '' ?>><?= e($s['supplier_name'] . ' (' . $s['supplier_code'] . ')') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="reorder_level">Reorder level</label>
          <input class="input" type="number" min="0" id="reorder_level" name="reorder_level" value="<?= e((string)$f['re']) ?>" required>
          <div class="hint">Flag the product as low stock at or below this quantity.</div>
        </div>
        <?php if ($editing): ?>
          <label style="display:flex;gap:8px;align-items:center;font-weight:500">
            <input type="checkbox" name="is_active" value="1" <?= $f['act'] ? 'checked' : '' ?>> Active (available for new movements)
          </label>
        <?php endif; ?>
        <button class="btn btn-primary" type="submit"><?= $editing ? 'Save changes' : 'Add product' ?></button>
      </form>
    </div>

    <div class="card">
      <div class="card-head"><div class="card-title">Add category</div></div>
      <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_category">
        <div class="field">
          <label for="category_name">Category name</label>
          <input class="input" id="category_name" name="category_name" maxlength="60" required>
        </div>
        <button class="btn btn-outline" type="submit"><?= icon('plus', 16) ?> Add category</button>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
