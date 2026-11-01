import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

/*
 * W135 MIXXTUNE_IDLE_LOGOUT
 * Watches for real user activity (mouse / keyboard / touch / scroll). After the configured
 * idle minutes with no activity it signs the user out, so the dashboard does not stay open.
 * Logging back in goes through the e-mail OTP flow. The server middleware enforces the same
 * limit as a backstop (for a tab that is closed and reopened later).
 */
export default function IdleLogout() {
    const page = usePage();
    const minutes = Number(page.props?.auth?.idle_minutes || 0);
    const loggedIn = Boolean(page.props?.auth?.user);
    const timer = useRef(null);

    useEffect(() => {
        if (!loggedIn || minutes < 1) {
            return undefined;
        }

        const ms = minutes * 60 * 1000;
        let gone = false;

        const logout = () => {
            if (gone) return;
            gone = true;
            try {
                router.post('/logout');
            } catch (e) {
                window.location.href = '/login';
            }
        };

        const reset = () => {
            if (gone) return;
            if (timer.current) window.clearTimeout(timer.current);
            timer.current = window.setTimeout(logout, ms);
        };

        const events = ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'click'];
        events.forEach((e) => window.addEventListener(e, reset, { passive: true }));
        reset();

        return () => {
            if (timer.current) window.clearTimeout(timer.current);
            events.forEach((e) => window.removeEventListener(e, reset));
        };
    }, [minutes, loggedIn]);

    return null;
}
