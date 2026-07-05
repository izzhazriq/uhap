/* ============================================================================
   motion.js — Shared motion behaviours for UiTM Health Unit
   Drop-in script: <script src="motion.js" defer></script>

   Features:
   1. Scroll-reveal: elements with class "motion-reveal" fade/slide in as they
      enter the viewport (via IntersectionObserver).
   2. Button loading state: forms with [data-motion-loading] disable their submit
      button and show a spinner + label while submitting.
   3. Smooth scrolling enabled on <html>.
   Respects prefers-reduced-motion.
   ============================================================================ */
(function () {
    'use strict';

    var prefersReduced = window.matchMedia &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function init() {
        document.documentElement.classList.add('motion-smooth');
        setupScrollReveal();
        setupFormLoading();
    }

    // ── 1. SCROLL REVEAL ────────────────────────────────────────────────────
    function setupScrollReveal() {
        var items = document.querySelectorAll('.motion-reveal');
        if (!items.length) return;

        if (prefersReduced || !('IntersectionObserver' in window)) {
            items.forEach(function (el) { el.classList.add('is-visible'); });
            return;
        }

        var observer = new IntersectionObserver(function (entries, obs) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    obs.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

        items.forEach(function (el) { observer.observe(el); });
    }

    // ── 2. FORM SUBMIT LOADING STATE ────────────────────────────────────────
    function setupFormLoading() {
        var forms = document.querySelectorAll('form[data-motion-loading]');
        forms.forEach(function (form) {
            form.addEventListener('submit', function () {
                // Skip if the form is invalid (HTML5 validation will block it)
                if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                    return;
                }
                var btn = form.querySelector('button[type="submit"], input[type="submit"]');
                if (!btn || btn.disabled) return;

                var loadingText = btn.getAttribute('data-loading-text') || 'Please wait…';
                // Preserve original label so it could be restored if needed
                btn.setAttribute('data-original-text', btn.innerHTML);
                btn.innerHTML = '<span class="motion-spinner"></span> ' + loadingText;

                // Defer disabling so the button's name/value is still submitted
                setTimeout(function () { btn.disabled = true; }, 0);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
