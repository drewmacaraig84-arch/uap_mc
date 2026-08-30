-- Migration 018: Compact news display_order to sequential 1,2,3... preserving current sort order
-- Fixes gaps like 2,3,4 → becomes 1,2,3

DROP TEMPORARY TABLE IF EXISTS _news_compact_tmp;

CREATE TEMPORARY TABLE _news_compact_tmp AS
  SELECT id, (@r := @r + 1) AS new_order
  FROM news_announcements, (SELECT @r := 0) AS init
  WHERE is_active = 1
  ORDER BY display_order ASC, id DESC;

UPDATE news_announcements n
  JOIN _news_compact_tmp t ON n.id = t.id
SET n.display_order = t.new_order;

DROP TEMPORARY TABLE IF EXISTS _news_compact_tmp;
