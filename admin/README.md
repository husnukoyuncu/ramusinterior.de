# Admin Panel

Project/category management panel for ramusinterior.de, backed by `api.php`
and the JSON files in `/data`.

## Login

The panel is protected by a username/password login (`login.php`), backed
by session cookies. Credentials are stored in `config.php` as a username
plus a bcrypt password hash — the hash is safe to keep in version control,
it cannot be reversed into the original password.

To change the password, generate a new hash and paste it into `config.php`:

```bash
php -r "echo password_hash('yeni-sifre-buraya', PASSWORD_BCRYPT), \"\n\";"
```

Then replace the `password_hash` value in `admin/config.php` with the output.

## Files

- `index.php` — the panel UI (requires login).
- `api.php` — JSON API used by the panel's JS (requires login).
- `login.php` / `logout.php` — session login/logout.
- `auth.php` — shared session/login-check helpers.
- `config.php` — username + password hash (not a plaintext secret).
