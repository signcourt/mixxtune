export default function TrackUploadOverlay({
    visible = false,
    fileName = '',
    progress = 0,
}) {
    if (!visible) {
        return null;
    }

    const safeProgress = Math.min(
        100,
        Math.max(0, Number(progress) || 0)
    );

    return (
        <div
            className="mixx-v5-upload-overlay"
            role="dialog"
            aria-modal="true"
            aria-label="Uploading master audio"
        >
            <div className="mixx-v5-upload-backdrop" />

            <div className="mixx-v5-upload-modal">
                <div className="mixx-v5-upload-light mixx-v5-upload-light-one" />
                <div className="mixx-v5-upload-light mixx-v5-upload-light-two" />

                <div className="mixx-v5-upload-studio">
                    <div className="mixx-v5-upload-microphone">
                        <span className="mixx-v5-upload-mic-head" />
                        <span className="mixx-v5-upload-mic-body" />
                        <span className="mixx-v5-upload-mic-stand" />
                    </div>

                    <div className="mixx-v5-upload-headphones">
                        <span>♫</span>
                    </div>

                    <div className="mixx-v5-upload-notes">
                        <span>♪</span>
                        <span>♫</span>
                        <span>♬</span>
                    </div>
                </div>

                <div className="mixx-v5-upload-copy">
                    <div className="mixx-v5-upload-badge">
                        Master WAV Upload
                    </div>

                    <h2>
                        Uploading your track
                    </h2>

                    <p className="mixx-v5-upload-file">
                        {fileName || 'Preparing audio file…'}
                    </p>

                    <div className="mixx-v5-upload-progress-row">
                        <span>
                            Secure upload
                        </span>

                        <strong>
                            {safeProgress}%
                        </strong>
                    </div>

                    <div
                        className="mixx-v5-upload-progress"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        aria-valuenow={safeProgress}
                        role="progressbar"
                    >
                        <div
                            className="mixx-v5-upload-progress-fill"
                            style={{
                                width: `${safeProgress}%`,
                            }}
                        >
                            <span />
                        </div>
                    </div>

                    <div className="mixx-v5-upload-status">
                        <span className="mixx-v5-upload-status-dot" />

                        Please keep this page open while your
                        WAV master is uploaded securely.
                    </div>
                </div>
            </div>
        </div>
    );
}
