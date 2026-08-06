const tips = {
    1: {
        title: 'Helpful Tip',
        text:
            'A clear release title and accurate details help your music reach the right audience and stores.',
        items: [
            'Save as draft and come back later',
            'Edit details before publishing',
            'Preview how it will appear on stores',
        ],
    },

    2: {
        title: 'Audio Requirements',
        text:
            'Upload a high-quality WAV master and complete all contributor information.',
        items: [
            'Use WAV audio only',
            'Check artist and contributor names',
            'Add ISRC or leave it pending',
        ],
    },

    3: {
        title: 'Helpful Tip',
        text:
            'Selecting more stores increases your fan reach and helps your music get discovered.',
        items: [
            'Add or remove stores before publishing',
            'Some stores may take longer to go live',
            'Earnings and reporting vary by store',
        ],
    },

    4: {
        title: 'Helpful Tip',
        text:
            'Choosing the correct territories helps you reach the right audience and avoid restrictions.',
        items: [
            'Worldwide is recommended by default',
            'You can exclude restricted countries',
            'Territories can affect release dates',
        ],
    },

    5: {
        title: 'What happens next?',
        text:
            'After submission, the Mixx Tune team will review your release.',
        items: [
            'Metadata and audio quality review',
            'Delivery to selected stores',
            'Email notification when approved',
        ],
    },
};

export default function ReleaseHelpfulTip({
    currentStep = 1,
}) {
    const tip =
        tips[currentStep] ??
        tips[1];

    return (
        <aside className="mixx-v4-help-card">
            <div className="mixx-v4-help-icon">
                ✦
            </div>

            <h3>{tip.title}</h3>

            <p className="mixx-v4-help-text">
                {tip.text}
            </p>

            <div className="mixx-v4-help-divider" />

            <div className="mixx-v4-help-label">
                You can always:
            </div>

            <div className="mixx-v4-help-list">
                {tip.items.map((item) => (
                    <div
                        key={item}
                        className="mixx-v4-help-item"
                    >
                        <span>✓</span>
                        <p>{item}</p>
                    </div>
                ))}
            </div>
        </aside>
    );
}
