LOST AND FOUND - FRONT END (HTML, CSS, JS)

HOW TO OPEN: double-click index.html, or put this folder inside htdocs (XAMPP) and visit http://localhost/lostfound-frontend/

WHERE TO EDIT
- Text, school name, contact info, steps, dropdown choices, sample items, and image paths: js/config.js
- Colors and fonts: top of css/style.css (the :root block)
- Page-specific wording: look for <!-- EDIT --> comments inside each .html file
- Form titles on report.html: js/main.js, search "EDIT: page title"
- Images: put files in the /images folder with the names used in js/config.js
  (logo.png, hero.jpg, item1.jpg ... item6.jpg). Missing images show a gray placeholder.

PAGES: index, browse, item, report (lost and found), login, my-posts, admin
BACK END: search main.js for "TODO (PHP)" to see where forms and lists connect to PHP + MySQL.
Currently the data is sample data and forms only show a success message (nothing is saved).
