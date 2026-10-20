import axios from 'axios';
import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/*
 * MIXXTUNE_BANK_CHANGE_PROTECTION_V1
 * Opens automatically when a save is stopped because bank / UPI details
 * changed. The code from the e-mail unlocks the change for 10 minutes;
 * the person then presses Save again on the same form.
 */
export default function BankChangeOtpPrompt() {
    const page = usePage();
    const message = page.props?.errors?.bank_change_otp ?? null;

    const [open, setOpen] = useState(false);
    const [otp, setOtp] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [done, setDone] = useState('');
    const [info, setInfo] = useState('');

    useEffect(() => {
        if (message) {
            setOpen(true);
            setOtp('');
            setError('');
            setDone('');
            setInfo('');
        }
    }, [page, message]);

    if (!open) {
        return null;
    }

    const firstError = (exception, fallback) => {
        const errors = exception?.response?.data?.errors ?? {};
        const first = Object.values(errors)[0];

        return (Array.isArray(first) ? first[0] : first) ?? exception?.response?.data?.message ?? fallback;
    };

    const confirm = async (event) => {
        event.preventDefault();

        if (busy || otp.trim().length !== 6) {
            return;
        }

        setBusy(true);
        setError('');

        try {
            const response = await axios.post('/v2/admin/bank-change/unlock', { otp: otp.trim() });
            setDone(response.data?.message ?? 'Confirmed. Now press Save again.');
        } catch (exception) {
            setError(firstError(exception, 'Could not check the code.'));
        } finally {
            setBusy(false);
        }
    };

    const resend = async () => {
        if (busy) {
            return;
        }

        setBusy(true);
        setError('');
        setInfo('');

        try {
            const response = await axios.post('/v2/admin/bank-change/resend');
            setInfo(response.data?.message ?? 'A new code was sent.');
        } catch (exception) {
            setError(firstError(exception, 'Could not send a new code.'));
        } finally {
            setBusy(false);
        }
    };

    return (
        <div className="fixed inset-0 z-[200] flex items-start justify-center overflow-y-auto bg-slate-900/50 p-4">
            <div className="mt-24 w-full max-w-md rounded-2xl bg-white p-6 text-left shadow-2xl">
                <div className="flex items-start justify-between gap-4">
                    <div className="text-lg font-semibold text-slate-900">Confirm bank details change</div>
                    <button
                        type="button"
                        onClick={() => setOpen(false)}
                        className="rounded-lg px-2 py-1 text-xl leading-none text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                    >
                        ×
                    </button>
                </div>

                {done ? (
                    <>
                        <div className="mt-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{done}</div>
                        <p className="mt-3 text-xs text-slate-500">
                            After saving: the owner gets an e-mail, payout KYC must be verified again, and payouts to this owner stay on hold for 48 hours.
                        </p>
                        <button
                            type="button"
                            onClick={() => setOpen(false)}
                            className="mt-5 w-full rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700"
                        >
                            OK, I will press Save again
                        </button>
                    </>
                ) : (
                    <form onSubmit={confirm}>
                        <p className="mt-3 text-sm text-slate-600">{message}</p>
                        <input
                            type="text"
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            autoFocus
                            maxLength={6}
                            value={otp}
                            onChange={(event) => setOtp(event.target.value.replace(/\D/g, '').slice(0, 6))}
                            placeholder="6-digit code"
                            className="mt-4 w-full rounded-xl border border-slate-300 px-4 py-3 text-center text-xl tracking-[0.4em] focus:border-violet-500 focus:outline-none"
                        />
                        {error && <div className="mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}
                        {info && <div className="mt-3 rounded-lg bg-sky-50 px-3 py-2 text-sm text-sky-800">{info}</div>}
                        <button
                            type="submit"
                            disabled={busy || otp.length !== 6}
                            className="mt-4 w-full rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-50"
                        >
                            {busy ? 'Checking...' : 'Confirm code'}
                        </button>
                        <button
                            type="button"
                            onClick={resend}
                            disabled={busy}
                            className="mt-2 w-full rounded-xl px-4 py-2 text-xs font-semibold text-slate-500 hover:bg-slate-50 disabled:opacity-50"
                        >
                            Send a new code
                        </button>
                    </form>
                )}
            </div>
        </div>
    );
}
