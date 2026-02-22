/**
 * Unified Navigation System
 * Rosalyn's Hotel 2026
 *
 * Full SPA routing: clicks on internal nav links swap only the content between
 * <header> and <footer> — the header never reloads, transitions are seamless.
 *
 * Key behaviours:
 *  • Wraps content between header and footer in #spa-content (display:contents)
 *  • Intercepts clicks on SPA-allowed pages, fetches via api/page-content.php
 *  • Fades old content out, swaps innerHTML, fades new content in
 *  • Re-runs inline <script> tags found in new content
 *  • Updates active nav state in both desktop and mobile menus
 *  • Handles browser back/forward via popstate
 *  • Singleton guard — safe when loaded from both page footer AND individual pages
 */

(function () {
    'use strict';

    // ── Singleton guard ──────────────────────────────────────────────────────
    if (window._unifiedNavLoaded) return;
    window._unifiedNavLoaded = true;

    // ── Constants ────────────────────────────────────────────────────────────
    const SPA_PAGES = [
        'index',
        'rooms-gallery',
        'rooms-showcase',
        'room',
        'restaurant',
        'events',
        'gym',
        'conference',
    ];

    const EXCLUDED_PAGES = [
        'admin',
        'booking',
        'check-availability',
        'booking-confirmation',
        'booking-lookup',
        'submit-review',
        'review-confirmation',
        'privacy-policy',
        'menu-pdf',
        'generate-sitemap',
        'robots',
    ];

    // ── Class ────────────────────────────────────────────────────────────────
    class UnifiedNavigation {
        constructor() {
            this.spaWrapper   = null;   // <div id="spa-content"> between header & footer
            this.isLoading    = false;
            this.currentPage  = this._pageName(window.location.pathname);
            this._init();
        }

        /* ================================================================
           Bootstrap
        ================================================================ */

        _init() {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this._setup());
            } else {
                this._setup();
            }
        }

        _setup() {
            this._buildSPAWrapper();
            this._setupMobileMenu();
            this._attachLinkListeners();

            window.addEventListener('popstate', e => this._onPopState(e));
            window.addEventListener('pageshow', e => { if (e.persisted) this._hideLoader(); });

            history.replaceState({ page: this.currentPage }, '', window.location.href);

            console.log('[UnifiedNavigation] Ready — SPA wrapper:', !!this.spaWrapper);
        }

        /* ================================================================
           SPA wrapper
           Wraps all nodes between <header.header> and <footer.footer>
           so that swapping only those nodes leaves the header untouched.
        ================================================================ */

        _buildSPAWrapper() {
            // Already exists (e.g. double-include)
            const existing = document.getElementById('spa-content');
            if (existing) { this.spaWrapper = existing; return; }

            const header = document.querySelector('header.header');
            const footer = document.querySelector('footer.footer');
            if (!header || !footer) return;

            // Gather sibling nodes that sit between header and footer
            const nodes = [];
            let node = header.nextSibling;
            while (node && node !== footer) {
                nodes.push(node);
                node = node.nextSibling;
            }

            // Create wrapper, insert after header, move nodes into it
            const wrapper = document.createElement('div');
            wrapper.id = 'spa-content';
            header.after(wrapper);
            nodes.forEach(n => wrapper.appendChild(n));

            // Make the wrapper layout-transparent so CSS that targets
            // body > main, body > section, etc. keeps working
            const style = document.createElement('style');
            style.textContent = '#spa-content { display: contents; }';
            document.head.appendChild(style);

            this.spaWrapper = wrapper;
        }

        /* ================================================================
           Mobile menu
        ================================================================ */

        _setupMobileMenu() {
            // 1. Bind the static toggle button (only once)
            const toggleBtn = document.querySelector('[data-mobile-toggle]');
            if (toggleBtn && !toggleBtn.dataset.navMobileInit) {
                toggleBtn.dataset.navMobileInit = '1';

                toggleBtn.addEventListener('click', () => {
                    const isExpanded = toggleBtn.getAttribute('aria-expanded') === 'true';
                    if (isExpanded) {
                        this._closeMobileMenu();
                    } else {
                        this._openMobileMenu();
                    }
                });

                // Global Escape key (only once)
                document.addEventListener('keydown', e => {
                    if (e.key === 'Escape') this._closeMobileMenu();
                });
            }

            // 2. Bind dynamic elements (panel, close btn, overlay)
            this._bindMobileMenuDynamicElements();
        }

        _bindMobileMenuDynamicElements() {
            const closeBtn = document.querySelector('[data-mobile-close]');
            const overlay = document.querySelector('[data-mobile-overlay]');
            
            // Use one-time event listeners or rely on element replacement to avoid duplicates
            if (closeBtn) {
                closeBtn.onclick = () => this._closeMobileMenu();
            }
            
            if (overlay) {
                overlay.onclick = () => this._closeMobileMenu();
            }
        }

        _openMobileMenu() {
            const panel = document.getElementById('mobile-menu');
            const overlay = document.querySelector('[data-mobile-overlay]');
            const toggleBtn = document.querySelector('[data-mobile-toggle]');
            
            if (panel) panel.classList.add('header__mobile--active');
            if (overlay) overlay.classList.add('header__overlay--active');
            if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
        }

        _closeMobileMenu() {
            const panel = document.getElementById('mobile-menu');
            const overlay = document.querySelector('[data-mobile-overlay]');
            const toggleBtn = document.querySelector('[data-mobile-toggle]');
            
            if (panel) panel.classList.remove('header__mobile--active');
            if (overlay) overlay.classList.remove('header__overlay--active');
            if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        }

        /* ================================================================
           Link interception
        ================================================================ */

        _attachLinkListeners() {
            // Capture phase so we get the click before any other handler
            document.addEventListener('click', e => this._onLinkClick(e), true);
            
            // Add non-SPA loader trigger for internal links
            document.addEventListener('click', e => this._onNonSPALinkClick(e), true);
        }

        _onLinkClick(e) {
            const link = e.target.closest('a');
            if (!link) return;

            const href = link.getAttribute('href');

            // Skip non-navigation hrefs
            if (!href || href === '' ||
                href.startsWith('#') ||
                href.startsWith('javascript:') ||
                href.startsWith('mailto:') ||
                href.startsWith('tel:')) return;

            // Skip external / new-tab / download
            if (this._isExternal(href))                         return;
            if (link.target === '_blank' || link.target === '_parent') return;
            if (link.hasAttribute('download'))                  return;
            if (link.hasAttribute('data-no-spa'))               return;

            // Skip admin links
            if (href.includes('/admin') || href.includes('admin/')) return;

            // Only intercept SPA-allowed pages
            const pageName = this._pageName(href);
            if (!this._isSPA(pageName)) return;

            // Build absolute URL
            let url;
            try { url = new URL(href, window.location.origin).href; }
            catch { url = href; }

            // Don't re-navigate to the same page (without params)
            const samePath = window.location.href.split('?')[0] === url.split('?')[0];
            if (samePath && !href.includes('?')) return;

            e.preventDefault();
            e.stopPropagation();

            // Close mobile menu if open
            this._closeMobileMenu();

            this._navigate(url, pageName);
        }

        /* ================================================================
           Non-SPA link loader trigger
           Shows loader for internal links that navigate to excluded pages
           (booking, admin, etc.) where SPA doesn't handle navigation
        ================================================================ */

        _onNonSPALinkClick(e) {
            // Skip if already handled by SPA
            if (e.defaultPrevented) return;
            
            const link = e.target.closest('a');
            if (!link) return;

            const href = link.getAttribute('href');

            // Skip non-navigation hrefs
            if (!href || href === '' ||
                href.startsWith('#') ||
                href.startsWith('javascript:') ||
                href.startsWith('mailto:') ||
                href.startsWith('tel:')) return;

            // Skip external / new-tab / download
            if (this._isExternal(href))                         return;
            if (link.target === '_blank' || link.target === '_parent') return;
            if (link.hasAttribute('download'))                  return;
            if (link.hasAttribute('data-no-loader'))            return;

            // Check if this is an internal link to an excluded page
            const pageName = this._pageName(href);
            if (this._isSPA(pageName)) return; // SPA handles these

            // Check if it's a same-origin internal link
            try {
                const url = new URL(href, window.location.origin);
                if (url.hostname === window.location.hostname) {
                    // This is an internal link to a non-SPA page
                    // Show loader before navigation with destination page subtext
                    this._showLoader(pageName);
                }
            } catch {
                // Invalid URL, skip
            }
        }

        /* ================================================================
           SPA navigation
        ================================================================ */

        async _navigate(url, pageName) {
            if (this.isLoading) return;
            this.isLoading = true;

            window.dispatchEvent(new CustomEvent('page:navigation:start', {
                detail: { url, page: pageName, spa: true }
            }));
            document.body.classList.add('page-loading');
            this._showLoader(pageName); // Pass destination page for correct subtext

            try {
                // Build API URL
                let apiUrl = `api/page-content.php?page=${encodeURIComponent(pageName)}`;
                if (pageName === 'room') {
                    const slug = new URL(url, window.location.origin).searchParams.get('room');
                    if (slug) apiUrl += `&slug=${encodeURIComponent(slug)}`;
                }

                const res  = await fetch(apiUrl);
                if (!res.ok) throw new Error(`HTTP ${res.status}`);

                const data = await res.json();
                if (data.error || !data.html) throw new Error(data.error || 'Empty response');

                // Swap content
                await this._swap(data);

                // Update browser history
                history.pushState({ page: pageName }, data.title || '', url);
                this.currentPage = pageName;

                this._updateActiveNav(url);
                this._reinit();

                window.dispatchEvent(new CustomEvent('page:navigation:end', {
                    detail: { url, page: pageName, spa: true }
                }));

                window.scrollTo({ top: 0, behavior: 'smooth' });

            } catch (err) {
                console.warn('[UnifiedNavigation] SPA failed, hard-navigating:', err.message);
                this._hideLoader();
                window.dispatchEvent(new CustomEvent('page:navigation:end', {
                    detail: { url, page: pageName, error: true }
                }));
                setTimeout(() => { window.location.href = url; }, 80);

            } finally {
                this.isLoading = false;
                setTimeout(() => document.body.classList.remove('page-loading'), 500);
            }
        }

        /* ================================================================
           Content swap with fade transition
           Preloads the hero image before fading in so the hero never
           appears after the rest of the content.
        ================================================================ */

        async _swap(data) {
            const target = this.spaWrapper || document.querySelector('main');
            if (!target) return;

            // ── 1. Fade out ───────────────────────────────────────────────
            target.style.transition = 'opacity 0.22s cubic-bezier(0.4, 0, 0.2, 1)';
            target.style.opacity    = '0';
            await new Promise(r => setTimeout(r, 230));

            // ── 2. Inject markup ──────────────────────────────────────────
            target.innerHTML = data.html;
            if (data.title) document.title = data.title;

            // Re-execute inline <script> tags
            // (innerHTML assignment does NOT run scripts automatically)
            target.querySelectorAll('script').forEach(old => {
                const fresh = document.createElement('script');
                Array.from(old.attributes).forEach(a => fresh.setAttribute(a.name, a.value));
                fresh.textContent = old.textContent;
                old.parentNode.replaceChild(fresh, old);
            });

            // ── 3. Preload hero image BEFORE revealing content ─────────────
            // This ensures the hero image is ready so it never pops in late.
            await this._preloadHeroImage(target);

            // ── 4. Fade in ────────────────────────────────────────────────
            target.style.transition = 'opacity 0.32s cubic-bezier(0.16, 1, 0.3, 1)';
            target.style.opacity    = '1';
            await new Promise(r => setTimeout(r, 340));

            this._hideLoader();
        }

        /* ================================================================
           Preload the hero image found in the newly injected content.
           Sets loading="eager" + fetchpriority="high" and waits for
           the image to decode before the caller fades content in.
           Has a 2.5 s safety timeout so slow networks never hang the UI.
        ================================================================ */

        _preloadHeroImage(container) {
            // Find the hero <img> — hero.php always puts it inside .hero__media
            const heroImg = container.querySelector('.hero__media img, img.hero__image');
            if (!heroImg) return Promise.resolve();

            // Force browser to treat it as a high-priority resource
            heroImg.loading       = 'eager';
            try { heroImg.fetchPriority = 'high'; } catch (_) { /* Safari <16 */ }

            // If the browser has it cached it's already complete
            if (heroImg.complete && heroImg.naturalWidth > 0) return Promise.resolve();

            // Kick off loading the best available source from the <picture> srcset
            const src = heroImg.currentSrc || heroImg.getAttribute('src');
            if (!src) return Promise.resolve();

            return new Promise(resolve => {
                const done    = () => { clearTimeout(timer); resolve(); };
                const timer   = setTimeout(resolve, 2500); // safety net
                heroImg.addEventListener('load',  done, { once: true });
                heroImg.addEventListener('error', done, { once: true });
                // Trigger load if not already started
                if (!heroImg.src) heroImg.src = src;
            });
        }

        /* ================================================================
           Active nav state
        ================================================================ */

        _updateActiveNav(url) {
            const pageName = this._pageName(url);

            // Desktop links
            document.querySelectorAll('.header__menu-link').forEach(link => {
                const lp = this._pageName(link.getAttribute('href') || '');
                const active = lp === pageName || (pageName === 'room' && lp === 'rooms-gallery');
                link.classList.toggle('header__menu-link--active', active);
            });

            // Mobile links (correct class: header__mobile-link, not header__mobile-menu-link)
            document.querySelectorAll('.header__mobile-link:not(.header__mobile-link--cta)').forEach(link => {
                const lp = this._pageName(link.getAttribute('href') || '');
                const active = lp === pageName || (pageName === 'room' && lp === 'rooms-gallery');
                link.classList.toggle('header__mobile-link--active', active);
            });
        }

        /* ================================================================
           Re-initialise JS enhancements after content swap
        ================================================================ */

        _reinit() {
            // Fire event that page-transitions.js, scroll-reveal.js, etc. listen to
            window.dispatchEvent(new CustomEvent('spa:contentLoaded', {
                detail: { page: this.currentPage }
            }));

            // Re-bind mobile menu dynamic elements (panel, overlay, close btn)
            this._bindMobileMenuDynamicElements();

            // Explicit re-init for known modules
            // Use try/catch to prevent any module errors from breaking SPA navigation
            try {
                window.Modal?.init?.();
                window.Enhancements?.init?.();
                window.ScrollLazyAnimations?.reinit?.();
                window.PageTransitions?.refresh?.();
            } catch (reinitErr) {
                // Log but don't fail - SPA navigation has already succeeded
                console.warn('[UnifiedNavigation] Module reinit warning:', reinitErr?.message || reinitErr);
            }
        }

        /* ================================================================
           Browser back/forward
        ================================================================ */

        _onPopState() {
            const url      = window.location.href;
            const pageName = this._pageName(url);
            if (this._isSPA(pageName)) {
                this._navigate(url, pageName);
            } else {
                // For non-SPA pages on back/forward, show loader with destination subtext
                this._showLoader(pageName);
                setTimeout(() => location.reload(), 50);
            }
        }

        /* ================================================================
           Loader helpers
        ================================================================ */

        _showLoader(destinationPage) {
            const l = document.getElementById('page-loader');
            if (l) {
                // Update loader subtext to show destination page
                if (destinationPage && typeof window.updateLoaderSubtext === 'function') {
                    window.updateLoaderSubtext(destinationPage);
                }
                // Remove hidden state first
                l.classList.remove('loader--hidden', 'loader--hiding');
                // Add active state to trigger proper CSS transitions
                l.classList.add('loader--active');
                // Clear any inline styles that might interfere
                l.style.opacity = '';
                l.style.visibility = '';
            }
        }

        _hideLoader() {
            const l = document.getElementById('page-loader');
            if (l) {
                // First add hiding state for smooth transition
                l.classList.add('loader--hiding');
                l.classList.remove('loader--active');
                // Then add hidden after transition completes
                setTimeout(() => {
                    l.classList.add('loader--hidden');
                    l.classList.remove('loader--hiding');
                }, 500);
            }
        }

        /* ================================================================
           Helpers
        ================================================================ */

        _pageName(url) {
            try {
                const obj  = new URL(url, window.location.origin);
                let path   = obj.pathname
                    .replace(/^\//, '')      // Remove leading slash
                    .replace(/\.php$/, '')   // Remove .php extension
                    .replace(/\/$/, '');      // Remove trailing slash
                
                // Handle room.php special case (with or without query params)
                if (url.includes('room.php') || path === 'room') return 'room';
                
                // Handle empty path or index as 'index'
                if (path === '' || path === 'index') return 'index';
                
                // Get the last path segment (handles paths like /some/page)
                return path.split('/').pop() || 'index';
            } catch {
                return 'index';
            }
        }

        _isSPA(pageName) {
            for (const ex of EXCLUDED_PAGES) { if (pageName.includes(ex)) return false; }
            return SPA_PAGES.includes(pageName);
        }

        _isExternal(href) {
            try { return new URL(href, window.location.origin).hostname !== window.location.hostname; }
            catch { return false; }
        }
    }

    // ── Boot ──────────────────────────────────────────────────────────────────
    function boot() {
        window.unifiedNav = new UnifiedNavigation();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    window.UnifiedNavigation = UnifiedNavigation;

})();
