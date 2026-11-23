-- CHANGE: Add item history and backfill existing reports and claim dates without dropping data.
USE lostfound;

CREATE TABLE IF NOT EXISTS item_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  item_id INT NOT NULL,
  claim_id INT NULL,
  actor_user_id INT NULL,
  event_type ENUM(
    'report_created',
    'item_approved',
    'item_rejected',
    'claim_submitted',
    'claim_approved',
    'claim_rejected',
    'item_returned',
    'outcome_found',
    'outcome_never_found',
    'item_archived'
  ) NOT NULL,
  claim_date DATETIME NULL,
  details VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (item_id) REFERENCES items(id),
  FOREIGN KEY (claim_id) REFERENCES claims(id),
  FOREIGN KEY (actor_user_id) REFERENCES users(id),
  INDEX idx_item_logs_item_created (item_id, created_at),
  INDEX idx_item_logs_claim (claim_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO item_logs (item_id, actor_user_id, event_type, details, created_at)
SELECT i.id, i.user_id, 'report_created', 'Existing report imported into history', i.created_at
FROM items i
LEFT JOIN item_logs il
  ON il.item_id = i.id AND il.event_type = 'report_created'
WHERE il.id IS NULL;

INSERT INTO item_logs (item_id, claim_id, actor_user_id, event_type, claim_date, details, created_at)
SELECT c.item_id, c.id, c.claimant_id, 'claim_submitted', c.created_at,
       'Existing claim imported into history', c.created_at
FROM claims c
LEFT JOIN item_logs il
  ON il.claim_id = c.id AND il.event_type = 'claim_submitted'
WHERE il.id IS NULL;

INSERT INTO item_logs (item_id, claim_id, actor_user_id, event_type, claim_date, details, created_at)
SELECT c.item_id, c.id, c.reviewed_by,
       CASE c.status WHEN 'approved' THEN 'claim_approved' ELSE 'claim_rejected' END,
       c.created_at, 'Existing claim review imported into history',
       COALESCE(c.reviewed_at, c.created_at)
FROM claims c
LEFT JOIN item_logs il
  ON il.claim_id = c.id
 AND il.event_type = CASE c.status WHEN 'approved' THEN 'claim_approved' ELSE 'claim_rejected' END
WHERE c.status IN ('approved', 'rejected') AND il.id IS NULL;

INSERT INTO item_logs (item_id, event_type, details, created_at)
SELECT i.id, 'item_returned', 'Existing return imported into history',
       COALESCE(i.returned_at, i.updated_at)
FROM items i
LEFT JOIN item_logs il
  ON il.item_id = i.id AND il.event_type = 'item_returned'
WHERE i.status = 'returned' AND il.id IS NULL;
