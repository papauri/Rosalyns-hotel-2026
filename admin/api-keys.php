<?php
/**
 * Admin Interface for Managing API Keys
 */

require_once 'admin-init.php';

if (($_SESSION['admin_role'] ?? '') !== 'admin') {
    header('Location: ' . BASE_URL . 'admin/dashboard.php');
    exit;
}

if (function_exists('ensureApiKeyRetrievableColumn')) {
    ensureApiKeyRetrievableColumn($pdo);
}

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'create_key':
                $clientName = trim((string)($_POST['client_name'] ?? ''));
                $clientWebsite = trim((string)($_POST['client_website'] ?? ''));
                $clientEmail = trim((string)($_POST['client_email'] ?? ''));
                $rateLimit = max(1, (int)($_POST['rate_limit_per_hour'] ?? 100));
                $permissions = $_POST['permissions'] ?? [];

                if ($clientName === '' || $clientEmail === '') {
                    throw new RuntimeException('Client name and email are required.');
                }

                $rawApiKey = bin2hex(random_bytes(32));
                $hashedApiKey = password_hash($rawApiKey, PASSWORD_DEFAULT);
                $encryptedApiKey = encryptApiKey($rawApiKey);

                $stmt = $pdo->prepare(
                    'INSERT INTO api_keys (api_key, api_key_plain, client_name, client_website, client_email, permissions, rate_limit_per_hour, is_active)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
                );
                $stmt->execute([
                    $hashedApiKey,
                    $encryptedApiKey,
                    $clientName,
                    $clientWebsite,
                    $clientEmail,
                    json_encode(array_values((array)$permissions)),
                    $rateLimit,
                ]);

                $safeClientName = htmlspecialchars($clientName, ENT_QUOTES, 'UTF-8');
                $safeRaw = htmlspecialchars($rawApiKey, ENT_QUOTES, 'UTF-8');
                $message = "API key created successfully.<br><br><strong>Client:</strong> {$safeClientName}<br><strong>API Key:</strong> <code>{$safeRaw}</code><br><br><strong>Important:</strong> You can reveal/copy this key anytime from the list below.";
                $messageType = 'success';
                break;

            case 'toggle_status':
                $keyId = (int)($_POST['key_id'] ?? 0);
                $isActive = (int)($_POST['is_active'] ?? 0) === 1 ? 1 : 0;

                $stmt = $pdo->prepare('UPDATE api_keys SET is_active = ? WHERE id = ?');
                $stmt->execute([$isActive, $keyId]);

                $message = 'API key status updated successfully.';
                $messageType = 'success';
                break;

            case 'regenerate_key':
                $keyId = (int)($_POST['key_id'] ?? 0);
                $rawApiKey = bin2hex(random_bytes(32));
                $hashedApiKey = password_hash($rawApiKey, PASSWORD_DEFAULT);
                $encryptedApiKey = encryptApiKey($rawApiKey);

                $stmt = $pdo->prepare('UPDATE api_keys SET api_key = ?, api_key_plain = ?, usage_count = 0, last_used_at = NULL WHERE id = ?');
                $stmt->execute([$hashedApiKey, $encryptedApiKey, $keyId]);

                $nameStmt = $pdo->prepare('SELECT client_name FROM api_keys WHERE id = ?');
                $nameStmt->execute([$keyId]);
                $client = $nameStmt->fetch(PDO::FETCH_ASSOC) ?: ['client_name' => 'Client'];

                $safeClientName = htmlspecialchars((string)$client['client_name'], ENT_QUOTES, 'UTF-8');
                $safeRaw = htmlspecialchars($rawApiKey, ENT_QUOTES, 'UTF-8');
                $message = "API key regenerated for <strong>{$safeClientName}</strong>.<br><br><strong>New API Key:</strong> <code>{$safeRaw}</code><br><br><strong>Important:</strong> You can reveal/copy this key anytime from the list below.";
                $messageType = 'success';
                break;

            case 'delete_key':
                $keyId = (int)($_POST['key_id'] ?? 0);

                $stmt = $pdo->prepare('DELETE FROM api_keys WHERE id = ?');
                $stmt->execute([$keyId]);

                $message = 'API key deleted successfully.';
                $messageType = 'success';
                break;
        }
    } catch (Throwable $e) {
        $message = 'Error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        $messageType = 'error';
    }
}

$apiKeys = [];
try {
    $stmt = $pdo->query(
        'SELECT ak.*, 
                (SELECT COUNT(*) FROM api_usage_logs WHERE api_key_id = ak.id) AS total_calls,
                (SELECT COUNT(*) FROM api_usage_logs WHERE api_key_id = ak.id AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)) AS calls_last_hour
         FROM api_keys ak
         ORDER BY ak.created_at DESC'
    );
    $apiKeys = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    if ($message === '') {
        $message = 'Error loading API keys: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        $messageType = 'error';
    }
}

$availablePermissions = [
    'rooms.read' => 'Read room information',
    'availability.check' => 'Check room availability',
    'bookings.create' => 'Create bookings',
    'bookings.read' => 'Read booking details',
    'bookings.update' => 'Update bookings',
    'bookings.delete' => 'Delete bookings',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Keys Management - Admin Panel</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/base/reset.css">
    <link rel="stylesheet" href="../css/base/typography.css">
    <link rel="stylesheet" href="../css/base/variables.css">
    <link rel="stylesheet" href="../css/components/buttons.css">
    <link rel="stylesheet" href="../css/components/cards.css">
    <link rel="stylesheet" href="../css/components/forms.css">
    <link rel="stylesheet" href="../css/utilities/animations.css">

    <style>
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; min-height: 100vh; }
        .admin-container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .admin-page-hero { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px; margin-bottom: 20px; }
        .admin-page-hero h1 { font-family: 'Playfair Display', serif; margin: 0 0 8px; }
        .card { background: #fff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .card-header { padding: 16px 20px; border-bottom: 1px solid #eee; }
        .card-body { padding: 20px; }
        .table-responsive { overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; vertical-align: top; }
        .table th { background: #f8f9fa; font-weight: 600; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: .85rem; font-weight: 600; }
        .badge-success { background: #28a745; color: #fff; }
        .badge-danger { background: #dc3545; color: #fff; }
        .badge-warning { background: #ffc107; color: #333; }
        .text-muted { color: #6c757d; }
        .btn { display: inline-block; padding: 8px 14px; border: 0; border-radius: 5px; cursor: pointer; }
        .btn-sm { padding: 5px 9px; font-size: .8rem; }
        .btn-primary { background: #667eea; color: #fff; }
        .btn-success { background: #28a745; color: #fff; }
        .btn-warning { background: #ffc107; color: #222; }
        .btn-danger { background: #dc3545; color: #fff; }
        .alert { padding: 14px 16px; border-radius: 6px; margin-bottom: 16px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .row { display: flex; flex-wrap: wrap; margin: -10px; }
        .col-md-8 { flex: 0 0 66.6667%; max-width: 66.6667%; padding: 10px; }
        .col-md-4 { flex: 0 0 33.3333%; max-width: 33.3333%; padding: 10px; }
        @media (max-width: 900px) { .col-md-8, .col-md-4 { flex: 0 0 100%; max-width: 100%; } }

        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 6px; }
        .form-control { width: 100%; border: 1px solid #ddd; border-radius: 5px; padding: 10px; }
        .checkbox-group { display: flex; flex-wrap: wrap; gap: 8px 12px; }
        .checkbox-item { display: inline-flex; gap: 6px; align-items: center; }

        .api-key-display { display: flex; align-items: center; gap: 6px; }
        .api-key-value { font-family: Consolas, monospace; background: #f8f9fa; padding: 4px 8px; border-radius: 4px; max-width: 240px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .api-key-value.revealed { max-width: none; white-space: normal; word-break: break-all; }
        .btn-api-key { border: 0; background: transparent; cursor: pointer; border-radius: 4px; padding: 3px 6px; }
        .btn-api-key:hover { background: #efefef; }
        .legacy-key-badge { font-size: 10px; background: #6c757d; color: #fff; padding: 2px 6px; border-radius: 10px; }
    </style>
</head>
<body>
<?php
$current_page = 'api-keys.php';
require_once 'includes/admin-header.php';
?>

<div class="admin-container">
    <div class="admin-page-hero">
        <h1><i class="fas fa-key"></i> API Keys Management</h1>
        <p>Manage external API keys for booking integrations.</p>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : 'success'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> API Keys</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($apiKeys)): ?>
                        <div class="alert alert-info">No API keys found. Create one using the form on the right.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                <tr>
                                    <th>Client</th>
                                    <th>API Key</th>
                                    <th>Website</th>
                                    <th>Usage</th>
                                    <th>Rate Limit</th>
                                    <th>Status</th>
                                    <th>Last Used</th>
                                    <th>Actions</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($apiKeys as $key): ?>
                                    <?php
                                    $decryptedKey = decryptApiKey((string)($key['api_key_plain'] ?? ''));
                                    $hasPlainKey = !empty($decryptedKey);
                                    $masked = str_repeat('•', 32);
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars((string)$key['client_name'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars((string)$key['client_email'], ENT_QUOTES, 'UTF-8'); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($hasPlainKey): ?>
                                                <div class="api-key-display">
                                                    <code
                                                        class="api-key-value"
                                                        id="key_<?php echo (int)$key['id']; ?>"
                                                        data-key="<?php echo htmlspecialchars((string)$decryptedKey, ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-masked="<?php echo $masked; ?>"
                                                    ><?php echo $masked; ?></code>
                                                    <button type="button" class="btn-api-key reveal-btn" data-target="key_<?php echo (int)$key['id']; ?>" title="Reveal key">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button type="button" class="btn-api-key copy-btn" data-target="key_<?php echo (int)$key['id']; ?>" title="Copy key">
                                                        <i class="fas fa-copy"></i>
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">Hidden</span>
                                                <span class="legacy-key-badge">legacy</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars((string)($key['client_website'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <strong><?php echo (int)($key['total_calls'] ?? 0); ?></strong> total<br>
                                            <small class="text-muted"><?php echo (int)($key['calls_last_hour'] ?? 0); ?> last hour</small>
                                        </td>
                                        <td><?php echo (int)($key['rate_limit_per_hour'] ?? 0); ?>/hour</td>
                                        <td>
                                            <?php if ((int)($key['is_active'] ?? 0) === 1): ?>
                                                <span class="badge badge-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Disabled</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo !empty($key['last_used_at']) ? htmlspecialchars((string)$key['last_used_at'], ENT_QUOTES, 'UTF-8') : '<span class="text-muted">Never</span>'; ?>
                                        </td>
                                        <td>
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="key_id" value="<?php echo (int)$key['id']; ?>">
                                                <input type="hidden" name="is_active" value="<?php echo (int)($key['is_active'] ?? 0) === 1 ? 0 : 1; ?>">
                                                <button type="submit" class="btn btn-sm <?php echo (int)($key['is_active'] ?? 0) === 1 ? 'btn-warning' : 'btn-success'; ?>">
                                                    <?php echo (int)($key['is_active'] ?? 0) === 1 ? 'Disable' : 'Enable'; ?>
                                                </button>
                                            </form>

                                            <form method="post" style="display:inline; margin-left:4px;">
                                                <input type="hidden" name="action" value="regenerate_key">
                                                <input type="hidden" name="key_id" value="<?php echo (int)$key['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('Regenerate API key for this client?');">Regenerate</button>
                                            </form>

                                            <form method="post" style="display:inline; margin-left:4px;">
                                                <input type="hidden" name="action" value="delete_key">
                                                <input type="hidden" name="key_id" value="<?php echo (int)$key['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete API key for this client? This cannot be undone.');">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-plus-circle"></i> Create API Key</h3>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="create_key">

                        <div class="form-group">
                            <label for="client_name">Client Name</label>
                            <input id="client_name" name="client_name" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="client_email">Client Email</label>
                            <input id="client_email" name="client_email" type="email" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="client_website">Client Website</label>
                            <input id="client_website" name="client_website" class="form-control" placeholder="https://example.com">
                        </div>

                        <div class="form-group">
                            <label for="rate_limit_per_hour">Rate Limit Per Hour</label>
                            <input id="rate_limit_per_hour" name="rate_limit_per_hour" type="number" min="1" value="100" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label>Permissions</label>
                            <div class="checkbox-group">
                                <?php foreach ($availablePermissions as $permKey => $permLabel): ?>
                                    <label class="checkbox-item">
                                        <input type="checkbox" name="permissions[]" value="<?php echo htmlspecialchars($permKey, ENT_QUOTES, 'UTF-8'); ?>">
                                        <span><?php echo htmlspecialchars($permLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">Create API Key</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.reveal-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = document.getElementById(btn.getAttribute('data-target'));
            if (!target) return;

            var isRevealed = target.classList.contains('revealed');
            if (isRevealed) {
                target.textContent = target.getAttribute('data-masked') || '••••••••••••••••••••••••••••••••';
                target.classList.remove('revealed');
                btn.innerHTML = '<i class="fas fa-eye"></i>';
                btn.title = 'Reveal key';
            } else {
                target.textContent = target.getAttribute('data-key') || '';
                target.classList.add('revealed');
                btn.innerHTML = '<i class="fas fa-eye-slash"></i>';
                btn.title = 'Hide key';
            }
        });
    });

    document.querySelectorAll('.copy-btn').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            var target = document.getElementById(btn.getAttribute('data-target'));
            if (!target) return;
            var key = target.getAttribute('data-key') || '';
            if (!key) return;

            try {
                await navigator.clipboard.writeText(key);
                var prev = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i>';
                setTimeout(function () { btn.innerHTML = prev; }, 1200);
            } catch (e) {
                var temp = document.createElement('textarea');
                temp.value = key;
                document.body.appendChild(temp);
                temp.select();
                document.execCommand('copy');
                document.body.removeChild(temp);
            }
        });
    });
});
</script>
</body>
</html>

