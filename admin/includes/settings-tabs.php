<?php
// Shared sub-nav for the Instellingen section, split across several pages
// since one single page had grown too long (contactgegevens, SEO,
// bevestigingsberichten, structuurdata, behandelaar — 20+ velden). Each
// settings-*.php page sets $settingsTab before requiring this partial.
$settingsTabs = [
    'algemeen' => ['settings.php', 'Algemeen'],
    'seo' => ['settings-seo.php', 'Weergave & SEO'],
    'messages' => ['settings-messages.php', 'Bevestigingsberichten'],
    'schema' => ['settings-schema.php', 'Structuurdata'],
];
?>
<nav class="settings-tabs">
    <?php foreach ($settingsTabs as $key => [$href, $label]): ?>
        <a href="<?= e($href) ?>" class="<?= ($settingsTab ?? '') === $key ? 'is-active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>
