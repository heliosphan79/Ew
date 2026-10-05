</main>
<?php $siteSettings = get_site_settings($mysqli); ?>
<footer class="site-footer">
    <div class="wrap">
        <?php if ($siteSettings['address'] !== '' || $siteSettings['phone'] !== '' || $siteSettings['email'] !== ''): ?>
        <p class="footer-contact">
            <?php if ($siteSettings['address'] !== ''): ?><span><?= e($siteSettings['address']) ?></span><?php endif; ?>
            <?php if ($siteSettings['phone'] !== ''): ?><span><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $siteSettings['phone'])) ?>"><?= e($siteSettings['phone']) ?></a></span><?php endif; ?>
            <?php if ($siteSettings['email'] !== ''): ?><span><a href="mailto:<?= e($siteSettings['email']) ?>"><?= e($siteSettings['email']) ?></a></span><?php endif; ?>
        </p>
        <?php endif; ?>
        <p>&copy; <?= date('Y') ?> <?= e($siteName) ?></p>
    </div>
</footer>
<?php if (!empty($config['google_analytics']['measurement_id'])): ?>
<div id="cookie-consent" class="cookie-consent" hidden>
    <div class="wrap cookie-consent-inner">
        <p>Deze site gebruikt Google Analytics om bezoek te meten. Ga je akkoord met het plaatsen van analytics-cookies?</p>
        <div class="cookie-consent-actions">
            <button type="button" id="cookie-decline" class="btn btn-secondary">Weiger</button>
            <button type="button" id="cookie-accept" class="btn btn-primary">Akkoord</button>
        </div>
    </div>
</div>
<?php endif; ?>
<script src="/assets/js/main.js"></script>
<script src="/assets/js/calendar-block.js"></script>
</body>
</html>
