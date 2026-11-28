import { useEffect, useState } from 'react';

/*
 * W133 MIXXTUNE_UI_DIALOGS
 * One centred, website-styled popup for the whole panel, in place of the browser's
 * native alert()/confirm() boxes ("www.mixxtune.com says ...").
 *
 * - window.alert(msg) is overridden here, so every existing alert() call shows the
 *   styled box automatically (no other file changes needed).
 * - uiConfirm(message, opts) returns a Promise<boolean> for the "are you sure?" dialogs;
 *   call sites are moved from confirm(...) to await uiConfirm(...) over the next waves.
 */

let _id = 0;
let _queue = [];
const _listeners = new Set();

function _emit() {
    _listeners.forEach((fn) => fn([..._queue]));
}

function _push(dialog) {
    return new Promise((resolve) => {
        _queue = [..._queue, { ..._dialog_defaults(dialog), id: ++_id, resolve }];
        _emit();
    });
}

function _dialog_defaults(d) {
    return {
        type: 'alert',
        title: '',
        message: '',
        okText: 'OK',
        cancelText: 'Cancel',
        danger: false,
        ...d,
    };
}

export function uiAlert(message, opts = {}) {
    return _push({ type: 'alert', message: String(message ?? ''), ...opts });
}

export function uiConfirm(message, opts = {}) {
    return _push({ type: 'confirm', message: String(message ?? ''), ...opts });
}

/* W244: stands in for the browser's prompt(): resolves to the typed text, or null when cancelled */
export function uiPrompt(message, defaultValue = '', opts = {}) {
    return _push({ type: 'prompt', message: String(message ?? ''), defaultValue: String(defaultValue ?? ''), ...opts });
}

/* Install the window.alert override once. window.confirm is left native until every
   call site has moved to uiConfirm (a Promise can't stand in for confirm's boolean). */
if (typeof window !== 'undefined' && !window.__mtDialogsInstalled) {
    window.__mtDialogsInstalled = true;
    try {
        window.alert = (msg) => {
            uiAlert(String(msg ?? ''));
        };
    } catch (e) {
        /* ignore */
    }
    window.uiAlert = uiAlert;
    window.uiConfirm = uiConfirm;
    window.uiPrompt = uiPrompt;
}

export function DialogHost() {
    const [items, setItems] = useState([]);

    useEffect(() => {
        const fn = (q) => setItems(q);
        _listeners.add(fn);
        setItems([..._queue]);
        return () => _listeners.delete(fn);
    }, []);

    const current = items[0];
    const [typed, setTyped] = useState('');

    useEffect(() => {
        setTyped(current?.type === 'prompt' ? current.defaultValue : '');
        if (current?.type === 'prompt') {
            /* the suggested text is selected, so typing replaces it (as the browser's own box did) */
            setTimeout(() => {
                const el = document.querySelector('.mt-dialog-overlay input');
                if (el) { el.focus(); el.select(); }
            }, 0);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [current?.id]);

    useEffect(() => {
        if (!current) return;
        const onKey = (e) => {
            if (e.key === 'Escape') { e.preventDefault(); close(current, cancelValue(current)); }
            /* Enter on a button is that button's own click; Enter in the text box sends what was typed */
            if (e.key === 'Enter' && current.type !== 'prompt' && !(e.target && e.target.tagName === 'BUTTON')) { e.preventDefault(); close(current, current.type === 'confirm' ? true : undefined); }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [current?.id]);

    /* follow the panel's day / night theme (mt-night class on <html>) */
    const isNight =
        typeof document !== 'undefined' &&
        document.documentElement.classList.contains('mt-night');
    const T = palette(isNight);

    if (!current) return null;

    const close = (dialog, value) => {
        _queue = _queue.filter((d) => d.id !== dialog.id);
        _emit();
        try {
            dialog.resolve(value);
        } catch (e) {
            /* ignore */
        }
    };

    const isPrompt = current.type === 'prompt';
    const isConfirm = current.type === 'confirm' || isPrompt;
    const title = current.title || (isPrompt ? 'Please type' : isConfirm ? 'Please confirm' : (typeof document !== 'undefined' && document.querySelector('meta[name="mt-brand"]')?.getAttribute('content')) || 'Mixx Tune');

    return (
        <div
            className="mt-dialog-overlay"
            style={T.OVERLAY}
            onMouseDown={(e) => {
                if (e.target === e.currentTarget) close(current, cancelValue(current));
            }}
        >
            <div style={T.CARD} role="dialog" aria-modal="true">
                <div style={T.TITLE}>{title}</div>
                <div style={T.MESSAGE}>{current.message}</div>
                {isPrompt && (
                    <input
                        autoFocus
                        value={typed}
                        onChange={(e) => setTyped(e.target.value)}
                        onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); close(current, typed); } }}
                        style={T.INPUT}
                    />
                )}
                <div style={T.ACTIONS}>
                    {isConfirm && (
                        <button type="button" style={T.BTN_CANCEL} onClick={() => close(current, cancelValue(current))}>
                            {current.cancelText}
                        </button>
                    )}
                    <button
                        type="button"
                        autoFocus={!isPrompt}
                        style={{ ...T.BTN_OK, ...(current.danger ? T.BTN_DANGER : {}) }}
                        onClick={() => close(current, isPrompt ? typed : isConfirm ? true : undefined)}
                    >
                        {current.okText}
                    </button>
                </div>
            </div>
        </div>
    );
}

function cancelValue(dialog) {
    return dialog.type === 'prompt' ? null : dialog.type === 'confirm' ? false : undefined;
}

/* Inline styles (never depend on a page CSS class being in the build), in two palettes that
   follow the panel's day / night theme. */
function palette(isNight) {
    const BTN_BASE = {
        appearance: 'none',
        border: 'none',
        cursor: 'pointer',
        fontSize: '14px',
        fontWeight: 600,
        padding: '9px 18px',
        borderRadius: '10px',
    };
    const OVERLAY = {
        position: 'fixed',
        inset: 0,
        zIndex: 100000,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        padding: '16px',
        background: isNight ? 'rgba(2, 6, 23, 0.7)' : 'rgba(15, 23, 42, 0.55)',
        backdropFilter: 'blur(2px)',
    };
    const CARD = {
        width: '100%',
        maxWidth: '420px',
        background: isNight ? '#1e293b' : '#ffffff',
        border: isNight ? '1px solid #334155' : 'none',
        borderRadius: '16px',
        boxShadow: isNight ? '0 20px 60px rgba(0,0,0,0.6)' : '0 20px 60px rgba(0,0,0,0.35)',
        padding: '22px 22px 18px',
        fontFamily: 'inherit',
    };
    const TITLE = {
        fontSize: '16px',
        fontWeight: 700,
        color: isNight ? '#f1f5f9' : '#0f172a',
        marginBottom: '8px',
    };
    const MESSAGE = {
        fontSize: '14px',
        lineHeight: 1.55,
        color: isNight ? '#cbd5e1' : '#334155',
        whiteSpace: 'pre-line',
    };
    const INPUT = {
        width: '100%',
        marginTop: '12px',
        padding: '10px 12px',
        fontSize: '14px',
        borderRadius: '10px',
        border: isNight ? '1px solid #475569' : '1px solid #cbd5e1',
        background: isNight ? '#0f172a' : '#ffffff',
        color: isNight ? '#f1f5f9' : '#0f172a',
        boxSizing: 'border-box',
    };
    const ACTIONS = { display: 'flex', justifyContent: 'flex-end', gap: '10px', marginTop: '20px' };
    return {
        OVERLAY,
        CARD,
        TITLE,
        MESSAGE,
        ACTIONS,
        INPUT,
        /* W244: the main button follows a distributor's colour theme (same purple without one) */
        BTN_OK: { ...BTN_BASE, background: isNight ? 'rgb(var(--mt-a-500, 139 92 246))' : 'rgb(var(--mt-a-600, 124 58 237))', color: '#ffffff' },
        BTN_DANGER: { background: isNight ? '#f43f5e' : '#e11d48', color: '#ffffff' },
        BTN_CANCEL: {
            ...BTN_BASE,
            background: isNight ? '#334155' : '#f1f5f9',
            color: isNight ? '#e2e8f0' : '#334155',
        },
    };
}

export default DialogHost;
