-- 013: Deduplicate chapter_milestones table and keep unique records
DELETE m1 FROM chapter_milestones m1
INNER JOIN chapter_milestones m2 
WHERE m1.id > m2.id 
  AND m1.year = m2.year 
  AND m1.title = m2.title;
