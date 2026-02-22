<?php
/**
 * Admin - Visitor Analytics
 * View website visitor sessions: who visited, from where, which device, etc.
 */

require_once 'admin-init.php';

if (!hasPermission($user['id'], 'reports')) {
    header('Location: dashboard.php?error=access_denied');
    exit;
}

$site_name = getSetting('site_name');
$filter_device = $_GET['device'] ?? '';
$filter_range = $_GET['range'] ?? 'today';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
            throw new RuntimeException('Invalid security token. Please refresh and try again.');
        }

        $action = $_POST['action'] ?? '';
        if ($action === 'cleanup_analytics') {
            $retentionDays = (int)($_POST['retention_days'] ?? 90);
            if (!in_array($retentionDays, [30, 60, 90, 180, 365], true)) {
                throw new RuntimeException('Invalid retention period selected.');
            }

            $cutoff = date('Y-m-d H:i:s', strtotime('-' . $retentionDays . ' days'));
            $pdo->beginTransaction();

            $siteDeleted = 0;
            $sessionDeleted = 0;
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'site_visitors'");
            if ($tableCheck && $tableCheck->rowCount() > 0) {
                $deleteSite = $pdo->prepare("DELETE FROM site_visitors WHERE created_at < ?");
                $deleteSite->execute([$cutoff]);
                $siteDeleted = $deleteSite->rowCount();
            }

            $sessionTableCheck = $pdo->query("SHOW TABLES LIKE 'session_logs'");
            if ($sessionTableCheck && $sessionTableCheck->rowCount() > 0) {
                $deleteSession = $pdo->prepare("DELETE FROM session_logs WHERE last_activity < ?");
                $deleteSession->execute([$cutoff]);
                $sessionDeleted = $deleteSession->rowCount();
            }

            $pdo->commit();
            $message = "Visitor analytics cleaned successfully. Removed {$siteDeleted} visitor rows and {$sessionDeleted} session rows older than {$retentionDays} days.";
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

if (!in_array($filter_device, ['', 'all', 'desktop', 'mobile', 'tablet', 'bot', 'unknown'], true)) {
    $filter_device = 'all';
}

if (!in_array($filter_range, ['today', '7days', '30days', 'custom'], true)) {
    $filter_range = 'today';
}

function normalizeCountryLabel(?string $country): string {
    $country = trim((string)$country);
    if ($country === '') {
        return 'Unknown';
    }
    if (stripos($country, ',') !== false) {
        $country = trim((string)substr($country, (int)strrpos($country, ',') + 1));
    }
    return $country !== '' ? $country : 'Unknown';
}

function sectionFromPageUrl(?string $url): string {
    $url = trim((string)$url);
    if ($url === '') {
        return 'Unknown';
    }
    $path = parse_url($url, PHP_URL_PATH) ?: $url;
    $path = trim((string)$path, '/');
    if ($path === '') {
        return 'Home';
    }

    $last = basename($path);
    $slug = strtolower((string)preg_replace('/\.php$/i', '', $last));
    $slug = trim((string)preg_replace('/[^a-z0-9_-]+/i', '-', $slug), '-');
    if ($slug === '' || $slug === 'index') {
        return 'Home';
    }
    return ucwords(str_replace(['-', '_'], ' ', $slug));
}

// Build date range
switch ($filter_range) {
    case 'today':
        $date_start = date('Y-m-d 00:00:00');
        $date_end = date('Y-m-d 23:59:59');
        break;
    case '7days':
        $date_start = date('Y-m-d 00:00:00', strtotime('-7 days'));
        $date_end = date('Y-m-d 23:59:59');
        break;
    case '30days':
        $date_start = date('Y-m-d 00:00:00', strtotime('-30 days'));
        $date_end = date('Y-m-d 23:59:59');
        break;
    case 'custom':
        $date_start = ($_GET['date_start'] ?? date('Y-m-d')) . ' 00:00:00';
        $date_end = ($_GET['date_end'] ?? date('Y-m-d')) . ' 23:59:59';
        break;
    default:
        $date_start = date('Y-m-d 00:00:00');
        $date_end = date('Y-m-d 23:59:59');
}

$table_exists = false;
$stats = ['total_views' => 0, 'unique_sessions' => 0, 'unique_ips' => 0, 'new_visitors' => 0];
$devices = [];
$browsers = [];
$operating_systems = [];
$top_pages = [];
$all_pages = [];
$top_sections = [];
$country_breakdown = [];
$top_ips = [];
$referrers = [];
$visitors = [];
$hourly_data = array_fill(0, 24, 0);
$total_visitor_rows = 0;
$page_num = max(1, (int)($_GET['page'] ?? 1));
$per_page = 50;
$total_pages = 1;

try {
    // Check if table exists
    $table_check = $pdo->query("SHOW TABLES LIKE 'site_visitors'");
    $table_exists = $table_check->rowCount() > 0;

    if ($table_exists) {
        $where = "created_at BETWEEN ? AND ?";
        $params = [$date_start, $date_end];

        if ($filter_device && $filter_device !== 'all') {
            $where .= " AND device_type = ?";
            $params[] = $filter_device;
        }

        // Summary stats
        $stats_sql = "SELECT
            COUNT(*) as total_views,
            COUNT(DISTINCT session_id) as unique_sessions,
            COUNT(DISTINCT ip_address) as unique_ips,
            SUM(is_first_visit) as new_visitors
            FROM site_visitors WHERE {$where}";

        $stats_stmt = $pdo->prepare($stats_sql);
        $stats_stmt->execute($params);
        $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC) ?: $stats;

        // Device breakdown
        $device_stmt = $pdo->prepare(" 
            SELECT device_type, COUNT(*) as count, COUNT(DISTINCT session_id) as sessions
            FROM site_visitors WHERE {$where}
            GROUP BY device_type ORDER BY count DESC
        ");
        $device_stmt->execute($params);
        $devices = $device_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Browser breakdown
        $browser_stmt = $pdo->prepare(" 
            SELECT browser, COUNT(*) as count
            FROM site_visitors WHERE {$where}
            GROUP BY browser ORDER BY count DESC LIMIT 10
        ");
        $browser_stmt->execute($params);
        $browsers = $browser_stmt->fetchAll(PDO::FETCH_ASSOC);

        // OS breakdown
        $os_stmt = $pdo->prepare(" 
            SELECT os, COUNT(*) as count
            FROM site_visitors WHERE {$where}
            GROUP BY os ORDER BY count DESC LIMIT 10
        ");
        $os_stmt->execute($params);
        $operating_systems = $os_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Top pages
        $pages_stmt = $pdo->prepare(" 
            SELECT page_url, COUNT(*) as views, COUNT(DISTINCT session_id) as unique_views
            FROM site_visitors WHERE {$where}
            GROUP BY page_url ORDER BY views DESC LIMIT 15
        ");
        $pages_stmt->execute($params);
        $top_pages = $pages_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Most visited sections (derived from page URL)
        $all_pages_stmt = $pdo->prepare(" 
            SELECT page_url, COUNT(*) as views, COUNT(DISTINCT session_id) as unique_views
            FROM site_visitors
            WHERE {$where}
            GROUP BY page_url
            ORDER BY views DESC
        ");
        $all_pages_stmt->execute($params);
        $all_pages = $all_pages_stmt->fetchAll(PDO::FETCH_ASSOC);

        $sectionAccumulator = [];
        foreach ($all_pages as $pg) {
            $section = sectionFromPageUrl($pg['page_url'] ?? '');
            if (!isset($sectionAccumulator[$section])) {
                $sectionAccumulator[$section] = ['section' => $section, 'views' => 0, 'unique_views' => 0];
            }
            $sectionAccumulator[$section]['views'] += (int)$pg['views'];
            $sectionAccumulator[$section]['unique_views'] += (int)$pg['unique_views'];
        }
        $top_sections = array_values($sectionAccumulator);
        usort($top_sections, static function (array $a, array $b): int {
            return $b['views'] <=> $a['views'];
        });
        $top_sections = array_slice($top_sections, 0, 12);

        // Country breakdown
        $country_stmt = $pdo->prepare(" 
            SELECT country, COUNT(*) as count, COUNT(DISTINCT session_id) as sessions
            FROM site_visitors
            WHERE {$where}
            GROUP BY country
            ORDER BY count DESC
            LIMIT 15
        ");
        $country_stmt->execute($params);
        $country_rows = $country_stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($country_rows as $row) {
            $label = normalizeCountryLabel($row['country'] ?? null);
            if (!isset($country_breakdown[$label])) {
                $country_breakdown[$label] = ['country' => $label, 'count' => 0, 'sessions' => 0];
            }
            $country_breakdown[$label]['count'] += (int)$row['count'];
            $country_breakdown[$label]['sessions'] += (int)$row['sessions'];
        }
        $country_breakdown = array_values($country_breakdown);
        usort($country_breakdown, static function (array $a, array $b): int {
            return $b['count'] <=> $a['count'];
        });

        // Top referrers
        $ref_stmt = $pdo->prepare(" 
            SELECT referrer_domain, COUNT(*) as count
            FROM site_visitors WHERE {$where} AND referrer_domain != '' AND referrer_domain IS NOT NULL
            GROUP BY referrer_domain ORDER BY count DESC LIMIT 10
        ");
        $ref_stmt->execute($params);
        $referrers = $ref_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Top visitor IPs
        $ip_stmt = $pdo->prepare(" 
            SELECT ip_address, country, COUNT(*) AS views, COUNT(DISTINCT session_id) AS sessions
            FROM site_visitors
            WHERE {$where}
            GROUP BY ip_address, country
            ORDER BY views DESC
            LIMIT 20
        ");
        $ip_stmt->execute($params);
        $top_ips = $ip_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Recent visitors (paginated)
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM site_visitors WHERE {$where}");
        $count_stmt->execute($params);
        $total_visitor_rows = (int)$count_stmt->fetchColumn();
        $total_pages = max(1, (int)ceil($total_visitor_rows / $per_page));
        if ($page_num > $total_pages) {
            $page_num = $total_pages;
        }
        $offset = ($page_num - 1) * $per_page;

        $visitors_sql = "SELECT * FROM site_visitors WHERE {$where} ORDER BY created_at DESC LIMIT {$per_page} OFFSET {$offset}";
        $visitors_stmt = $pdo->prepare($visitors_sql);
        $visitors_stmt->execute($params);
        $visitors = $visitors_stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($visitors as &$visitor) {
            $visitor['country_display'] = normalizeCountryLabel($visitor['country'] ?? null);
        }
        unset($visitor);

        // Hourly distribution
        $hourly_stmt = $pdo->prepare(" 
            SELECT HOUR(created_at) as hour, COUNT(*) as count
            FROM site_visitors WHERE {$where}
            GROUP BY HOUR(created_at) ORDER BY hour
        ");
        $hourly_stmt->execute($params);
        $hourly = $hourly_stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($hourly as $h) {
            $hourly_data[$h['hour']] = (int)$h['count'];
        }
    }
} catch (PDOException $e) {
    $table_exists = false;
    error_log('Visitor analytics error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor Analytics - <?php echo htmlspecialchars($site_name); ?> Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400;1,500&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="css/admin-styles.css">
    <link rel="stylesheet" href="css/admin-components.css">
    <link rel="stylesheet" href="css/visitor-analytics.css">
</head>
<body>
    <?php require_once 'includes/admin-header.php'; ?>

    <div class="content">
        <div class="analytics-container">
            <div class="page-header">
                <div>
                    <h1 class="page-title"><i class="fas fa-chart-line" style="color: var(--gold);"></i> Visitor Analytics</h1>
                    <p style="color: #888; margin-top: 4px;">Monitor your website traffic and visitor behavior</p>
                </div>
                <form method="POST" class="cleanup-inline-form">
                    <input type="hidden" name="action" value="cleanup_analytics">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <label for="retention_days" class="cleanup-inline-label">Cleanup older than</label>
                    <select id="retention_days" name="retention_days" class="cleanup-inline-select">
                        <option value="30">30 days</option>
                        <option value="60">60 days</option>
                        <option value="90" selected>90 days</option>
                        <option value="180">180 days</option>
                        <option value="365">365 days</option>
                    </select>
                    <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Cleanup old visitor analytics data now?')">
                        <i class="fas fa-broom"></i> Cleanup
                    </button>
                </form>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success" style="margin-bottom: 14px;"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error" style="margin-bottom: 14px;"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!$table_exists): ?>
                <div class="no-data">
                    <i class="fas fa-database"></i>
                    <h3>Visitor tracking not yet initialized</h3>
                    <p>The tracking table will be created automatically when the first visitor accesses your website.</p>
                    <p style="margin-top: 10px;">Or run the migration: <code>Database/migrations/002_create_site_visitors.sql</code></p>
                </div>
            <?php else: ?>

            <!-- Filters -->
            <form class="filter-bar" method="GET">
                <label style="font-weight: 600; color: var(--navy); font-size: 13px;"><i class="fas fa-filter"></i> Period:</label>
                <select name="range" onchange="toggleCustomDates(this.value)">
                    <option value="today" <?php echo $filter_range === 'today' ? 'selected' : ''; ?>>Today</option>
                    <option value="7days" <?php echo $filter_range === '7days' ? 'selected' : ''; ?>>Last 7 Days</option>
                    <option value="30days" <?php echo $filter_range === '30days' ? 'selected' : ''; ?>>Last 30 Days</option>
                    <option value="custom" <?php echo $filter_range === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                </select>
                <div id="customDates" style="display: <?php echo $filter_range === 'custom' ? 'flex' : 'none'; ?>; gap: 8px; align-items: center;">
                    <input type="date" name="date_start" value="<?php echo htmlspecialchars($_GET['date_start'] ?? date('Y-m-d')); ?>">
                    <span>to</span>
                    <input type="date" name="date_end" value="<?php echo htmlspecialchars($_GET['date_end'] ?? date('Y-m-d')); ?>">
                </div>
                <label style="font-weight: 600; color: var(--navy); font-size: 13px;">Device:</label>
                <select name="device">
                    <option value="all" <?php echo $filter_device === 'all' || empty($filter_device) ? 'selected' : ''; ?>>All Devices</option>
                    <option value="desktop" <?php echo $filter_device === 'desktop' ? 'selected' : ''; ?>>Desktop</option>
                    <option value="mobile" <?php echo $filter_device === 'mobile' ? 'selected' : ''; ?>>Mobile</option>
                    <option value="tablet" <?php echo $filter_device === 'tablet' ? 'selected' : ''; ?>>Tablet</option>
                    <option value="bot" <?php echo $filter_device === 'bot' ? 'selected' : ''; ?>>Bots</option>
                </select>
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Apply</button>
            </form>

            <!-- Summary Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-eye"></i></div>
                    <div class="stat-value"><?php echo number_format($stats['total_views'] ?? 0); ?></div>
                    <div class="stat-label">Page Views</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-value"><?php echo number_format($stats['unique_sessions'] ?? 0); ?></div>
                    <div class="stat-label">Unique Sessions</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-globe"></i></div>
                    <div class="stat-value"><?php echo number_format($stats['unique_ips'] ?? 0); ?></div>
                    <div class="stat-label">Unique IPs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-user-plus"></i></div>
                    <div class="stat-value"><?php echo number_format($stats['new_visitors'] ?? 0); ?></div>
                    <div class="stat-label">New Visitors</div>
                </div>
            </div>

            <!-- Hourly Traffic -->
            <div class="analytics-card" style="margin-bottom: 20px;">
                <h3><i class="fas fa-clock"></i> Hourly Traffic Distribution</h3>
                <?php $max_hourly = max(1, max($hourly_data)); ?>
                <div class="hourly-chart">
                    <?php for ($h = 0; $h < 24; $h++): ?>
                        <div class="hourly-bar" style="height: <?php echo max(2, ($hourly_data[$h] / $max_hourly) * 100); ?>%;">
                            <span class="tooltip"><?php echo sprintf('%02d:00', $h); ?> - <?php echo $hourly_data[$h]; ?> visits</span>
                        </div>
                    <?php endfor; ?>
                </div>
                <div class="hourly-labels">
                    <span>12am</span><span>3am</span><span>6am</span><span>9am</span>
                    <span>12pm</span><span>3pm</span><span>6pm</span><span>9pm</span>
                </div>
            </div>

            <!-- Breakdowns -->
            <div class="analytics-grid">
                <!-- Country Breakdown -->
                <div class="analytics-card">
                    <h3><i class="fas fa-earth-africa"></i> Countries</h3>
                    <?php if (empty($country_breakdown)): ?>
                        <p style="color: #999; text-align: center; padding: 20px;">No country data yet</p>
                    <?php else: ?>
                    <ul class="breakdown-list">
                        <?php
                        $total_country = max(1, array_sum(array_column($country_breakdown, 'count')));
                        foreach ($country_breakdown as $c):
                            $pct = round(($c['count'] / $total_country) * 100);
                        ?>
                        <li>
                            <div class="breakdown-bar">
                                <span><?php echo htmlspecialchars($c['country']); ?></span>
                                <div class="breakdown-fill" style="width: <?php echo $pct; ?>%;"></div>
                            </div>
                            <span class="breakdown-count"><?php echo number_format($c['count']); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>

                <!-- Device Breakdown -->
                <div class="analytics-card">
                    <h3><i class="fas fa-mobile-alt"></i> Devices</h3>
                    <?php if (empty($devices)): ?>
                        <p style="color: #999; text-align: center; padding: 20px;">No data yet</p>
                    <?php else: ?>
                    <ul class="breakdown-list">
                        <?php
                        $total_device = max(1, array_sum(array_column($devices, 'count')));
                        foreach ($devices as $d):
                            $pct = round(($d['count'] / $total_device) * 100);
                            $icons = ['desktop' => 'fa-desktop', 'mobile' => 'fa-mobile-alt', 'tablet' => 'fa-tablet-alt', 'bot' => 'fa-robot', 'unknown' => 'fa-question-circle'];
                            $icon = $icons[$d['device_type']] ?? 'fa-question-circle';
                        ?>
                        <li>
                            <div class="breakdown-bar">
                                <i class="fas <?php echo $icon; ?>" style="color: var(--gold); width: 20px;"></i>
                                <span><?php echo ucfirst($d['device_type']); ?></span>
                                <div class="breakdown-fill" style="width: <?php echo $pct; ?>%;"></div>
                            </div>
                            <span class="breakdown-count"><?php echo $d['count']; ?> (<?php echo $pct; ?>%)</span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>

                <!-- Browser Breakdown -->
                <div class="analytics-card">
                    <h3><i class="fas fa-globe"></i> Browsers</h3>
                    <?php if (empty($browsers)): ?>
                        <p style="color: #999; text-align: center; padding: 20px;">No data yet</p>
                    <?php else: ?>
                    <ul class="breakdown-list">
                        <?php
                        $total_browser = max(1, array_sum(array_column($browsers, 'count')));
                        foreach ($browsers as $b):
                            $pct = round(($b['count'] / $total_browser) * 100);
                        ?>
                        <li>
                            <div class="breakdown-bar">
                                <span><?php echo htmlspecialchars($b['browser']); ?></span>
                                <div class="breakdown-fill" style="width: <?php echo $pct; ?>%;"></div>
                            </div>
                            <span class="breakdown-count"><?php echo $b['count']; ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>

                <!-- OS Breakdown -->
                <div class="analytics-card">
                    <h3><i class="fas fa-laptop"></i> Operating Systems</h3>
                    <?php if (empty($operating_systems)): ?>
                        <p style="color: #999; text-align: center; padding: 20px;">No data yet</p>
                    <?php else: ?>
                    <ul class="breakdown-list">
                        <?php
                        $total_os = max(1, array_sum(array_column($operating_systems, 'count')));
                        foreach ($operating_systems as $o):
                            $pct = round(($o['count'] / $total_os) * 100);
                        ?>
                        <li>
                            <div class="breakdown-bar">
                                <span><?php echo htmlspecialchars($o['os']); ?></span>
                                <div class="breakdown-fill" style="width: <?php echo $pct; ?>%;"></div>
                            </div>
                            <span class="breakdown-count"><?php echo $o['count']; ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>

                <!-- Top Referrers -->
                <div class="analytics-card">
                    <h3><i class="fas fa-link"></i> Top Referrers</h3>
                    <?php if (empty($referrers)): ?>
                        <p style="color: #999; text-align: center; padding: 20px;">No referrer data yet</p>
                    <?php else: ?>
                    <ul class="breakdown-list">
                        <?php
                        $total_ref = max(1, array_sum(array_column($referrers, 'count')));
                        foreach ($referrers as $r):
                            $pct = round(($r['count'] / $total_ref) * 100);
                        ?>
                        <li>
                            <div class="breakdown-bar">
                                <span><?php echo htmlspecialchars($r['referrer_domain']); ?></span>
                                <div class="breakdown-fill" style="width: <?php echo $pct; ?>%;"></div>
                            </div>
                            <span class="breakdown-count"><?php echo $r['count']; ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Most Visited Sections -->
            <div class="analytics-card" style="margin-bottom: 20px;">
                <h3><i class="fas fa-layer-group"></i> Most Visited Sections</h3>
                <div class="table-wrapper">
                    <table class="visitors-table">
                        <thead>
                            <tr>
                                <th>Section</th>
                                <th>Views</th>
                                <th>Unique Sessions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_sections)): ?>
                            <tr><td colspan="3" style="text-align: center; color: #999; padding: 40px;">No section data yet</td></tr>
                            <?php else: ?>
                            <?php foreach ($top_sections as $section): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($section['section']); ?></td>
                                <td><strong><?php echo number_format($section['views']); ?></strong></td>
                                <td><?php echo number_format($section['unique_views']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Top Visitor IPs -->
            <div class="analytics-card" style="margin-bottom: 20px;">
                <h3><i class="fas fa-network-wired"></i> Top Visitor IPs</h3>
                <div class="table-wrapper">
                    <table class="visitors-table">
                        <thead>
                            <tr>
                                <th>IP Address</th>
                                <th>Country</th>
                                <th>Views</th>
                                <th>Sessions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_ips)): ?>
                            <tr><td colspan="4" style="text-align: center; color: #999; padding: 40px;">No IP data yet</td></tr>
                            <?php else: ?>
                            <?php foreach ($top_ips as $ip_row): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($ip_row['ip_address']); ?></code></td>
                                <td><?php echo htmlspecialchars(normalizeCountryLabel($ip_row['country'] ?? null)); ?></td>
                                <td><strong><?php echo number_format((int)$ip_row['views']); ?></strong></td>
                                <td><?php echo number_format((int)$ip_row['sessions']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Top Pages -->
            <div class="analytics-card" style="margin-bottom: 20px;">
                <h3><i class="fas fa-file-alt"></i> Top Pages</h3>
                <div class="table-wrapper">
                    <table class="visitors-table">
                        <thead>
                            <tr>
                                <th>Page</th>
                                <th>Views</th>
                                <th>Unique</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($top_pages)): ?>
                            <tr><td colspan="3" style="text-align: center; color: #999; padding: 40px;">No page data yet</td></tr>
                            <?php else: ?>
                            <?php foreach ($top_pages as $pg): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($pg['page_url']); ?></td>
                                <td><strong><?php echo number_format($pg['views']); ?></strong></td>
                                <td><?php echo number_format($pg['unique_views']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Visitors Log -->
            <div class="analytics-card">
                <h3><i class="fas fa-list"></i> Recent Visitor Log</h3>
                <div class="table-wrapper">
                    <table class="visitors-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>IP Address</th>
                                <th>Page</th>
                                <th>Country</th>
                                <th>Device</th>
                                <th>Browser</th>
                                <th>OS</th>
                                <th>Referrer</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($visitors)): ?>
                            <tr><td colspan="8" style="text-align: center; color: #999; padding: 40px;">No visitor data for this period</td></tr>
                            <?php else: ?>
                            <?php foreach ($visitors as $v): ?>
                            <tr>
                                <td style="white-space: nowrap;"><?php echo date('H:i:s', strtotime($v['created_at'])); ?><br><small style="color:#999;"><?php echo date('M j', strtotime($v['created_at'])); ?></small></td>
                                <td><code ><?php echo htmlspecialchars($v['ip_address']); ?></code></td>
                                <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($v['page_url']); ?></td>
                                <td><?php echo htmlspecialchars($v['country_display'] ?? 'Unknown'); ?></td>
                                <td><span class="device-badge device-<?php echo $v['device_type']; ?>"><?php echo ucfirst($v['device_type']); ?></span></td>
                                <td><?php echo htmlspecialchars($v['browser']); ?></td>
                                <td><?php echo htmlspecialchars($v['os']); ?></td>
                                <td style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($v['referrer_domain'] ?: '-'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page_num > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page_num - 1])); ?>">&laquo; Prev</a>
                    <?php endif; ?>
                    <a class="active" href="#">Page <?php echo $page_num; ?> of <?php echo $total_pages; ?></a>
                    <?php if ($page_num < $total_pages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page_num + 1])); ?>">Next &raquo;</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleCustomDates(val) {
            document.getElementById('customDates').style.display = val === 'custom' ? 'flex' : 'none';
        }
    </script>
    <script src="js/admin-components.js"></script>
    <?php require_once 'includes/admin-footer.php'; ?>
