import {
    Head,
    Link,
    useForm,
} from '@inertiajs/react';
import { ArrowLeft, Info } from 'lucide-react';
import { useMemo } from 'react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';
import { FilePicker } from '@/V2/Shared/Support/supportUi';

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

/* W73 MIXXTUNE_LEGAL_OPS_V2: link fields must be real links */
const URL_FIELDS = ['content_url', 'evidence_url'];

const inputClass = 'mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-100';

function FieldError({ message }) {
    return message ? <p className="mt-1 text-xs font-medium text-red-600">{message}</p> : null;
}

export default function Create({
    role = 'artist',
    legalSection = null,
    categories = {},
    presetCategory = null,
    catalogue = [],
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

    const { data, setData, post, processing, errors, transform, setError, clearErrors } = useForm({
        subject: '',
        /* MIXXTUNE_SUPPORT_DESK_V1: topic chosen by the user (was always "general") */
        category: legalSection ? config.category : presetCategory || config.category,
        priority: 'normal',
        message: '',
        attachments: [],
        legal_section: legalSection || '',
        legal_issue_type: '',
        claimant: '',
        release: '',
        identifier: '',
        content_url: '',
        evidence_url: '',
    });

    /* "Title — Artist" labels for the catalogue picker */
    const options = useMemo(
        () => catalogue.map((r) => ({ ...r, label: `${r.title}${r.artist ? ` — ${r.artist}` : ''}` })),
        [catalogue],
    );
    const picked = options.find((o) => o.label === data.release) || null;

    const pickRelease = (value) => {
        const match = options.find((o) => o.label === value);
        setData((d) => ({
            ...d,
            release: value,
            identifier: match && !d.identifier ? match.upc || '' : d.identifier,
        }));
    };

    const errorFor = (name) => errors[name] || errors[`${name}.0`] || Object.entries(errors).find(([k]) => k.startsWith(`${name}.`))?.[1];

    const submit = (event) => {
        event.preventDefault();

        /* the case text is wrapped in extra lines, so check the user's own text here */
        clearErrors();
        if (legalSection && data.message.trim().length < 3) {
            setError('message', 'Please write what happened and what you need.');
            return;
        }

        const lines = [
            legalSection ? `[Legal Operations: ${config.title}]` : '',
            data.legal_issue_type ? `Issue Type: ${data.legal_issue_type}` : '',
            data.claimant ? `Related Person / Company / Platform: ${data.claimant}` : '',
            data.release ? `Release / Track / Matter: ${data.release}` : '',
            data.identifier ? `ISRC / UPC: ${data.identifier}` : '',
            data.content_url ? `Content URL: ${data.content_url}` : '',
            data.evidence_url ? `Evidence / Reference URL: ${data.evidence_url}` : '',
        ].filter(Boolean);

        transform((d) => ({
            ...d,
            subject: d.subject.trim() || (legalSection ? d.legal_issue_type || config.subject : ''),
            legal_section: legalSection || '',
            legal_issue_type: legalSection ? d.legal_issue_type : '',
            content_url: d.content_url.trim(),
            evidence_url: d.evidence_url.trim(),
            message: legalSection ? [...lines, '', 'Details:', d.message].join('\n') : d.message,
        }));

        post('/support', { forceFormData: true, preserveScroll: true });
    };

    return (
        <PanelLayout role={role} title={config.title} subtitle={config.subtitle}>
            <Head title={config.title} />

            <form onSubmit={submit} className="mx-auto max-w-4xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                {legalSection && (
                    <div className="mb-5 flex flex-col gap-3 rounded-xl border border-violet-200 bg-violet-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div className="text-xs font-semibold uppercase tracking-wide text-violet-600">Legal Operations · new case</div>
                            <div className="mt-0.5 text-base font-semibold text-violet-900">{config.title}</div>
                            <div className="mt-0.5 text-sm text-violet-700">{config.subtitle}</div>
                        </div>
                        <Link href="/legal-operations" className="inline-flex items-center gap-1 text-xs font-semibold text-violet-700 hover:text-violet-900">
                            <ArrowLeft size={14} /> All legal topics
                        </Link>
                    </div>
                )}

                <div className="grid gap-4 md:grid-cols-2">
                    {legalSection && config.issues.length > 0 && (
                        <label>
                            <span className="text-sm font-semibold text-slate-700">
                                {config.issueLabel} <span className="text-red-600">*</span>
                            </span>
                            <select value={data.legal_issue_type} onChange={(e) => setData('legal_issue_type', e.target.value)} className={inputClass}>
                                <option value="">Select issue type</option>
                                {config.issues.map((issue) => (
                                    <option key={issue} value={issue}>
                                        {issue}
                                    </option>
                                ))}
                            </select>
                            <FieldError message={errors.legal_issue_type} />
                        </label>
                    )}

                    <label className={legalSection ? '' : 'md:col-span-2'}>
                        <span className="text-sm font-semibold text-slate-700">
                            Subject {!legalSection && <span className="text-red-600">*</span>}
                        </span>
                        <input
                            type="text"
                            value={data.subject}
                            onChange={(e) => setData('subject', e.target.value)}
                            maxLength={200}
                            placeholder={legalSection ? 'Short title, e.g. "Claim on my video Tum Hi Ho"' : 'Short title of the problem'}
                            className={inputClass}
                        />
                        {legalSection && !errors.subject && <p className="mt-1 text-[11px] text-slate-400">Left empty? We use the issue type.</p>}
                        <FieldError message={errors.subject} />
                    </label>

                    {legalSection &&
                        config.fields.map(([label, name]) =>
                            name === 'release' ? (
                                <label key={name}>
                                    <span className="text-sm font-semibold text-slate-700">{label}</span>
                                    <input
                                        type="text"
                                        list={options.length ? 'own-catalogue' : undefined}
                                        value={data.release}
                                        onChange={(e) => pickRelease(e.target.value)}
                                        placeholder={options.length ? 'Type to pick one of your releases' : ''}
                                        className={inputClass}
                                    />
                                    {options.length > 0 && (
                                        <datalist id="own-catalogue">
                                            {options.map((o) => (
                                                <option key={o.id} value={o.label} />
                                            ))}
                                        </datalist>
                                    )}
                                    {picked && picked.tracks.length > 1 && (
                                        <select
                                            value=""
                                            onChange={(e) => e.target.value && setData('identifier', e.target.value)}
                                            className="mt-1.5 w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs text-slate-700"
                                        >
                                            <option value="">Pick a track to use its ISRC…</option>
                                            {picked.tracks
                                                .filter((t) => t.isrc)
                                                .map((t) => (
                                                    <option key={t.isrc} value={t.isrc}>
                                                        {t.title} · {t.isrc}
                                                    </option>
                                                ))}
                                        </select>
                                    )}
                                </label>
                            ) : (
                                <label key={name}>
                                    <span className="text-sm font-semibold text-slate-700">{label}</span>
                                    <input
                                        type={URL_FIELDS.includes(name) ? 'url' : 'text'}
                                        inputMode={URL_FIELDS.includes(name) ? 'url' : undefined}
                                        placeholder={URL_FIELDS.includes(name) ? 'https://' : ''}
                                        value={data[name]}
                                        onChange={(e) => setData(name, e.target.value)}
                                        className={inputClass}
                                    />
                                    <FieldError message={errors[name]} />
                                </label>
                            ),
                        )}

                    {!legalSection && Object.keys(categories).length > 0 && (
                        <label>
                            <span className="text-sm font-semibold text-slate-700">Topic</span>
                            <select value={data.category} onChange={(e) => setData('category', e.target.value)} className={inputClass}>
                                {Object.entries(categories).map(([key, label]) => (
                                    <option key={key} value={key}>
                                        {label}
                                    </option>
                                ))}
                            </select>
                        </label>
                    )}

                    <label>
                        <span className="text-sm font-semibold text-slate-700">Priority</span>
                        <select value={data.priority} onChange={(e) => setData('priority', e.target.value)} className={inputClass}>
                            <option value="low">Low</option>
                            <option value="normal">Normal</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent - money or a live release is at risk</option>
                        </select>
                    </label>

                    <label className="md:col-span-2">
                        <span className="text-sm font-semibold text-slate-700">
                            {legalSection ? 'What happened and what do you need?' : 'Message'} <span className="text-red-600">*</span>
                        </span>
                        <textarea
                            rows={7}
                            value={data.message}
                            onChange={(e) => setData('message', e.target.value)}
                            placeholder={legalSection ? 'Explain the matter, since when, and what action you want us to take.' : 'Describe the issue clearly.'}
                            className={inputClass}
                        />
                        <FieldError message={errors.message} />
                    </label>

                    <div className="md:col-span-2">
                        <span className="text-sm font-semibold text-slate-700">{legalSection ? 'Proof / documents' : 'Attachments'}</span>
                        <div className="mt-1.5">
                            <FilePicker files={data.attachments} onChange={(files) => setData('attachments', files)} error={errorFor('attachments')} />
                        </div>
                    </div>
                </div>

                {legalSection && (
                    <p className="mt-5 flex items-start gap-2 rounded-xl bg-slate-50 px-3.5 py-2.5 text-xs leading-5 text-slate-600">
                        <Info size={15} className="mt-0.5 shrink-0 text-slate-400" />
                        You get a case number at once. Our team replies inside the case and may ask for more proof; you can follow it under Legal Operations → Case History.
                    </p>
                )}

                <div className="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <Link href={legalSection ? '/legal-operations' : '/support'} className="inline-flex items-center justify-center rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Cancel
                    </Link>
                    <button type="submit" disabled={processing} className="rounded-xl bg-violet-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50">
                        {processing ? 'Submitting...' : legalSection ? 'Submit case' : 'Submit ticket'}
                    </button>
                </div>
            </form>
        </PanelLayout>
    );
}
