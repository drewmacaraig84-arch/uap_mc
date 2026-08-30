-- 014: Deduplicate and add unique constraint on chapter_milestones (year, title)
DELETE m1 FROM chapter_milestones m1
INNER JOIN chapter_milestones m2 
WHERE m1.id > m2.id 
  AND m1.year = m2.year 
  AND m1.title = m2.title;

-- Add unique index to permanently prevent duplicates at the database level
ALTER TABLE chapter_milestones ADD UNIQUE KEY uq_milestone_year_title (year, title);
