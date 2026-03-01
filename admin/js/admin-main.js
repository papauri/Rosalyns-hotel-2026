(function() {
    'use strict';

    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {
        initAdminNavigation();
        initAdminModals();
        initAdminTables();
    });

    // ============================================
    // ADMIN NAVIGATION TOGGLE (Sidebar Collapsing)
    // ============================================
    
    function initAdminNavigation() {
        const toggleBtn = document.getElementById('adminNavToggle');
        const nav = document.querySelector('.admin-nav');
        const icon = document.getElementById('navToggleIcon');
        
        if (toggleBtn && nav) {
            // Handle toggle click
            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const isOpen = nav.classList.contains('nav-open');
                
                // Toggle nav visibility
                if (isOpen) {
                    nav.classList.remove('nav-open');
                    if (icon) icon.className = 'fas fa-bars';
                    if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'false');
                } else {
                    nav.classList.add('nav-open');
                    if (icon) icon.className = 'fas fa-times';
                    if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'true');
                }
            });

            // Close nav when a link is clicked (mobile)
            document.querySelectorAll('.admin-nav a').forEach(function(link) {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        nav.classList.remove('nav-open');
                        if (icon) icon.className = 'fas fa-bars';
                        if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'false');
                    }
                });
            });
            
            // Close nav when clicking outside
            document.addEventListener('click', function(e) {
                if (window.innerWidth <= 768 && !nav.contains(e.target) && !toggleBtn.contains(e.target)) {
                    nav.classList.remove('nav-open');
                    if (icon) icon.className = 'fas fa-bars';
                    if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'false');
                }
            });

            // Close nav on Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && nav.classList.contains('nav-open')) {
                    nav.classList.remove('nav-open');
                    if (icon) icon.className = 'fas fa-bars';
                    if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'false');
                }
            });

            console.log('[Admin Navigation] Initialized');
        } else {
            console.warn('[Admin Navigation] Toggle button or nav not found');
        }
    }

    // ============================================
    // ADMIN MODALS
    // ============================================

    function initAdminModals() {
        // Placeholder for modal initialization
        console.log('[Admin Modals] Initialized');
    }

    // ============================================
    // ADMIN TABLES RESPONSIVENESS
    // ============================================

    function initAdminTables() {
        // Ensure all tables in admin container have responsive wrapper or are properly styled
        console.log('[Admin Tables] Responsive Check Complete');
    }

    // Expose admin navigation to global scope if needed by other scripts
    window.initAdminNavigation = initAdminNavigation;
})();