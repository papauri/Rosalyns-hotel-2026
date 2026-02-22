<?php
/**
 * Unified Media Management Portal
 * Centralized ordered catalog for all hotel media assets.
 */

require_once 'admin-init.php';
require_once '../includes/alert.php';

$message = '';
$error = '';

function mm_resolve_preview_url(?string $mediaUrl): string {
    $value = trim((string)$mediaUrl);
    if ($value === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $value)) {
        return $value;
    }
    return '../' . ltrim($value, '/');
}

function mm_store_uploaded_media(array $fileInput, string $mediaType): array {
    if (!isset($fileInput['error']) || (int)$fileInput['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Please upload a valid media file.');
    }

    $tmp = $fileInput['tmp_name'] ?? '';
    $orig = $fileInput['name'] ?? '';
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Uploaded file was not received correctly.');
    }

    $detected = mime_content_type($tmp) ?: '';
    $isImage = stripos($detected, 'image/') === 0;
    $isVideo = stripos($detected, 'video/') === 0;

    if (($mediaType === 'image' && !$isImage) || ($mediaType === 'video' && !$isVideo)) {
        throw new RuntimeException('Uploaded file does not match selected media type.');
    }

    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if ($ext === '') {
        $ext = ($mediaType === 'video') ? 'mp4' : 'jpg';
    }

    $dir = ($mediaType === 'video') ? (__DIR__ . '/../videos/managed/') : (__DIR__ . '/../images/managed/');
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create upload directory.');
    }

    $name = 'media_' . time() . '_' . random_int(1000, 9999) . '.' . $ext;
    $dest = $dir . $name;
    if (!move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('Failed to save uploaded file.');
    }

    return [
        'media_url' => (($mediaType === 'video') ? 'videos/managed/' : 'images/managed/') . $name,
        'mime_type' => $detected,
        'source_type' => 'upload',
    ];
}

function mm_get_allowed_source_columns(): array {
    return [
        'about_us' => ['image_url'],
        'conference_rooms' => ['image_path'],
        'events' => ['image_path', 'video_path'],
        'rooms' => ['image_url', 'video_path'],
        'gallery' => ['image_url'],
        'page_heroes' => ['hero_image_path', 'hero_video_path'],
        'hotel_gallery' => ['image_url', 'video_path'],
        'restaurant_gallery' => ['image_path'],
        'gym_content' => ['hero_image_path', 'wellness_image_path', 'personal_training_image_path'],
        'welcome' => ['image_path'],
        'testimonials' => ['guest_image'],
        'individual_room_photos' => ['image_path'],
        'site_settings' => ['setting_value'],
    ];
}

function mm_sync_page_hero_media(PDO $pdo): void {
    if (!function_exists('upsertManagedMediaForSource')) {
        return;
    }

    try {
        $stmt = $pdo->query("SELECT id, page_slug, hero_title, hero_image_path, hero_video_path FROM page_heroes WHERE is_active = 1");
        $heroes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return;
    }

    foreach ($heroes as $hero) {
        $heroId = (int)($hero['id'] ?? 0);
        if ($heroId <= 0) {
            continue;
        }

        $pageSlug = trim((string)($hero['page_slug'] ?? ''));
        $heroTitle = trim((string)($hero['hero_title'] ?? '')) ?: 'Page Hero';

        $heroImagePath = trim((string)($hero['hero_image_path'] ?? ''));
        if ($heroImagePath !== '') {
            upsertManagedMediaForSource('page_heroes', $heroId, 'hero_image_path', $heroImagePath, [
                'title' => $heroTitle . ' (Hero Image)',
                'placement_key' => 'page_hero.' . $pageSlug . '.image',
                'page_slug' => $pageSlug,
                'section_key' => 'hero',
                'entity_type' => 'page_hero',
                'entity_id' => $heroId,
                'source_context' => 'page_hero_media',
                'display_order' => 0,
            ]);
        }

        $heroVideoPath = trim((string)($hero['hero_video_path'] ?? ''));
        if ($heroVideoPath !== '') {
            upsertManagedMediaForSource('page_heroes', $heroId, 'hero_video_path', $heroVideoPath, [
                'title' => $heroTitle . ' (Hero Video)',
                'placement_key' => 'page_hero.' . $pageSlug . '.video',
                'page_slug' => $pageSlug,
                'section_key' => 'hero',
                'entity_type' => 'page_hero',
                'entity_id' => $heroId,
                'source_context' => 'page_hero_media',
                'display_order' => 0,
            ]);
        }
    }
}

function mm_sync_about_us_media(PDO $pdo): void {
    if (!function_exists('upsertManagedMediaForSource')) {
        return;
    }

    try {
        $stmt = $pdo->query("SELECT id, title, image_url FROM about_us WHERE section_type = 'main' AND is_active = 1 AND image_url IS NOT NULL AND image_url != '' ORDER BY display_order ASC, id ASC");
        $aboutItems = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return;
    }

    foreach ($aboutItems as $about) {
        $aboutId = (int)($about['id'] ?? 0);
        if ($aboutId <= 0) {
            continue;
        }

        $aboutTitle = trim((string)($about['title'] ?? '')) ?: 'About Us Section';
        $aboutImageUrl = trim((string)($about['image_url'] ?? ''));

        if ($aboutImageUrl !== '') {
            upsertManagedMediaForSource('about_us', $aboutId, 'image_url', $aboutImageUrl, [
                'title' => $aboutTitle . ' (About Us Image)',
                'placement_key' => 'about_us.main.image',
                'section_key' => 'about',
                'entity_type' => 'about_us',
                'entity_id' => $aboutId,
                'source_context' => 'about_us_media',
                'display_order' => 0,
            ]);
        }
    }
}

function mm_propagate_media_to_sources(PDO $pdo, int $catalogId, ?string $mediaUrl, bool $deleteMode = false): void {
    $linksStmt = $pdo->prepare("SELECT source_table, source_record_id, source_column, source_context FROM managed_media_links WHERE media_catalog_id = ?");
    $linksStmt->execute([$catalogId]);
    $links = $linksStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (empty($links)) {
        return;
    }

    $allow = mm_get_allowed_source_columns();

    foreach ($links as $link) {
        $table = $link['source_table'] ?? '';
        $column = $link['source_column'] ?? '';
        $recordId = (string)($link['source_record_id'] ?? '');

        if ($table === '' || $column === '' || $recordId === '') {
            continue;
        }

        if (!isset($allow[$table]) || !in_array($column, $allow[$table], true)) {
            continue;
        }

        if ($table === 'site_settings') {
            $update = $pdo->prepare("UPDATE site_settings SET setting_value = ? WHERE id = ?");
            $update->execute([$deleteMode ? '' : (string)$mediaUrl, (int)$recordId]);
            continue;
        }

        if ($table === 'page_heroes') {
            if (ctype_digit($recordId)) {
                $sql = "UPDATE page_heroes SET `{$column}` = ? WHERE id = ?";
                $update = $pdo->prepare($sql);
                $update->execute([$deleteMode ? null : $mediaUrl, (int)$recordId]);
            } else {
                $sql = "UPDATE page_heroes SET `{$column}` = ? WHERE page_slug = ?";
                $update = $pdo->prepare($sql);
                $update->execute([$deleteMode ? null : $mediaUrl, $recordId]);
            }
            continue;
        }

        $sql = "UPDATE `{$table}` SET `{$column}` = ? WHERE id = ?";
        $update = $pdo->prepare($sql);
        $update->execute([$deleteMode ? null : $mediaUrl, ctype_digit($recordId) ? (int)$recordId : $recordId]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $action = trim((string)($_POST['action'] ?? ''));

        try {
            if ($action === 'create_item') {
                if (!hasPermission($user['id'], 'media_create')) {
                    throw new RuntimeException('You do not have permission to create media items.');
                }

                $title = trim((string)($_POST['title'] ?? ''));
                $mediaType = (($_POST['media_type'] ?? 'image') === 'video') ? 'video' : 'image';
                $sourceType = (($_POST['source_type'] ?? 'upload') === 'url') ? 'url' : 'upload';

                if ($title === '') {
                    throw new RuntimeException('Title is required.');
                }

                $mediaUrl = null;
                $mimeType = null;

                if ($sourceType === 'url') {
                    $inputUrl = trim((string)($_POST['media_url_input'] ?? ''));
                    if ($inputUrl === '') {
                        throw new RuntimeException('Media URL is required for URL source type.');
                    }
                    $mediaUrl = $inputUrl;
                    $mimeType = ($mediaType === 'video') ? 'url' : null;
                } else {
                    $uploaded = mm_store_uploaded_media($_FILES['media_file'] ?? [], $mediaType);
                    $mediaUrl = $uploaded['media_url'];
                    $mimeType = $uploaded['mime_type'];
                    $sourceType = 'upload';
                }

                $stmt = $pdo->prepare("INSERT INTO managed_media_catalog (title, description, media_type, source_type, media_url, mime_type, alt_text, caption, placement_key, page_slug, section_key, entity_type, entity_id, is_active, display_order, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $title,
                    trim((string)($_POST['description'] ?? '')) ?: null,
                    $mediaType,
                    $sourceType,
                    $mediaUrl,
                    $mimeType,
                    trim((string)($_POST['alt_text'] ?? '')) ?: null,
                    trim((string)($_POST['caption'] ?? '')) ?: null,
                    trim((string)($_POST['placement_key'] ?? '')) ?: null,
                    trim((string)($_POST['page_slug'] ?? '')) ?: null,
                    trim((string)($_POST['section_key'] ?? '')) ?: null,
                    trim((string)($_POST['entity_type'] ?? '')) ?: null,
                    ($_POST['entity_id'] ?? '') !== '' ? (int)$_POST['entity_id'] : null,
                    isset($_POST['is_active']) ? 1 : 0,
                    (int)($_POST['display_order'] ?? 0),
                    (int)$user['id'],
                ]);

                $message = 'Media item created.';
            }

            if ($action === 'update_item') {
                if (!hasPermission($user['id'], 'media_edit')) {
                    throw new RuntimeException('You do not have permission to update media items.');
                }

                $itemId = (int)($_POST['item_id'] ?? 0);
                if ($itemId <= 0) {
                    throw new RuntimeException('Invalid media item.');
                }

                $currentStmt = $pdo->prepare("SELECT id, source_type, media_url, mime_type FROM managed_media_catalog WHERE id = ?");
                $currentStmt->execute([$itemId]);
                $current = $currentStmt->fetch(PDO::FETCH_ASSOC);
                if (!$current) {
                    throw new RuntimeException('Media item not found.');
                }

                $mediaType = (($_POST['media_type'] ?? 'image') === 'video') ? 'video' : 'image';
                $newMediaUrl = $current['media_url'];
                $newMimeType = $current['mime_type'];
                $newSourceType = $current['source_type'];

                $urlReplacement = trim((string)($_POST['media_url_input'] ?? ''));
                $hasFileReplacement = isset($_FILES['media_file']) && (int)($_FILES['media_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;

                if ($urlReplacement !== '') {
                    $newMediaUrl = $urlReplacement;
                    $newSourceType = 'url';
                    $newMimeType = ($mediaType === 'video') ? 'url' : null;
                } elseif ($hasFileReplacement) {
                    $uploaded = mm_store_uploaded_media($_FILES['media_file'], $mediaType);
                    $newMediaUrl = $uploaded['media_url'];
                    $newMimeType = $uploaded['mime_type'];
                    $newSourceType = 'upload';

                    if (($current['source_type'] ?? '') === 'upload' && !empty($current['media_url'])) {
                        $oldPath = __DIR__ . '/../' . ltrim($current['media_url'], '/');
                        if (is_file($oldPath)) {
                            @unlink($oldPath);
                        }
                    }
                }

                $stmt = $pdo->prepare("UPDATE managed_media_catalog SET title = ?, description = ?, media_type = ?, source_type = ?, media_url = ?, mime_type = ?, alt_text = ?, caption = ?, placement_key = ?, page_slug = ?, section_key = ?, entity_type = ?, entity_id = ?, is_active = ?, display_order = ? WHERE id = ?");
                $stmt->execute([
                    trim((string)($_POST['title'] ?? '')),
                    trim((string)($_POST['description'] ?? '')) ?: null,
                    $mediaType,
                    $newSourceType,
                    $newMediaUrl,
                    $newMimeType,
                    trim((string)($_POST['alt_text'] ?? '')) ?: null,
                    trim((string)($_POST['caption'] ?? '')) ?: null,
                    trim((string)($_POST['placement_key'] ?? '')) ?: null,
                    trim((string)($_POST['page_slug'] ?? '')) ?: null,
                    trim((string)($_POST['section_key'] ?? '')) ?: null,
                    trim((string)($_POST['entity_type'] ?? '')) ?: null,
                    ($_POST['entity_id'] ?? '') !== '' ? (int)$_POST['entity_id'] : null,
                    isset($_POST['is_active']) ? 1 : 0,
                    (int)($_POST['display_order'] ?? 0),
                    $itemId,
                ]);

                mm_propagate_media_to_sources($pdo, $itemId, $newMediaUrl, false);

                if (function_exists('invalidateDataCaches')) {
                    invalidateDataCaches();
                }

                $message = 'Media item updated.';
            }

            if ($action === 'delete_item') {
                if (!hasPermission($user['id'], 'media_delete')) {
                    throw new RuntimeException('You do not have permission to delete media items.');
                }

                $itemId = (int)($_POST['item_id'] ?? 0);
                if ($itemId <= 0) {
                    throw new RuntimeException('Invalid media item.');
                }

                $currentStmt = $pdo->prepare("SELECT source_type, media_url FROM managed_media_catalog WHERE id = ?");
                $currentStmt->execute([$itemId]);
                $current = $currentStmt->fetch(PDO::FETCH_ASSOC);

                mm_propagate_media_to_sources($pdo, $itemId, null, true);

                if (function_exists('invalidateDataCaches')) {
                    invalidateDataCaches();
                }

                $stmt = $pdo->prepare("DELETE FROM managed_media_catalog WHERE id = ?");
                $stmt->execute([$itemId]);

                if ($current && ($current['source_type'] ?? '') === 'upload' && !empty($current['media_url'])) {
                    $fullPath = __DIR__ . '/../' . ltrim($current['media_url'], '/');
                    if (is_file($fullPath)) {
                        @unlink($fullPath);
                    }
                }

                $message = 'Media item deleted.';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$items = [];
try {
    mm_sync_page_hero_media($pdo);
    mm_sync_about_us_media($pdo);
    $stmt = $pdo->query("SELECT c.*, GROUP_CONCAT(CONCAT(l.source_table, ':', l.source_column, ':', l.source_record_id, IF(l.source_context IS NULL OR l.source_context = '', '', CONCAT(':', l.source_context))) ORDER BY l.source_table, l.source_column, l.source_record_id SEPARATOR ' | ') AS source_links FROM managed_media_catalog c LEFT JOIN managed_media_links l ON l.media_catalog_id = c.id GROUP BY c.id ORDER BY COALESCE(c.page_slug, ''), COALESCE(c.section_key, ''), c.display_order ASC, c.id ASC");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Unable to load unified media catalog. Run Database/migrations/011_unified_media_catalog.sql first. ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Media Portal - Admin Panel</title>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="css/admin-styles.css">
    <link rel="stylesheet" href="css/admin-components.css">
    <style>
        .portal-form {display:grid;gap:10px;border:1px solid #e5e7eb;border-radius:10px;padding:14px;background:#fff;margin-bottom:18px}
        .portal-grid-2 {display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
        .portal-actions {display:flex;gap:8px;flex-wrap:wrap}
        .portal-danger {background:#c0392b;color:#fff;border:none;padding:8px 12px;border-radius:6px;cursor:pointer}
        .portal-table-wrap {overflow:auto;border:1px solid #e5e7eb;border-radius:10px;background:#fff}
        .portal-table {width:100%;border-collapse:collapse;min-width:1300px}
        .portal-table th,.portal-table td{padding:10px;border-bottom:1px solid #f0f2f4;vertical-align:top}
        .portal-table th{background:#f9fafb;text-align:left;font-size:13px}
        .portal-preview{width:180px;height:100px;object-fit:cover;border-radius:8px;background:#f3f4f6;display:block}
        .portal-source{font-size:12px;color:#444;word-break:break-all;max-width:240px}
    </style>
</head>
<body>
<?php require_once 'includes/admin-header.php'; ?>
<div class="content">
    <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
        <div>
            <h2 class="page-title"><i class="fas fa-photo-video"></i> Unified Media Portal</h2>
            <p style="margin:4px 0 0;color:#666;">Centralized ordered media catalog for all hotel images/videos/URLs.</p>
        </div>
    </div>

    <?php if ($message): ?><?php showAlert($message, 'success'); ?><?php endif; ?>
    <?php if ($error): ?><?php showAlert($error, 'error'); ?><?php endif; ?>

    <?php if (hasPermission($user['id'], 'media_create')): ?>
    <form method="POST" enctype="multipart/form-data" class="portal-form">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <input type="hidden" name="action" value="create_item">
        <h3 style="margin:0;">Create Media Item</h3>
        <div class="portal-grid-2">
            <input type="text" name="title" required placeholder="Title">
            <input type="number" name="display_order" value="0" min="0" placeholder="Display order">
        </div>
        <input type="text" name="description" placeholder="Description">
        <div class="portal-grid-2">
            <select name="media_type">
                <option value="image">Image</option>
                <option value="video">Video</option>
            </select>
            <select name="source_type">
                <option value="upload">Upload File</option>
                <option value="url">External URL</option>
            </select>
        </div>
        <div class="portal-grid-2">
            <input type="url" name="media_url_input" placeholder="Optional URL source (https://...)">
            <input type="file" name="media_file" accept="image/*,video/*">
        </div>
        <div class="portal-grid-2">
            <input type="text" name="alt_text" placeholder="Alt text">
            <input type="text" name="caption" placeholder="Caption">
        </div>
        <div class="portal-grid-2">
            <input type="text" name="placement_key" placeholder="Placement key (e.g. index_hotel_gallery)">
            <input type="text" name="page_slug" placeholder="Page slug (optional)">
        </div>
        <div class="portal-grid-2">
            <input type="text" name="section_key" placeholder="Section key (optional)">
            <input type="text" name="entity_type" placeholder="Entity type (optional)">
        </div>
        <div class="portal-grid-2">
            <input type="number" name="entity_id" min="1" placeholder="Entity ID (optional)">
            <label><input type="checkbox" name="is_active" checked> Active</label>
        </div>
        <div class="portal-actions">
            <button class="btn-save-perms" type="submit"><i class="fas fa-plus"></i> Create Item</button>
        </div>
    </form>
    <?php endif; ?>

    <div class="portal-table-wrap">
        <table class="portal-table">
            <thead>
                <tr>
                    <th>Preview</th>
                    <th>Current Source</th>
                    <th>Type</th>
                    <th>Order</th>
                    <th>Placement</th>
                    <th>Source Context</th>
                    <th>Update</th>
                    <th>Delete</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <?php $preview = mm_resolve_preview_url($item['media_url'] ?? ''); ?>
                <tr>
                    <td>
                        <?php if (($item['media_type'] ?? '') === 'video' && $preview !== ''): ?>
                            <video class="portal-preview" src="<?php echo htmlspecialchars($preview); ?>" muted controls></video>
                        <?php elseif ($preview !== ''): ?>
                            <img class="portal-preview" src="<?php echo htmlspecialchars($preview); ?>" alt="<?php echo htmlspecialchars((string)($item['alt_text'] ?: $item['title'])); ?>">
                        <?php else: ?>
                            <div class="portal-preview"></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="portal-source"><strong><?php echo htmlspecialchars((string)($item['media_url'] ?? '')); ?></strong></div>
                        <div style="font-size:12px;color:#666;margin-top:6px;">MIME: <?php echo htmlspecialchars((string)($item['mime_type'] ?? '')); ?></div>
                        <div style="font-size:12px;color:#666;">Source type: <?php echo htmlspecialchars((string)($item['source_type'] ?? '')); ?></div>
                    </td>
                    <td>
                        <div><?php echo htmlspecialchars((string)($item['media_type'] ?? '')); ?></div>
                        <div style="font-size:12px;color:#666;">Active: <?php echo ((int)($item['is_active'] ?? 0) === 1) ? 'Yes' : 'No'; ?></div>
                    </td>
                    <td><?php echo (int)($item['display_order'] ?? 0); ?></td>
                    <td>
                        <div style="font-size:12px">Key: <?php echo htmlspecialchars((string)($item['placement_key'] ?? '')); ?></div>
                        <div style="font-size:12px">Page: <?php echo htmlspecialchars((string)($item['page_slug'] ?? '')); ?></div>
                        <div style="font-size:12px">Section: <?php echo htmlspecialchars((string)($item['section_key'] ?? '')); ?></div>
                    </td>
                    <td>
                        <div style="font-size:12px;color:#444;max-width:320px;word-break:break-word;">
                            <?php echo htmlspecialchars((string)($item['source_links'] ?? 'unlinked')); ?>
                        </div>
                    </td>
                    <td>
                        <?php if (hasPermission($user['id'], 'media_edit')): ?>
                        <form method="POST" enctype="multipart/form-data" class="portal-form" style="margin:0;">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="action" value="update_item">
                            <input type="hidden" name="item_id" value="<?php echo (int)$item['id']; ?>">
                            <input type="text" name="title" value="<?php echo htmlspecialchars((string)$item['title']); ?>" required>
                            <input type="text" name="description" value="<?php echo htmlspecialchars((string)$item['description']); ?>" placeholder="Description">
                            <div class="portal-grid-2">
                                <select name="media_type">
                                    <option value="image" <?php echo (($item['media_type'] ?? '') === 'image') ? 'selected' : ''; ?>>Image</option>
                                    <option value="video" <?php echo (($item['media_type'] ?? '') === 'video') ? 'selected' : ''; ?>>Video</option>
                                </select>
                                <input type="number" name="display_order" value="<?php echo (int)($item['display_order'] ?? 0); ?>" min="0">
                            </div>
                            <div class="portal-grid-2">
                                <input type="url" name="media_url_input" placeholder="Optional replacement URL (leave blank to keep current)">
                                <input type="file" name="media_file" accept="image/*,video/*">
                            </div>
                            <div class="portal-grid-2">
                                <input type="text" name="alt_text" value="<?php echo htmlspecialchars((string)$item['alt_text']); ?>" placeholder="Alt text">
                                <input type="text" name="caption" value="<?php echo htmlspecialchars((string)$item['caption']); ?>" placeholder="Caption">
                            </div>
                            <div class="portal-grid-2">
                                <input type="text" name="placement_key" value="<?php echo htmlspecialchars((string)$item['placement_key']); ?>" placeholder="Placement key">
                                <input type="text" name="page_slug" value="<?php echo htmlspecialchars((string)$item['page_slug']); ?>" placeholder="Page slug">
                            </div>
                            <div class="portal-grid-2">
                                <input type="text" name="section_key" value="<?php echo htmlspecialchars((string)$item['section_key']); ?>" placeholder="Section key">
                                <input type="text" name="entity_type" value="<?php echo htmlspecialchars((string)$item['entity_type']); ?>" placeholder="Entity type">
                            </div>
                            <div class="portal-grid-2">
                                <input type="number" name="entity_id" min="1" value="<?php echo ($item['entity_id'] !== null && $item['entity_id'] !== '') ? (int)$item['entity_id'] : ''; ?>" placeholder="Entity ID">
                                <label><input type="checkbox" name="is_active" <?php echo ((int)($item['is_active'] ?? 0) === 1) ? 'checked' : ''; ?>> Active</label>
                            </div>
                            <button class="btn-save-perms" type="submit">Update Item</button>
                        </form>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (hasPermission($user['id'], 'media_delete')): ?>
                        <form method="POST" onsubmit="return confirm('Delete this media item?');">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="action" value="delete_item">
                            <input type="hidden" name="item_id" value="<?php echo (int)$item['id']; ?>">
                            <button class="portal-danger" type="submit">Delete</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once 'includes/admin-footer.php'; ?>
</body>
</html>

