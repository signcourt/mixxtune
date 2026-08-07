const stepContent = {
    1: {
        title: 'Release Details',
        subtitle:
            'Provide the basic information about your release.',
    },
    2: {
        title: 'Track Upload',
        subtitle:
            'Upload your audio files and provide track details.',
    },
    3: {
        title: 'Stores',
        subtitle:
            'Choose the stores and streaming services where your music will be available.',
    },
    4: {
        title: 'Territories',
        subtitle:
            'Choose where your release will be available.',
    },
    5: {
        title: 'Review & Publish',
        subtitle:
            'Review all details before publishing your release.',
    },
};

export default function ReleaseStepHeader({
    currentStep = 1,
    saveState = 'manual',
    onSave = () => {},
    processing = false,
}) {
    const content =
        stepContent[currentStep] ??
        stepContent[1];

    return (
        <header className="mixx-v4-step-header">
            <div>
                <div className="mixx-v4-step-pill">
                    Step {currentStep} of 5
                </div>

                <h1 className="mixx-v4-step-title">
                    {content.title}
                </h1>

                <p className="mixx-v4-step-subtitle">
                    {content.subtitle}
                </p>
            </div>

            <button
                type="button"
                onClick={onSave}
                disabled={processing}
                className="mixx-v4-save-button"
            >
                <span aria-hidden="true">▣</span>

                {saveState === 'saving'
                    ? 'Saving...'
                    : saveState === 'saved'
                        ? 'Saved'
                        : 'Save Draft'}
            </button>
        </header>
    );
}
