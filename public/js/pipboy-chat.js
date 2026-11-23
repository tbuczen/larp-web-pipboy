// Direct chat thread: sends without reloading and polls for new messages.
// The page works without this file (forms post and redirect). Messages are
// inserted as text, never as HTML.
(() => {
    const t = window.pipT ?? ((text) => text);
    const ACTIVE_INTERVAL = 4000;
    const HIDDEN_INTERVAL = 30000;

    const init = () => {
        const thread = document.querySelector('[data-chat-thread]');
        if (!thread) {
            return;
        }
        let after = Number(thread.dataset.after) || 0;
        let timer = null;
        const otherLabel = (document.querySelector('.pip-detail h2')?.textContent || '').split(' · ')[0];

        const scrollToEnd = () => { thread.scrollTop = thread.scrollHeight; };

        const append = (messages) => {
            for (const message of messages) {
                if (message.id <= after || thread.querySelector(`[data-id="${message.id}"]`)) {
                    continue;
                }
                const item = document.createElement('li');
                item.className = `pip-chat-msg ${message.mine ? 'pip-chat-msg--mine' : 'pip-chat-msg--theirs'} is-new`;
                item.dataset.id = String(message.id);
                const body = document.createElement('p');
                body.textContent = message.body;
                const time = document.createElement('time');
                time.dateTime = message.sentAt;
                time.textContent = `${message.mine ? t('You') : otherLabel} · ${message.time}`;
                item.append(body, time);
                thread.append(item);
                after = Math.max(after, message.id);
            }
            scrollToEnd();
        };

        const poll = async () => {
            window.clearTimeout(timer);
            try {
                const response = await fetch(`${thread.dataset.pollUrl}?after=${after}`, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                if (response.ok) {
                    const data = await response.json();
                    append(data.messages);
                    // Accepted, closed or answered elsewhere: show the new state.
                    if (data.status !== thread.dataset.status) {
                        window.location.reload();
                        return;
                    }
                }
            } catch {
                // Offline for a moment; try again on the next tick.
            }
            schedule();
        };

        const schedule = () => {
            timer = window.setTimeout(poll, document.visibilityState === 'visible' ? ACTIVE_INTERVAL : HIDDEN_INTERVAL);
        };

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                poll();
            }
        });

        const form = document.querySelector('[data-chat-form]');
        const error = form?.querySelector('[data-chat-error]');
        const textarea = form?.querySelector('textarea');
        form?.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!textarea.value.trim()) {
                return;
            }
            const button = form.querySelector('button[type="submit"]');
            button.disabled = true;
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                const data = await response.json();
                if (response.ok) {
                    append(data.messages);
                    textarea.value = '';
                    error.hidden = true;
                } else {
                    error.textContent = data.error || t('The message could not be sent.');
                    error.hidden = false;
                }
            } catch {
                error.textContent = t('No signal. The message was not sent.');
                error.hidden = false;
            } finally {
                button.disabled = false;
                textarea.focus();
            }
        });
        // Enter sends; Shift+Enter starts a new line.
        textarea?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
                event.preventDefault();
                form.requestSubmit();
            }
        });

        scrollToEnd();
        schedule();
    };

    document.addEventListener('DOMContentLoaded', init);
})();
