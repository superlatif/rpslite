---
paths:
  - 'app/Filament/Pages/**'
---

# Pages

## Date traps in report queries
SQLite stores trs_date as datetime string ("2026-08-13 00:00:00") even for `date` columns: use whereDate() for range comparisons, never whereBetween on the raw column. Carbon diffInDays() returns a signed float (negative when the later date is the receiver): wrap with abs() before bucketing/aging.
