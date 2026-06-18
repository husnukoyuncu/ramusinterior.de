# Admin Panel

Project/category management panel for ramusinterior.de, backed by `api.php`
and the JSON files in `/data`.

## Security warning

`api.php` currently has **no authentication**. Anyone who can reach
`/admin/api.php` can create, edit, or delete projects and categories, and
upload images. Add a login gate (e.g. session-based auth with a hashed
password, or HTTP Basic Auth at the webserver level restricted to
`/admin/`) before relying on this panel in production.
