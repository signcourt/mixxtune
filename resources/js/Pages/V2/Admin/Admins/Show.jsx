import { useState } from "react";
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
    onToggle,
}) {
    const [query, setQuery] = useState("");
    const [open, setOpen] = useState(false);

    const selectedIds = new Set(
        (selected ?? []).map((id) => Number(id)),
    );

    const selectedItems = (items ?? []).filter((item) =>
        selectedIds.has(Number(item.id)),
    );

    const search = query.trim().toLowerCase();

    const suggestions =
        search.length >= 3
            ? (items ?? [])
                  .filter((item) => {
                      if (
                          selectedIds.has(
                              Number(item.id),
                          )
                      ) {
                          return false;
                      }

                      const name = String(
                          item[nameKey] ?? "",
                      ).toLowerCase();

                      const email = String(
                          item.email ?? "",
                      ).toLowerCase();

                      return (
                          name.includes(search) ||
                          email.includes(search)
                      );
                  })
                  .slice(0, 12)
            : [];

    const choose = (id) => {
        if (!selectedIds.has(Number(id))) {
            onToggle(id);
        }

        setQuery("");
        setOpen(true);
    };

    return (
        <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div className="mb-3 flex items-center justify-between gap-3">
                <div>
                    <h2 className="text-base font-semibold text-slate-900">
                        {title}
                    </h2>

                    <p className="mt-0.5 text-xs text-slate-500">
                        Search by name or email.
                    </p>
                </div>

                {selectedItems.length > 0 && (
                    <span className="rounded-full bg-violet-50 px-2.5 py-1 text-xs font-semibold text-violet-700">
                        {selectedItems.length} selected
                    </span>
                )}
            </div>

            <div className="relative">
                <div className="flex min-h-[46px] flex-wrap items-center gap-2 rounded-lg border border-slate-300 bg-white px-2.5 py-2 focus-within:border-violet-500 focus-within:ring-2 focus-within:ring-violet-100">
                    {selectedItems.map((item) => (
                        <span
                            key={item.id}
                            className="inline-flex items-center gap-1.5 rounded-md border border-violet-200 bg-violet-50 px-2.5 py-1.5 text-xs font-semibold text-violet-800"
                        >
                            <span className="max-w-[210px] truncate">
                                {item[nameKey]}
                            </span>

                            <button
                                type="button"
                                onClick={() =>
                                    onToggle(item.id)
                                }
                                className="flex h-4 w-4 items-center justify-center rounded-full text-sm leading-none text-violet-500 hover:bg-violet-200 hover:text-violet-900"
                                title="Remove"
                            >
                                ×
                            </button>
                        </span>
                    ))}

                    <input
                        type="text"
                        value={query}
                        autoComplete="off"
                        onFocus={() => setOpen(true)}
                        onChange={(event) => {
                            setQuery(event.target.value);
                            setOpen(true);
                        }}
                        onBlur={() => {
                            window.setTimeout(
                                () => setOpen(false),
                                180,
                            );
                        }}
                        placeholder={
                            selectedItems.length
                                ? "Search more..."
                                : "Type minimum 3 characters..."
                        }
                        className="min-w-[180px] flex-1 border-0 bg-transparent px-1 py-1.5 text-sm outline-none placeholder:text-slate-400 focus:ring-0"
                    />
                </div>

                {open &&
                    search.length > 0 &&
                    search.length < 3 && (
                        <div className="absolute left-0 right-0 z-50 mt-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-500 shadow-xl">
                            Type {3 - search.length} more character
                            {3 - search.length === 1 ? "" : "s"}.
                        </div>
                    )}

                {open && search.length >= 3 && (
                    <div className="absolute left-0 right-0 z-50 mt-1 max-h-64 overflow-y-auto rounded-lg border border-slate-200 bg-white p-1 shadow-xl">
                        {suggestions.length > 0 ? (
                            suggestions.map((item) => (
                                <button
                                    key={item.id}
                                    type="button"
                                    onMouseDown={(event) => {
                                        event.preventDefault();
                                        choose(item.id);
                                    }}
                                    className="flex w-full items-center justify-between gap-4 rounded-md px-3 py-2.5 text-left hover:bg-violet-50"
                                >
                                    <div className="min-w-0">
                                        <div className="truncate text-sm font-semibold text-slate-900">
                                            {item[nameKey]}
                                        </div>

                                        <div className="mt-0.5 truncate text-xs text-slate-500">
                                            {item.email ?? "No email"}
                                        </div>
                                    </div>

                                    <span className="text-xs font-semibold text-violet-600">
                                        Select
                                    </span>
                                </button>
                            ))
                        ) : (
                            <div className="px-3 py-4 text-center text-sm text-slate-500">
                                No matching records found.
                            </div>
                        )}
                    </div>
                )}
            </div>
        </section>
    );
}
