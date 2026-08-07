import {
    Head,
    useForm,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Show({
    role = 'super_admin',
    admin,
    artists = [],
    labels = [],
    assignedArtistIds = [],
    assignedLabelIds = [],
}) {
    const {
        data,
        setData,
        patch,
        processing,
    } = useForm({
        artist_ids:
            assignedArtistIds.map(Number),

        label_ids:
            assignedLabelIds.map(Number),

        assignment_role: 'manager',

        can_view: true,
        can_edit: true,
        can_manage_releases: true,
        can_manage_team: false,
        can_manage_splits: false,
    });

    const toggle = (
        field,
        id
    ) => {
        const numberId = Number(id);

        setData(
            field,
            data[field].includes(numberId)
                ? data[field].filter(
                      (item) =>
                          item !== numberId
                  )
                : [
                      ...data[field],
                      numberId,
                  ]
        );
    };

    const submit = (event) => {
        event.preventDefault();

        patch(
            `/v2/admin/admins/${admin.id}/assignments`,
            {
                preserveScroll: true,
            }
        );
    };

    return (
        <PanelLayout
            role={role}
            title="Admin Assignments"
            subtitle={`${admin.name} • ${admin.email}`}
        >
            <Head title="Admin Assignments" />

            <form
                onSubmit={submit}
                className="space-y-6"
            >
                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 className="text-lg font-semibold text-slate-900">
                        Assignment Permissions
                    </h2>

                    <div className="mt-4 grid gap-4 md:grid-cols-3">
                        <select
                            value={
                                data.assignment_role
                            }
                            onChange={(event) =>
                                setData(
                                    'assignment_role',
                                    event.target.value
                                )
                            }
                            className="rounded-xl border border-slate-300 px-4 py-3"
                        >
                            <option value="manager">
                                Manager
                            </option>

                            <option value="reviewer">
                                Reviewer
                            </option>

                            <option value="finance">
                                Finance
                            </option>

                            <option value="support">
                                Support
                            </option>

                            <option value="custom">
                                Custom
                            </option>
                        </select>

                        {[
                            [
                                'can_view',
                                'Can View',
                            ],
                            [
                                'can_edit',
                                'Can Edit',
                            ],
                            [
                                'can_manage_releases',
                                'Manage Releases',
                            ],
                            [
                                'can_manage_team',
                                'Manage Team',
                            ],
                            [
                                'can_manage_splits',
                                'Manage Splits',
                            ],
                        ].map(
                            ([field, label]) => (
                                <label
                                    key={field}
                                    className="flex items-center gap-3 rounded-xl border border-slate-200 p-3"
                                >
                                    <input
                                        type="checkbox"
                                        checked={
                                            data[field]
                                        }
                                        onChange={(
                                            event
                                        ) =>
                                            setData(
                                                field,
                                                event
                                                    .target
                                                    .checked
                                            )
                                        }
                                    />

                                    <span className="text-sm font-semibold text-slate-700">
                                        {label}
                                    </span>
                                </label>
                            )
                        )}
                    </div>
                </section>

                <div className="grid gap-6 xl:grid-cols-2">
                    <SelectionSection
                        title="Assign Artists"
                        items={artists}
                        selected={
                            data.artist_ids
                        }
                        nameKey="stage_name"
                        statusKey="account_status"
                        onToggle={(id) =>
                            toggle(
                                'artist_ids',
                                id
                            )
                        }
                    />

                    <SelectionSection
                        title="Assign Labels"
                        items={labels}
                        selected={
                            data.label_ids
                        }
                        nameKey="name"
                        statusKey="status"
                        onToggle={(id) =>
                            toggle(
                                'label_ids',
                                id
                            )
                        }
                    />
                </div>

                <div className="sticky bottom-4 flex justify-end">
                    <button
                        type="submit"
                        disabled={processing}
                        className="rounded-xl bg-violet-600 px-7 py-3 text-sm font-semibold text-white shadow-lg disabled:opacity-50"
                    >
                        {processing
                            ? 'Saving...'
                            : 'Save Assignments'}
                    </button>
                </div>
            </form>
        </PanelLayout>
    );
}

function SelectionSection({
    title,
    items,
    selected,
    nameKey,
    statusKey,
    onToggle,
}) {
    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex items-center justify-between">
                <h2 className="text-lg font-semibold text-slate-900">
                    {title}
                </h2>

                <span className="rounded-full bg-violet-100 px-3 py-1 text-xs font-semibold text-violet-700">
                    {selected.length} selected
                </span>
            </div>

            <div className="mt-4 max-h-[520px] space-y-2 overflow-y-auto">
                {items.map((item) => {
                    const checked =
                        selected.includes(
                            Number(item.id)
                        );

                    return (
                        <label
                            key={item.id}
                            className={[
                                'flex cursor-pointer items-center gap-3 rounded-xl border p-4',
                                checked
                                    ? 'border-violet-400 bg-violet-50'
                                    : 'border-slate-200 bg-white',
                            ].join(' ')}
                        >
                            <input
                                type="checkbox"
                                checked={checked}
                                onChange={() =>
                                    onToggle(item.id)
                                }
                            />

                            <div className="min-w-0 flex-1">
                                <div className="truncate font-semibold text-slate-900">
                                    {item[nameKey]}
                                </div>

                                <div className="truncate text-xs text-slate-500">
                                    {item.email ||
                                        'No email'}
                                </div>
                            </div>

                            <span className="text-xs capitalize text-slate-500">
                                {item[statusKey]}
                            </span>
                        </label>
                    );
                })}
            </div>
        </section>
    );
}
