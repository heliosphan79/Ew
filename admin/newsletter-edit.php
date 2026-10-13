<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$newsletter = null;

if ($id) {
    $stmt = $mysqli->prepare('SELECT * FROM newsletters WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $newsletter = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$newsletter) {
        set_flash('error', 'Nieuwsbrief niet gevonden.');
        redirect('newsletters.php');
    }
}

$isReadOnly = $newsletter && $newsletter['status'] !== 'draft';
$errors = [];
$form = ['subject' => $newsletter['subject'] ?? ''];
$groups = newsletter_get_groups($mysqli);
$groupNamesById = [];
foreach ($groups as $g) {
    $groupNamesById[(int) $g['id']] = $g['name'];
}
$form['audience'] = $newsletter['audience'] ?? 'all';
$form['group_ids'] = $id ? newsletter_target_group_ids($mysqli, $id) : [];
$blocksForEditor = $newsletter ? decode_blocks($newsletter['content']) : [];

// Stops an in-progress send — e.g. one stuck on an unresponsive SMTP
// server with no way to tell how far it got. Whatever already went out
// stays sent (newsletter_sends rows are never touched); the rest simply
// never does. Handled separately from the block below since that one is
// gated on !$isReadOnly, and a 'sending' newsletter IS read-only.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_send']) && $newsletter && $newsletter['status'] === 'sending') {
    csrf_verify();
    $stmt = $mysqli->prepare("UPDATE newsletters SET status = 'cancelled', sent_at = NOW() WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    set_flash('success', 'Verzending geannuleerd.');
    redirect('newsletter-edit.php?id=' . $id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isReadOnly) {
    csrf_verify();

    // "Verstuur" is a distinct action from the regular save, and only
    // valid on an already-saved draft — queue every currently-subscribed
    // recipient of the SAVED audience (everyone, or the union of the chosen
    // groups) that doesn't already have a send row for this newsletter (so
    // re-clicking after a partial batch failure never double-queues
    // anyone), then flip to 'sending' so the page below takes over with
    // the batch-processing view. Deliberately uses the saved audience, never
    // anything posted with this request: the page disables "Verstuur" while
    // the audience fields have unsaved changes.
    if (isset($_POST['start_send']) && $id) {
        $sendAudience = (string) $newsletter['audience'];
        $recipientIds = newsletter_recipient_ids($mysqli, $sendAudience, newsletter_target_group_ids($mysqli, $id));
        if (empty($recipientIds)) {
            set_flash('error', $sendAudience === 'groups'
                ? 'De gekozen groep(en) hebben geen actieve abonnees (of bestaan niet meer) — kies andere ontvangers en sla het concept op.'
                : 'Er zijn geen actieve abonnees om naar te versturen.');
            redirect('newsletter-edit.php?id=' . $id);
        }

        $mysqli->begin_transaction();
        try {
            // Every row needs its own random send_token, which a single
            // INSERT...SELECT can't generate — queue in a loop instead.
            // Subscriber lists here are at most a few thousand rows, and
            // this only runs once per send (re-clicking is a no-op per
            // subscriber thanks to the uniq_newsletter_subscriber key).
            $insert = $mysqli->prepare(
                'INSERT IGNORE INTO newsletter_sends (newsletter_id, subscriber_id, send_token) VALUES (?, ?, ?)'
            );
            foreach ($recipientIds as $subscriberId) {
                $token = newsletter_generate_token();
                $insert->bind_param('iis', $id, $subscriberId, $token);
                $insert->execute();
            }
            $insert->close();

            $stmt = $mysqli->prepare("UPDATE newsletters SET status = 'sending' WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();

            $mysqli->commit();
        } catch (mysqli_sql_exception $e) {
            $mysqli->rollback();
            throw $e;
        }

        redirect('newsletter-edit.php?id=' . $id);
    }

    $form['subject'] = trim((string) ($_POST['subject'] ?? ''));

    $form['audience'] = ($_POST['audience'] ?? '') === 'groups' ? 'groups' : 'all';
    $form['group_ids'] = newsletter_filter_group_ids($mysqli, $_POST['group_ids'] ?? []);
    if ($form['audience'] === 'groups' && empty($form['group_ids'])) {
        $errors[] = 'Kies minstens één groep, of kies "Alle abonnees".';
    }

    $rawBlocks = json_decode((string) ($_POST['blocks_json'] ?? '[]'), true);
    $blocksForEditor = is_array($rawBlocks) ? $rawBlocks : [];

    if ($form['subject'] === '' || mb_strlen($form['subject']) > 200) {
        $errors[] = 'Vul een onderwerp in (max. 200 tekens).';
    }

    $sanitized = sanitize_blocks($blocksForEditor);
    $errors = array_merge($errors, $sanitized['errors']);
    // Only the newsletter-safe subset actually renders in the e-mail, so a
    // page-only block type (kalender, kaart, ...) would silently vanish
    // from what's sent — flag it now instead of surprising the sender
    // after the fact.
    foreach ($sanitized['blocks'] as $block) {
        if (!in_array($block['type'] ?? '', NEWSLETTER_BLOCK_TYPES, true)) {
            $errors[] = 'Bloktype "' . e($block['type'] ?? '?') . '" wordt niet ondersteund in een nieuwsbrief en verschijnt niet in de verzonden mail.';
        }
    }
    if (empty($sanitized['blocks'])) {
        $errors[] = 'Voeg minstens één blok toe aan de nieuwsbrief.';
    }

    if (empty($errors)) {
        $contentJson = json_encode($sanitized['blocks'], JSON_UNESCAPED_UNICODE);

        if ($id) {
            $stmt = $mysqli->prepare('UPDATE newsletters SET subject = ?, content = ?, audience = ? WHERE id = ?');
            $stmt->bind_param('sssi', $form['subject'], $contentJson, $form['audience'], $id);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $mysqli->prepare('INSERT INTO newsletters (subject, content, audience) VALUES (?, ?, ?)');
            $stmt->bind_param('sss', $form['subject'], $contentJson, $form['audience']);
            $stmt->execute();
            $id = $mysqli->insert_id;
            $stmt->close();
        }
        // Bij 'all' blijven eerder gekozen groepen bewust niet bewaard: wie
        // terugschakelt naar "alle abonnees" en later weer "groepen" kiest,
        // begint met een lege keuze i.p.v. een verouderde selectie.
        newsletter_set_target_groups($mysqli, $id, $form['audience'] === 'groups' ? $form['group_ids'] : []);

        set_flash('success', 'Nieuwsbrief opgeslagen.');
        redirect('newsletter-edit.php?id=' . $id);
    }
}

// Aantal ontvangers van de BEWAARDE doelgroep, naast de "Verstuur"-knop,
// zodat de afzender de echte reikwijdte kent voor hij bevestigt.
$savedAudience = $newsletter['audience'] ?? 'all';
$savedGroupIds = $id ? newsletter_target_group_ids($mysqli, $id) : [];
$recipientCount = $id ? count(newsletter_recipient_ids($mysqli, $savedAudience, $savedGroupIds)) : 0;
$audienceLabel = newsletter_audience_label($savedAudience, $savedGroupIds, $groupNamesById);

$sendStats = null;
if ($id && $newsletter && $newsletter['status'] !== 'draft') {
    $stmt = $mysqli->prepare(
        'SELECT COUNT(*) AS total, SUM(sent_at IS NOT NULL) AS sent, SUM(opened_at IS NOT NULL) AS opened
         FROM newsletter_sends WHERE newsletter_id = ?'
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $sendStats = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$pageTitle = $id ? 'Nieuwsbrief bewerken' : 'Nieuwe nieuwsbrief';
require __DIR__ . '/includes/header.php';
?>

<h1><?= e($pageTitle) ?></h1>

<?php if (!empty($errors)): ?>
    <ul class="form-errors">
        <?php foreach ($errors as $error): ?>
            <li><?= e($error) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php if ($isReadOnly): ?>

    <div class="page-form page-form-wide">
        <p><strong>Onderwerp:</strong> <?= e($form['subject']) ?></p>
        <p><strong>Ontvangers:</strong> <?= e($audienceLabel) ?></p>

        <div id="newsletter-send-progress" data-newsletter-id="<?= (int) $id ?>"
             data-csrf="<?= e(csrf_token()) ?>"
             data-sending="<?= $newsletter['status'] === 'sending' ? '1' : '0' ?>">
            <?php if ($newsletter['status'] === 'sending'): ?>
                <p>Bezig met verzenden: <span data-sent-count><?= (int) ($sendStats['sent'] ?? 0) ?></span> / <?= (int) ($sendStats['total'] ?? 0) ?></p>
                <progress data-progress-bar max="<?= (int) ($sendStats['total'] ?? 1) ?>" value="<?= (int) ($sendStats['sent'] ?? 0) ?>"></progress>
                <?php if (!empty($newsletter['last_send_error'])): ?>
                    <p class="field-hint" data-last-smtp-error>Laatste foutmelding van de mailserver: <?= e($newsletter['last_send_error']) ?></p>
                <?php else: ?>
                    <p class="field-hint" data-last-smtp-error hidden></p>
                <?php endif; ?>
                <p class="form-errors" data-send-error hidden></p>
                <div class="page-form-actions">
                    <button type="button" class="button-secondary" data-retry-send hidden>Opnieuw proberen</button>
                    <form method="post" action="newsletter-edit.php?id=<?= (int) $id ?>"
                          onsubmit="return confirm('Verzending annuleren? Reeds verzonden mails kunnen niet worden teruggehaald; abonnees die nog niet aan de beurt waren, ontvangen deze nieuwsbrief niet.');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="cancel_send" value="1">
                        <button type="submit" class="button-secondary">Annuleren</button>
                    </form>
                </div>
            <?php elseif ($newsletter['status'] === 'cancelled'): ?>
                <p>Verzending geannuleerd op <?= e(date('d/m/Y H:i', strtotime($newsletter['sent_at']))) ?> —
                <?= (int) ($sendStats['sent'] ?? 0) ?> / <?= (int) ($sendStats['total'] ?? 0) ?> abonnees ontvingen de mail nog.</p>
                <?php if (!empty($newsletter['last_send_error'])): ?>
                    <p class="field-hint">Laatste foutmelding van de mailserver vóór het annuleren: <?= e($newsletter['last_send_error']) ?></p>
                <?php endif; ?>
            <?php else: ?>
                <p>Verzonden op <?= e(date('d/m/Y H:i', strtotime($newsletter['sent_at']))) ?> aan <?= (int) ($sendStats['total'] ?? 0) ?> abonnees.
                Geopend door <a href="newsletter-report.php?id=<?= (int) $id ?>"><?= (int) ($sendStats['opened'] ?? 0) ?> (bekijk wie)</a>.</p>
                <?php if (!empty($newsletter['last_send_error'])): ?>
                    <p class="field-hint">
                        Let op: de mailserver meldde een fout bij (een deel van) de laatste batch —
                        "<?= e($newsletter['last_send_error']) ?>". Elke verzending wordt als verzonden
                        geregistreerd zodra ze geprobeerd is, ook als de mailserver ze weigerde — dit
                        is dus geen garantie dat alle abonnees de mail effectief ontvingen.
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <h2>Inhoud</h2>
        <?= render_newsletter_email_body($blocksForEditor, $mysqli) ?>

        <p>
            <a href="newsletters.php" class="button-secondary">Terug naar overzicht</a>
            <a href="newsletter-preview.php?id=<?= (int) $id ?>" class="button-secondary" target="_blank" rel="noopener"><?= admin_icon('eye') ?> Voorvertoning</a>
            <a href="newsletter-report.php?id=<?= (int) $id ?>" class="button-secondary"><?= admin_icon('chart') ?> Wie opende en klikte</a>
        </p>

        <form method="post" action="newsletter-test-send.php" class="page-form">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <label for="test_email">Testmail (opnieuw) versturen naar</label>
            <input type="email" id="test_email" name="test_email" placeholder="jij@voorbeeld.be" required>
            <div class="page-form-actions">
                <button type="submit" class="button-secondary">Stuur testmail</button>
            </div>
        </form>
    </div>

<?php else: ?>

    <?php if ($id): ?>
        <!-- Standalone, unnested <form>s for the sidebar's test-send and
        "Verstuur" actions — their fields live inside the big save-draft
        <form> below (for layout), associated here via each field's
        form="..." attribute instead of actually nesting <form> inside
        <form>, which HTML forbids: a browser silently closes the
        OUTER form at the first nested </form> it meets, corrupting
        everything after it in the same form. -->
        <form id="newsletter-test-send-form" method="post" action="newsletter-test-send.php"></form>
        <form id="newsletter-start-send-form" method="post" action="newsletter-edit.php?id=<?= (int) $id ?>"
              onsubmit="return confirm('Nieuwsbrief versturen naar <?= $recipientCount ?> abonnee<?= $recipientCount === 1 ? '' : 's' ?> (<?= e(addslashes($audienceLabel)) ?>)? Dit kan niet ongedaan gemaakt worden.');"></form>
    <?php endif; ?>

    <form method="post" action="newsletter-edit.php<?= $id ? '?id=' . (int) $id : '' ?>" class="page-form page-form-wide">
        <?= csrf_field() ?>

        <label for="subject">Onderwerp</label>
        <input type="text" id="subject" name="subject" value="<?= e($form['subject']) ?>" required maxlength="200">
        <p class="field-hint">
            Gebruik <code>{{voornaam}}</code> om een abonnee bij de voornaam
            aan te spreken, zowel hier als in de inhoud hieronder — bv. "Hallo
            {{voornaam}}," wordt "Hallo Jan,". Onbekende voornaam valt terug
            op "daar".
        </p>

        <div class="page-form-columns">
            <div class="page-form-main">
                <label>Inhoud</label>
                <p class="field-hint">
                    Beperkte blokkenset — enkel wat betrouwbaar weergeeft in
                    e-mailclients (tekst, foto, quote, lijst, knoppen). Geen
                    kalender, evenementen, kaart, foto+tekst of kolommen.
                </p>
                <div id="block-editor" class="block-editor" data-block-types="text,image,quote,list,buttons,newsletter_events"></div>
                <div id="block-toolbar" class="block-toolbar"></div>
                <script type="application/json" id="initial-blocks"><?= json_encode($blocksForEditor, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
                <input type="hidden" id="blocks_json" name="blocks_json">
            </div>

            <div class="page-form-sidebar">
                <div class="page-form-actions">
                    <button type="submit">Opslaan als concept</button>
                    <a href="newsletters.php" class="button-secondary">Annuleren</a>
                </div>

                <hr>
                <fieldset class="audience-picker" id="audience-picker">
                    <legend>Ontvangers</legend>
                    <?php if (empty($groups) && $form['audience'] === 'all'): ?>
                        <p class="field-hint">
                            Deze nieuwsbrief gaat naar alle abonnees. Wil je enkel een
                            bepaalde doelgroep bereiken? <a href="newsletter-groups.php">Maak eerst groepen aan</a>.
                        </p>
                        <input type="hidden" name="audience" value="all">
                    <?php else: ?>
                        <label class="form-checkbox-row">
                            <input type="radio" name="audience" value="all" <?= $form['audience'] === 'all' ? 'checked' : '' ?>> Alle abonnees
                        </label>
                        <label class="form-checkbox-row">
                            <input type="radio" name="audience" value="groups" <?= $form['audience'] === 'groups' ? 'checked' : '' ?>> Alleen deze groepen:
                        </label>
                        <div class="audience-groups" id="audience-groups">
                            <?php foreach ($groups as $g): ?>
                                <label class="form-checkbox-row">
                                    <input type="checkbox" name="group_ids[]" value="<?= (int) $g['id'] ?>"
                                        <?= in_array((int) $g['id'], $form['group_ids'], true) ? 'checked' : '' ?>>
                                    <?= e($g['name']) ?> <span class="field-hint">(<?= (int) $g['active_count'] ?>)</span>
                                </label>
                            <?php endforeach; ?>
                            <p class="field-hint">Wie in meerdere gekozen groepen zit, krijgt de mail één keer. <a href="newsletter-groups.php">Groepen beheren</a></p>
                        </div>
                    <?php endif; ?>
                </fieldset>

                <?php if ($id): ?>
                    <hr>
                    <p><a href="newsletter-preview.php"?id=<?= (int) $id ?>" target="_blank" rel="noopener"><?= admin_icon('eye') ?> Voorvertoning</a></p>
                    <p class="field-hint">Gebaseerd op de laatst opgeslagen versie.</p>

                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" form="newsletter-test-send-form">
                    <input type="hidden" name="id" value="<?= (int) $id ?>" form="newsletter-test-send-form">
                    <label for="test_email">Testmail naar</label>
                    <input type="email" id="test_email" name="test_email" placeholder="jij@voorbeeld.be" required form="newsletter-test-send-form">
                    <div class="page-form-actions">
                        <button type="submit" form="newsletter-test-send-form" class="button-secondary">Stuur testmail</button>
                    </div>

                    <hr>
                    <p id="send-summary"><strong><?= e($audienceLabel) ?></strong><br>
                        <?= $recipientCount ?> abonnee<?= $recipientCount === 1 ? '' : 's' ?> op dit moment.</p>
                    <p class="field-hint" id="send-dirty-hint" hidden>Je hebt de ontvangers gewijzigd — sla eerst op als concept; versturen gebruikt de bewaarde keuze.</p>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" form="newsletter-start-send-form">
                    <input type="hidden" name="start_send" value="1" form="newsletter-start-send-form">
                    <button type="submit" form="newsletter-start-send-form" class="button" id="send-button" <?= $recipientCount === 0 ? 'disabled' : '' ?>><?= admin_icon('mail') ?> Verstuur</button>
                <?php else: ?>
                    <p class="field-hint">Sla eerst op als concept — versturen en testen kan pas daarna.</p>
                <?php endif; ?>
            </div>
        </div>
    </form>

<?php endif; ?>

<?php if (!$isReadOnly): ?>
<script>
// Toont de groepenlijst enkel bij "Alleen deze groepen", en blokkeert
// "Verstuur" zodra de ontvangers gewijzigd zijn maar nog niet bewaard
// (versturen gebruikt altijd de bewaarde keuze).
(function () {
    var picker = document.getElementById('audience-picker');
    if (!picker) return;
    var groupsBox = document.getElementById('audience-groups');
    var radios = picker.querySelectorAll('input[name="audience"][type="radio"]');
    var sendButton = document.getElementById('send-button');
    var hint = document.getElementById('send-dirty-hint');
    function sync() {
        var chosen = picker.querySelector('input[name="audience"]:checked');
        if (groupsBox && chosen) groupsBox.hidden = chosen.value !== 'groups';
    }
    function markDirty() {
        if (sendButton) sendButton.disabled = true;
        if (hint) hint.hidden = false;
    }
    radios.forEach(function (r) { r.addEventListener('change', function () { sync(); markDirty(); }); });
    picker.querySelectorAll('input[name="group_ids[]"]').forEach(function (c) { c.addEventListener('change', markDirty); });
    sync();
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
