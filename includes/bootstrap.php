<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';


if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            exit('<h2 style="font-family:sans-serif;color:#5d4037">Database connection failed.</h2>'
               . '<p style="font-family:sans-serif">Start MySQL in XAMPP, import <code>database/warehouse_inventory.sql</code>, '
               . 'and check <code>config/config.php</code>.</p>');
        }
    }
    return $pdo;
}

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function pull_flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

function movement_code(int $id): string
{
    return 'MV-' . str_pad((string)$id, 2, '0', STR_PAD_LEFT);
}

function fmt_date(string $date): string
{
    $t = strtotime($date);
    return $t ? date('M j, Y', $t) : $date;
}

function fmt_qty(int|string $n): string
{
    return number_format((int)$n);
}

function type_badge(string $type): string
{
    $map = ['IN' => 'badge-in', 'OUT' => 'badge-out', 'ADJUST' => 'badge-adjust'];
    return '<span class="badge ' . ($map[$type] ?? '') . '">' . e($type) . '</span>';
}

function signed_qty(string $type, int $qty): string
{
    if ($type === 'OUT') {
        return '<span class="qty-neg">-' . fmt_qty($qty) . '</span>';
    }
    if ($type === 'ADJUST' && $qty < 0) {
        return '<span class="qty-neg">' . fmt_qty($qty) . '</span>';
    }
    return '<span class="qty-pos">+' . fmt_qty($qty) . '</span>';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(419);
        exit('Your session expired or the request was invalid. Go back, refresh the page and try again.');
    }
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function dashboard_path(string $role): string
{
    return $role === 'manager' ? 'manager/dashboard.php' : 'staff/dashboard.php';
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        flash('warning', 'Please sign in to continue.');
        redirect('login.php');
    }

    $st = db()->prepare('SELECT role, is_active FROM users WHERE user_id = ?');
    $st->execute([$user['id']]);
    $row = $st->fetch();
    if (!$row || (int)$row['is_active'] !== 1) {
        $_SESSION = [];
        session_regenerate_id(true);
        flash('error', 'Your account is no longer active.');
        redirect('login.php');
    }
    if ($row['role'] !== $user['role']) {
        $_SESSION['user']['role'] = $row['role'];
        $user['role'] = $row['role'];
    }
    return $user;
}

function require_role(string ...$roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        $pageTitle = 'Access denied';
        $active = '';
        require __DIR__ . '/header.php';
        echo '<div class="empty-state"><div class="empty-icon">' . icon('lock', 36) . '</div>'
           . '<h2>403 - Access denied</h2>'
           . '<p>Your role (<strong>' . e($user['role']) . '</strong>) is not allowed to open this page.</p>'
           . '<a class="btn btn-primary" href="' . e(url(dashboard_path($user['role']))) . '">Back to my dashboard</a></div>';
        require __DIR__ . '/footer.php';
        exit;
    }
    return $user;
}

function guest_only(): void
{
    if (is_logged_in()) {
        redirect(dashboard_path(current_user()['role']));
    }
}

function icon(string $name, int $size = 18): string
{
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        'box'       => '<path d="M21 8 12 3 3 8v8l9 5 9-5V8z"/><path d="M3 8l9 5 9-5M12 13v8"/>',
        'layers'    => '<path d="M12 2 2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5M2 12l10 5 10-5"/>',
        'list'      => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
        'truck'     => '<path d="M1 3h15v13H1zM16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2"/><circle cx="18.5" cy="18.5" r="2"/>',
        'users'     => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'plus'      => '<path d="M12 5v14M5 12h14"/>',
        'logout'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'arrow-in'  => '<path d="M12 3v12M7 10l5 5 5-5M5 21h14"/>',
        'arrow-out' => '<path d="M12 21V9M7 14l5-5 5 5M5 3h14"/>',
        'sliders'   => '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/>',
        'alert'     => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0zM12 9v4M12 17h.01"/>',
        'lock'      => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'check'     => '<path d="M20 6 9 17l-5-5"/>',
        'edit'      => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7M18.5 2.5a2.1 2.1 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>',
        'shield'    => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'search'    => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
    ];
    $p = $paths[$name] ?? '';
    return '<svg class="icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" '
         . 'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}
