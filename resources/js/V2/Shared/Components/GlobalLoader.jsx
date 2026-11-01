import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import BrandLoader from '@/V2/Shared/Components/BrandLoader';

/*
 * W136 MIXXTUNE_BRAND_LOADER
 * Shows the "Live Audio" logo motion whenever a page is loading / refreshing — i.e. during any
 * Inertia visit (navigation, a form submit that reloads, report generation that redirects when
 * done). It appears only if the visit takes longer than a moment (250 ms), so quick loads don't
 * flash, and it is skipped for background partial reloads (router.reload with `only:`), so a
 * polling page does not blink every few seconds.
 */
export default function GlobalLoader() {
    const [show, setShow] = useState(false);

    useEffect(() => {
        let timer = null;

        const start = (event) => {
            const visit = event?.detail?.visit;
            /* skip background partial reloads (the import / report polls) */
            if (visit && Array.isArray(visit.only) && visit.only.length > 0) {
                return;
            }
            if (timer) window.clearTimeout(timer);
            timer = window.setTimeout(() => setShow(true), 250);
        };

        const stop = () => {
            if (timer) {
                window.clearTimeout(timer);
                timer = null;
            }
            setShow(false);
        };

        const offStart = router.on('start', start);
        const offFinish = router.on('finish', stop);
        const offError = router.on('error', stop);
        const offInvalid = router.on('invalid', stop);
        const offException = router.on('exception', stop);

        return () => {
            if (timer) window.clearTimeout(timer);
            offStart();
            offFinish();
            offError();
            offInvalid();
            offException();
        };
    }, []);

    if (!show) return null;
    /* one single loader, centred on the whole page, transparent background, no text */
    return <BrandLoader overlay size={120} />;
}
