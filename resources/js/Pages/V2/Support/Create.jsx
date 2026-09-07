import {
    Head,
    useForm,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

const LEGAL_CONFIG = {
    copyright: {
        title: 'Copyright & Claims',
        subtitle: 'Copyright claims, disputes and takedown matters',
        category: 'copyright',
        subject: 'Copyright / Claim Issue',
        issueLabel: 'Claim / Dispute Type',
        issues: [
            'Incorrect Copyright Claim',
            'Copyright Dispute',
            'Content ID Claim',
            'Takedown Request',
            'Copyright Strike',
            'Other Copyright Issue',
        ],
        fields: [
            ['Claimant / Rights Holder', 'claimant'],
            ['Release / Track Name', 'release'],
            ['ISRC / UPC', 'identifier'],
            ['Claimed Content URL', 'content_url'],
            ['Evidence / Reference URL', 'evidence_url'],
        ],
    },

    youtube: {
        title: 'YouTube Operations',
        subtitle: 'YouTube Content ID, copyright, strikes and takedowns',
        category: 'copyright',
        subject: 'YouTube Operations Request',
        issueLabel: 'YouTube Issue Type',
        issues: [
            'Content ID Claim',
            'Copyright Strike',
            'Video Takedown',
            'Channel Issue',
            'Content ID Release',
            'Other YouTube Issue',
        ],
        fields: [
            ['YouTube Video / Channel URL', 'content_url'],
            ['Claimant / Channel', 'claimant'],
            ['Release / Track Name', 'release'],
            ['ISRC / UPC', 'identifier'],
            ['Evidence / Reference URL', 'evidence_url'],
        ],
    },

    spotify: {
        title: 'Spotify Operations',
        subtitle: 'Spotify artist, profile, release and rights-related matters',
        category: 'general',
        subject: 'Spotify Operations Request',
        issueLabel: 'Spotify Issue Type',
        issues: [
            'Artist Profile Issue',
            'Release / Metadata Issue',
            'Rights / Ownership Issue',
            'Artist Claim Issue',
            'Removal / Takedown Issue',
            'Other Spotify Issue',
        ],
        fields: [
            ['Spotify Artist / Release URL', 'content_url'],
            ['Artist Name', 'claimant'],
            ['Release / Track Name', 'release'],
            ['ISRC / UPC', 'identifier'],
            ['Evidence / Reference URL', 'evidence_url'],
        ],
    },

    dsp: {
        title: 'Other DSP Operations',
        subtitle: 'Platform-specific distribution, rights and removal matters',
        category: 'general',
        subject: 'DSP Operations Request',
        issueLabel: 'DSP Issue Type',
        issues: [
            'Release / Metadata Issue',
            'Rights / Ownership Issue',
            'Removal / Takedown',
            'Artist Profile Issue',
            'Delivery Issue',
            'Other DSP Issue',
        ],
        fields: [
            ['DSP / Platform Name', 'claimant'],
            ['Platform Release / Track URL', 'content_url'],
            ['Release / Track Name', 'release'],
            ['ISRC / UPC', 'identifier'],
            ['Evidence / Reference URL', 'evidence_url'],
        ],
    },

    rights: {
        title: 'Rights & Ownership',
        subtitle: 'Ownership, rights transfer and authorization matters',
        category: 'general',
        subject: 'Rights & Ownership Request',
        issueLabel: 'Rights Issue Type',
        issues: [
            'Ownership Verification',
            'Rights Transfer',
            'Rights Dispute',
            'Authorization Issue',
            'Catalog Ownership Issue',
            'Other Rights Issue',
        ],
        fields: [
            ['Rights Holder / Claimant', 'claimant'],
            ['Release / Track Name', 'release'],
            ['ISRC / UPC', 'identifier'],
            ['Agreement / Reference URL', 'evidence_url'],
        ],
    },

    request: {
        title: 'Legal Request',
        subtitle: 'Submit a general legal or operational request',
        category: 'general',
        subject: 'Legal Request',
        issueLabel: 'Request Type',
        issues: [
            'Legal Notice',
            'Contract / Agreement',
            'Rights Verification',
            'Takedown / Removal',
            'Dispute',
            'Other Legal Request',
        ],
        fields: [
            ['Related Person / Company', 'claimant'],
            ['Release / Track / Matter', 'release'],
            ['Reference URL', 'evidence_url'],
        ],
    },
};

export default function Create({
    role = 'artist',
    legalSection = null,
}) {
    const config = LEGAL_CONFIG[legalSection] || {
        title: 'New Support Ticket',
        subtitle: 'Describe the issue clearly',
        category: 'general',
        subject: '',
        issueLabel: 'Issue Type',
        issues: [],
        fields: [],
    };

    const {
        data,
        setData,
        post,
        processing,
        errors,
    } = useForm({
        subject: config.subject,
        category: config.category,
        priority: 'normal',
        message: '',
        attachments: [],
        legal_issue_type: '',
        legal_context: legalSection || '',
        claimant: '',
        release: '',
        identifier: '',
        content_url: '',
        evidence_url: '',
    });

    const submit = (event) => {
        event.preventDefault();

        const contextHeader = legalSection
            ? `[Legal Operations: ${config.title}]`
            : '';

        const structuredMessage = [
            contextHeader,
            data.legal_issue_type
                ? `Issue Type: ${data.legal_issue_type}`
                : '',
            data.claimant
                ? `Related Person / Company / Platform: ${data.claimant}`
                : '',
            data.release
                ? `Release / Track / Matter: ${data.release}`
                : '',
            data.identifier
                ? `ISRC / UPC: ${data.identifier}`
                : '',
            data.content_url
                ? `Content URL: ${data.content_url}`
                : '',
            data.evidence_url
                ? `Evidence / Reference URL: ${data.evidence_url}`
                : '',
            '',
            'Details:',
            data.message,
        ]
            .filter(Boolean)
            .join('\n');

        post('/v2/support', {
            forceFormData: true,
            transform: (formData) => ({
                ...formData,
                subject: legalSection
                    ? `${config.title} — ${data.subject || config.subject}`
                    : data.subject,
                message: structuredMessage,
            }),
        });
    };

    return (
        <PanelLayout
            role={role}
            title={config.title}
            subtitle={config.subtitle}
        >
            <Head title={config.title} />

            <form
                onSubmit={submit}
                className="mx-auto max-w-4xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
            >
                {legalSection && (
                    <div className="mb-6 rounded-xl border border-violet-200 bg-violet-50 p-4">
                        <div className="text-xs font-semibold uppercase tracking-wide text-violet-600">
                            Legal Operations
                        </div>

                        <div className="mt-1 text-base font-semibold text-violet-900">
                            {config.title}
                        </div>

                        <div className="mt-1 text-sm text-violet-700">
                            {config.subtitle}
                        </div>
                    </div>
                )}

                <div className="grid gap-5 md:grid-cols-2">
                    <label className="md:col-span-2">
                        <span className="text-sm font-semibold text-slate-700">
                            Subject
                        </span>

                        <input
                            type="text"
                            value={data.subject}
                            onChange={(e) =>
                                setData('subject', e.target.value)
                            }
                            className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-violet-500"
                        />

                        {errors.subject && (
                            <p className="mt-1 text-xs text-red-600">
                                {errors.subject}
                            </p>
                        )}
                    </label>

                    {legalSection && config.issues.length > 0 && (
                        <label>
                            <span className="text-sm font-semibold text-slate-700">
                                {config.issueLabel}
                            </span>

                            <select
                                value={data.legal_issue_type}
                                onChange={(e) =>
                                    setData(
                                        'legal_issue_type',
                                        e.target.value
                                    )
                                }
                                className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-violet-500"
                            >
                                <option value="">
                                    Select issue type
                                </option>

                                {config.issues.map((issue) => (
                                    <option key={issue} value={issue}>
                                        {issue}
                                    </option>
                                ))}
                            </select>
                        </label>
                    )}

                    {legalSection &&
                        config.fields.map(([label, name]) => (
                            <label key={name}>
                                <span className="text-sm font-semibold text-slate-700">
                                    {label}
                                </span>

                                <input
                                    type="text"
                                    value={data[name]}
                                    onChange={(e) =>
                                        setData(name, e.target.value)
                                    }
                                    className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-violet-500"
                                />
                            </label>
                        ))}

                    <label>
                        <span className="text-sm font-semibold text-slate-700">
                            Priority
                        </span>

                        <select
                            value={data.priority}
                            onChange={(e) =>
                                setData('priority', e.target.value)
                            }
                            className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-violet-500"
                        >
                            <option value="low">Low</option>
                            <option value="normal">Normal</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </label>

                    <label className="md:col-span-2">
                        <span className="text-sm font-semibold text-slate-700">
                            {legalSection
                                ? 'Detailed Description'
                                : 'Message'}
                        </span>

                        <textarea
                            rows={8}
                            value={data.message}
                            onChange={(e) =>
                                setData('message', e.target.value)
                            }
                            placeholder={
                                legalSection
                                    ? 'Explain the matter, what happened, and what action you need.'
                                    : 'Describe the issue clearly.'
                            }
                            className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-violet-500"
                        />

                        {errors.message && (
                            <p className="mt-1 text-xs text-red-600">
                                {errors.message}
                            </p>
                        )}
                    </label>

                    <label className="md:col-span-2">
                        <span className="text-sm font-semibold text-slate-700">
                            Attachments
                        </span>

                        <input
                            type="file"
                            multiple
                            onChange={(e) =>
                                setData(
                                    'attachments',
                                    Array.from(e.target.files || [])
                                )
                            }
                            className="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        />
                    </label>
                </div>

                <div className="mt-6 flex justify-end">
                    <button
                        type="submit"
                        disabled={processing}
                        className="rounded-xl bg-violet-600 px-6 py-3 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {processing
                            ? 'Submitting...'
                            : legalSection
                                ? 'Submit Legal Request'
                                : 'Submit Ticket'}
                    </button>
                </div>
            </form>
        </PanelLayout>
    );
}
