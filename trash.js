const trashElement = id => document.getElementById(id);
let trashRequestVersion = 0;
let trashLoading = false;
let trashActions = 0;

function trashStatus(message, error = false) {
    const status = trashElement('trashStatus');
    status.textContent = message;
    status.classList.toggle('error', error);
}

async function trashRequest(action, fields) {
    let url = 'api.php?action=' + encodeURIComponent(action);
    const options = { credentials: 'same-origin' };
    if (fields) {
        const body = new FormData();
        body.set('csrf', window.STUDYHUB_CSRF);
        Object.entries(fields).forEach(([key, value]) => body.set(key, value));
        options.method = 'POST';
        options.body = body;
    } else {
        url += '&sort=' + encodeURIComponent(trashElement('trashSort').value);
    }
    const response = await fetch(url, options);
    const data = await response.json();
    if (!response.ok || !data.ok) throw new Error(data.error || 'Could not update Trash. Please try again.');
    return data;
}

function renderTrash(notes) {
    const list = trashElement('trashList');
    list.replaceChildren();
    trashElement('trashCount').textContent = notes.length + (notes.length === 1 ? ' deleted note' : ' deleted notes');
    if (!notes.length) {
        const empty = document.createElement('p');
        empty.className = 'trash-empty';
        empty.textContent = 'Your Trash is empty.';
        list.append(empty);
        return;
    }
    notes.forEach(note => {
        const card = document.createElement('article');
        card.className = 'trash-note';
        const content = document.createElement('div');
        content.className = 'trash-note-content';
        const title = document.createElement('h2');
        title.textContent = note.title;
        const dates = document.createElement('p');
        const deleted = new Date(note.deleted_ts * 1000);
        const expires = new Date(note.expires_ts * 1000);
        dates.textContent = 'Deleted ' + deleted.toLocaleString() + ' · Permanent deletion ' + expires.toLocaleString();
        const files = document.createElement('p');
        files.className = 'trash-file-count';
        files.textContent = note.file_count + (note.file_count === 1 ? ' source file' : ' source files');
        content.append(title, dates, files);
        const actions = document.createElement('div');
        actions.className = 'trash-note-actions';
        const restore = document.createElement('button');
        restore.type = 'button';
        restore.className = 'btn small';
        restore.textContent = 'Restore';
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn ghost small trash-delete';
        remove.textContent = 'Delete permanently';
        const runAction = async (action, success) => {
            restore.disabled = true;
            remove.disabled = true;
            trashActions++;
            try {
                await trashRequest(action, { id: note.id });
                await loadTrash();
                trashStatus(success);
            } catch (error) {
                await loadTrash().catch(() => {});
                trashStatus(error.message, true);
            } finally {
                trashActions--;
                restore.disabled = false;
                remove.disabled = false;
            }
        };
        restore.onclick = () => runAction('restore_note', 'Restored “' + note.title + '” to Study Hub.');
        remove.onclick = () => {
            if (!window.confirm('Permanently delete “' + note.title + '”? This cannot be undone.')) return;
            return runAction('permanently_delete_note', 'Permanently deleted “' + note.title + '”.');
        };
        actions.append(restore, remove);
        card.append(content, actions);
        list.append(card);
    });
}

async function loadTrash() {
    const version = ++trashRequestVersion;
    trashLoading = true;
    trashElement('trashList').setAttribute('aria-busy', 'true');
    try {
        const data = await trashRequest('list_trash');
        if (version === trashRequestVersion) renderTrash(data.notes);
    } catch (error) {
        if (version === trashRequestVersion) {
            trashStatus(error.message, true);
            throw error;
        }
    } finally {
        if (version === trashRequestVersion) {
            trashLoading = false;
            trashElement('trashList').setAttribute('aria-busy', 'false');
        }
    }
}

trashElement('trashSort').onchange = () => loadTrash().catch(() => {});
document.addEventListener('visibilitychange', () => {
    if (!document.hidden && !trashLoading && !trashActions) loadTrash().catch(() => {});
});
setInterval(() => {
    if (!document.hidden && !trashLoading && !trashActions) loadTrash().catch(() => {});
}, 60000);
loadTrash().catch(() => {});