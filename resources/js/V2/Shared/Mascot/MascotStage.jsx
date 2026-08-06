import MascotCharacter from './MascotCharacter';

export default function MascotStage({
    state,
    currentStep = 1,
    totalSteps = 5,
}) {
    if (!state) {
        return null;
    }

    const progress = Math.min(
        Math.max(
            Math.round(
                (currentStep / totalSteps) * 100
            ),
            0
        ),
        100
    );

    return (
        <aside className="mixx-mascot-stage">
            <div className="mixx-mascot-card">
                <div className="mixx-mascot-card-glow mixx-mascot-card-glow-one" />
                <div className="mixx-mascot-card-glow mixx-mascot-card-glow-two" />

                <div
                    key={`${currentStep}-${state.activity}-${state.status}`}
                    className={`mixx-mascot-card-content mixx-mascot-card-state-${state.activity}`}
                >
                    <div className="mixx-mascot-card-header">
                        <div className="mixx-mascot-card-icon">
                            {state.icon}
                        </div>

                        <div className="min-w-0">
                            <div className="mixx-mascot-card-kicker">
                                MIXX TUNE ASSISTANT
                            </div>

                            <h3 className="mixx-mascot-card-title">
                                {state.title}
                            </h3>
                        </div>
                    </div>

                    <p className="mixx-mascot-card-message">
                        {state.message}
                    </p>

                    <div className="mixx-mascot-character-wrap">
                        <div className="mixx-mascot-floor" />

                        <MascotCharacter
                            key={`${currentStep}-${state.activity}`}
                            activity={state.activity}
                        />
                    </div>

                    <div className="mixx-mascot-progress-card">
                        <div className="mixx-mascot-progress-row">
                            <span>
                                Step {currentStep} of {totalSteps}
                            </span>

                            <strong>
                                {progress}%
                            </strong>
                        </div>

                        <div className="mixx-mascot-progress-track">
                            <div
                                className="mixx-mascot-progress-value"
                                style={{
                                    width: `${progress}%`,
                                }}
                            />
                        </div>

                        <div className="mixx-mascot-status">
                            <span className="mixx-mascot-status-dot" />
                            {state.status}
                        </div>
                    </div>

                    <div className="mixx-mascot-tip">
                        <div className="mixx-mascot-tip-icon">
                            💡
                        </div>

                        <div>
                            <div className="mixx-mascot-tip-title">
                                Quick tip
                            </div>

                            <p className="mixx-mascot-tip-text">
                                {state.tip}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </aside>
    );
}
