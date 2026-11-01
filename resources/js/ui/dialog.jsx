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

    useEffect(() => {
        if (!current) return;
        const onKey = (e) => {
            if (e.key === 'Escape') close(current, current.type === 'confirm' ? false : undefined);
            if (e.key === 'Enter') close(current, current.type === 'confirm' ? true : undefined);
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [current?.id]);

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

    const isConfirm = current.type === 'confirm';
    const title = current.title || (isConfirm ? 'Please confirm' : 'Mixx Tune');

    return (
        <div
            className="mt-dialog-overlay"
            style={OVERLAY}
            onMouseDown={(e) => {
                if (e.target === e.currentTarget) close(current, isConfirm ? false : undefined);
            }}
        >
            <div style={CARD} role="dialog" aria-modal="true">
                <div style={TITLE}>{title}</div>
                <div style={MESSAGE}>{current.message}</div>
                <div style={ACTIONS}>
                    {isConfirm && (
                        <button type="button" style={BTN_CANCEL} onClick={() => close(current, false)}>
                            {current.cancelText}
                        </button>
                    )}
                    <button
                        type="button"
                        autoFocus
                        style={{ ...BTN_OK, ...(current.danger ? BTN_DANGER : {}) }}
                        onClick={() => close(current, isConfirm ? true : undefined)}
                    >
                        {current.okText}
                    </button>
                </div>
            </div>
        </div>
    );
}

/* Inline styles so the dialog looks the same regardless of page CSS / theme, and never
   depends on a utility class being present in the build. */
const OVERLAY = {
    position: 'fixed',
    inset: 0,
    zIndex: 100000,
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    padding: '16px',
    background: 'rgba(15, 23, 42, 0.55)',
    backdropFilter: 'blur(2px)',
};
const CARD = {
    width: '100%',
    maxWidth: '420px',
    background: '#ffffff',
    borderRadius: '16px',
    boxShadow: '0 20px 60px rgba(0,0,0,0.35)',
    padding: '22px 22px 18px',
    fontFamily: 'inherit',
};
const TITLE = { fontSize: '16px', fontWeight: 700, color: '#0f172a', marginBottom: '8px' };
const MESSAGE = { fontSize: '14px', lineHeight: 1.55, color: '#334155', whiteSpace: 'pre-line' };
const ACTIONS = { display: 'flex', justifyContent: 'flex-end', gap: '10px', marginTop: '20px' };
const BTN_BASE = {
    appearance: 'none',
    border: 'none',
    cursor: 'pointer',
    fontSize: '14px',
    fontWeight: 600,
    padding: '9px 18px',
    borderRadius: '10px',
};
const BTN_OK = { ...BTN_BASE, background: '#7c3aed', color: '#ffffff' };
const BTN_DANGER = { background: '#e11d48', color: '#ffffff' };
const BTN_CANCEL = { ...BTN_BASE, background: '#f1f5f9', color: '#334155' };

export default DialogHost;
