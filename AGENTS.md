# Rules for any AI tool working on this repo

Read these rules and `docs/BLUEPRINT.md` BEFORE writing any code. The blueprint is the source of truth.
Project: Campus Lost & Found. Plain PHP 8 + MySQL (PDO) + HTML/CSS/vanilla JS on XAMPP.

## Scope
1. Only touch the files named in my task. Do not rename, move, delete or reformat any other file.
2. If the task seems to need a change in a file I did not name, STOP and tell me which file and why. Do not edit it.
3. Do not add frameworks, Composer packages, npm packages or CDN scripts.

## Architecture (see BLUEPRINT sections 10-13)
4. `public/` pages are thin: read input, call a function from `src/`, include templates. No SQL in pages.
5. All SQL lives in `src/`. `src/` functions never echo HTML and never redirect. They return arrays.
6. Before writing a new function, check `src/` for one that already exists. Do not create duplicates.
7. Function signatures in BLUEPRINT section 13 are contracts. Never change one. Add a new function instead.
8. Always include `templates/header.php` and `templates/footer.php`. Do not write a full HTML page by hand.

## Database
9. Never change the database structure from PHP code. If a column or table is needed, STOP and propose the change to `sql/schema.sql` and BLUEPRINT section 12 first.
10. Use the exact status strings from BLUEPRINT section 3. Never invent a new status.
11. Column and table names are exactly as in `sql/schema.sql`.

## Security (non-negotiable)
12. Every query uses PDO prepared statements. Never concatenate user input into SQL.
13. Passwords: `password_hash()` and `password_verify()` only.
14. Escape all output with `e()`. Every POST form uses `csrf_field()` and `csrf_check()`.
15. Check the role with `require_login()` / `require_admin()` at the top of every protected page.
16. Uploads: validate type (jpg, png) and size, rename the file, save only inside `uploads/`.

## Style
17. snake_case for PHP functions, variables, DB columns and file names. UPPER_CASE for constants.
18. CSS class names in kebab-case. Put new CSS in your own file, not in someone else's.
19. Keep code simple and commented in plain English. No clever one-liners.

## How to answer me
20. Show the complete content of each changed file (only files in scope).
21. After the code, list: files changed, what each does, and how to test it in the browser.
22. If you are unsure about anything, ask me. Do not guess about files you cannot see.
23. If a contract or the schema changed, tell me exactly which lines of BLUEPRINT.md to update, and add one line to `docs/CHANGELOG.md`.
