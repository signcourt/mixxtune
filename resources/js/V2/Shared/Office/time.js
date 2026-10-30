/* W127 MIXXTUNE_OFFICE: seconds -> "7h 42m" */
export const hm = (s) => {
    const n = Math.max(0, Math.floor(Number(s) || 0));
    const h = Math.floor(n / 3600);
    const m = Math.floor((n % 3600) / 60);
    return `${h}h ${m}m`;
};
export const hms = (s) => {
    const n = Math.max(0, Math.floor(Number(s) || 0));
    const h = String(Math.floor(n / 3600)).padStart(2, '0');
    const m = String(Math.floor((n % 3600) / 60)).padStart(2, '0');
    const x = String(n % 60).padStart(2, '0');
    return `${h}:${m}:${x}`;
};
