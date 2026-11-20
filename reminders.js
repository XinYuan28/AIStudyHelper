const byId = id => document.getElementById(id);
const zone = Intl.DateTimeFormat().resolvedOptions().timeZone;
let editingId = null;
let reminders = [];
let savedNotes = [];
let selectedDay = localInput(new Date()).slice(0, 10);
let visibleMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1);
byId('todayLabel').textContent = new Date().toLocaleDateString(undefined, {
    weekday: 'long', day: 'numeric', month: 'long'
});

function chooseDay(day) {
    selectedDay = day;
    byId('reminderDate').value = day;
    byId('selectedDateLabel').textContent = new Date(day + 'T12:00:00').toLocaleDateString(undefined, {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
    });
    renderCalendar();
    renderReminders();
    updateSessionPreview();
}

function renderCalendar() {
    byId('calendarMonth').textContent = visibleMonth.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
    const days = byId('calendarDays');
    days.replaceChildren();
    const year = visibleMonth.getFullYear();
    const month = visibleMonth.getMonth();
    const firstWeekday = new Date(year, month, 1).getDay();
    const count = new Date(year, month + 1, 0).getDate();
    const today = localInput(new Date()).slice(0, 10);
    const reminderDays = new Set(reminders.map(item => localInput(new Date(item.scheduled_at)).slice(0, 10)));
    for (let blank = 0; blank < firstWeekday; blank++) {
        days.append(document.createElement('span'));
    }
    for (let number = 1; number <= count; number++) {
        const day = localInput(new Date(year, month, number)).slice(0, 10);
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = number;
        button.className = 'calendar-day';
        button.classList.toggle('has-reminders', reminderDays.has(day));
        button.setAttribute('aria-pressed', String(day === selectedDay));
        button.setAttribute('aria-label', new Date(year, month, number).toLocaleDateString()
            + (reminderDays.has(day) ? ', has study reminders' : ''));
        if (day === today) button.setAttribute('aria-current', 'date');
        button.onclick = () => chooseDay(day);
        days.append(button);
    }
}

async function loadNotes() {
    savedNotes = (await request('list')).notes;
    const select = byId('reminderTitle');
    select.replaceChildren(new Option('Choose a saved note', ''));
    savedNotes.forEach(note => select.add(new Option(note.title, note.id)));
    byId('noteSelectionStatus').textContent = savedNotes.length
        ? '' : 'Save study materials in Study Hub to add a reminder.';
    byId('saveReminder').disabled = !savedNotes.length;
}

async function request(action, fields) {
    const options = { credentials: 'same-origin' };
    if (fields) {
        const body = new FormData();
        body.set('csrf', window.STUDYHUB_CSRF);
        Object.entries(fields).forEach(([key, value]) => body.set(key, value));
        options.method = 'POST';
        options.body = body;
    }
    const response = await fetch('api.php?action=' + action, options);
    const data = await response.json();
    if (!response.ok || !data.ok) {
        const error = new Error(data.error || 'Please try again.');
        error.status = response.status;
        throw error;
    }
    return data;
}

function localInput(date) {
    const pad = value => String(value).padStart(2, '0');
    return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate())
        + 'T' + pad(date.getHours()) + ':' + pad(date.getMinutes());
}

function durationMinutes() {
    const hours = Number(byId('hours').value);
    const minutes = Number(byId('minutes').value);
    if (!Number.isInteger(hours) || hours < 0 || !Number.isInteger(minutes) || minutes < 0 || minutes > 59) return NaN;
    return hours * 60 + minutes;
}

function updateSessionPreview() {
    const start = new Date(byId('reminderDate').value + 'T' + byId('reminderTime').value);
    const duration = durationMinutes();
    const preview = byId('sessionPreview');
    if (!Number.isFinite(start.getTime()) || !Number.isInteger(duration) || duration < 1) {
        preview.textContent = '';
        return;
    }
    const end = new Date(start.getTime() + duration * 60000);
    const format = { hour: 'numeric', minute: '2-digit' };
    const endLabel = localInput(start).slice(0, 10) === localInput(end).slice(0, 10)
        ? end.toLocaleTimeString([], format) : end.toLocaleString([], { ...format, month: 'short', day: 'numeric' });
    preview.textContent = start.toLocaleTimeString([], format) + ' – ' + endLabel + ' · ' + duration + ' minutes';
}
['hours', 'minutes', 'reminderTime'].forEach(id => byId(id).addEventListener('input', updateSessionPreview));

function resetForm() {
    editingId = null;
    byId('reminderForm').reset();
    updateReminderMethod();
    chooseDay(selectedDay);
    byId('formTitle').textContent = 'Study reminders';
    byId('saveReminder').textContent = 'Add reminder';
    const legacyOption = byId('reminderTitle').querySelector('[data-legacy]');
    if (legacyOption) legacyOption.remove();
    byId('resetReminder').hidden = true;
}

async function loadReminders() {
    reminders = (await request('list_reminders')).reminders;
    renderCalendar();
    renderReminders();
}

function renderReminders() {
    const list = byId('reminderList');
    list.replaceChildren();
    const daily = reminders.filter(item => localInput(new Date(item.scheduled_at)).slice(0, 10) === selectedDay);
    if (!daily.length) {
        list.textContent = 'No study reminders for this day yet.';
        return;
    }
    daily.forEach(reminder => {
        const card = document.createElement('article');
        card.className = 'card';
        const title = document.createElement('h4');
        title.textContent = reminder.title;
        const details = document.createElement('p');
        details.textContent = new Date(reminder.scheduled_at).toLocaleString() + ' · '
            + reminder.duration_minutes + ' minutes · Ends '
            + new Date(new Date(reminder.scheduled_at).getTime() + reminder.duration_minutes * 60000).toLocaleString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) + ' · '
            + (reminder.delivery_method === 'calendar' ? 'Calendar' : 'Telegram')
            + (reminder.delivery_method === 'telegram' ? ' · ' + (reminder.lead_minutes ? reminder.lead_minutes + ' minutes before' : 'At study start') : '')
            + (reminder.telegram_sent_at_utc ? ' · Sent' : '');
        card.append(title, details);
        if (reminder.description) {
            const description = document.createElement('p');
            description.textContent = reminder.description;
            card.append(description);
        }
        if (savedNotes.some(note => String(note.id) === String(reminder.note_id))) {
            const noteLink = document.createElement('a');
            noteLink.href = 'studyhub.php?note=' + encodeURIComponent(reminder.note_id);
            noteLink.textContent = 'Open study note';
            card.append(noteLink);
        }
        if (reminder.delivery_method === 'calendar') {
            const link = document.createElement('a');
            link.className = 'calendar-link';
            link.href = 'api.php?action=download_reminder_calendar&id=' + reminder.id;
            link.textContent = 'Add to calendar (.ics)';
            card.append(link);
            const google = document.createElement('a');
            const start = new Date(reminder.scheduled_at);
            const end = new Date(start.getTime() + reminder.duration_minutes * 60000);
            const stamp = date => date.toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, '');
            google.href = 'https://calendar.google.com/calendar/render?' + new URLSearchParams({
                action: 'TEMPLATE', text: reminder.title, dates: stamp(start) + '/' + stamp(end),
                details: (reminder.description ? reminder.description + '\n\n' : '')
                    + 'Study Helper reminder. Set a notification at the event time before saving.'
            });
            google.target = '_blank';
            google.rel = 'noopener';
            google.textContent = 'Add to Google Calendar';
            card.append(google);
        }
        {
            const edit = document.createElement('button');
            edit.className = 'btn ghost';
            edit.textContent = 'Edit';
            edit.onclick = () => {
                editingId = reminder.id;
                const matchingNote = savedNotes.find(note => String(note.id) === String(reminder.note_id));
                const legacy = byId('reminderTitle').querySelector('[data-legacy]');
                if (legacy) legacy.remove();
                if (!matchingNote) {
                    const option = new Option(reminder.title + ' (previous reminder)', 'legacy');
                    option.dataset.legacy = 'true';
                    byId('reminderTitle').add(option);
                }
                byId('reminderTitle').value = matchingNote ? matchingNote.id : 'legacy';
                const local = localInput(new Date(reminder.scheduled_at));
                byId('reminderDate').value = local.slice(0, 10);
                byId('reminderTime').value = local.slice(11, 16);
                byId('hours').value = Math.floor(reminder.duration_minutes / 60);
                byId('minutes').value = reminder.duration_minutes % 60;
                updateSessionPreview();
                byId('reminderDescription').value = reminder.description || '';
                byId('reminderMethod').value = reminder.delivery_method;
                byId('reminderLead').value = reminder.lead_minutes || 0;
                updateReminderMethod();
                byId('formTitle').textContent = 'Edit reminder';
                byId('saveReminder').textContent = 'Save changes';
                byId('saveReminder').disabled = false;
                byId('resetReminder').hidden = false;
                byId('reminderTitle').focus();
            };
            const cancel = document.createElement('button');
            cancel.className = 'btn ghost';
            cancel.textContent = 'Delete reminder';
            cancel.onclick = async () => {
                cancel.disabled = true;
                try {
                    await request('delete_reminder', { id: reminder.id });
                    if (editingId === reminder.id) resetForm();
                    await loadReminders();
                    byId('reminderStatus').textContent = 'Reminder deleted. Remove any copy you saved in your calendar.';
                } catch (error) {
                    byId('reminderStatus').textContent = error.message;
                    cancel.disabled = false;
                }
            };
            card.append(edit, cancel);
        }
        list.append(card);
    });
}

byId('reminderForm').onsubmit = async event => {
    event.preventDefault();
    const inputDate = byId('reminderDate').value + 'T' + byId('reminderTime').value;
    const date = new Date(inputDate);
    if (!Number.isFinite(date.getTime()) || date <= new Date()) {
        byId('reminderStatus').textContent = 'Choose a future date and time.';
        return;
    }
    if (localInput(date) !== inputDate) {
        byId('reminderStatus').textContent = 'Choose a valid local time.';
        return;
    }
    const duration = durationMinutes();
    if (!Number.isInteger(duration) || duration < 1 || duration > 2147483647) {
        byId('reminderStatus').textContent = 'Choose a study period of at least one minute.';
        byId('minutes').focus();
        return;
    }
    const offset = -date.getTimezoneOffset();
    const pad = value => String(value).padStart(2, '0');
    const suffix = (offset >= 0 ? '+' : '-') + pad(Math.floor(Math.abs(offset) / 60)) + ':' + pad(Math.abs(offset) % 60);
    const noteId = byId('reminderTitle').value;
    const note = savedNotes.find(item => String(item.id) === noteId);
    const previous = reminders.find(item => item.id === editingId);
    if (!note && !(editingId && noteId === 'legacy' && previous)) {
        byId('reminderStatus').textContent = 'Choose a saved study note.';
        return;
    }
    const fields = { title: note ? note.title : previous.title,
        scheduled_at: localInput(date) + ':00' + suffix,
        note_id: note ? note.id : '',
        duration_minutes: durationMinutes(),
        lead_minutes: byId('reminderLead').value,
        description: byId('reminderDescription').value,
        time_zone: zone, delivery_method: byId('reminderMethod').value };
    if (editingId) fields.id = editingId;
    byId('saveReminder').disabled = true;
    try {
        await request(editingId ? 'edit_reminder' : 'create_reminder', fields);
        resetForm();
        await loadReminders();
        byId('reminderStatus').textContent = fields.delivery_method === 'calendar'
            ? 'Saved. Add the event to your calendar and check its notification time.'
            : 'Saved. Your connected Telegram bot will send the reminder.';
    } catch (error) {
        byId('reminderStatus').textContent = error.message;
        if (error.status === 409) {
            byId('reminderTime').focus();
            await loadReminders().catch(() => {});
        }
    } finally {
        byId('saveReminder').disabled = false;
    }
};
byId('resetReminder').onclick = resetForm;
byId('reminderDate').onchange = () => {
    if (!byId('reminderDate').value) return;
    visibleMonth = new Date(byId('reminderDate').value + 'T12:00:00');
    visibleMonth.setDate(1);
    chooseDay(byId('reminderDate').value);
};
byId('calendarPrevious').onclick = () => {
    visibleMonth = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() - 1, 1);
    renderCalendar();
};
byId('calendarNext').onclick = () => {
    visibleMonth = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() + 1, 1);
    renderCalendar();
};
byId('calendarToday').onclick = () => {
    const today = new Date();
    visibleMonth = new Date(today.getFullYear(), today.getMonth(), 1);
    chooseDay(localInput(today).slice(0, 10));
};
async function checkTelegram() {
    const data = await request('telegram_status');
    byId('telegramStatus').textContent = !data.configured ? 'Telegram reminders are awaiting bot setup.'
        : data.connected ? 'Telegram connected.' : 'Telegram is not connected. After pressing Start, allow up to a minute, then check the connection.';
    byId('connectTelegram').disabled = !data.configured;
    byId('disconnectTelegram').hidden = !data.connected;
}
byId('connectTelegram').onclick = async () => {
    try {
        const data = await request('connect_telegram', {});
        byId('telegramLink').href = data.url;
        byId('telegramLink').hidden = false;
        byId('telegramStartHint').hidden = false;
    } catch (error) { byId('telegramStatus').textContent = error.message; }
};
byId('checkTelegram').onclick = () => checkTelegram().catch(error => { byId('telegramStatus').textContent = error.message; });
byId('disconnectTelegram').onclick = async () => {
    try { await request('disconnect_telegram', {}); await checkTelegram(); }
    catch (error) { byId('telegramStatus').textContent = error.message; }
};
function updateReminderMethod() {
    const telegram = byId('reminderMethod').value === 'telegram';
    byId('reminderLeadField').hidden = !telegram;
    byId('methodHelp').textContent = telegram
        ? 'Choose when NotePawBot should notify you before study starts.'
        : 'After saving, add the event to your calendar and check its alert. Calendar copies need to be updated separately.';
}
byId('reminderMethod').addEventListener('change', updateReminderMethod);
updateReminderMethod();

chooseDay(selectedDay);
loadNotes().then(loadReminders).catch(error => {
    byId('reminderStatus').textContent = error.message;
    byId('saveReminder').disabled = true;
});
checkTelegram().catch(error => { byId('telegramStatus').textContent = error.message; });
