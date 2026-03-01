# QR Code Menu PDF Fix - Instructions

## Problem
The QR code on restaurant.php was pointing to `api/menu-pdf.php` which doesn't exist. The correct file is `menu-pdf.php` in the root directory.

## Solution Implemented

### 1. Code Protection (Already in restaurant.php)
The `restaurant.php` file already has protection logic that:
- Detects if settings point to incorrect paths like `/api/menu-pdf.php`
- Automatically uses the correct fallback: `menu-pdf.php`
- Works on all environments (local, dev, production)

### 2. Migration Created
Created migration `admin/migrations/014_fix_menu_pdf_url.php` that:
- Checks for incorrect paths in database settings
- Updates them to the correct path
- Verifies the fix was successful

### 3. Verification
✓ Migration ran successfully
✓ `menu-pdf.php` exists in root directory
✓ No database changes needed (settings already correct or using fallback)

## Steps to Test

### Option 1: Clear Browser Cache
1. Open restaurant.php
2. Press `Ctrl + Shift + R` (Windows) or `Cmd + Shift + R` (Mac) to hard refresh
3. Scan the QR code with your phone
4. Verify it opens `menu-pdf.php` (not `api/menu-pdf.php`)

### Option 2: Check Current Settings
Run this SQL to verify current settings:
```sql
SELECT setting_key, setting_value 
FROM site_settings 
WHERE setting_key IN ('restaurant_menu_url', 'restaurant_menu_pdf_url');
```

If settings are empty or correct, the fallback logic will use `menu-pdf.php`.

### Option 3: Manually Update Settings (if needed)
If you want to explicitly set the settings in the database:

```sql
UPDATE site_settings 
SET setting_value = 'menu-pdf.php' 
WHERE setting_key = 'restaurant_menu_url';

UPDATE site_settings 
SET setting_value = 'menu-pdf.php' 
WHERE setting_key = 'restaurant_menu_pdf_url';
```

## Why It Works Now

The code in `restaurant.php` (lines 127-145) includes:

```php
$invalid_paths = ['/api/menu-pdf.php', 'api/menu-pdf.php', '/api/menu-pdf'];

if (in_array($menu_view_setting, $invalid_paths) || 
    in_array($menu_pdf_setting, $invalid_paths) ||
    strpos($menu_view_setting, '/api/') !== false ||
    strpos($menu_pdf_setting, '/api/') !== false) {
    
    // Use correct fallback
    $menu_view_url = $menu_page_fallback;
    $menu_pdf_url = $menu_page_fallback;
}
```

This means even if the database has incorrect settings, the QR code will always point to the correct file.

## Final Verification

After clearing cache, test the QR code:
1. Scan with any QR code scanner
2. The URL should be: `https://yourdomain.com/menu-pdf.php`
3. NOT: `https://yourdomain.com/api/menu-pdf.php`

## If Issue Persists

If the QR code still shows the wrong URL after clearing cache:

1. Check browser developer tools (F12)
2. Go to Network tab
3. Reload restaurant.php
4. Find the QR code image request
5. Check the `data=` parameter in the URL
6. Verify it doesn't contain `api/menu-pdf.php`

The code at line 148 generates the QR code:
```php
$menu_qr_image = 'https://api.qrserver.com/v1/create-qr-code/?size=520x520&data=' . urlencode($menu_view_url) . '...';
```

The `$menu_view_url` variable is guaranteed to be correct by the fallback logic.