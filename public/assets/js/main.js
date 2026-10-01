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
