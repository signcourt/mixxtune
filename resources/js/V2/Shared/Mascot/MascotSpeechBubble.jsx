export default function MascotSpeechBubble({
    title,
    message,
    icon,
}) {
    return (
        <div className="mixx-mascot-bubble">
            <div className="mixx-mascot-bubble-icon">
                {icon}
            </div>

            <div className="min-w-0">
                <div className="text-sm font-black text-slate-950">
                    {title}
                </div>

                <p className="mt-1 text-xs leading-5 text-slate-600">
                    {message}
                </p>
            </div>
        </div>
    );
}
