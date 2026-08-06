export const mascotStates = {
    idle: {
        title: 'Ready When You Are',
        message: 'Let’s create something amazing.',
        activity: 'idle',
        icon: '♪',
        tip: 'Complete each step carefully before submitting.',
        status: 'Waiting',
    },

    releaseInfo: {
        title: 'Release Information',
        message: 'Tell me about your upcoming release.',
        activity: 'guitar',
        icon: '🎸',
        tip: 'Use the exact artist name shown on Spotify and Apple Music.',
        status: 'Creating metadata',
    },

    tracks: {
        title: 'Add Your Tracks',
        message: 'Upload your WAV master and complete the track details.',
        activity: 'listening',
        icon: '🎧',
        tip: 'Use high-quality WAV audio and check contributor details.',
        status: 'Listening mode',
    },

    stores: {
        title: 'Choose Music Stores',
        message: 'Select the platforms where your music should appear.',
        activity: 'distribution',
        icon: '🌍',
        tip: 'All supported stores are selected by default.',
        status: 'Selecting stores',
    },

    territory: {
        title: 'Select Territory',
        message: 'Choose where your release should be available.',
        activity: 'globe',
        icon: '🌐',
        tip: 'Worldwide delivery is recommended for most releases.',
        status: 'Planning delivery',
    },

    review: {
        title: 'Final Review',
        message: 'Let’s check everything before submission.',
        activity: 'review',
        icon: '📋',
        tip: 'Fix every warning before clicking Submit for Review.',
        status: 'Reviewing release',
    },

    saving: {
        title: 'Saving Your Work',
        message: 'Please wait while your progress is being saved.',
        activity: 'working',
        icon: '✦',
        tip: 'Do not close this page while saving.',
        status: 'Saving...',
    },

    success: {
        title: 'All Changes Saved',
        message: 'Great work! Your release progress is safe.',
        activity: 'celebration',
        icon: '✓',
        tip: 'Continue to the next step whenever you are ready.',
        status: 'Saved',
    },

    submitting: {
        title: 'Preparing Your Release',
        message: 'Your release is getting ready to fly.',
        activity: 'flying',
        icon: '🚀',
        tip: 'Submission may take a few seconds.',
        status: 'Submitting...',
    },

    error: {
        title: 'Something Needs Attention',
        message: 'Please check the highlighted information.',
        activity: 'thinking',
        icon: '!',
        tip: 'Complete all required fields marked in red.',
        status: 'Action required',
    },

    trackUploading: {
        title: 'Uploading Master Audio',
        message: 'I am listening while your WAV master uploads.',
        activity: 'listening',
        icon: '🎧',
        tip: 'Keep this page open until the upload reaches 100%.',
        status: 'Uploading audio...',
    },

    trackUploaded: {
        title: 'Track Uploaded',
        message: 'Your master audio has been added successfully.',
        activity: 'celebration',
        icon: '✓',
        tip: 'Check track metadata before moving ahead.',
        status: 'Audio ready',
    },

    distributionSaving: {
        title: 'Saving Distribution',
        message: 'Your stores and territories are being prepared.',
        activity: 'distribution',
        icon: '🌍',
        tip: 'All selected stores will receive this release.',
        status: 'Saving delivery...',
    },

    distributionSaved: {
        title: 'Distribution Saved',
        message: 'Stores and territories are configured.',
        activity: 'celebration',
        icon: '✓',
        tip: 'You can review these selections before submission.',
        status: 'Delivery ready',
    },

    submittingRelease: {
        title: 'Release Taking Flight',
        message: 'Your release is being submitted for review.',
        activity: 'flying',
        icon: '🚀',
        tip: 'Please wait while we finish the submission.',
        status: 'Submitting release...',
    },

    submissionError: {
        title: 'Submission Needs Attention',
        message: 'The release could not be submitted yet.',
        activity: 'thinking',
        icon: '!',
        tip: 'Review the error and complete the missing information.',
        status: 'Submission stopped',
    },
};

export const getMascotState = ({
    currentStep = 1,
    saveState = 'manual',
    processing = false,
    mascotEvent = null,
}) => {
    const eventStates = {
        'track-uploading': mascotStates.trackUploading,
        'track-uploaded': mascotStates.trackUploaded,
        'track-error': mascotStates.error,
        'distribution-saving':
            mascotStates.distributionSaving,
        'distribution-saved':
            mascotStates.distributionSaved,
        'distribution-error':
            mascotStates.error,
        submitting:
            mascotStates.submittingRelease,
        'submission-error':
            mascotStates.submissionError,
    };

    if (
        mascotEvent &&
        eventStates[mascotEvent]
    ) {
        return eventStates[mascotEvent];
    }

    if (processing && currentStep === 5) {
        return mascotStates.submitting;
    }

    if (saveState === 'saving') {
        return mascotStates.saving;
    }

    if (saveState === 'error') {
        return mascotStates.error;
    }

    const stepStates = {
        1: mascotStates.releaseInfo,
        2: mascotStates.tracks,
        3: mascotStates.stores,
        4: mascotStates.territory,
        5: mascotStates.review,
    };

    return stepStates[currentStep] ?? mascotStates.idle;
};
