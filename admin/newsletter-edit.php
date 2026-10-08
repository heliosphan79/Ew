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
    // address that doesn't already have a send row for this newsletter
    // (so re-clicking after a partial batch failure never double-queues
    // anyone), then flip to 'sending' so the page below takes over with
    // the batch-processing view.
    if (isset($_POST['start_send']) && $id) {
        $mysqli->begin_transaction();
        try {
            // Every row needs its own random send_token, which a single
            // INSERT...SELECT can't generate — queue in a loop instead.
            // Subscriber lists here are at most a few thousand rows, and
            // this only runs once per send (re-clicking is a no-op per
            // subscriber thanks to the uniq_newsletter_subscriber key).
            $subscribers = $mysqli->query("SELECT id FROM newsletter_subscribers WHERE status = 'subscribed'")->fetch_all(MYSQLI_ASSOC);
            $insert = $mysqli->prepare(
                'INSERT IGNORE INTO newsletter_sends (newsletter_id, subscriber_id, send_token) VALUES (?, ?, ?)'
            );
            foreach ($subscribers as $sub) {
                $token = newsletter_generate_token();
                $subscriberId = (int) $sub['id'];
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
            $stmt = $mysqli->prepare('UPDATE newsletters SET subject = ?, content = ? WHERE id = ?');
            $stmt->bind_param('ssi', $form['subject'], $contentJson, $id);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $mysqli->prepare('INSERT INTO newsletters (subject, content) VALUES (?, ?)');
            $stmt->bind_param('ss', $form['subject'], $contentJson);
            $stmt->execute();
            $id = $mysqli->insert_id;
            $stmt->close();
        }

        set_flash('success', 'Nieuwsbrief opgeslagen.');
        redirect('newsletter-edit.php?id=' . $id);
    }
}

// Subscriber count, shown next to the "Verstuur" button so the sender
// knows the actual reach before committing.
$subscriberCount = (int) $mysqli->query("SELECT COUNT(*) AS total FROM newsletter_subscribers WHERE status = 'subscribed'")->fetch_assoc()['total'];

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
                Geopend door <?= (int) ($sendStats['opened'] ?? 0) ?>.</p>
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
        <?= render_newsletter_email_body($blocksForEditor) ?>

        <p>
            <a href="newsletters.php" class="button-secondary">Terug naar overzicht</a>
            <a href="newsletter-preview.php?id=<?= (int) $id ?>" class="button-secondary" target="_blank" rel="noopener"><?= admin_icon('eye') ?> Voorvertoning</a>
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
              onsubmit="return confirm('Nieuwsbrief versturen naar <?= $subscriberCount ?> abonnees? Dit kan niet ongedaan gemaakt worden.');"></form>
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
                <div id="block-editor" class="block-editor" data-block-types="text,image,quote,list,buttons"></div>
                <div id="block-toolbar" class="block-toolbar"></div>
                <script type="application/json" id="initial-blocks"><?= json_encode($blocksForEditor, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
                <input type="hidden" id="blocks_json" name="blocks_json">
            </div>

            <div class="page-form-sidebar">
                <div class="page-form-actions">
                    <button type="submit">Opslaan als concept</button>
                    <a href="newsletters.php" class="button-secondary">Annuleren</a>
                </div>

                <?php if ($id): ?>
                    <hr>
                    <p><a href="newsletter-preview.php?id=<?= (int) $id ?>" target="_blank" rel="noopener"><?= admin_icon('eye') ?> Voorvertoning</a></p>
                    <p class="field-hint">Gebaseerd op de laatst opgeslagen versie.</p>

                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" form="newsletter-test-send-form">
                    <input type="hidden" name="id" value="<?= (int) $id ?>" form="newsletter-test-send-form">
                    <label for="test_email">Testmail naar</label>
                    <input type="email" id="test_email" name="test_email" placeholder="jij@voorbeeld.be" required form="newsletter-test-send-form">
                    <div class="page-form-actions">
                        <button type="submit" form="newsletter-test-send-form" class="button-secondary">Stuur testmail</button>
                    </div>

                    <hr>
                    <p><?= $subscriberCount ?> abonnee<?= $subscriberCount === 1 ? '' : 's' ?> op dit moment.</p>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>" form="newsletter-start-send-form">
                    <input type="hidden" name="start_send" value="1" form="newsletter-start-send-form">
                    <button type="submit" form="newsletter-start-send-form" class="button" <?= $subscriberCount === 0 ? 'disabled' : '' ?>><?= admin_icon('mail') ?> Verstuur</button>
                <?php else: ?>
                    <p class="field-hint">Sla eerst op als concept — versturen en testen kan pas daarna.</p>
                <?php endif; ?>
            </div>
        </div>
    </form>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
