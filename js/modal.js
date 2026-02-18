/**
 * Modal Component - Front-end Modal Controller
 * Provides reusable modal functionality for front-end pages
 */

(function() {
    'use strict';

    // ============================================
    // MODAL CONTROLLER
    // ============================================
    
    const Modal = {
        activeModals: [],

        // Open a modal by ID
        open: function(modalId) {
            const modal = document.getElementById(modalId);
            const backdrop = modal?.querySelector('.modal__backdrop');
             
            if (!modal) {
                console.error('Modal not found:', modalId);
                return;
            }

            // Add to active modals stack
            this.activeModals.push(modalId);

            // Show modal with BEM class
            modal.classList.add('modal--active');

            // Prevent body scroll
            document.body.classList.add('modal-open');

            // Focus first focusable element
            setTimeout(() => {
                const focusable = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
                if (focusable) focusable.focus();
            }, 100);

            // Trigger custom event
            modal.dispatchEvent(new CustomEvent('modal:open', { detail: { modalId } }));
        },

        // Close a modal by ID
        close: function(modalId) {
            const modal = document.getElementById(modalId);
             
            if (!modal) return;

            // Remove from active modals
            this.activeModals = this.activeModals.filter(id => id !== modalId);

            // Hide modal with BEM class
            modal.classList.remove('modal--active');

            // Restore body scroll if no modals are open
            if (this.activeModals.length === 0) {
                document.body.classList.remove('modal-open');
            }

            // Trigger custom event
            modal.dispatchEvent(new CustomEvent('modal:close', { detail: { modalId } }));
        },

        // Close all open modals
        closeAll: function() {
            [...this.activeModals].forEach(id => this.close(id));
        },

        // Initialize modal event listeners
        init: function() {
            // Close button clicks
            document.querySelectorAll('[data-modal-close]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const modal = btn.closest('[data-modal]');
                    if (modal) this.close(modal.id);
                });
            });

            // Backdrop clicks (using new BEM structure)
            document.querySelectorAll('.modal__backdrop').forEach(backdrop => {
                backdrop.addEventListener('click', (e) => {
                    if (e.target === backdrop) {
                        const modal = backdrop.closest('.modal');
                        if (modal && modal.dataset.closeOnOverlay !== 'false') {
                            this.close(modal.id);
                        }
                    }
                });
            });

            // Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.activeModals.length > 0) {
                    const topModalId = this.activeModals[this.activeModals.length - 1];
                    const modal = document.getElementById(topModalId);
                    if (modal && modal.dataset.closeOnEscape !== 'false') {
                        this.close(topModalId);
                    }
                }
            });

            // Open buttons
            document.querySelectorAll('[data-modal-open]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const modalId = btn.dataset.modalOpen;
                    this.open(modalId);
                });
            });

            // Policy overlay (special case for footer policy modals)
            const policyOverlay = document.querySelector('[data-policy-overlay]');
            if (policyOverlay) {
                policyOverlay.addEventListener('click', (e) => {
                    if (e.target === policyOverlay) {
                        this.closeAll();
                    }
                });
            }
        }
    };

    // Expose Modal to global scope
    window.Modal = Modal;

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => Modal.init());
    } else {
        Modal.init();
    }
})();