# ExamSlot (merged: PHP backend + ExamSlot frontend theme)
- PHP/MySQL backend from `examslot_datesheet.zip`; all pages now use the frontend's `css/style.css` (navy/crimson, light + dark), `js/theme.js` (theme toggle), `js/mascot.js`, split login layout and admin sidebar. Bootstrap removed (compat rules are at the end of style.css).
- Setup: import `sql/schema.sql` (+ `sql/seed_sample.sql`), run `sql/seed_admin.php`, set DB in `config/db.php`, open `/examslot/login.php`.
