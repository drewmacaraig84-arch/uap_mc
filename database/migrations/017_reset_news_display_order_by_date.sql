-- Migration 017: Re-apply display_order reset for news_announcements by date proximity
-- Needed because migration 016 only updated rows where display_order = 0
-- This fully resets ALL rows: closest event date to TODAY = position 1 (shown first)

DROP TEMPORARY TABLE IF EXISTS _news_rank_tmp;

CREATE TEMPORARY TABLE _news_rank_tmp AS
  SELECT id, (@r := @r + 1) AS new_order
  FROM news_announcements, (SELECT @r := 0) AS init
  ORDER BY ABS(DATEDIFF(date_posted, CURDATE())) ASC, id DESC;

UPDATE news_announcements n
  JOIN _news_rank_tmp t ON n.id = t.id
SET n.display_order = t.new_order;

DROP TEMPORARY TABLE IF EXISTS _news_rank_tmp;
