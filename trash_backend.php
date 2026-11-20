<?php
require_once __DIR__ . '/db.php';

const TRASH_RETENTION_SECONDS = 10 * 24 * 60 * 60;

function purge_expired_notes(?int $uid = null): int
{
    $sql = 'DELETE FROM notes WHERE deleted_at IS NOT NULL
        AND deleted_at <= FROM_UNIXTIME(UNIX_TIMESTAMP() - ' . TRASH_RETENTION_SECONDS . ')';
    $params = [];
    if ($uid !== null) {
        $sql .= ' AND user_id = ?';
        $params[] = $uid;
    }
    $st = db()->prepare($sql . ' ORDER BY deleted_at, id LIMIT 500');
    $total = 0;
    do {
        $st->execute($params);
        $count = $st->rowCount();
        $total += $count;
    } while ($count === 500);
    return $total;
}

function list_trashed_notes(int $uid, string $sort): array
{
    purge_expired_notes($uid);
    $direction = $sort === 'oldest' ? 'ASC' : 'DESC';
    $st = db()->prepare('SELECT n.id, n.title, UNIX_TIMESTAMP(n.deleted_at) AS deleted_ts,
        UNIX_TIMESTAMP(n.deleted_at) + ' . TRASH_RETENTION_SECONDS . ' AS expires_ts,
        (SELECT COUNT(*) FROM note_files f WHERE f.note_id = n.id) AS file_count
        FROM notes n WHERE n.user_id = ? AND n.deleted_at IS NOT NULL
        AND n.deleted_at > FROM_UNIXTIME(UNIX_TIMESTAMP() - ' . TRASH_RETENTION_SECONDS . ')
        ORDER BY n.deleted_at ' . $direction . ', n.id ' . $direction);
    $st->execute([$uid]);
    $notes = $st->fetchAll();
    foreach ($notes as &$note) {
        $note['id'] = (int) $note['id'];
        $note['deleted_ts'] = (int) $note['deleted_ts'];
        $note['expires_ts'] = (int) $note['expires_ts'];
        $note['file_count'] = (int) $note['file_count'];
    }
    return $notes;
}

function restore_trashed_note(int $uid, int $id): bool
{
    $st = db()->prepare('UPDATE notes SET deleted_at = NULL, updated_at = CURRENT_TIMESTAMP
        WHERE id = ? AND user_id = ? AND deleted_at IS NOT NULL
        AND deleted_at > FROM_UNIXTIME(UNIX_TIMESTAMP() - ' . TRASH_RETENTION_SECONDS . ')');
    $st->execute([$id, $uid]);
    return $st->rowCount() > 0;
}

function permanently_delete_trashed_note(int $uid, int $id): bool
{
    $st = db()->prepare('DELETE FROM notes WHERE id = ? AND user_id = ? AND deleted_at IS NOT NULL');
    $st->execute([$id, $uid]);
    return $st->rowCount() > 0;
}