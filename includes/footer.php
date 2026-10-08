</main>
<?php $siteSettings = get_site_settings($mysqli); ?>
<footer class="site-footer">
    <div class="wrap">
        <?php $footerAddress = format_address($siteSettings); ?>
        <?php if ($footerAddress !== '' || $siteSettings['phone'] !== '' || $siteSettings['email'] !== ''): ?>
        <p class="footer-contact">
            <?php if ($footerAddress !== ''): ?><span><?= footer_icon('pin') ?><?= e($footerAddress) ?></span><?php endif; ?>
            <?php if ($siteSettings['phone'] !== ''): ?><span><?= footer_icon('phone') ?><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $siteSettings['phone'])) ?>"><?= e($siteSettings['phone']) ?></a></span><?php endif; ?>
            <?php if ($siteSettings['email'] !== ''): ?><span><?= footer_icon('mail') ?><a href="mailto:<?= e($siteSettings['email']) ?>"><?= e($siteSettings['email']) ?></a></span><?php endif; ?>
        </p>
        <?php endif; ?>
        <?php $privacyUrl = get_privacy_page_url($mysqli); ?>
        <p>
            &copy; <?= date('Y') ?> <?= e($siteName) ?>
            <?php if ($privacyUrl !== null): ?> · <a href="<?= e($privacyUrl) ?>">Privacybeleid</a><?php endif; ?>
        </p>
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
