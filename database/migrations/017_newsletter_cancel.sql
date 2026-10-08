-- Adds a 'cancelled' status so an in-progress send (status = 'sending')
-- can be stopped from the admin instead of only ever running to
-- completion or getting stuck — see admin/newsletter-edit.php's
-- "Annuleren" action. Already-sent newsletter_sends rows are untouched;
-- whatever hadn't gone out yet simply never does.
ALTER TABLE newsletters MODIFY COLUMN status ENUM('draft', 'sending', 'sent', 'cancelled') NOT NULL DEFAULT 'draft';
