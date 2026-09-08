-- Migration: switch pages from free-form WYSIWYG HTML to a block-based
-- content model, and add a per-page frontend theme variant (a/b/c/d).
--
-- Only needed if you already ran the original schema.sql (before this
-- change) and imported it into a real database. Fresh installs should just
-- (re)import the current database/schema.sql instead.
--
-- IMPORTANT: this does NOT try to convert existing Quill HTML into blocks
-- (the two content models are too different to convert automatically).
-- Any pages you already created will have their content reset to an empty
-- block list — recreate that content using the new block editor after
-- running this migration.

ALTER TABLE pages
    ADD COLUMN theme_variant VARCHAR(4) NOT NULL DEFAULT 'a' AFTER content;

UPDATE pages SET content = '[]';
