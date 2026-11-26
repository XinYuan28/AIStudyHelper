<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/reminders_backend.php';

// Both operations keep deleted notes recoverable through the existing Trash system.
function delete_folder_notes(int $uid, int $folderId, bool $removeFolder): int {
    $pdo=db(); $pdo->beginTransaction();
    try {
        $st=$pdo->prepare('SELECT id FROM folders WHERE id=? AND user_id=? FOR UPDATE');
        $st->execute([$folderId,$uid]);
        if (!$st->fetchColumn()) throw new ReminderException(404, 'That folder could not be found.');
        $st=$pdo->prepare('UPDATE notes SET deleted_at=NOW() WHERE folder_id=? AND user_id=? AND deleted_at IS NULL');
        $st->execute([$folderId,$uid]); $count=$st->rowCount();
        if ($removeFolder) {
            $pdo->prepare('UPDATE notes SET folder_id=NULL WHERE folder_id=? AND user_id=?')->execute([$folderId,$uid]);
            $pdo->prepare('DELETE FROM folders WHERE id=? AND user_id=?')->execute([$folderId,$uid]);
        }
        $pdo->commit(); return $count;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
