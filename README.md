# TCW and History Activities Compilation

A simple static site for **Home**, **Members**, and **Activities**, with Activities backed by **MySQL** and **PHP** so folders and uploaded files persist on the server. Deleted files are kept in a **Recently deleted** list until you restore them or remove them permanently.

## Requirements

- [XAMPP](https://www.apachefriends.org/) (or similar) with:
  - **Apache**
  - **PHP** (with PDO MySQL and `fileinfo` enabled — default in XAMPP)
  - **MySQL** / MariaDB
- A modern web browser

## Installation

### 1. Put the project in the web root

Example (XAMPP on Windows):

`C:\xampp\htdocs\cris\`

### 2. Create the database

1. Start **MySQL** in the XAMPP Control Panel.
2. Open **phpMyAdmin** (usually `http://localhost/phpmyadmin`).
3. Import the file **`database/schema.sql`**, or run its SQL in the SQL tab.

This creates the database `cris_activities` and tables `folders` and `activity_files`.

### 3. Check database credentials (if needed)

Edit **`includes/config.php`** if your MySQL user or password is not the XAMPP default (`root` with an empty password).

### 4. Member and home photos

Place JPG (or other) images in the **`images/`** folder. The site expects paths like `images/<filename>.jpg` as referenced in **`index.html`**.

### 5. Upload storage

Uploaded activity files are stored under **`uploads/`** (created automatically when someone uploads). Ensure the web server user can create files there (default XAMPP setup is fine).

## How to run the site

1. Start **Apache** and **MySQL** in XAMPP.
2. Open the site in the browser using the **HTTP** address, for example:
   - `http://localhost/cris/`
   - or `http://localhost/cris/index.html`

**Do not** open `index.html` by double-clicking it. If the address bar starts with `file:///`, **Activities** will not work (PHP will not run and the API cannot be reached).

If Apache uses a custom port (e.g. 8080), use:

`http://localhost:8080/cris/`

## Using the site

### Navigation

- **HOME** — Cover image, title, and quote.
- **MEMBERS** — Group member cards with photos and profile links.
- **ACTIVITIES** — Folders and file uploads tied to the database.

### Activities

1. Type a folder name under **New folder** and click **Add folder**.
2. In each folder, use the file input to **upload** documents (PDF, images, etc.).
3. **View** opens the file in a new tab (served by the server).
4. **Delete** on a file moves it to **Recently deleted** (soft delete); the file stays in the database and on disk until you purge it.
5. **Update** / **Delete** on a folder renames it or removes it from the main list; files in that folder are moved to **Recently deleted**.
6. Open **Recently deleted** to **Restore** a file or **Delete forever** (removes the database row and the file under `uploads/`).

### PHP upload limits

Very large uploads may require raising `upload_max_filesize` and `post_max_size` in `php.ini`, then restarting Apache.

## Project layout

| Path | Purpose |
|------|---------|
| `index.html` | Main UI (all sections and client scripts) |
| `images/` | Static photos for home and members |
| `api/activities.php` | REST-style API: list folders, upload, soft delete, restore, download |
| `includes/config.php` | Database DSN and upload directory helper |
| `database/schema.sql` | MySQL schema |
| `uploads/` | Stored uploads (created at runtime) |

## Troubleshooting

| Problem | What to try |
|--------|-------------|
| **Failed to fetch** on Activities | Use `http://localhost/cris/` (not `file://`). Start Apache. |
| Database errors | Import `database/schema.sql`. Start MySQL. Check `includes/config.php`. |
| Images missing | Confirm files exist under `images/` and names match `index.html`. |
| Permission errors on upload | Check that `uploads/` is writable by Apache. |

## Security note

This project is intended for **local or trusted classroom use**. It does not include login or hardened public-facing security. Do not expose it to the open internet without adding authentication and a full security review.
