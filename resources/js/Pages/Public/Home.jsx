import { Head, useForm, usePage } from "@inertiajs/react";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";

import "../../../css/home-galaxy.css";

/*
 * W121 MIXXTUNE_WEBSITE_HOMEPAGE + MIXXTUNE_WEBSITE_JOIN
 * The galaxy homepage. Words, sections, stores, contact and the Join form
 * questions come from Website -> Homepage (Super Admin). A sent Join form
 * becomes a row in Website -> Join Requests.
 */

const BARS = [
    [52, 237, 96], [88, 180, 185], [118, 136, 266], [150, 152, 270], [184, 84, 362], [218, 58, 392], [249, 57, 398],
    [281, 57, 394], [313, 82, 364], [345, 158, 272], [378, 133, 270], [406, 180, 188], [443, 245, 102],
];

/* the official Mixx Tune mark: 13 purple bars */
function Logo({ size = 40, color = "#6D69F0", animated = false }) {
    return (
        <svg width={size} height={size} viewBox="0 0 512 512" aria-hidden="true">
            <g fill={color}>
                {BARS.map(([x, y, h], i) => (
                    <rect key={x} className={animated ? "mtg-eq" : undefined} x={x} y={y} width="17" height={h} rx="8.5" style={animated ? { animationDelay: `${Math.abs(i - 6) * 0.08}s` } : undefined} />
                ))}
            </g>
        </svg>
    );
}

function makeStars(count, seed, maxSize) {
    let s = seed;
    const rnd = () => {
        s = (s * 9301 + 49297) % 233280;
        return s / 233280;
    };
    return Array.from({ length: count }, () => {
        const size = 0.8 + rnd() * maxSize;
        const hue = rnd() > 0.82 ? "#7DD3FC" : rnd() > 0.75 ? "#C9C3FF" : "#FFFFFF";
        return {
            position: "absolute",
            display: "block",
            borderRadius: "50%",
            left: `${(rnd() * 100).toFixed(2)}%`,
            top: `${(rnd() * 100).toFixed(2)}%`,
            width: `${size.toFixed(1)}px`,
            height: `${size.toFixed(1)}px`,
            background: hue,
            boxShadow: `0 0 ${(size * 3).toFixed(1)}px ${hue}`,
            animationDelay: `${(rnd() * 4).toFixed(2)}s`,
            animationDuration: `${(3 + rnd() * 4).toFixed(2)}s`,
        };
    });
}

const DOTS = ["#7DD3FC", "#A78BFA", "#F0ABFC", "#FCD34D"];

function Ring({ names, rvar, offset, className }) {
    return (
        <div className={className} style={{ position: "absolute", left: "50%", top: "50%", width: 0, height: 0 }}>
            {names.map((name, i) => {
                const a = offset + (360 / names.length) * i;
                return (
                    <div key={name} style={{ position: "absolute", left: 0, top: 0, transform: `rotate(${a}deg) translateX(var(${rvar})) rotate(${-a}deg) translate(-50%,-50%)` }}>
                        <span className="mtg-chip" style={{ display: "inline-flex", alignItems: "center", gap: 8, padding: "9px 15px", borderRadius: 999, background: "rgba(22,20,64,.88)", border: "1px solid rgba(201,195,255,.3)", fontSize: 13, fontWeight: 600, whiteSpace: "nowrap", color: "#FFFFFF", boxShadow: "0 6px 24px rgba(0,0,0,.4)" }}>
                            <i style={{ display: "block", width: 8, height: 8, borderRadius: "50%", background: DOTS[i % DOTS.length] }} />
                            {name}
                        </span>
                    </div>
                );
            })}
        </div>
    );
}

const STEPS = [
    ["mtg-fly-l", "01", "Details", "Title, artists, catalogue label, genre, language, release date and cover art."],
    ["mtg-reveal", "02", "Tracks", "Upload WAV masters and add lyricist, composer and featured artist credits."],
    ["mtg-reveal", "03", "Territories", "Go worldwide, or pick exactly the countries where your music should play."],
    ["mtg-fly-r", "04", "Review & Publish", "Check everything on one page and send it to our team for review."],
];

const FEATURES = [
    ["mtg-fly-l", "Store distribution", "Follow every release store by store, from processing to delivered to live.", "M12 2v20M2 12h20M5 5l14 14M19 5L5 19", "167,139,250"],
    ["mtg-reveal", "100% Transparent", "No hidden charges. Ever. Every play, every rupee and every deduction is visible in your panel.", "M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12zM12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6z", "125,211,252"],
    ["mtg-fly-r", "Analytics", "See earnings by store, country, track and month in clear charts.", "M3 3v18h18M7 15l4-4 3 3 5-6", "240,171,252"],
    ["mtg-fly-l", "Wallet & bank payouts", "Royalties land in your wallet, and your payment goes straight to your bank account.", "M3 7h18v12H3zM3 7l2-3h14l2 3M16 13h2", "252,211,77"],
    ["mtg-reveal", "Label & sub-label splits", "Each label and sub-label keeps its own agreed revenue share.", "M12 3v6M12 9l-6 6M12 9l6 6M6 15v6M18 15v6", "167,139,250"],
    ["mtg-fly-r", "Secure by design", "E-mail codes on login and money actions, verified KYC before any payout.", "M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6zM9 12l2 2 4-4", "125,211,252"],
];

const PAY = [
    ["mtg-pc1 mtg-fly-l", "#7DD3FC", "M14 3H6v18h12V7zM14 3v4h4M9 13h6M9 17h6", "1 · Store reports arrive", "The stores send their monthly reports for every play."],
    ["mtg-pc2 mtg-fly-l", "#A78BFA", "M4 12l5 5L20 6", "2 · Checked line by line", "Every play is matched to your track, label and agreed share."],
    ["mtg-pc3 mtg-fly-r", "#F0ABFC", "M4 20V10M10 20V4M16 20v-7M22 20H2", "3 · Published to you", "Your wallet is credited and the statement PDF is ready."],
    ["mtg-pc4 mtg-fly-r", "#FCD34D", "M3 10l9-6 9 6M5 10v8M10 10v8M14 10v8M19 10v8M3 21h18", "4 · Paid to your bank", "Your payment goes straight into your bank account, without any hassle."],
];

function PayCard({ cls, color, icon, title, text }) {
    return (
        <article className={`${cls} mtg-planet`} style={{ padding: 22, borderRadius: 22, background: "rgba(20,18,62,.92)", border: "1px solid rgba(201,195,255,.24)", boxShadow: "0 20px 60px rgba(0,0,0,.45)" }}>
            <div style={{ display: "flex", alignItems: "center", gap: 12 }}>
                <span style={{ flex: "0 0 auto", width: 44, height: 44, borderRadius: 14, display: "flex", alignItems: "center", justifyContent: "center", background: color }}>
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#06051A" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                        <path d={icon} />
                    </svg>
                </span>
                <strong style={{ fontSize: 17, color: "#FFFFFF" }}>{title}</strong>
            </div>
            <p style={{ margin: "10px 0 0", fontSize: 14, lineHeight: 1.55, color: "#B9B5DE" }}>{text}</p>
        </article>
    );
}

const eyebrowStyle = { margin: "0 0 14px", fontSize: 13, fontWeight: 700, letterSpacing: ".16em", textTransform: "uppercase", color: "#7DD3FC" };
const h2Style = { margin: 0, fontWeight: 800, fontSize: "clamp(30px, 4.4vw, 56px)", lineHeight: 1.08, letterSpacing: "-0.02em", color: "#FFFFFF" };
const primaryBtn = { border: 0, padding: "15px 30px", borderRadius: 999, background: "#4F4BDC", color: "#FFFFFF", fontWeight: 700, fontSize: 16, minHeight: 52, display: "inline-flex", alignItems: "center", boxShadow: "0 10px 40px rgba(79,75,220,.6)" };
const ghostBtn = { textDecoration: "none", padding: "15px 28px", borderRadius: 999, background: "rgba(255,255,255,.06)", border: "1px solid rgba(201,195,255,.3)", color: "#FFFFFF", fontWeight: 600, fontSize: 16, minHeight: 52, display: "inline-flex", alignItems: "center" };

function JoinModal({ join, onClose }) {
    const { flash = {} } = usePage().props;
    const fields = (join.fields ?? []).filter((f) => f.show);
    const initial = useMemo(() => Object.fromEntries(fields.map((f) => [f.id, ""])), []); // eslint-disable-line react-hooks/exhaustive-deps
    const { data, setData, post, processing, errors, clearErrors } = useForm({ answers: initial, website: "" });
    const [done, setDone] = useState(false);
    const boxRef = useRef(null);

    useEffect(() => {
        const prev = document.body.style.overflow;
        document.body.style.overflow = "hidden";
        const onKey = (e) => e.key === "Escape" && onClose();
        window.addEventListener("keydown", onKey);
        boxRef.current?.querySelector("input, select, textarea")?.focus();
        return () => {
            document.body.style.overflow = prev;
            window.removeEventListener("keydown", onKey);
        };
    }, [onClose]);

    const set = (id, value) => {
        setData("answers", { ...data.answers, [id]: value });
        if (errors[`answers.${id}`]) clearErrors(`answers.${id}`);
    };

    const submit = (e) => {
        e.preventDefault();
        post("/join", { preserveScroll: true, preserveState: true, onSuccess: () => setDone(true) });
    };

    const label = { display: "flex", flexDirection: "column", gap: 6, fontSize: 13, fontWeight: 600, color: "#D9D5FF" };

    return (
        <div className="mtg-modal" role="dialog" aria-modal="true" aria-labelledby="mtg-join-title" onMouseDown={(e) => e.target === e.currentTarget && onClose()}>
            <div ref={boxRef} style={{ position: "relative", width: "100%", maxWidth: 580, maxHeight: "calc(100vh - 32px)", overflow: "auto", borderRadius: 28, background: "#110F38", border: "1px solid rgba(201,195,255,.28)", padding: "34px 30px 30px", boxShadow: "0 30px 120px rgba(79,75,220,.45)" }}>
                <button type="button" onClick={onClose} aria-label="Close" className="mtg-btn" style={{ position: "absolute", right: 14, top: 14, width: 44, height: 44, borderRadius: "50%", border: "1px solid rgba(201,195,255,.25)", background: "transparent", color: "#FFFFFF", display: "flex", alignItems: "center", justifyContent: "center" }}>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden="true">
                        <path d="M6 6l12 12M18 6L6 18" />
                    </svg>
                </button>

                {done ? (
                    <div role="status" style={{ display: "flex", flexDirection: "column", alignItems: "center", textAlign: "center", gap: 14, padding: "20px 0 6px" }}>
                        <span style={{ width: 76, height: 76, borderRadius: "50%", background: "#7DD3FC", display: "flex", alignItems: "center", justifyContent: "center", boxShadow: "0 0 50px rgba(125,211,252,.6)" }}>
                            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#06051A" strokeWidth="2.6" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                                <path d="M5 12l5 5L20 7" />
                            </svg>
                        </span>
                        <h2 id="mtg-join-title" className="mtg-display" style={{ margin: 0, fontWeight: 800, fontSize: 26, color: "#FFFFFF" }}>
                            Request received!
                        </h2>
                        <p style={{ margin: 0, maxWidth: 400, fontSize: 15, lineHeight: 1.6, color: "#B9B5DE" }}>{flash.success || join.thanks}</p>
                        <button type="button" onClick={onClose} className="mtg-btn" style={{ marginTop: 6, border: "1px solid rgba(201,195,255,.3)", height: 48, padding: "0 26px", borderRadius: 999, background: "transparent", color: "#FFFFFF", fontWeight: 600, fontSize: 15 }}>
                            Close
                        </button>
                    </div>
                ) : (
                    <form onSubmit={submit} noValidate style={{ display: "flex", flexDirection: "column", gap: 16 }}>
                        <div>
                            <h2 id="mtg-join-title" className="mtg-display" style={{ margin: 0, fontWeight: 800, fontSize: 28, color: "#FFFFFF" }}>
                                {join.title}
                            </h2>
                            <p style={{ margin: "8px 0 0", fontSize: 15, lineHeight: 1.55, color: "#B9B5DE" }}>{join.sub}</p>
                        </div>

                        <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))", gap: 14 }}>
                            {fields.map((f) => {
                                const err = errors[`answers.${f.id}`];
                                const id = `mtg-f-${f.id}`;
                                const common = {
                                    id,
                                    name: f.id,
                                    value: data.answers[f.id] ?? "",
                                    onChange: (e) => set(f.id, e.target.value),
                                    required: f.required,
                                    "aria-invalid": err ? "true" : undefined,
                                    "aria-describedby": err ? `${id}-err` : undefined,
                                    className: err ? "mtg-err" : undefined,
                                    placeholder: f.hint || undefined,
                                };
                                return (
                                    <div key={f.id} style={{ ...label, gridColumn: f.type === "textarea" ? "1 / -1" : undefined }}>
                                        <label htmlFor={id}>
                                            {f.label}
                                            {f.required ? " *" : ""}
                                        </label>
                                        {f.type === "textarea" ? (
                                            <textarea rows={3} maxLength={2000} {...common} />
                                        ) : f.type === "select" ? (
                                            <select {...common}>
                                                <option value="">Select</option>
                                                {f.options.map((o) => (
                                                    <option key={o} value={o}>
                                                        {o}
                                                    </option>
                                                ))}
                                            </select>
                                        ) : (
                                            <input
                                                type={{ phone: "tel", email: "email", number: "number", date: "date" }[f.type] ?? "text"}
                                                maxLength={f.type === "phone" ? 20 : 190}
                                                autoComplete={{ name: "name", phone: "tel", email: "email", city: "address-level2" }[f.id]}
                                                inputMode={f.type === "phone" ? "tel" : undefined}
                                                {...common}
                                            />
                                        )}
                                        {err && (
                                            <span id={`${id}-err`} style={{ fontSize: 12, fontWeight: 600, color: "#FCA5A5" }}>
                                                {err}
                                            </span>
                                        )}
                                    </div>
                                );
                            })}
                        </div>

                        {/* robots fill this box; people never see it */}
                        <div aria-hidden="true" style={{ position: "absolute", left: "-10000px", width: 1, height: 1, overflow: "hidden" }}>
                            <label htmlFor="mtg-website">Website</label>
                            <input id="mtg-website" tabIndex={-1} autoComplete="off" value={data.website} onChange={(e) => setData("website", e.target.value)} />
                        </div>

                        {errors.join && <p style={{ margin: 0, fontSize: 13, fontWeight: 600, color: "#FCA5A5" }}>{errors.join}</p>}
                        <p style={{ margin: 0, fontSize: 12, lineHeight: 1.5, color: "#A9A5D2" }}>By sending, you agree that our team may call or message you about joining.</p>
                        <button type="submit" disabled={processing} className="mtg-btn" style={{ border: 0, height: 54, borderRadius: 999, background: "#4F4BDC", color: "#FFFFFF", fontWeight: 700, fontSize: 16, boxShadow: "0 10px 40px rgba(79,75,220,.55)", opacity: processing ? 0.7 : 1 }}>
                            {processing ? "Sending…" : join.button}
                        </button>
                    </form>
                )}
            </div>
        </div>
    );
}

export default function Home({ content }) {
    const { hero, stores, sections, contact, join } = content;
    const [joinOpen, setJoinOpen] = useState(false);
    const farStars = useMemo(() => makeStars(130, 7, 1.6), []);
    const nearStars = useMemo(() => makeStars(55, 91, 2.6), []);
    const ring1 = stores.slice(0, 3);
    const ring2 = stores.slice(3, 7);
    const ring3 = stores.slice(7, 12);
    const marquee = [...stores, ...stores];
    const titleLines = String(hero.title || "").split("\n");
    const openJoin = useCallback(() => setJoinOpen(true), []);
    const closeJoin = useCallback(() => setJoinOpen(false), []);

    const stream = useMemo(() => {
        const names = stores.slice(0, 5);
        const spots = [
            ["-62vw", "-30vh", "-20deg"], ["60vw", "-34vh", "30deg"], ["64vw", "18vh", "18deg"], ["-58vw", "26vh", "-30deg"], ["-66vw", "-6vh", "-14deg"],
            ["56vw", "36vh", "24deg"], ["62vw", "-20vh", "16deg"], ["-60vw", "-36vh", "-26deg"], ["-64vw", "34vh", "-18deg"], ["64vw", "4vh", "28deg"],
        ];
        return spots.map(([x, y, rot], i) => ({ x, y, rot, from: 6 + i * 7, label: i % 2 === 0 ? `${names[(i / 2) % Math.max(names.length, 1)] ?? "Store"} report` : "₹" }));
    }, [stores]);

    return (
        <div className="mtg-root">
            <Head title="Music distribution for labels & artists">
                <meta name="description" content={hero.text} />
                <link rel="preconnect" href="https://fonts.googleapis.com" />
                <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
                <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Unbounded:wght@400;600;800&family=Inter:wght@400;500;600;700&display=swap" />
            </Head>

            <div className="mtg-stars-far" aria-hidden="true" style={{ position: "absolute", left: 0, top: 0, width: "100%", height: "120%", pointerEvents: "none" }}>
                {farStars.map((st, i) => (
                    <span key={i} className="mtg-star" style={st} />
                ))}
            </div>
            <div className="mtg-stars-near" aria-hidden="true" style={{ position: "absolute", left: 0, top: 0, width: "100%", height: "135%", pointerEvents: "none" }}>
                {nearStars.map((st, i) => (
                    <span key={i} className="mtg-star" style={st} />
                ))}
            </div>
            <div aria-hidden="true" style={{ position: "absolute", left: "-10%", top: 1900, width: "70%", height: 900, borderRadius: "50%", background: "radial-gradient(closest-side, rgba(79,75,220,.28), rgba(79,75,220,0))", pointerEvents: "none" }} />
            <div aria-hidden="true" style={{ position: "absolute", right: "-15%", top: 3600, width: "70%", height: 1000, borderRadius: "50%", background: "radial-gradient(closest-side, rgba(56,189,248,.16), rgba(56,189,248,0))", pointerEvents: "none" }} />

            {/* NAV */}
            <header style={{ position: "relative", zIndex: 7, maxWidth: 1240, margin: "0 auto", padding: "22px 24px", display: "flex", alignItems: "center", justifyContent: "space-between", gap: 16, flexWrap: "wrap" }}>
                <a href="#top" style={{ display: "flex", alignItems: "center", gap: 12, textDecoration: "none", color: "#FFFFFF" }}>
                    <Logo size={42} />
                    <span className="mtg-display" style={{ fontWeight: 600, fontSize: 19, letterSpacing: "-0.01em" }}>
                        mixx tune
                    </span>
                </a>
                <nav className="mtg-navlinks" aria-label="Main" style={{ display: "flex", gap: 30, fontSize: 15, fontWeight: 500 }}>
                    {sections.how && <a href="#how" style={{ textDecoration: "none" }}>How it works</a>}
                    {sections.features && <a href="#features" style={{ textDecoration: "none" }}>Features</a>}
                    {sections.royalty && <a href="#royalties" style={{ textDecoration: "none" }}>Royalties</a>}
                    <a href="#contact" style={{ textDecoration: "none" }}>Contact</a>
                </nav>
                <div style={{ display: "flex", gap: 10, alignItems: "center" }}>
                    <a className="mtg-btn" href="/login" style={{ textDecoration: "none", padding: "11px 20px", borderRadius: 999, border: "1px solid rgba(201,195,255,.35)", color: "#FFFFFF", fontWeight: 600, fontSize: 14, minHeight: 44, display: "inline-flex", alignItems: "center" }}>
                        Log in
                    </a>
                    <button type="button" className="mtg-btn" onClick={openJoin} style={{ border: 0, padding: "11px 24px", borderRadius: 999, background: "#4F4BDC", color: "#FFFFFF", fontWeight: 700, fontSize: 14, minHeight: 44, display: "inline-flex", alignItems: "center", boxShadow: "0 0 30px rgba(79,75,220,.55)" }}>
                        Join
                    </button>
                </div>
            </header>

            <main>
                {/* HERO */}
                <section id="top" style={{ position: "relative", zIndex: 6, minHeight: 860, display: "flex", flexDirection: "column", alignItems: "center", padding: "30px 24px 80px" }}>
                    <div className="mtg-herocopy" style={{ position: "relative", zIndex: 3, textAlign: "center", maxWidth: 900 }}>
                        <p style={{ margin: "0 0 18px", display: "inline-flex", alignItems: "center", gap: 10, padding: "8px 16px", borderRadius: 999, border: "1px solid rgba(201,195,255,.25)", background: "rgba(20,18,60,.6)", fontSize: 13, fontWeight: 600, letterSpacing: ".08em", textTransform: "uppercase", color: "#C9C3FF" }}>
                            <span aria-hidden="true" style={{ display: "inline-flex", gap: 3, alignItems: "flex-end", height: 14 }}>
                                {[0, 0.2, 0.4].map((d) => (
                                    <i key={d} className="mtg-bar" style={{ display: "block", width: 3, height: 14, borderRadius: 2, background: "#7DD3FC", animationDelay: `${d}s` }} />
                                ))}
                            </span>
                            {hero.eyebrow}
                        </p>
                        <h1 className="mtg-display" style={{ margin: 0, fontWeight: 800, fontSize: "clamp(38px, 6.4vw, 84px)", lineHeight: 1.02, letterSpacing: "-0.03em", color: "#FFFFFF" }}>
                            {titleLines.map((line, i) => (
                                <span key={i}>
                                    {i > 0 && <br />}
                                    {line}
                                </span>
                            ))}
                        </h1>
                        <p style={{ margin: "22px auto 0", maxWidth: 620, fontSize: "clamp(16px, 1.6vw, 19px)", lineHeight: 1.6, color: "#B9B5DE" }}>{hero.text}</p>
                        <div style={{ marginTop: 30, display: "flex", gap: 12, justifyContent: "center", flexWrap: "wrap" }}>
                            <button type="button" className="mtg-btn" onClick={openJoin} style={primaryBtn}>
                                {hero.join_button}
                            </button>
                            {sections.how && (
                                <a className="mtg-btn" href="#how" style={ghostBtn}>
                                    {hero.second_button}
                                </a>
                            )}
                        </div>
                    </div>

                    <div style={{ position: "relative", zIndex: 1, marginTop: 30, width: "var(--sys)", height: "var(--sys)", maxWidth: "100%" }}>
                        <div className="mtg-galaxy" style={{ position: "absolute", inset: 0 }}>
                            <div className="mtg-halo" aria-hidden="true" style={{ position: "absolute", left: "50%", top: "50%", width: 340, height: 340, margin: "-170px 0 0 -170px", borderRadius: "50%", background: "radial-gradient(closest-side, rgba(124,108,255,.55), rgba(79,75,220,0))" }} />
                            {[
                                ["--r1", "1px dashed rgba(201,195,255,.22)"],
                                ["--r2", "1px solid rgba(201,195,255,.12)"],
                                ["--r3", "1px dashed rgba(125,211,252,.14)"],
                            ].map(([r, border]) => (
                                <div key={r} aria-hidden="true" style={{ position: "absolute", left: "50%", top: "50%", width: `calc(var(${r}) * 2)`, height: `calc(var(${r}) * 2)`, transform: "translate(-50%, -50%)", borderRadius: "50%", border }} />
                            ))}
                            <Ring names={ring1} rvar="--r1" offset={-20} className="mtg-orbit1" />
                            <Ring names={ring2} rvar="--r2" offset={25} className="mtg-orbit2" />
                            <Ring names={ring3} rvar="--r3" offset={0} className="mtg-orbit3" />
                        </div>

                        {/* the Mixx Tune logo in the centre */}
                        <div style={{ position: "absolute", left: "50%", top: "50%", width: 132, height: 132, margin: "-66px 0 0 -66px", zIndex: 4 }}>
                            <div aria-hidden="true" className="mtg-moons" style={{ position: "absolute", left: -26, top: -26, width: 184, height: 184, borderRadius: "50%", border: "1px dashed rgba(125,211,252,.4)" }}>
                                <i style={{ position: "absolute", left: "50%", top: -6, width: 12, height: 12, marginLeft: -6, borderRadius: "50%", background: "#7DD3FC", boxShadow: "0 0 16px #7DD3FC" }} />
                                <i style={{ position: "absolute", left: -5, top: "50%", width: 9, height: 9, marginTop: -4, borderRadius: "50%", background: "#F0ABFC", boxShadow: "0 0 14px #F0ABFC" }} />
                            </div>
                            <div role="img" aria-label="Mixx Tune" style={{ position: "absolute", inset: 0, borderRadius: "50%", background: "#FFFFFF", display: "flex", alignItems: "center", justifyContent: "center", boxShadow: "0 0 90px rgba(124,108,255,.9), 0 0 0 6px rgba(79,75,220,.28)" }}>
                                <Logo size={104} color="#4F4BDC" animated />
                            </div>
                        </div>
                    </div>
                    <p aria-hidden="true" style={{ margin: "8px 0 0", fontSize: 12, letterSpacing: ".22em", textTransform: "uppercase", color: "#8F8BBF" }}>
                        Scroll to launch ↓
                    </p>
                </section>

                {/* STORES STRIP */}
                {sections.stores && (
                    <section aria-label="Stores" style={{ position: "relative", zIndex: 2, borderTop: "1px solid rgba(201,195,255,.12)", borderBottom: "1px solid rgba(201,195,255,.12)", background: "rgba(12,10,40,.7)", overflow: "hidden", padding: "22px 0" }}>
                        <div className="mtg-track mtg-display" style={{ display: "flex", gap: 56, width: "max-content", fontWeight: 600, fontSize: 22, color: "#D9D5FF", whiteSpace: "nowrap" }}>
                            {marquee.map((m, i) => (
                                <span key={`${m}-${i}`} aria-hidden={i >= stores.length ? "true" : undefined} style={{ display: "inline-flex", alignItems: "center", gap: 56 }}>
                                    {m}
                                    <i aria-hidden="true" style={{ display: "block", width: 8, height: 8, borderRadius: "50%", background: "#7DD3FC" }} />
                                </span>
                            ))}
                        </div>
                    </section>
                )}

                {/* HOW IT WORKS */}
                {sections.how && (
                    <section id="how" style={{ position: "relative", zIndex: 2, maxWidth: 1240, margin: "0 auto", padding: "140px 24px 60px" }}>
                        <div className="mtg-zoom" style={{ textAlign: "center", maxWidth: 760, margin: "0 auto 64px" }}>
                            <p style={eyebrowStyle}>From studio to stars</p>
                            <h2 className="mtg-display" style={h2Style}>
                                Four steps to lift-off
                            </h2>
                            <p style={{ margin: "18px 0 0", fontSize: 18, lineHeight: 1.6, color: "#B9B5DE" }}>Fill in your release once. Our team reviews it and sends it to the stores.</p>
                        </div>
                        <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(250px, 1fr))", gap: 22 }}>
                            {STEPS.map(([cls, n, title, text]) => (
                                <article key={n} className={`${cls} mtg-planet`} style={{ position: "relative", padding: "30px 26px", borderRadius: 28, background: "linear-gradient(180deg, rgba(36,32,96,.75), rgba(14,12,44,.85))", border: "1px solid rgba(201,195,255,.2)", minHeight: 270 }}>
                                    <div className="mtg-display" style={{ width: 64, height: 64, borderRadius: "50%", display: "flex", alignItems: "center", justifyContent: "center", fontWeight: 800, fontSize: 22, color: "#FFFFFF", background: "radial-gradient(circle at 35% 30%, #9A8CFF, #4F4BDC 60%, #2A2780)", boxShadow: "0 0 34px rgba(124,108,255,.55)" }}>{n}</div>
                                    <h3 className="mtg-display" style={{ margin: "24px 0 10px", fontWeight: 600, fontSize: 21, color: "#FFFFFF" }}>
                                        {title}
                                    </h3>
                                    <p style={{ margin: 0, fontSize: 15, lineHeight: 1.6, color: "#B9B5DE" }}>{text}</p>
                                </article>
                            ))}
                        </div>
                    </section>
                )}

                {/* FEATURES */}
                {sections.features && (
                    <section id="features" style={{ position: "relative", zIndex: 2, maxWidth: 1240, margin: "0 auto", padding: "120px 24px 60px" }}>
                        <div className="mtg-zoom" style={{ textAlign: "center", maxWidth: 760, margin: "0 auto 70px" }}>
                            <p style={eyebrowStyle}>Your own music universe</p>
                            <h2 className="mtg-display" style={h2Style}>
                                Everything orbits around your panel
                            </h2>
                        </div>
                        <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(min(320px, 100%), 1fr))", gap: 26 }}>
                            {FEATURES.map(([cls, title, text, icon, rgb]) => (
                                <article key={title} className={`${cls} mtg-planet`} style={{ position: "relative", overflow: "hidden", padding: "34px 30px", borderRadius: 30, background: "rgba(16,14,50,.85)", border: "1px solid rgba(201,195,255,.18)", minHeight: 250 }}>
                                    <div aria-hidden="true" style={{ position: "absolute", right: -70, top: -70, width: 200, height: 200, borderRadius: "50%", background: `radial-gradient(circle at 35% 35%, rgba(${rgb},.5), rgba(${rgb},.12) 60%, rgba(${rgb},0) 72%)` }} />
                                    <div style={{ position: "relative", width: 52, height: 52, borderRadius: 16, background: "rgba(124,108,255,.18)", border: "1px solid rgba(201,195,255,.3)", display: "flex", alignItems: "center", justifyContent: "center" }}>
                                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#C9C3FF" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                                            <path d={icon} />
                                        </svg>
                                    </div>
                                    <h3 className="mtg-display" style={{ position: "relative", margin: "22px 0 10px", fontWeight: 600, fontSize: 20, color: "#FFFFFF" }}>
                                        {title}
                                    </h3>
                                    <p style={{ position: "relative", margin: 0, fontSize: 15, lineHeight: 1.6, color: "#B9B5DE", maxWidth: 420 }}>{text}</p>
                                </article>
                            ))}
                        </div>
                    </section>
                )}

                {/* ROYALTY SCENE */}
                {sections.royalty && (
                    <section id="royalties" className="mtg-pay" style={{ position: "relative", zIndex: 2 }}>
                        <div className="mtg-pay-stage" style={{ display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "center", gap: 34, padding: "90px 24px 60px", overflow: "clip" }}>
                            <div className="mtg-stream-layer" aria-hidden="true" style={{ position: "absolute", left: "50%", top: "62%", width: 0, height: 0, pointerEvents: "none" }}>
                                {stream.map((s, i) => {
                                    const coin = s.label === "₹";
                                    return (
                                        <span
                                            key={i}
                                            className="mtg-sc"
                                            style={{
                                                position: "absolute", left: 0, top: 0, margin: "-20px 0 0 -40px", whiteSpace: "nowrap",
                                                "--x": s.x, "--y": s.y, "--rot": s.rot, animationRange: `contain ${s.from}% contain ${s.from + 22}%`,
                                                ...(coin
                                                    ? { width: 46, height: 46, borderRadius: "50%", display: "flex", alignItems: "center", justifyContent: "center", background: "radial-gradient(circle at 35% 30%, #FDE68A, #F59E0B)", color: "#3B2300", fontSize: 22, fontWeight: 800, boxShadow: "0 0 30px rgba(252,211,77,.6)" }
                                                    : { padding: "9px 16px", borderRadius: 999, background: "rgba(22,20,64,.92)", border: "1px solid rgba(125,211,252,.45)", color: "#FFFFFF", fontSize: 13, fontWeight: 600, boxShadow: "0 0 26px rgba(125,211,252,.35)" }),
                                            }}
                                        >
                                            {s.label}
                                        </span>
                                    );
                                })}
                            </div>

                            <div className="mtg-pay-head" style={{ position: "relative", textAlign: "center", maxWidth: 820 }}>
                                <p style={{ ...eyebrowStyle, margin: "0 0 12px" }}>Royalties, crystal clear</p>
                                <h2 className="mtg-display" style={{ ...h2Style, fontSize: "clamp(28px, 3.8vw, 50px)" }}>
                                    Every rupee travels a path you can see
                                </h2>
                                <p style={{ margin: "14px auto 0", maxWidth: 600, fontSize: 17, lineHeight: 1.6, color: "#B9B5DE" }}>From the store report to your bank account, each step shows up in your panel.</p>
                            </div>

                            <div style={{ position: "relative", width: "100%", maxWidth: 1100, display: "flex", flexWrap: "wrap", alignItems: "center", justifyContent: "center", gap: 40 }}>
                                <div style={{ flex: "1 1 280px", maxWidth: 320, display: "flex", flexDirection: "column", gap: 18 }}>
                                    <PayCard cls={PAY[0][0]} color={PAY[0][1]} icon={PAY[0][2]} title={PAY[0][3]} text={PAY[0][4]} />
                                    <PayCard cls={PAY[1][0]} color={PAY[1][1]} icon={PAY[1][2]} title={PAY[1][3]} text={PAY[1][4]} />
                                </div>

                                <div className="mtg-orb" style={{ position: "relative", flex: "0 0 auto", width: 250, height: 250 }}>
                                    <div aria-hidden="true" className="mtg-halo" style={{ position: "absolute", left: -60, top: -60, width: 370, height: 370, borderRadius: "50%", background: "radial-gradient(closest-side, rgba(124,108,255,.5), rgba(79,75,220,0))" }} />
                                    <div aria-hidden="true" className="mtg-ring-fill" style={{ position: "absolute", inset: 0, borderRadius: "50%" }} />
                                    <div style={{ position: "absolute", inset: 10, borderRadius: "50%", background: "radial-gradient(circle at 35% 30%, #2C2790, #110F3C 70%)", display: "flex", flexDirection: "column", alignItems: "center", justifyContent: "center", gap: 4, boxShadow: "inset 0 0 0 1px rgba(201,195,255,.2)" }}>
                                        <span className="mtg-display" aria-hidden="true" style={{ fontWeight: 800, fontSize: 76, lineHeight: 1, color: "#FFFFFF", textShadow: "0 0 40px rgba(125,211,252,.7)" }}>
                                            ₹
                                        </span>
                                        <span style={{ fontSize: 14, fontWeight: 600, color: "#C9C3FF" }}>Your wallet</span>
                                        <span className="mtg-paid" style={{ marginTop: 6, padding: "5px 12px", borderRadius: 999, background: "#7DD3FC", color: "#06051A", fontSize: 12, fontWeight: 700 }}>
                                            Paid to bank
                                        </span>
                                    </div>
                                </div>

                                <div style={{ flex: "1 1 280px", maxWidth: 320, display: "flex", flexDirection: "column", gap: 18 }}>
                                    <PayCard cls={PAY[2][0]} color={PAY[2][1]} icon={PAY[2][2]} title={PAY[2][3]} text={PAY[2][4]} />
                                    <PayCard cls={PAY[3][0]} color={PAY[3][1]} icon={PAY[3][2]} title={PAY[3][3]} text={PAY[3][4]} />
                                </div>
                            </div>
                        </div>
                    </section>
                )}

                {/* FOR LABELS / ARTISTS */}
                {sections.audience && (
                    <section style={{ position: "relative", zIndex: 2, maxWidth: 1240, margin: "0 auto", padding: "120px 24px 60px" }}>
                        <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(min(320px, 100%), 1fr))", gap: 26 }}>
                            {[
                                ["mtg-fly-l", "For labels", "rgba(79,75,220,.5)", ["Your whole catalogue, releases and tracks in one place", "Sub-labels with their own agreed revenue share", "Team logins and managers with the access you choose", "Monthly statements as PDF, ready for your accounts"]],
                                ["mtg-fly-r", "For artists", "rgba(56,189,248,.32)", ["Upload your WAV and fill details while it uploads", "Credit every lyricist, composer and featured artist", "Choose worldwide or only the countries you want", "See where your songs play, month by month"]],
                            ].map(([cls, title, tint, items]) => (
                                <article key={title} className={cls} style={{ padding: "44px 38px", borderRadius: 34, background: `linear-gradient(160deg, ${tint}, rgba(16,14,50,.9) 60%)`, border: "1px solid rgba(201,195,255,.24)" }}>
                                    <h3 className="mtg-display" style={{ margin: "0 0 14px", fontWeight: 800, fontSize: 30, color: "#FFFFFF" }}>
                                        {title}
                                    </h3>
                                    <ul style={{ margin: 0, padding: "0 0 0 20px", display: "flex", flexDirection: "column", gap: 10, fontSize: 16, lineHeight: 1.5, color: "#D9D5FF", listStyle: "disc" }}>
                                        {items.map((t) => (
                                            <li key={t}>{t}</li>
                                        ))}
                                    </ul>
                                </article>
                            ))}
                        </div>
                    </section>
                )}

                {/* CTA */}
                {sections.cta && (
                    <section style={{ position: "relative", zIndex: 2, padding: "150px 24px", textAlign: "center", overflow: "hidden" }}>
                        <div aria-hidden="true" className="mtg-warp" style={{ position: "absolute", left: "50%", top: "50%", width: 760, height: 760, margin: "-380px 0 0 -380px", borderRadius: "50%", border: "1px solid rgba(201,195,255,.14)" }} />
                        <div aria-hidden="true" className="mtg-halo" style={{ position: "absolute", left: "50%", top: "50%", width: 520, height: 520, margin: "-260px 0 0 -260px", borderRadius: "50%", background: "radial-gradient(closest-side, rgba(124,108,255,.45), rgba(79,75,220,0))" }} />
                        <div className="mtg-zoom" style={{ position: "relative", maxWidth: 820, margin: "0 auto" }}>
                            <h2 className="mtg-display" style={{ ...h2Style, fontSize: "clamp(34px, 5.6vw, 72px)", lineHeight: 1.04, letterSpacing: "-0.03em" }}>
                                Ready for lift-off?
                            </h2>
                            <p style={{ margin: "22px auto 0", maxWidth: 560, fontSize: 18, lineHeight: 1.6, color: "#B9B5DE" }}>Send us a join request. Our team will call you, understand your music and set everything up with you.</p>
                            <div style={{ marginTop: 34, display: "flex", gap: 12, justifyContent: "center", flexWrap: "wrap" }}>
                                <button type="button" className="mtg-btn mtg-float" onClick={openJoin} style={{ ...primaryBtn, padding: "17px 36px", fontSize: 17, minHeight: 56, boxShadow: "0 12px 50px rgba(79,75,220,.7)" }}>
                                    {hero.join_button}
                                </button>
                                <a className="mtg-btn" href="#contact" style={{ ...ghostBtn, padding: "17px 34px", fontSize: 17, minHeight: 56 }}>
                                    Talk to us
                                </a>
                            </div>
                        </div>
                    </section>
                )}
            </main>

            {/* FOOTER */}
            <footer id="contact" style={{ position: "relative", zIndex: 2, borderTop: "1px solid rgba(201,195,255,.12)", background: "rgba(8,7,28,.92)" }}>
                <div style={{ maxWidth: 1240, margin: "0 auto", padding: "56px 24px 40px", display: "flex", flexWrap: "wrap", gap: 40, justifyContent: "space-between" }}>
                    <div style={{ flex: "1 1 280px", minWidth: 0 }}>
                        <div style={{ display: "flex", alignItems: "center", gap: 12 }}>
                            <Logo size={38} />
                            <span className="mtg-display" style={{ fontWeight: 600, fontSize: 18, color: "#FFFFFF" }}>
                                mixx tune
                            </span>
                        </div>
                        <p style={{ margin: "16px 0 0", maxWidth: 320, fontSize: 14, lineHeight: 1.6, color: "#A9A5D2" }}>Music distribution, royalties and analytics for labels and artists.</p>
                    </div>
                    <div style={{ flex: "1 1 200px", minWidth: 0 }}>
                        <h2 style={{ margin: "0 0 14px", fontSize: 13, letterSpacing: ".14em", textTransform: "uppercase", color: "#8F8BBF" }}>Contact</h2>
                        <div style={{ display: "flex", flexDirection: "column", gap: 6, fontSize: 15, lineHeight: 1.6, color: "#D9D5FF" }}>
                            {contact.email && <a href={`mailto:${contact.email}`} style={{ textDecoration: "none" }}>{contact.email}</a>}
                            {contact.phone && <a href={`tel:${contact.phone.replace(/[^0-9+]/g, "")}`} style={{ textDecoration: "none" }}>{contact.phone}</a>}
                            {contact.address && <span style={{ whiteSpace: "pre-line" }}>{contact.address}</span>}
                            <button type="button" onClick={openJoin} className="mtg-btn" style={{ alignSelf: "flex-start", marginTop: 6, padding: 0, border: 0, background: "transparent", color: "#C9C3FF", fontSize: 15, fontWeight: 600, textDecoration: "underline", minHeight: 32 }}>
                                Send a join request
                            </button>
                        </div>
                    </div>
                    <div style={{ flex: "1 1 200px", minWidth: 0 }}>
                        <h2 style={{ margin: "0 0 14px", fontSize: 13, letterSpacing: ".14em", textTransform: "uppercase", color: "#8F8BBF" }}>Panel</h2>
                        <div style={{ display: "flex", flexDirection: "column", gap: 6, fontSize: 15, lineHeight: 1.6 }}>
                            <a href="/login" style={{ textDecoration: "none" }}>Log in</a>
                            <a href="/privacy" style={{ textDecoration: "none" }}>Privacy</a>
                            <a href="/terms" style={{ textDecoration: "none" }}>Terms</a>
                        </div>
                    </div>
                </div>
                <p style={{ margin: 0, padding: "18px 24px 28px", textAlign: "center", fontSize: 13, color: "#8F8BBF" }}>© {new Date().getFullYear()} Mixx Tune. All rights reserved.</p>
            </footer>

            {joinOpen && <JoinModal join={join} onClose={closeJoin} />}
        </div>
    );
}
