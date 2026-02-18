# Implementation Plan

## 1. Admin Cleanup
- **Objective:** Remove the deprecated Theme Management functionality to prevent conflicts and declutter the admin interface.
- **Files to Modify:**
  - `admin/theme-management.php`: Delete this file.
  - `admin/includes/permissions.php`: Remove the `'theme'` permission entry from `getAllPermissions()` and any references in `getDefaultPermissionsForRole()`.
  - `admin/includes/admin-header.php`: Remove the navigation list item for "Theme Management".

## 2. Section Headers Standardization
- **Objective:** Ensure all major frontend sections fetch their labels, titles, and descriptions from the database using the standard `renderSectionHeader()` helper.
- **Files to Modify:**
  - `includes/reviews-section.php`: Replace the hardcoded HTML header with `renderSectionHeader('hotel_reviews', 'global', [...])`.
  - `includes/hotel-gallery.php`: Replace the hardcoded HTML header with `renderSectionHeader('hotel_gallery', 'index', [...])`.
  - `includes/upcoming-events.php`: Replace the hardcoded HTML header with `renderSectionHeader('upcoming_events', 'index', [...])`.

## 3. Styling & UI Consistency
- **Objective:** Standardize button colors and toggle switches to match the new "Gold & Black" editorial theme.
- **Files to Modify:**
  - `css/components/buttons.css`: Ensure `.btn-primary` and `.btn-secondary` adhere to the `--color-primary` (Gold) and `--navy` (Black/Charcoal) palette.
  - `css/components/forms.css`: Add or update `.switch` (toggle) styles to ensure a clear "Green (Active) / Gray (Inactive)" state, replacing any inline or inconsistent styles.
  - `admin/css/admin-components.css`: Ensure admin-side toggles also follow this pattern if shared.

## 4. Cache & Verification
- **Objective:** Ensure the system is clean and changes take effect immediately.
- **Actions:**
  - Manually clear the `cache/` directory to remove old page/settings caches.
  - Verify the Admin Panel no longer shows "Theme Management".
  - Verify the Frontend displays the correct section headers and the styling is consistent.
