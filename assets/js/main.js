// Reveals content blocks with a small, one-time fade-up as they scroll
// into view. Deliberately restrained: short distance, no bounce, no
// re-triggering — calm rather than flashy. See .block-scroll-in in style.css
// and the "js" class added in includes/header.php for the no-JS fallback.
(function () {
    var targets = document.querySelectorAll('[data-animate]');
    if (!targets.length) return;

    if (!('IntersectionObserver' in window)) {
        targets.forEach(function (el) { el.classList.add('is-visible'); });
        return;
    }

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });

    targets.forEach(function (el) { observer.observe(el); });
})();

// Sticky header "lift": adds a shadow and shrinks the padding slightly
// once the page has scrolled past the top, so the header (pinned via
// position: sticky in CSS) visibly responds to scrolling rather than
// sitting there as a flat static bar. rAF-throttled to avoid doing this
// work on every scroll event.
(function () {
    var header = document.querySelector('.site-header');
    if (!header) return;

    var ticking = false;
    function update() {
        header.classList.toggle('is-scrolled', window.scrollY > 10);
        ticking = false;
    }
    window.addEventListener('scroll', function () {
        if (!ticking) {
            window.requestAnimationFrame(update);
            ticking = true;
        }
    }, { passive: true });
    update(); // page can load already scrolled (anchor link, back/forward)
})();

// Mobile hamburger menu: toggles the collapsible nav panel open/closed.
// Pure CSS handles the button-to-ring-and-cross and panel-reveal animation
// (see .menu-btn / .site-nav in style.css) — this just flips the state.
(function () {
    var btn = document.getElementById('menu-toggle');
    var nav = document.getElementById('site-nav');
    if (!btn || !nav) return;

    btn.addEventListener('click', function () {
        var open = btn.getAttribute('aria-expanded') === 'true';
        btn.setAttribute('aria-expanded', open ? 'false' : 'true');
        btn.setAttribute('aria-label', open ? 'Menu openen' : 'Menu sluiten');
        nav.classList.toggle('is-open', !open);
    });
})();

// Google Analytics: only loaded after explicit consent (EU cookie rules —
// no tracking request fires before the visitor agrees). The measurement ID
// lives in a <meta> tag (see includes/header.php), set from config.php;
// if it's absent, Analytics is off entirely and the banner never appears.
(function () {
    var meta = document.querySelector('meta[name="ga-measurement-id"]');
    var measurementId = meta ? meta.content : '';
    if (!measurementId) return;

    var STORAGE_KEY = 'ew-analytics-consent';

    function loadAnalytics() {
        var script = document.createElement('script');
        script.async = true;
        script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(measurementId);
        document.head.appendChild(script);

        window.dataLayer = window.dataLayer || [];
        window.gtag = function () { window.dataLayer.push(arguments); };
        window.gtag('js', new Date());
        window.gtag('config', measurementId);
    }

    var stored = null;
    try { stored = window.localStorage.getItem(STORAGE_KEY); } catch (e) { /* private browsing, etc. */ }

    if (stored === 'granted') {
        loadAnalytics();
        return;
    }
    if (stored === 'denied') {
        return;
    }

    var banner = document.getElementById('cookie-consent');
    if (!banner) return;
    banner.hidden = false;

    function decide(value) {
        try { window.localStorage.setItem(STORAGE_KEY, value); } catch (e) { /* ignore */ }
        banner.hidden = true;
        if (value === 'granted') loadAnalytics();
    }

    var acceptBtn = document.getElementById('cookie-accept');
    var declineBtn = document.getElementById('cookie-decline');
    if (acceptBtn) acceptBtn.addEventListener('click', function () { decide('granted'); });
    if (declineBtn) declineBtn.addEventListener('click', function () { decide('denied'); });
})();

// reCAPTCHA v3 on the contact form: fetches an invisible spam-score token
// right before submit and attaches it as a hidden field. Only active when
// includes/header.php injected a site-key meta tag (config.php has
// recaptcha.site_key set) — see recaptcha_verify() in functions.php for
// the server-side check. form.submit() below is the native DOM method,
// which (unlike a click or Enter) does not re-fire the 'submit' event —
// that's what keeps this from looping back into itself.
(function () {
    var form = document.querySelector('.contact-form');
    var siteKeyMeta = document.querySelector('meta[name="recaptcha-site-key"]');
    if (!form || !siteKeyMeta || !siteKeyMeta.content) return;
    var siteKey = siteKeyMeta.content;

    var tokenField = document.createElement('input');
    tokenField.type = 'hidden';
    tokenField.name = 'recaptcha_token';
    form.appendChild(tokenField);

    form.addEventListener('submit', function (e) {
        if (typeof window.grecaptcha === 'undefined') return;
        e.preventDefault();
        window.grecaptcha.ready(function () {
            window.grecaptcha.execute(siteKey, { action: 'contact' }).then(function (token) {
                tokenField.value = token;
                form.submit();
            });
        });
    });
})();
