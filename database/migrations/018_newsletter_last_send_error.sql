-- Keeps the most recent SMTP-level failure message (an auth rejection, a
-- relay refusal, a timeout, ...) visible on the newsletter itself, not
-- just transiently during the live "Bezig met verzenden" view — every
-- send is still marked sent_at regardless of actual delivery success (see
-- admin/newsletter-send.php), so without this, a newsletter that fully
-- failed at the SMTP level still ends up looking identical to one that
-- actually went out. Refreshed every batch: NULL once a batch completes
-- with no failures, so it reflects the current state, not a stale one
-- from early in a long send.
ALTER TABLE newsletters ADD COLUMN last_send_error VARCHAR(500) DEFAULT NULL;
