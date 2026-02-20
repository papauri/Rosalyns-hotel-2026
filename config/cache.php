<?php
/**
 * Enhanced Cache Management System
 * Supports instant clearing, disabling, and automatic invalidation
 */

// Cache directory configuration
// Use dirname(__DIR__) to get the parent directory of config/
define('CACHE_DIR', dirname(__DIR__) . '/cache');
define('IMAGE_CACHE_DIR', dirname(__DIR__) . '/data/image-cache');

// Global cache enable/disable flag
define('CACHE_ENABLED', true); // This can be overridden by database setting

/**
 * Check if caching is globally enabled
 */
function isCacheEnabled($type = null) {
    try {
        // Check global setting from database
        global $pdo;
        if (!$pdo) {
            return CACHE_ENABLED;
        }
        
        $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'cache_global_enabled' LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['setting_value'] == '0') {
            return false;
        }
        
        // Check specific cache type
        if ($type) {
            $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ? LIMIT 1");
            $stmt->execute(["cache_{$type}_enabled"]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && $result['setting_value'] == '0') {
                return false;
            }
        }
    } catch (Exception $e) {
        // If database query fails, default to enabled
        error_log("Cache enable check failed: " . $e->getMessage());
    }
    
    return CACHE_ENABLED;
}

/**
 * Get cached value with readable filename
 * Respects cache enable/disable settings
 */
function getCache($key, $default = null, $type = 'settings') {
    try {
        // Check if caching is enabled for this type
        if (!isCacheEnabled($type)) {
            return $default;
        }
        
        // Ensure cache directory exists
        if (!file_exists(CACHE_DIR)) {
            return $default;
        }
        
        // Generate readable cache filename with prefix
        $cacheFile = CACHE_DIR . '/' . getReadableCacheFilename($key);
        
        if (!file_exists($cacheFile)) {
            return $default;
        }
        
        $data = @file_get_contents($cacheFile);
        if ($data === false) {
            return $default;
        }
        
        $cache = json_decode($data, true);
        if (!$cache || !isset($cache['data']) || !isset($cache['expiry'])) {
            return $default;
        }
        
        // Check if expired
        if (time() > $cache['expiry']) {
            @unlink($cacheFile);
            return $default;
        }
        
        return $cache['data'];
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Generate a human-readable cache filename with prefix
 */
function getReadableCacheFilename($key) {
    // Sanitize key to be filesystem-safe
    $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
    // Generate short hash for uniqueness
    $shortHash = substr(md5($key), 0, 8);
    return "{$sanitized}_{$shortHash}.cache";
}

/**
 * Set cached value with readable filename
 * Respects cache enable/disable settings
 */
function setCache($key, $value, $ttl = 3600, $type = 'settings') {
    // Don't cache if disabled
    if (!isCacheEnabled($type)) {
        return false;
    }
    
    // Create cache directory if it doesn't exist
    if (!file_exists(CACHE_DIR)) {
        @mkdir(CACHE_DIR, 0755, true);
    }
    
    $cacheFile = CACHE_DIR . '/' . getReadableCacheFilename($key);
    
    $cacheData = [
        'key' => $key,
        'data' => $value,
        'created' => time(),
        'expiry' => time() + $ttl,
        'ttl' => $ttl
    ];
    
    $data = json_encode($cacheData);
    return @file_put_contents($cacheFile, $data, LOCK_EX) !== false;
}

/**
 * Delete cached value with readable filename
 */
function deleteCache($key) {
    $cacheFile = CACHE_DIR . '/' . getReadableCacheFilename($key);
    if (file_exists($cacheFile)) {
        return @unlink($cacheFile);
    }
    return false;
}

/**
 * Recursively delete a directory and its contents
 */
function recursiveDelete($dir) {
    if (!file_exists($dir)) {
        return true;
    }
    
    if (!is_dir($dir)) {
        return @unlink($dir);
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? recursiveDelete("$dir/$file") : @unlink("$dir/$file");
    }
    
    return @rmdir($dir);
}

/**
 * Clear directory contents (keep the directory itself)
 */
function emptyDirectory($dir) {
    if (!is_dir($dir)) {
        return 0;
    }
    
    $count = 0;
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = "$dir/$file";
        if (is_dir($path)) {
            if (recursiveDelete($path)) {
                $count++;
            }
        } else {
            if (@unlink($path)) {
                $count++;
            }
        }
    }
    return $count;
}

/**
 * Clear all cache files instantly
 * Uses recursive deletion to ensure everything is gone
 */
function clearCache() {
    $cleared = 0;
    
    // Clear main cache directory
    if (is_dir(CACHE_DIR)) {
        $cleared += emptyDirectory(CACHE_DIR);
    }
    
    // Also clear image cache
    $cleared += clearImageCache();
    
    // Clear in-memory cache
    global $_SITE_SETTINGS;
    if (isset($_SITE_SETTINGS)) {
        $_SITE_SETTINGS = [];
    }
    
    return $cleared;
}

/**
 * Clear image cache
 */
function clearImageCache() {
    if (!is_dir(IMAGE_CACHE_DIR)) {
        return 0;
    }
    return emptyDirectory(IMAGE_CACHE_DIR);
}

/**
 * List all cache files with their details
 */
function listCache() {
    if (!is_dir(CACHE_DIR)) {
        return [];
    }
    
    $files = glob(CACHE_DIR . '/*.cache');
    $caches = [];
    
    if ($files) {
        foreach ($files as $file) {
            $data = @file_get_contents($file);
            if ($data) {
                $cache = json_decode($data, true);
                if ($cache) {
                    $caches[] = [
                        'file' => basename($file),
                        'key' => $cache['key'] ?? 'unknown',
                        'size' => filesize($file),
                        'size_formatted' => formatBytes(filesize($file)),
                        'created' => $cache['created'] ?? null,
                        'created_formatted' => $cache['created'] ? date('Y-m-d H:i:s', $cache['created']) : 'unknown',
                        'expires' => $cache['expiry'] ?? null,
                        'expires_formatted' => $cache['expiry'] ? date('Y-m-d H:i:s', $cache['expiry']) : 'unknown',
                        'expired' => ($cache['expiry'] ?? 0) < time(),
                        'ttl' => ($cache['expiry'] ?? time()) - time()
                    ];
                }
            }
        }
    }
    
    // Sort by file name for easier reading
    usort($caches, function($a, $b) {
        return strcmp($a['file'], $b['file']);
    });
    
    return $caches;
}

/**
 * List all cache files from all cache directories (main, image, page)
 * Returns a unified array with source directory indicator
 */
function listAllCache() {
    $allCaches = [];
    
    // Process main cache directory
    if (is_dir(CACHE_DIR)) {
        $files = glob(CACHE_DIR . '/*.cache');
        if ($files) {
            foreach ($files as $file) {
                $data = @file_get_contents($file);
                if ($data) {
                    $cache = @json_decode($data, true);
                    if ($cache) {
                        $allCaches[] = [
                            'file' => basename($file),
                            'key' => $cache['key'] ?? 'unknown',
                            'size' => filesize($file),
                            'size_formatted' => formatBytes(filesize($file)),
                            'created' => $cache['created'] ?? null,
                            'created_formatted' => $cache['created'] ? date('Y-m-d H:i:s', $cache['created']) : 'unknown',
                            'expires' => $cache['expiry'] ?? null,
                            'expires_formatted' => $cache['expiry'] ? date('Y-m-d H:i:s', $cache['expiry']) : 'unknown',
                            'expired' => ($cache['expiry'] ?? 0) < time(),
                            'ttl' => ($cache['expiry'] ?? time()) - time(),
                            'source' => 'main',
                            'source_label' => 'Main Cache'
                        ];
                    }
                }
            }
        }
    }
    
    // Process image cache directory
    if (is_dir(IMAGE_CACHE_DIR)) {
        $items = @scandir(IMAGE_CACHE_DIR);
        if ($items !== false) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $path = IMAGE_CACHE_DIR . DIRECTORY_SEPARATOR . $item;
                if (is_file($path)) {
                    $created = filemtime($path);
                    $allCaches[] = [
                        'file' => $item,
                        'key' => 'image/' . $item,
                        'size' => filesize($path),
                        'size_formatted' => formatBytes(filesize($path)),
                        'created' => $created,
                        'created_formatted' => date('Y-m-d H:i:s', $created),
                        'expires' => null,
                        'expires_formatted' => 'N/A',
                        'expired' => false,
                        'ttl' => null,
                        'source' => 'image',
                        'source_label' => 'Image Cache'
                    ];
                }
            }
        }
    }
    
    // Process page cache directory
    if (is_dir(PAGE_CACHE_DIR)) {
        $files = glob(PAGE_CACHE_DIR . '/*.html');
        if ($files) {
            foreach ($files as $file) {
                $data = @file_get_contents($file);
                if ($data) {
                    $cache = @json_decode($data, true);
                    $created = $cache['created'] ?? filemtime($file);
                    $expiry = $cache['expiry'] ?? null;
                    $allCaches[] = [
                        'file' => basename($file),
                        'key' => 'page/' . basename($file, '.html'),
                        'size' => filesize($file),
                        'size_formatted' => formatBytes(filesize($file)),
                        'created' => $created,
                        'created_formatted' => date('Y-m-d H:i:s', $created),
                        'expires' => $expiry,
                        'expires_formatted' => $expiry ? date('Y-m-d H:i:s', $expiry) : 'N/A',
                        'expired' => $expiry ? ($expiry < time()) : false,
                        'ttl' => $expiry ? ($expiry - time()) : null,
                        'source' => 'page',
                        'source_label' => 'Page Cache'
                    ];
                }
            }
        }
    }
    
    // Sort by source, then by file name for easier reading
    usort($allCaches, function($a, $b) {
        $sourceCompare = strcmp($a['source'], $b['source']);
        if ($sourceCompare !== 0) {
            return $sourceCompare;
        }
        return strcmp($a['file'], $b['file']);
    });
    
    return $allCaches;
}

/**
 * Clear cache by key pattern (supports wildcards)
 */
function clearCacheByPattern($pattern) {
    if (!is_dir(CACHE_DIR)) {
        return 0;
    }
    
    $files = glob(CACHE_DIR . '/*.cache');
    $cleared = 0;
    
    if ($files) {
        // Convert pattern to regex
        $regex = '/^' . str_replace('*', '.*', $pattern) . '$/';
        
        foreach ($files as $file) {
            $data = @file_get_contents($file);
            if ($data) {
                $cache = json_decode($data, true);
                if ($cache && isset($cache['key'])) {
                    if (preg_match($regex, $cache['key'])) {
                        if (@unlink($file)) {
                            $cleared++;
                        }
                    }
                }
            }
        }
    }
    
    return $cleared;
}

/**
 * Get cache statistics (comprehensive - includes all cache directories)
 */
function getCacheStats() {
    // Ensure cache directories exist
    if (!file_exists(CACHE_DIR)) {
        @mkdir(CACHE_DIR, 0755, true);
    }
    if (!file_exists(IMAGE_CACHE_DIR)) {
        @mkdir(IMAGE_CACHE_DIR, 0755, true);
    }
    if (!file_exists(PAGE_CACHE_DIR)) {
        @mkdir(PAGE_CACHE_DIR, 0755, true);
    }
    
    $stats = [
        'total_files' => 0,
        'active_files' => 0,
        'expired_files' => 0,
        'total_size' => 0,
        'total_size_formatted' => '0 B',
        'oldest_file' => null,
        'newest_file' => null,
        'caches' => [],
        // Breakdown by cache type
        'main_cache' => [
            'files' => 0,
            'size' => 0,
            'size_formatted' => '0 B'
        ],
        'image_cache' => [
            'files' => 0,
            'size' => 0,
            'size_formatted' => '0 B'
        ],
        'page_cache' => [
            'files' => 0,
            'size' => 0,
            'size_formatted' => '0 B'
        ]
    ];
    
    $now = time();
    $oldest = PHP_INT_MAX;
    $newest = 0;
    
    // Process main cache directory
    $files = @glob(CACHE_DIR . '/*.cache');
    if ($files === false) {
        $files = [];
    }
    
    if ($files) {
        foreach ($files as $file) {
            $data = @file_get_contents($file);
            if ($data) {
                $cache = @json_decode($data, true);
                if ($cache) {
                    $stats['total_files']++;
                    $stats['main_cache']['files']++;
                    $fileSize = filesize($file);
                    $stats['total_size'] += $fileSize;
                    $stats['main_cache']['size'] += $fileSize;
                    
                    $created = $cache['created'] ?? 0;
                    if ($created < $oldest) {
                        $oldest = $created;
                        $stats['oldest_file'] = $cache['key'];
                    }
                    if ($created > $newest) {
                        $newest = $created;
                        $stats['newest_file'] = $cache['key'];
                    }
                    
                    if (($cache['expiry'] ?? 0) < $now) {
                        $stats['expired_files']++;
                    } else {
                        $stats['active_files']++;
                    }
                }
            }
        }
    }
    
    // Process image cache directory
    $imageFiles = @scandir(IMAGE_CACHE_DIR);
    if ($imageFiles !== false) {
        foreach ($imageFiles as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = IMAGE_CACHE_DIR . DIRECTORY_SEPARATOR . $file;
            if (is_file($path)) {
                $stats['total_files']++;
                $stats['image_cache']['files']++;
                $fileSize = filesize($path);
                $stats['total_size'] += $fileSize;
                $stats['image_cache']['size'] += $fileSize;
                
                $created = filemtime($path);
                if ($created < $oldest) {
                    $oldest = $created;
                    $stats['oldest_file'] = 'image/' . $file;
                }
                if ($created > $newest) {
                    $newest = $created;
                    $stats['newest_file'] = 'image/' . $file;
                }
            }
        }
    }
    
    // Process page cache directory
    $pageFiles = @glob(PAGE_CACHE_DIR . '/*.html');
    if ($pageFiles === false) {
        $pageFiles = [];
    }
    
    if ($pageFiles) {
        foreach ($pageFiles as $file) {
            $data = @file_get_contents($file);
            if ($data) {
                $cache = @json_decode($data, true);
                $stats['total_files']++;
                $stats['page_cache']['files']++;
                $fileSize = filesize($file);
                $stats['total_size'] += $fileSize;
                $stats['page_cache']['size'] += $fileSize;
                
                $created = $cache['created'] ?? filemtime($file);
                if ($created < $oldest) {
                    $oldest = $created;
                    $stats['oldest_file'] = 'page/' . basename($file, '.html');
                }
                if ($created > $newest) {
                    $newest = $created;
                    $stats['newest_file'] = 'page/' . basename($file, '.html');
                }
                
                if (($cache['expiry'] ?? 0) < $now) {
                    $stats['expired_files']++;
                } else {
                    $stats['active_files']++;
                }
            }
        }
    }
    
    // Format sizes
    $stats['total_size_formatted'] = formatBytes($stats['total_size']);
    $stats['main_cache']['size_formatted'] = formatBytes($stats['main_cache']['size']);
    $stats['image_cache']['size_formatted'] = formatBytes($stats['image_cache']['size']);
    $stats['page_cache']['size_formatted'] = formatBytes($stats['page_cache']['size']);
    
    return $stats;
}

/**
 * Format bytes to human-readable format
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Clear specific cache by exact key
 * Alias for deleteCache() for consistency
 */
function clearSpecificCache($key) {
    return deleteCache($key);
}

/**
 * Clear all room-related cache instantly
 * Call this when rooms, prices, or images are updated
 */
function clearRoomCache() {
    // Clear all room-related caches
    $patterns = [
        'rooms_*',
        'table_rooms_*',
        'room_*',
        'facilities_*',
        'gallery_images',
        'page_hero*'
    ];
    
    $total = 0;
    foreach ($patterns as $pattern) {
        $total += clearCacheByPattern($pattern);
    }
    
    // Also clear image cache
    $total += clearImageCache();
    
    // Clear in-memory cache
    global $_SITE_SETTINGS;
    if (isset($_SITE_SETTINGS)) {
        unset($_SITE_SETTINGS['rooms']);
    }
    
    return $total;
}

/**
 * Clear all settings cache instantly
 * Call this when site settings are updated
 */
function clearSettingsCache() {
    return clearCacheByPattern('setting_*');
}

/**
 * Clear all email cache instantly
 * Call this when email settings are updated
 */
function clearEmailCache() {
    return clearCacheByPattern('email_*');
}

/**
 * Force cache refresh by clearing and immediately rebuilding
 * Useful for ensuring data is fresh
 */
function forceCacheRefresh($key, $callback, $ttl = 3600, $type = 'settings') {
    deleteCache($key);
    $data = $callback();
    if ($data !== null) {
        setCache($key, $data, $ttl, $type);
    }
    return $data;
}
