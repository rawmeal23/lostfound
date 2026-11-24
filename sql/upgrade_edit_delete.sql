-- CHANGE: Add two history events so owners can edit and delete their own pending reports.
-- Run this ONCE in phpMyAdmin (SQL tab) on an existing database before using
-- edit_post.php and the Delete button on My Posts. It keeps all existing data.
USE lostfound;

ALTER TABLE item_logs
  MODIFY event_type ENUM(
    'report_created',
    'item_approved',
    'item_rejected',
    'claim_submitted',
    'claim_approved',
    'claim_rejected',
    'item_returned',
    'outcome_found',
    'outcome_never_found',
    'item_archived',
    'report_edited',
    'report_withdrawn'
  ) NOT NULL;
