<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$user = require_role('staff');   
$pdo = db();
$errors = [];

$products   = $pdo->query('SELECT sku, product_name FROM products WHERE is_active = 1 ORDER BY sku')->fetchAll();
$warehouses = $pdo->query('SELECT warehouse_code, warehouse_name FROM warehouses ORDER BY warehouse_code')->fetchAll();

function on_hand(PDO $pdo, string $sku, string $wh): int
{
    $st = $pdo->prepare(
        "SELECT COALESCE(SUM(CASE movement_type WHEN 'OUT' THEN -quantity ELSE quantity END), 0)
           FROM stock_movements WHERE sku = ? AND warehouse_code = ?"
    );
    $st->execute([$sku, $wh]);
    return (int)$st->fetchColumn();
}

$today = date('Y-m-d');
$form = [
    'date' => $today,
    'sku'  => '',
    'wh'   => '',
    'type' => in_array($_GET['type'] ?? '', ['IN', 'OUT', 'ADJUST'], true) ? $_GET['type'] : 'IN',
    'qty'  => '',
    'remarks' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $form['date']    = (string)($_POST['movement_date'] ?? '');
    $form['sku']     = (string)($_POST['sku'] ?? '');
    $form['wh']      = (string)($_POST['warehouse_code'] ?? '');
    $form['type']    = (string)($_POST['movement_type'] ?? '');
    $form['qty']     = trim((string)($_POST['quantity'] ?? ''));
    $form['remarks'] = trim((string)($_POST['remarks'] ?? ''));

    $d = DateTime::createFromFormat('Y-m-d', $form['date']);
    if (!$d || $d->format('Y-m-d') !== $form['date'])      $errors[] = 'Enter a valid date.';
    elseif ($form['date'] > $today)                         $errors[] = 'The movement date cannot be in the future.';

    if (!in_array($form['type'], ['IN', 'OUT', 'ADJUST'], true)) $errors[] = 'Choose a movement type.';
    if (!in_array($form['wh'], array_column($warehouses, 'warehouse_code'), true)) $errors[] = 'Choose a warehouse.';
    if (!in_array($form['sku'], array_column($products, 'sku'), true))             $errors[] = 'Choose an active product.';

    $qty = filter_var($form['qty'], FILTER_VALIDATE_INT, ['options' => ['min_range' => -1000000, 'max_range' => 1000000]]);
    if ($qty === false || $qty === 0) {
        $errors[] = 'Quantity must be a whole number (not zero).';
    } elseif (in_array($form['type'], ['IN', 'OUT'], true) && $qty < 0) {
        $errors[] = 'IN and OUT quantities must be positive. Use ADJUST for negative corrections.';
    }
    if ($form['type'] === 'ADJUST' && $form['remarks'] === '') {
        $errors[] = 'A reason is required for an adjustment.';
    }
    if (mb_strlen($form['remarks']) > 255) $errors[] = 'Remarks are too long (max 255 characters).';

    if (!$errors) {
        try {
            $pdo->beginTransaction();

          
            $pdo->prepare('SELECT sku FROM products WHERE sku = ? FOR UPDATE')->execute([$form['sku']]);
            $current = on_hand($pdo, $form['sku'], $form['wh']);

            $delta = $form['type'] === 'OUT' ? -$qty : $qty;
            if ($current + $delta < 0) {
                $pdo->rollBack();
                $errors[] = "Insufficient stock. {$form['wh']} has only " . fmt_qty($current)
                          . " unit(s) of {$form['sku']}, so this movement would make stock negative.";
            } else {
                $pdo->prepare(
                    'INSERT INTO stock_movements (movement_date, sku, warehouse_code, movement_type, quantity, handled_by, remarks)
                     VALUES (?,?,?,?,?,?,?)'
                )->execute([$form['date'], $form['sku'], $form['wh'], $form['type'], $qty, $user['id'], $form['remarks'] ?: null]);
                $newId = (int)$pdo->lastInsertId();
                $pdo->commit();

                flash('success', movement_code($newId) . ' posted. ' . $form['wh'] . ' now holds '
                    . fmt_qty($current + $delta) . ' unit(s) of ' . $form['sku'] . '.');
                redirect('staff/my_movements.php');
            }
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            // SQLSTATE 45000 = message raised by our own database triggers
            $errors[] = ($ex->getCode() === '45000')
                ? $ex->errorInfo[2]
                : 'The movement could not be saved. Please try again.';
        }
    }
}


$map = [];
foreach ($pdo->query('SELECT sku, warehouse_code, on_hand FROM v_stock_on_hand')->fetchAll() as $r) {
    $map[$r['sku'] . '|' . $r['warehouse_code']] = (int)$r['on_hand'];
}

$pageTitle = 'Record Movement';
$pageSub   = 'Posted movements cannot be edited or deleted, so double-check before you submit.';
$active    = 'record';
require __DIR__ . '/../includes/header.php';
?>
<?php if ($errors): ?>
  <div class="alert alert-error"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="grid grid-main">
  <div class="card">
    <form method="post" class="form-grid" id="movementForm" novalidate>
      <?= csrf_field() ?>

      <div class="field">
        <label>Movement type</label>
        <div class="segmented">
          <?php foreach (['IN' => 'Stock received', 'OUT' => 'Stock dispatched', 'ADJUST' => 'Count correction'] as $t => $desc): ?>
            <input type="radio" name="movement_type" id="t_<?= $t ?>" value="<?= $t ?>" <?= $form['type'] === $t ? 'checked' : '' ?>>
            <label for="t_<?= $t ?>"><?= $t ?><small><?= e($desc) ?></small></label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="form-grid cols-2">
        <div class="field">
          <label for="sku">Product</label>
          <select class="select" id="sku" name="sku" required>
            <option value="">Select product</option>
            <?php foreach ($products as $p): ?>
              <option value="<?= e($p['sku']) ?>" <?= $form['sku'] === $p['sku'] ? 'selected' : '' ?>><?= e($p['sku'] . ' - ' . $p['product_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="warehouse_code">Warehouse location</label>
          <select class="select" id="warehouse_code" name="warehouse_code" required>
            <option value="">Select warehouse</option>
            <?php foreach ($warehouses as $w): ?>
              <option value="<?= e($w['warehouse_code']) ?>" <?= $form['wh'] === $w['warehouse_code'] ? 'selected' : '' ?>><?= e($w['warehouse_code'] . ' - ' . $w['warehouse_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="stock-hint" id="stockHint" hidden>
        <span>Currently on hand at this location</span><strong id="stockValue">0</strong>
      </div>

      <div class="form-grid cols-2">
        <div class="field">
          <label for="movement_date">Date</label>
          <input class="input" type="date" id="movement_date" name="movement_date" value="<?= e($form['date']) ?>" max="<?= e($today) ?>" required>
        </div>
        <div class="field">
          <label for="quantity">Quantity</label>
          <input class="input" type="number" step="1" id="quantity" name="quantity" value="<?= e($form['qty']) ?>" required>
          <div class="hint" id="qtyHint">Whole units received into the warehouse.</div>
        </div>
      </div>

      <div class="field">
        <label for="remarks">Remarks <span class="muted" id="remarksReq">(optional)</span></label>
        <textarea class="textarea" id="remarks" name="remarks" maxlength="255" placeholder="Delivery receipt no., customer order, reason for adjustment..."><?= e($form['remarks']) ?></textarea>
      </div>

      <div class="btn-row">
        <button class="btn btn-primary" type="submit"><?= icon('check', 16) ?> Post movement</button>
        <a class="btn btn-outline" href="<?= e(url('staff/dashboard.php')) ?>">Cancel</a>
      </div>
    </form>
  </div>

  <div class="card" style="align-self:start">
    <div class="card-title" style="margin-bottom:10px">Rules that apply</div>
    <ul style="margin:0;padding-left:18px;color:var(--brown-900);display:grid;gap:8px">
      <li><strong>IN</strong> adds to stock and needs a positive quantity.</li>
      <li><strong>OUT</strong> removes stock and is refused if it would go below zero.</li>
      <li><strong>ADJUST</strong> accepts a signed quantity (for example <span class="mono">-2</span> for damaged units) and needs a reason.</li>
      <li>Every movement is stamped with your employee ID: <strong><?= e($user['code']) ?></strong>.</li>
      <li>Mistakes are corrected with a new ADJUST movement, never by editing history.</li>
    </ul>
  </div>
</div>

<script>
(function () {
  var stock = <?= json_encode($map, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var sku = document.getElementById('sku');
  var wh = document.getElementById('warehouse_code');
  var hint = document.getElementById('stockHint');
  var val = document.getElementById('stockValue');
  var qty = document.getElementById('quantity');
  var qtyHint = document.getElementById('qtyHint');
  var req = document.getElementById('remarksReq');
  var messages = {
    IN: 'Whole units received into the warehouse.',
    OUT: 'Whole units leaving the warehouse. Cannot exceed the quantity on hand.',
    ADJUST: 'Signed whole number: use a negative value to remove stock, positive to add.'
  };

  function currentType() {
    var r = document.querySelector('input[name="movement_type"]:checked');
    return r ? r.value : 'IN';
  }
  function refresh() {
    var t = currentType();
    qtyHint.textContent = messages[t];
    qty.min = t === 'ADJUST' ? '' : '1';
    req.textContent = t === 'ADJUST' ? '(required for adjustments)' : '(optional)';
    if (sku.value && wh.value) {
      hint.hidden = false;
      val.textContent = (stock[sku.value + '|' + wh.value] || 0).toLocaleString();
    } else {
      hint.hidden = true;
    }
  }
  [sku, wh].forEach(function (el) { el.addEventListener('change', refresh); });
  document.querySelectorAll('input[name="movement_type"]').forEach(function (el) { el.addEventListener('change', refresh); });
  refresh();
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
