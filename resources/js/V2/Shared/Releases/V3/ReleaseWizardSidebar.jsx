const wizardSteps = [
    {
        number: 1,
        title: 'Release Details',
        description: 'Tell us about your release',
        mascotLabel: 'Playing guitar',
        mascotIcon: '🎸',
    },
    {
        number: 2,
        title: 'Track Upload',
        description: 'Add your tracks and info',
        mascotLabel: 'Recording vocals',
        mascotIcon: '🎙️',
    },
    {
        number: 3,
        title: 'Stores',
        description: 'Choose where to distribute',
        mascotLabel: 'Listening to the beat',
        mascotIcon: '🎧',
    },
    {
        number: 4,
        title: 'Territories',
        description: 'Select your release territory',
        mascotLabel: 'Planning worldwide delivery',
        mascotIcon: '🌍',
    },
    {
        number: 5,
        title: 'Review & Publish',
        description: 'Review and publish your release',
        mascotLabel: 'Ready for launch',
        mascotIcon: '🚀',
    },
];

export default function ReleaseWizardSidebar({
    currentStep = 1,
    onStepChange = () => {},
    release = null,
}) {
    const canOpenStep = (stepNumber) => {
        if (stepNumber === 1) {
            return true;
        }

        return Boolean(release?.id);
    };

    const activeStep =
        wizardSteps.find(
            (step) => step.number === currentStep
        ) ?? wizardSteps[0];

    return (
        <aside className="mixx-v3-sidebar">
            <div className="mixx-v3-mascot-stage">
                <div className="mixx-v3-mascot-glow" />

                <div
                    className={`mixx-v3-mascot-placeholder mixx-v3-mascot-step-${currentStep}`}
                >
                    <span className="mixx-v3-mascot-icon">
                        {activeStep.mascotIcon}
                    </span>

                    <span className="mixx-v3-mascot-placeholder-title">
                        Mixx Tune Artist
                    </span>

                    <span className="mixx-v3-mascot-placeholder-text">
                        {activeStep.mascotLabel}
                    </span>
                </div>

                <div className="mixx-v3-music-notes">
                    <span>♪</span>
                    <span>♫</span>
                    <span>✦</span>
                </div>
            </div>

            <nav
                className="mixx-v3-step-navigation"
                aria-label="Create Release Steps"
            >
                {wizardSteps.map((step, index) => {
                    const isActive =
                        step.number === currentStep;

                    const isComplete =
                        step.number < currentStep;

                    const enabled =
                        canOpenStep(step.number);

                    return (
                        <div
                            key={step.number}
                            className="mixx-v3-step-wrap"
                        >
                            {index > 0 && (
                                <div
                                    className={`mixx-v3-step-line ${
                                        isComplete ||
                                        isActive
                                            ? 'mixx-v3-step-line-active'
                                            : ''
                                    }`}
                                />
                            )}

                            <button
                                type="button"
                                disabled={!enabled}
                                onClick={() => {
                                    if (enabled) {
                                        onStepChange(
                                            step.number
                                        );
                                    }
                                }}
                                className={`mixx-v3-step-button ${
                                    isActive
                                        ? 'mixx-v3-step-active'
                                        : ''
                                } ${
                                    isComplete
                                        ? 'mixx-v3-step-complete'
                                        : ''
                                }`}
                            >
                                <span className="mixx-v3-step-number">
                                    {isComplete
                                        ? '✓'
                                        : step.number}
                                </span>

                                <span className="mixx-v3-step-copy">
                                    <strong>
                                        {step.title}
                                    </strong>

                                    <small>
                                        {
                                            step.description
                                        }
                                    </small>
                                </span>
                            </button>
                        </div>
                    );
                })}
            </nav>
        </aside>
    );
}
