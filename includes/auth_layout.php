<?php
function auth_open(string $title): void
{
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> | <?= e(APP_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<div class="auth">
  <section class="auth-aside">
    <div class="brand" style="padding:0">
      <span class="brand-mark"><?= icon('box', 20) ?></span>
      <div>
        <div class="brand-name"><?= e(APP_NAME) ?></div>
        <div class="brand-sub"><?= e(APP_TAGLINE) ?></div>
      </div>
    </div>
    <div>
      <h2>One reliable ledger for every pallet that moves.</h2>
      <p>Replace scattered spreadsheets with a single, auditable record of what comes in, goes out and sits on your shelves.</p>
      <div class="auth-points">
        <div><span class="dot"><?= icon('check', 14) ?></span> Live quantity on hand per warehouse</div>
        <div><span class="dot"><?= icon('check', 14) ?></span> Posted movements can never be overwritten</div>
        <div><span class="dot"><?= icon('check', 14) ?></span> Role-based access for managers and staff</div>
      </div>
    </div>
    <div class="brand-sub">Wholesale Distribution Warehouse</div>
  </section>
  <section class="auth-main">
    <div class="auth-card">
<?php
}

function auth_close(): void
{
    ?>
    </div>
  </section>
</div>
</body>
</html>
<?php
}
