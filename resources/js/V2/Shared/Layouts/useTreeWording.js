import { useEffect } from "react";

/*
 * W232 - a sub-label under a distributor reads the distributor's name wherever a page says "Mixx Tune".
 * The pages themselves are not rewritten: after they are drawn, the words are swapped in the visible text
 * (never in what somebody types, never in code). Only used for a sub-label login; everybody else: nothing runs.
 */
const RX = /mixx\s?tune/i;
const RXG = /mixx\s?tune/gi;

export default function useTreeWording(brand) {
    useEffect(() => {
        const name = String(brand || "").trim();
        if (!name || RX.test(name) || typeof document === "undefined" || typeof MutationObserver === "undefined") {
            return undefined;
        }

        const skip = (node) => {
            const p = node.parentElement;
            return !p || Boolean(p.closest('script,style,textarea,input,select,code,pre,[contenteditable="true"],[data-keep-wording]'));
        };
        const fix = (node) => {
            if (!node || node.nodeType !== 3) return;
            const v = node.nodeValue;
            if (!v || !RX.test(v) || skip(node)) return;
            /* W232b: web and mail addresses stay as they are (mixxtune.com, name@mixxtune.com, backstagemixxtune...) */
            const next = v.replace(RXG, (m, off, str) => {
                const before = off > 0 ? str[off - 1] : "";
                const after = str.slice(off + m.length, off + m.length + 40);
                if (/[@./\w-]/.test(before) || /^[\w-]/.test(after) || /^\.[a-z]/i.test(after)) return m;
                return name;
            });
            if (next !== v) node.nodeValue = next;
        };
        const walk = (root) => {
            if (!root) return;
            const w = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
            let n = w.nextNode();
            while (n) {
                fix(n);
                n = w.nextNode();
            }
        };

        walk(document.body);
        const mo = new MutationObserver((list) => {
            for (const m of list) {
                if (m.type === "characterData") {
                    fix(m.target);
                } else {
                    m.addedNodes.forEach((n) => {
                        if (n.nodeType === 3) fix(n);
                        else if (n.nodeType === 1) walk(n);
                    });
                }
            }
        });
        mo.observe(document.body, { childList: true, subtree: true, characterData: true });

        return () => mo.disconnect();
    }, [brand]);
}
