<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';

// Pages opened as file:/// still call this API at http://localhost/... — browsers need CORS.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function build_folder_tree(PDO $pdo): array
{
    $folders = $pdo
        ->query(
            'SELECT id, name FROM folders WHERE deleted_at IS NULL ORDER BY id ASC',
        )
        ->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT id, folder_id, original_name AS name, mime_type AS type, size_bytes AS size
         FROM activity_files WHERE deleted_at IS NULL ORDER BY id ASC',
    );
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $byFolder = [];
    foreach ($rows as $r) {
        $fid = (int) $r['folder_id'];
        if (!isset($byFolder[$fid])) {
            $byFolder[$fid] = [];
        }
        $byFolder[$fid][] = [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'type' => $r['type'],
            'size' => (int) $r['size'],
        ];
    }

    $out = [];
    foreach ($folders as $f) {
        $id = (int) $f['id'];
        $out[] = [
            'id' => $id,
            'name' => $f['name'],
            'files' => $byFolder[$id] ?? [],
        ];
    }

    return $out;
}

function list_deleted_files(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT f.id, f.folder_id, f.original_name AS name, f.mime_type AS type,
                f.size_bytes AS size, f.deleted_at, fo.name AS folder_name
         FROM activity_files f
         INNER JOIN folders fo ON fo.id = f.folder_id
         WHERE f.deleted_at IS NOT NULL
         ORDER BY f.deleted_at DESC',
    );

    return $stmt->fetchAll();
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $pdo = db();

    if ($method === 'GET') {
        $action = $_GET['action'] ?? '';

        if ($action === 'list') {
            json_out(['ok' => true, 'folders' => build_folder_tree($pdo)]);
        }

        if ($action === 'deleted') {
            json_out(['ok' => true, 'files' => list_deleted_files($pdo)]);
        }

        if ($action === 'download') {
            $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            if ($id < 1) {
                json_out(['ok' => false, 'error' => 'Invalid file id'], 400);
            }
            $stmt = $pdo->prepare(
                'SELECT stored_name, original_name, mime_type FROM activity_files WHERE id = ?',
            );
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) {
                json_out(['ok' => false, 'error' => 'File not found'], 404);
            }
            $path = upload_dir() . DIRECTORY_SEPARATOR . $row['stored_name'];
            if (!is_file($path)) {
                json_out(['ok' => false, 'error' => 'File missing on server'], 404);
            }
            $mime = $row['mime_type'] ?: 'application/octet-stream';
            $name = $row['original_name'] ?: 'download';
            header('Content-Type: ' . $mime);
            header(
                'Content-Disposition: inline; filename="' . rawurlencode($name) . '"',
            );
            header('Content-Length: ' . (string) filesize($path));
            readfile($path);
            exit;
        }

        json_out(['ok' => false, 'error' => 'Unknown action'], 400);
    }

    if ($method === 'POST') {
        if (!empty($_FILES['file']) && isset($_POST['action']) && $_POST['action'] === 'file_upload') {
            $folderId = isset($_POST['folder_id']) ? (int) $_POST['folder_id'] : 0;
            if ($folderId < 1) {
                json_out(['ok' => false, 'error' => 'Invalid folder'], 400);
            }
            $check = $pdo->prepare(
                'SELECT id FROM folders WHERE id = ? AND deleted_at IS NULL',
            );
            $check->execute([$folderId]);
            if (!$check->fetch()) {
                json_out(['ok' => false, 'error' => 'Folder not found'], 404);
            }

            $f = $_FILES['file'];
            if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                json_out(['ok' => false, 'error' => 'Upload failed'], 400);
            }

            $original = basename((string) $f['name']);
            if ($original === '' || $original === '.' || $original === '..') {
                json_out(['ok' => false, 'error' => 'Invalid filename'], 400);
            }

            $mime = mime_content_type($f['tmp_name']) ?: 'application/octet-stream';
            $size = (int) $f['size'];
            $stored = bin2hex(random_bytes(16));
            $dest = upload_dir() . DIRECTORY_SEPARATOR . $stored;

            if (!move_uploaded_file($f['tmp_name'], $dest)) {
                json_out(['ok' => false, 'error' => 'Could not save file'], 500);
            }

            $ins = $pdo->prepare(
                'INSERT INTO activity_files (folder_id, original_name, stored_name, mime_type, size_bytes)
                 VALUES (?, ?, ?, ?, ?)',
            );
            $ins->execute([$folderId, $original, $stored, $mime, $size]);
            $newId = (int) $pdo->lastInsertId();

            json_out([
                'ok' => true,
                'file' => [
                    'id' => $newId,
                    'name' => $original,
                    'type' => $mime,
                    'size' => $size,
                ],
                'folders' => build_folder_tree($pdo),
            ]);
        }

        $raw = file_get_contents('php://input');
        $body = $raw !== false && $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($body)) {
            json_out(['ok' => false, 'error' => 'Invalid JSON body'], 400);
        }

        $action = $body['action'] ?? '';

        if ($action === 'folder_create') {
            $name = isset($body['name']) ? trim((string) $body['name']) : '';
            if ($name === '') {
                json_out(['ok' => false, 'error' => 'Name required'], 400);
            }
            $ins = $pdo->prepare('INSERT INTO folders (name) VALUES (?)');
            $ins->execute([$name]);
            json_out(['ok' => true, 'folders' => build_folder_tree($pdo)]);
        }

        if ($action === 'folder_rename') {
            $id = isset($body['id']) ? (int) $body['id'] : 0;
            $name = isset($body['name']) ? trim((string) $body['name']) : '';
            if ($id < 1 || $name === '') {
                json_out(['ok' => false, 'error' => 'Invalid folder or name'], 400);
            }
            $upd = $pdo->prepare(
                'UPDATE folders SET name = ? WHERE id = ? AND deleted_at IS NULL',
            );
            $upd->execute([$name, $id]);
            json_out(['ok' => true, 'folders' => build_folder_tree($pdo)]);
        }

        if ($action === 'folder_delete') {
            $id = isset($body['id']) ? (int) $body['id'] : 0;
            if ($id < 1) {
                json_out(['ok' => false, 'error' => 'Invalid folder'], 400);
            }
            $pdo->prepare(
                'UPDATE folders SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL',
            )->execute([$id]);
            $pdo->prepare(
                'UPDATE activity_files SET deleted_at = NOW()
                 WHERE folder_id = ? AND deleted_at IS NULL',
            )->execute([$id]);
            json_out([
                'ok' => true,
                'folders' => build_folder_tree($pdo),
                'deleted' => list_deleted_files($pdo),
            ]);
        }

        if ($action === 'file_delete') {
            $id = isset($body['id']) ? (int) $body['id'] : 0;
            if ($id < 1) {
                json_out(['ok' => false, 'error' => 'Invalid file'], 400);
            }
            $pdo->prepare(
                'UPDATE activity_files SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL',
            )->execute([$id]);
            json_out([
                'ok' => true,
                'folders' => build_folder_tree($pdo),
                'deleted' => list_deleted_files($pdo),
            ]);
        }

        if ($action === 'file_restore') {
            $id = isset($body['id']) ? (int) $body['id'] : 0;
            if ($id < 1) {
                json_out(['ok' => false, 'error' => 'Invalid file'], 400);
            }
            $stmt = $pdo->prepare(
                'SELECT folder_id FROM activity_files WHERE id = ? AND deleted_at IS NOT NULL',
            );
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) {
                json_out(['ok' => false, 'error' => 'Nothing to restore'], 404);
            }
            $folderId = (int) $row['folder_id'];
            $pdo->prepare(
                'UPDATE folders SET deleted_at = NULL WHERE id = ?',
            )->execute([$folderId]);
            $pdo->prepare(
                'UPDATE activity_files SET deleted_at = NULL WHERE id = ?',
            )->execute([$id]);
            json_out([
                'ok' => true,
                'folders' => build_folder_tree($pdo),
                'deleted' => list_deleted_files($pdo),
            ]);
        }

        if ($action === 'file_purge') {
            $id = isset($body['id']) ? (int) $body['id'] : 0;
            if ($id < 1) {
                json_out(['ok' => false, 'error' => 'Invalid file'], 400);
            }
            $stmt = $pdo->prepare(
                'SELECT stored_name FROM activity_files WHERE id = ? AND deleted_at IS NOT NULL',
            );
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row) {
                json_out(['ok' => false, 'error' => 'Not in trash or still active'], 400);
            }
            $path = upload_dir() . DIRECTORY_SEPARATOR . $row['stored_name'];
            if (is_file($path)) {
                unlink($path);
            }
            $pdo->prepare('DELETE FROM activity_files WHERE id = ?')->execute([$id]);
            json_out([
                'ok' => true,
                'folders' => build_folder_tree($pdo),
                'deleted' => list_deleted_files($pdo),
            ]);
        }

        json_out(['ok' => false, 'error' => 'Unknown action'], 400);
    }

    json_out(['ok' => false, 'error' => 'Method not allowed'], 405);
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], 500);
}
