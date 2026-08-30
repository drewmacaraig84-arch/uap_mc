-- Migration 016: Seed display_order for news_announcements based on date proximity (closest to today = top)
-- This is idempotent: only updates rows where display_order = 0

SET @rank := 0;

UPDATE news_announcements
SET display_order = (@rank := @rank + 1)
WHERE display_order = 0
ORDER BY ABS(DATEDIFF(date_posted, CURDATE())) ASC, id DESC;

-- Also normalize any duplicate display_order values by resetting all based on current sort
-- (safe to skip if already set)
