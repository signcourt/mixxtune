import { useRef, useState } from 'react';

export default function ReleaseCoverArtCard({
    data,
    setData,
    error = null,
}) {
    const [localError, setLocalError] = useState('');
    const fileInputRef = useRef(null);

    const openFilePicker = () => {
        fileInputRef.current?.click();
    };

    const validateArtwork = (file, input) => {
        setLocalError('');

        if (!file) {
            return;
        }

        const allowedTypes = [
            'image/jpeg',
            'image/png',
        ];

        if (!allowedTypes.includes(file.type)) {
            setData('artwork', null);
            setData('artwork_preview', null);

            setLocalError(
                'Only JPG or PNG artwork is accepted.'
            );

            input.value = '';
            return;
        }

        const image = new Image();
        const objectUrl = URL.createObjectURL(file);

        image.onload = () => {
            const valid =
                image.naturalWidth === 3000 &&
                image.naturalHeight === 3000;

            if (!valid) {
                URL.revokeObjectURL(objectUrl);

                setData('artwork', null);
                setData('artwork_preview', null);

                setLocalError(
                    `Artwork must be exactly 3000 × 3000 px. Selected image is ${image.naturalWidth} × ${image.naturalHeight} px.`
                );

                input.value = '';
                return;
            }

            setData('artwork', file);
            setData(
                'artwork_preview',
                objectUrl
            );
        };

        image.onerror = () => {
            URL.revokeObjectURL(objectUrl);

            setData('artwork', null);
            setData('artwork_preview', null);

            setLocalError(
                'This image could not be read. Please select a valid JPG or PNG file.'
            );

            input.value = '';
        };

        image.src = objectUrl;
    };

    return (
        <aside className="mixx-v4-cover-card">
            <div className="mixx-v4-cover-heading">
                <div>
                    <span className="mixx-v4-cover-eyebrow">
                        RELEASE ARTWORK
                    </span>

                    <h3>
                        Cover Art
                        <span className="mixx-v4-cover-required">
                            *
                        </span>
                    </h3>
                </div>

                <span className="mixx-v4-cover-size-badge">
                    3000 × 3000
                </span>
            </div>

            <p className="mixx-v4-cover-description">
                Upload the final square artwork that
                will appear across music stores.
            </p>

            <div
                className="mixx-v4-cover-upload"
                role="button"
                tabIndex={0}
                onClick={openFilePicker}
                onKeyDown={(event) => {
                    if (
                        event.key === 'Enter' ||
                        event.key === ' '
                    ) {
                        event.preventDefault();
                        openFilePicker();
                    }
                }}
            >
                {data.artwork_preview ? (
                    <div className="mixx-v4-cover-preview-wrap">
                        <img
                            src={data.artwork_preview}
                            alt="Release cover artwork"
                            className="mixx-v4-cover-preview"
                        />

                        <div className="mixx-v4-cover-change">
                            Change Artwork
                        </div>
                    </div>
                ) : (
                    <div className="mixx-v4-cover-empty">
                        <div className="mixx-v4-cover-upload-icon">
                            ↑
                        </div>

                        <strong>
                            Upload Cover Art
                        </strong>

                        <span>
                            JPG or PNG
                        </span>

                        <span className="mixx-v4-cover-browse">
                            Browse Files
                        </span>
                    </div>
                )}

                <input
                    ref={fileInputRef}
                    type="file"
                    accept="image/jpeg,image/png"
                    className="hidden"
                    onChange={(event) =>
                        validateArtwork(
                            event.target.files?.[0] ?? null,
                            event.target
                        )
                    }
                />
            </div>

            <div className="mixx-v4-cover-requirements">
                <div>
                    <span>✓</span>
                    Exactly 3000 × 3000 pixels
                </div>

                <div>
                    <span>✓</span>
                    Square 1:1 artwork only
                </div>

                <div>
                    <span>✓</span>
                    JPG or PNG format
                </div>
            </div>

            {(localError || error) && (
                <div className="mixx-v4-cover-error">
                    {localError || error}
                </div>
            )}
        </aside>
    );
}
