const readSession = (key, fallback = null) => {
    try {
        const value = window.sessionStorage.getItem(key);

        return value === null ? fallback : value;
    } catch {
        return fallback;
    }
};

const writeSession = (key, value) => {
    try {
        window.sessionStorage.setItem(key, value);
    } catch {
        // Storage can be unavailable in privacy modes. The widget still works in-memory.
    }
};

const removeSession = (key) => {
    try {
        window.sessionStorage.removeItem(key);
    } catch {
        // Storage can be unavailable in privacy modes. Nothing else is required.
    }
};

const clearSupportChatSession = () => {
    try {
        const keys = [];
        for (let index = 0; index < window.sessionStorage.length; index += 1) {
            const key = window.sessionStorage.key(index);
            if (key?.startsWith('lolo-support-chat:')) keys.push(key);
        }
        keys.forEach((key) => window.sessionStorage.removeItem(key));
    } catch {
        // Storage can be unavailable in privacy modes. The server session still ends normally.
    }
};

document.addEventListener('click', (event) => {
    if (event.target.closest('[data-clear-support-chat]')) clearSupportChatSession();
}, true);

document.addEventListener('submit', (event) => {
    if (event.target.matches('[data-clear-support-chat]')) clearSupportChatSession();
}, true);

const newClientMessageId = () => {
    if (window.crypto?.randomUUID) {
        return window.crypto.randomUUID();
    }

    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (character) => {
        const random = Math.floor(Math.random() * 16);
        const value = character === 'x' ? random : (random & 0x3) | 0x8;

        return value.toString(16);
    });
};

document.addEventListener('alpine:init', () => {
    window.Alpine.data('supportChatWidget', ({
        userId,
        initialTicketId = null,
        initialUnreadCount = 0,
    }) => ({
        actionInFlight: false,
        announcement: '',
        draft: '',
        initialUnreadCount,
        isMobile: false,
        mediaQuery: null,
        online: window.navigator.onLine,
        open: false,
        pendingMessage: null,
        pollTimer: null,
        popstateHandler: null,
        sendError: '',
        sending: false,
        shouldStickToBottom: true,
        ticketId: initialTicketId,
        userId,
        viewportHandler: null,
        visibilityHandler: null,

        init() {
            this.open = readSession(this.openKey(), 'false') === 'true';
            this.draft = readSession(this.draftKey(), '') ?? '';
            this.mediaQuery = window.matchMedia('(max-width: 639px)');
            this.isMobile = this.mediaQuery.matches;

            this.modeHandler = () => {
                this.isMobile = this.mediaQuery.matches;
                this.applyScrollLock();
                this.updateVisualViewport();
            };
            this.mediaQuery.addEventListener?.('change', this.modeHandler);
            this.mediaQuery.addListener?.(this.modeHandler);

            this.popstateHandler = () => {
                if (this.open) {
                    this.closeImmediately(true);
                }
            };
            window.addEventListener('popstate', this.popstateHandler);

            this.viewportHandler = () => this.updateVisualViewport();
            window.visualViewport?.addEventListener('resize', this.viewportHandler);
            window.visualViewport?.addEventListener('scroll', this.viewportHandler);
            window.addEventListener('orientationchange', this.viewportHandler);
            window.addEventListener('resize', this.viewportHandler);

            this.visibilityHandler = () => this.schedulePoll(250);
            document.addEventListener('visibilitychange', this.visibilityHandler);

            this.$nextTick(() => {
                this.updateVisualViewport();
                this.resizeComposer();
                if (this.open) {
                    this.applyScrollLock();
                    this.prepareHistoryState();
                    this.restoreScroll(this.initialUnreadCount > 0);
                    this.$wire.openPanel().catch(() => {});
                }
            });

            this.schedulePoll(this.open ? 5000 : 10000);
        },

        destroy() {
            window.clearTimeout(this.pollTimer);
            window.removeEventListener('popstate', this.popstateHandler);
            window.visualViewport?.removeEventListener('resize', this.viewportHandler);
            window.visualViewport?.removeEventListener('scroll', this.viewportHandler);
            window.removeEventListener('orientationchange', this.viewportHandler);
            window.removeEventListener('resize', this.viewportHandler);
            document.removeEventListener('visibilitychange', this.visibilityHandler);
            this.mediaQuery?.removeEventListener?.('change', this.modeHandler);
            this.mediaQuery?.removeListener?.(this.modeHandler);
            this.unlockPageScroll();
        },

        openKey() {
            return `lolo-support-chat:open:${this.userId}`;
        },

        draftKey(ticketId = this.ticketId) {
            return `lolo-support-chat:draft:${this.userId}:${ticketId ?? 'new'}`;
        },

        scrollKey(ticketId = this.ticketId) {
            return `lolo-support-chat:scroll:${this.userId}:${ticketId ?? 'new'}`;
        },

        showPanel() {
            this.open = true;
            writeSession(this.openKey(), 'true');
            this.prepareHistoryState();
            this.applyScrollLock();
            this.updateVisualViewport();
            this.announcement = 'Support chat opened.';

            this.$nextTick(() => {
                this.restoreScroll(this.initialUnreadCount > 0);
                this.$refs.composer?.focus({ preventScroll: true });
            });

            this.$wire.openPanel()
                .then(() => {
                    this.initialUnreadCount = 0;
                })
                .catch(() => {});
            this.schedulePoll(5000);
        },

        minimize() {
            if (this.isMobile && window.history.state?.loloSupportChat) {
                window.history.back();

                return;
            }

            this.closeImmediately(false);
        },

        closeForNavigation() {
            if (window.history.state?.loloSupportChat) {
                const state = { ...window.history.state };
                delete state.loloSupportChat;
                window.history.replaceState(state, '', window.location.href);
            }

            this.closeImmediately(false, false);
        },

        closeImmediately(fromPopstate = false, restoreFocus = true) {
            this.open = false;
            writeSession(this.openKey(), 'false');
            this.unlockPageScroll();
            this.rememberScroll();
            this.announcement = 'Support chat minimized.';
            this.schedulePoll(10000);

            if (restoreFocus && ! fromPopstate) {
                this.$nextTick(() => this.$refs.launcher?.focus({ preventScroll: true }));
            }
        },

        prepareHistoryState() {
            if (! this.isMobile || window.history.state?.loloSupportChat) {
                return;
            }

            window.history.pushState(
                { ...(window.history.state ?? {}), loloSupportChat: true },
                '',
                window.location.href,
            );
        },

        applyScrollLock() {
            if (! this.open || ! this.isMobile) {
                this.unlockPageScroll();

                return;
            }

            if (document.body.dataset.supportChatLocked === 'true') {
                return;
            }

            document.body.dataset.supportChatLocked = 'true';
            document.body.dataset.supportChatOverflow = document.body.style.overflow || '';
            document.documentElement.dataset.supportChatOverflow = document.documentElement.style.overflow || '';
            document.body.style.overflow = 'hidden';
            document.documentElement.style.overflow = 'hidden';
        },

        unlockPageScroll() {
            if (document.body.dataset.supportChatLocked !== 'true') {
                return;
            }

            document.body.style.overflow = document.body.dataset.supportChatOverflow || '';
            document.documentElement.style.overflow = document.documentElement.dataset.supportChatOverflow || '';
            delete document.body.dataset.supportChatLocked;
            delete document.body.dataset.supportChatOverflow;
            delete document.documentElement.dataset.supportChatOverflow;
        },

        updateVisualViewport() {
            const viewport = window.visualViewport;
            const height = viewport?.height ?? window.innerHeight;
            const offsetTop = viewport?.offsetTop ?? 0;
            const keyboardInset = Math.max(0, window.innerHeight - height - offsetTop);

            this.$root.style.setProperty('--support-chat-viewport-height', `${height}px`);
            this.$root.style.setProperty('--support-chat-viewport-offset', `${offsetTop}px`);
            this.$root.style.setProperty('--support-chat-keyboard-inset', `${keyboardInset}px`);

            this.$nextTick(() => this.resizeComposer());
        },

        draftChanged() {
            writeSession(this.draftKey(), this.draft);
            this.sendError = '';
            this.resizeComposer();
        },

        resizeComposer() {
            const composer = this.$refs.composer;
            if (! composer) return;

            composer.style.height = 'auto';
            composer.style.height = `${Math.min(112, Math.max(44, composer.scrollHeight))}px`;
        },

        sendMessage() {
            const body = this.draft.trim();
            if (! body || this.sending) return;

            if (this.pendingMessage?.status === 'failed' && this.pendingMessage.body === body) {
                this.retryPending();

                return;
            }

            const clientId = newClientMessageId();
            this.pendingMessage = { body, clientId, status: this.online ? 'sending' : 'failed' };
            this.sendError = this.online ? '' : "You're offline. We'll send when you reconnect.";
            this.sending = this.online;
            writeSession(this.draftKey(), this.draft);
            this.$nextTick(() => this.scrollToBottom());

            if (! this.online) {
                this.announcement = 'Message not sent because you are offline.';

                return;
            }

            this.invokeSend(body, clientId);
        },

        retryPending() {
            if (! this.pendingMessage || this.sending) return;

            if (! this.online) {
                this.sendError = "You're offline. We'll send when you reconnect.";
                this.announcement = 'Message not sent because you are offline.';

                return;
            }

            this.pendingMessage.status = 'sending';
            this.sending = true;
            this.sendError = '';
            this.announcement = 'Retrying support message.';
            this.invokeSend(this.pendingMessage.body, this.pendingMessage.clientId);
        },

        invokeSend(body, clientId) {
            this.actionInFlight = true;
            this.$wire.sendMessage(body, clientId)
                .catch((error) => {
                    if (this.pendingMessage?.clientId === clientId) {
                        this.pendingMessage.status = 'failed';
                    }
                    this.sending = false;
                    const responseStatus = Number(error?.status ?? error?.response?.status ?? error?.cause?.status);
                    const sessionExpired = [401, 419].includes(responseStatus);
                    this.sendError = sessionExpired
                        ? 'Your session expired. Sign in again to send this message. Your draft is safe.'
                        : (window.navigator.onLine
                            ? 'We could not send that message. Your draft is safe; try again.'
                            : "You're offline. We'll send when you reconnect.");
                    this.announcement = sessionExpired ? this.sendError : 'Message failed to send. Try again.';
                })
                .finally(() => {
                    this.actionInFlight = false;
                });
        },

        messageSent(detail) {
            if (this.pendingMessage && detail.clientId !== this.pendingMessage.clientId) return;

            const previousDraftKey = this.draftKey();
            this.ticketId = detail.ticketId ?? this.ticketId;
            removeSession(previousDraftKey);
            removeSession(this.draftKey());
            this.pendingMessage = null;
            this.sending = false;
            this.sendError = '';
            this.draft = '';
            this.announcement = 'Message sent.';
            this.initialUnreadCount = 0;

            this.$nextTick(() => {
                this.resizeComposer();
                this.scrollToBottom();
                this.$refs.composer?.focus({ preventScroll: true });
            });
        },

        messageFailed(detail) {
            if (this.pendingMessage && detail.clientId !== this.pendingMessage.clientId) return;

            if (this.pendingMessage) {
                this.pendingMessage.status = 'failed';
            }
            this.sending = false;
            this.sendError = detail.message || 'We could not send that message. Try again.';
            this.announcement = `${this.sendError} Your draft is still here.`;
        },

        conversationReset() {
            const previousDraftKey = this.draftKey();
            this.ticketId = null;
            removeSession(previousDraftKey);
            this.draft = readSession(this.draftKey(), '') ?? '';
            this.pendingMessage = null;
            this.sending = false;
            this.sendError = '';
            this.announcement = 'New support conversation ready.';
            this.$nextTick(() => {
                this.resizeComposer();
                this.$refs.composer?.focus({ preventScroll: true });
            });
        },

        wentOffline() {
            this.online = false;
            if (this.pendingMessage?.status === 'sending') {
                this.pendingMessage.status = 'failed';
                this.sending = false;
            }
            this.sendError = this.pendingMessage
                ? "You're offline. We'll send when you reconnect."
                : this.sendError;
            this.announcement = 'You are offline.';
        },

        wentOnline() {
            this.online = true;
            this.announcement = 'You are back online.';
            if (this.pendingMessage?.status === 'failed') {
                this.retryPending();
            }
            this.schedulePoll(100);
        },

        schedulePoll(delay = null) {
            window.clearTimeout(this.pollTimer);
            const nextDelay = delay ?? (document.hidden ? 30000 : (this.open ? 5000 : 10000));
            this.pollTimer = window.setTimeout(() => this.poll(), nextDelay);
        },

        async poll() {
            if (this.actionInFlight || ! this.online) {
                this.schedulePoll();

                return;
            }

            const messageArea = this.$refs.messages;
            const previousScrollTop = messageArea?.scrollTop ?? 0;
            const wasNearBottom = messageArea
                ? messageArea.scrollHeight - messageArea.scrollTop - messageArea.clientHeight < 72
                : true;

            try {
                await this.$wire.refreshWidget(this.open);
                this.$nextTick(() => {
                    if (wasNearBottom) {
                        this.scrollToBottom();
                    } else if (this.$refs.messages) {
                        this.$refs.messages.scrollTop = previousScrollTop;
                    }
                });
            } catch {
                // The next scheduled refresh is the safe fallback for transient failures.
            } finally {
                this.schedulePoll();
            }
        },

        rememberScroll() {
            const messageArea = this.$refs.messages;
            if (! messageArea) return;

            this.shouldStickToBottom = messageArea.scrollHeight - messageArea.scrollTop - messageArea.clientHeight < 72;
            writeSession(this.scrollKey(), String(messageArea.scrollTop));
        },

        restoreScroll(forceBottom = false) {
            const messageArea = this.$refs.messages;
            if (! messageArea) return;

            const stored = Number(readSession(this.scrollKey(), ''));
            if (forceBottom || ! Number.isFinite(stored) || stored < 0) {
                this.scrollToBottom();

                return;
            }

            messageArea.scrollTop = stored;
        },

        scrollToBottom() {
            const messageArea = this.$refs.messages;
            if (! messageArea) return;

            messageArea.scrollTop = messageArea.scrollHeight;
            this.shouldStickToBottom = true;
            writeSession(this.scrollKey(), String(messageArea.scrollTop));
        },

        handleKeydown(event) {
            if (! this.open) return;

            if (event.key === 'Escape') {
                event.preventDefault();
                this.minimize();

                return;
            }

            if (event.key !== 'Tab' || ! this.isMobile) return;

            const focusable = Array.from(this.$refs.panel?.querySelectorAll(
                'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])',
            ) ?? []).filter((element) => ! element.hidden && element.offsetParent !== null);

            if (focusable.length === 0) return;

            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (! event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        },
    }));
});
