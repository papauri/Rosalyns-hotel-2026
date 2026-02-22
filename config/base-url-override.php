<?php
/**
 * Base URL Override
 * 
 * This file sets a manual override for BASE_URL to prevent issues with
 * auto-detection. If your site is at:
 * - https://example.com/ → set to 'https://example.com/'
 * - https://example.com/rosalyns/ → set to 'https://example.com/rosalyns/'
 * 
 * IMPORTANT: Do NOT include /admin/ in the BASE_URL.
 * The BASE_URL should point to the ROOT of your website, not the admin directory.
 */

// Set BASE_URL to your website's root URL (without /admin/)
// Uncomment and modify the line below:
define('BASE_URL_OVERRIDE', 'https://your-website.com/');

// If your site is in a subdirectory, include it:
// define('BASE_URL_OVERRIDE', 'https://your-website.com/subdirectory/');
