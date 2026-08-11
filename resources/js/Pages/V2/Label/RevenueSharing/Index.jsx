import {
    Head,
    router,
} from '@inertiajs/react';

import {
    useState,
} from 'react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

function ShareRow({
    item,
}) {
    const [
        percent,
        setPercent,
    ] = useState(
        Number(
            item.revenue_share_percent ?? 0
        )
    );

    const [
        showShare,
        setShowShare,
    ] = useState(
        Boolean(
            item.show_revenue_share
        )
    );

    const [
        processing,
        setProcessing,
    ] = useState(false);

    const safePercent = Math.min(
        100,
        Math.max(
            0,
            Number(percent) || 0
        )
    );

    const masterPercent =
        100 - safePercent;

    const save = () => {
        setProcessing(true);

        router.patch(
            `/v2/label/revenue-sharing/${item.type}/${item.id}`,
            {
                revenue_share_percent:
                    safePercent,

                show_revenue_share:
                    showShare,
            },
            {
                preserveScroll: true,

                onFinish: () =>
                    setProcessing(false),
            }
        );
    };

    const toggle = () => {
        if (!item.share_id) {
            return;
        }

        router.patch(
            `/v2/label/revenue-sharing/${item.share_id}/toggle`,
            {},
            {
                preserveScroll: true,
            }
        );
    };

    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
                <div className="min-w-0 xl:w-64">
                    <div className="flex items-center gap-2">
                        <h3 className="truncate text-base font-bold text-slate-900">
                            {item.name}
                        </h3>

                        <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold capitalize text-slate-600">
                            {item.type}
                        </span>
                    </div>

                    <p className="mt-1 text-xs capitalize text-slate-500">
                        {item.status ?? 'active'}
                    </p>

                    {!item.share_configured && (
                        <p className="mt-2 text-xs font-medium text-amber-600">
                            No share configured — master keeps 100%
                        </p>
                    )}
                </div>

                <div className="grid flex-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Child Share
                        </label>

                        <div className="relative">
                            <input
                                type="number"
                                min="0"
                                max="100"
                                step="0.01"
                                value={percent}
                                onChange={(event) =>
                                    setPercent(
                                        event.target.value
                                    )
                                }
                                className="w-full rounded-xl border border-slate-300 px-4 py-3 pr-10 text-sm font-semibold outline-none focus:border-violet-500"
                            />

                            <span className="absolute right-4 top-3 text-sm font-semibold text-slate-400">
                                %
                            </span>
                        </div>
                    </div>

                    <div>
                        <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Master Keeps
                        </label>

                        <div className="rounded-xl bg-violet-50 px-4 py-3 text-sm font-bold text-violet-700">
                            {masterPercent.toFixed(2)}%
                        </div>
                    </div>

                    <div>
                        <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Percentage Visibility
                        </label>

                        <button
                            type="button"
                            onClick={() =>
                                setShowShare(
                                    (current) =>
                                        !current
                                )
                            }
                            className={[
                                'w-full rounded-xl border px-4 py-3 text-sm font-semibold transition',
                                showShare
                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                    : 'border-slate-300 bg-slate-50 text-slate-600',
                            ].join(' ')}
                        >
                            {showShare
                                ? 'Visible to beneficiary'
                                : 'Hidden from beneficiary'}
                        </button>
                    </div>

                    <div>
                        <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Split Status
                        </label>

                        <div
                            className={[
                                'rounded-xl px-4 py-3 text-center text-sm font-bold',
                                item.is_active
                                    ? 'bg-emerald-50 text-emerald-700'
                                    : 'bg-slate-100 text-slate-600',
                            ].join(' ')}
                        >
                            {item.is_active
                                ? 'Active'
                                : 'Inactive'}
                        </div>
                    </div>
                </div>

                <div className="flex gap-2 xl:w-56 xl:justify-end">
                    <button
                        type="button"
                        disabled={processing}
                        onClick={save}
                        className="rounded-xl bg-violet-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {processing
                            ? 'Saving...'
                            : 'Save Share'}
                    </button>

                    {item.share_id && (
                        <button
                            type="button"
                            onClick={toggle}
                            className="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            {item.is_active
                                ? 'Disable'
                                : 'Enable'}
                        </button>
                    )}
                </div>
            </div>

            <div className="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                ₹100 revenue example:
                {' '}
                <strong className="text-slate-900">
                    ₹{safePercent.toFixed(2)}
                </strong>
                {' '}
                goes to {item.name} and
                {' '}
                <strong className="text-violet-700">
                    ₹{masterPercent.toFixed(2)}
                </strong>
                {' '}
                remains with the master label.
            </div>
        </div>
    );
}

const revenueMoney = (
    value,
    currency = 'INR'
) =>
    new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: currency || 'INR',
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0));

const revenueNumber = (value) =>
    Number(value ?? 0).toLocaleString(
        'en-IN'
    );

export default function Index({
    master,
    beneficiaries = [],
    revenueReport = {
        summary: {},
        beneficiaries: [],
        rows: [],
        display_limit: 100,
    },
}) {
    const labels = beneficiaries.filter(
        (item) =>
            item.type === 'label'
    );

    const artists = beneficiaries.filter(
        (item) =>
            item.type === 'artist'
    );

    return (
        <PanelLayout
            role="label"
            title="Revenue Sharing"
            subtitle={`${master.name} — manage direct beneficiary shares`}
        >
            <Head title="Revenue Sharing" />

            <div className="space-y-6">
                <div className="grid gap-4 md:grid-cols-3">
                    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Master Label
                        </p>

                        <p className="mt-2 text-xl font-bold text-slate-900">
                            {master.name}
                        </p>
                    </div>

                    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Direct Sub-Labels
                        </p>

                        <p className="mt-2 text-3xl font-bold text-violet-700">
                            {labels.length}
                        </p>
                    </div>

                    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Direct Artists
                        </p>

                        <p className="mt-2 text-3xl font-bold text-violet-700">
                            {artists.length}
                        </p>
                    </div>
                </div>

                <div className="rounded-2xl border border-violet-200 bg-violet-50 p-5">
                    <h2 className="font-bold text-violet-900">
                        Master Revenue Rule
                    </h2>

                    <p className="mt-2 text-sm leading-6 text-violet-800">
                        All catalogue revenue belongs to the master first.
                        A configured percentage is credited to the direct
                        Sub-Label or Artist. The remaining percentage stays
                        with the master. Sub-Labels and Artists cannot create
                        another hierarchy.
                    </p>
                </div>

                  <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                      <div className="flex flex-col gap-4 border-b border-slate-200 px-5 py-5 lg:flex-row lg:items-center lg:justify-between">
                          <div>
                              <h2 className="text-lg font-bold text-slate-900">
                                  Revenue Beneficiary Breakdown
                              </h2>

                              <p className="mt-1 text-sm text-slate-500">
                                  See exactly which tracks generated revenue, the applied share and the amount retained by the master.
                              </p>
                          </div>

                          <a
                              href="/v2/label/revenue-sharing/export"
                              className="inline-flex w-fit items-center justify-center rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-violet-700"
                          >
                              Download Full Report
                          </a>
                      </div>

                      <div className="grid gap-3 border-b border-slate-200 bg-slate-50/70 p-4 sm:grid-cols-2 xl:grid-cols-4">
                          <div className="rounded-xl border border-slate-200 bg-white p-4">
                              <p className="text-xs font-bold uppercase tracking-wide text-slate-500">
                                  Managed Revenue
                              </p>
                              <p className="mt-2 text-xl font-black text-slate-950">
                                  {revenueMoney(
                                      revenueReport.summary?.managed_revenue,
                                      master.currency
                                  )}
                              </p>
                          </div>

                          <div className="rounded-xl border border-slate-200 bg-white p-4">
                              <p className="text-xs font-bold uppercase tracking-wide text-slate-500">
                                  Beneficiary Payable
                              </p>
                              <p className="mt-2 text-xl font-black text-emerald-700">
                                  {revenueMoney(
                                      revenueReport.summary?.beneficiary_payable,
                                      master.currency
                                  )}
                              </p>
                          </div>

                          <div className="rounded-xl border border-slate-200 bg-white p-4">
                              <p className="text-xs font-bold uppercase tracking-wide text-slate-500">
                                  Master Retained
                              </p>
                              <p className="mt-2 text-xl font-black text-violet-700">
                                  {revenueMoney(
                                      revenueReport.summary?.master_retained,
                                      master.currency
                                  )}
                              </p>
                          </div>

                          <div className="rounded-xl border border-slate-200 bg-white p-4">
                              <p className="text-xs font-bold uppercase tracking-wide text-slate-500">
                                  Streams
                              </p>
                              <p className="mt-2 text-xl font-black text-slate-950">
                                  {revenueNumber(
                                      revenueReport.summary?.streams
                                  )}
                              </p>
                          </div>
                      </div>

                      {revenueReport.beneficiaries?.length > 0 && (
                          <div className="overflow-x-auto border-b border-slate-200">
                              <table className="min-w-full divide-y divide-slate-200">
                                  <thead className="bg-slate-50">
                                      <tr>
                                          {[
                                              'Type',
                                              'Beneficiary',
                                              'Tracks',
                                              'Managed',
                                              'Share',
                                              'Beneficiary Payable',
                                              'Master Retained',
                                              'Download',
                                          ].map((heading) => (
                                              <th
                                                  key={heading}
                                                  className="whitespace-nowrap px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500"
                                              >
                                                  {heading}
                                              </th>
                                          ))}
                                      </tr>
                                  </thead>

                                  <tbody className="divide-y divide-slate-100">
                                      {revenueReport.beneficiaries.map(
                                          (item) => (
                                              <tr
                                                  key={`${item.type}-${item.id}`}
                                                  className="hover:bg-slate-50"
                                              >
                                                  <td className="whitespace-nowrap px-4 py-3 text-sm">
                                                      <span className="rounded-full bg-violet-50 px-2.5 py-1 text-xs font-bold text-violet-700">
                                                          {item.type === 'artist'
                                                              ? 'Artist'
                                                              : 'Sub-Label'}
                                                      </span>
                                                  </td>

                                                  <td className="whitespace-nowrap px-4 py-3 text-sm font-bold text-slate-900">
                                                      {item.name}
                                                  </td>

                                                  <td className="whitespace-nowrap px-4 py-3 text-sm text-slate-700">
                                                      {revenueNumber(
                                                          item.track_count
                                                      )}
                                                  </td>

                                                  <td className="whitespace-nowrap px-4 py-3 text-sm text-slate-700">
                                                      {revenueMoney(
                                                          item.managed_revenue,
                                                          master.currency
                                                      )}
                                                  </td>

                                                  <td className="whitespace-nowrap px-4 py-3 text-sm font-bold text-slate-700">
                                                      {Number(
                                                          item.share_percent ?? 0
                                                      ).toFixed(2)}
                                                      %
                                                  </td>

                                                  <td className="whitespace-nowrap px-4 py-3 text-sm font-bold text-emerald-700">
                                                      {revenueMoney(
                                                          item.beneficiary_payable,
                                                          master.currency
                                                      )}
                                                  </td>

                                                  <td className="whitespace-nowrap px-4 py-3 text-sm font-bold text-violet-700">
                                                      {revenueMoney(
                                                          item.master_retained,
                                                          master.currency
                                                      )}
                                                  </td>

                                                  <td className="whitespace-nowrap px-4 py-3">
                                                      <a
                                                          href={`/v2/label/revenue-sharing/${item.type}/${item.id}/export`}
                                                          className="text-sm font-bold text-violet-700 hover:text-violet-900"
                                                      >
                                                          Download CSV
                                                      </a>
                                                  </td>
                                              </tr>
                                          )
                                      )}
                                  </tbody>
                              </table>
                          </div>
                      )}

                      <div className="border-b border-slate-200 px-5 py-4">
                          <h3 className="font-bold text-slate-900">
                              Track-Level Allocation
                          </h3>

                          <p className="mt-1 text-xs text-slate-500">
                              Latest {revenueReport.display_limit ?? 100} eligible rows are shown here. Full CSV contains all eligible rows.
                          </p>
                      </div>

                      <div className="overflow-x-auto">
                          <table className="min-w-full divide-y divide-slate-200">
                              <thead className="bg-slate-50">
                                  <tr>
                                      {[
                                          'Month',
                                          'Beneficiary',
                                          'Track',
                                          'ISRC',
                                          'Platform',
                                          'Streams',
                                          'Managed',
                                          'Share',
                                          'Payable',
                                          'Master Retained',
                                      ].map((heading) => (
                                          <th
                                              key={heading}
                                              className="whitespace-nowrap px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500"
                                          >
                                              {heading}
                                          </th>
                                      ))}
                                  </tr>
                              </thead>

                              <tbody className="divide-y divide-slate-100">
                                  {revenueReport.rows?.length ? (
                                      revenueReport.rows.map(
                                          (row) => (
                                              <tr
                                                  key={row.id}
                                                  className="hover:bg-slate-50"
                                              >
                                                  <td className="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                                                      {row.sale_month || '—'}
                                                  </td>

                                                  <td className="whitespace-nowrap px-4 py-3">
                                                      <div className="text-sm font-bold text-slate-900">
                                                          {row.beneficiary}
                                                      </div>

                                                      <div className="text-xs text-slate-500">
                                                          {row.type === 'artist'
                                                              ? 'Artist'
                                                              : 'Sub-Label'}
                                                      </div>
                                                  </td>

                                                  <td className="min-w-[220px] px-4 py-3">
                                                      <div className="text-sm font-bold text-slate-900">
                                                          {row.track_title}
                                                      </div>

                                                      <div className="text-xs text-slate-500">
                                                          {row.track_artist || '—'}
                                                      </div>
                                                  </td>

                                                  <td className="whitespace-nowrap px-4 py-3 font-mono text-xs text-slate-600">
                                                      {row.isrc || '—'}
                                                  </td>

                                                  <td className="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                                                      {row.platform || '—'}
                                                  </td>

                                                  <td className="whitespace-nowrap px-4 py-3 text-sm text-slate-700">
                                                      {revenueNumber(
                                                          row.streams
                                                      )}
                                                  </td>

                                                  <td className="whitespace-nowrap px-4 py-3 text-sm text-slate-700">
                                                      {revenueMoney(
                                                          row.managed_revenue,
                                                          row.currency
                                                      )}
                                                  </td>

                                                  <td className="whitespace-nowrap px-4 py-3 text-sm font-bold text-slate-700">
                                                      {Number(
                                                          row.share_percent ?? 0
                                                      ).toFixed(2)}
                                                      %
                                                  </td>

                                                  <td className="whitespace-nowrap px-4 py-3 text-sm font-bold text-emerald-700">
                                                      {revenueMoney(
                                                          row.beneficiary_payable,
                                                          row.currency
                                                      )}
                                                  </td>

                                                  <td className="whitespace-nowrap px-4 py-3 text-sm font-bold text-violet-700">
                                                      {revenueMoney(
                                                          row.master_retained,
                                                          row.currency
                                                      )}
                                                  </td>
                                              </tr>
                                          )
                                      )
                                  ) : (
                                      <tr>
                                          <td
                                              colSpan={10}
                                              className="px-5 py-10 text-center text-sm text-slate-500"
                                          >
                                              No eligible beneficiary revenue rows available yet.
                                          </td>
                                      </tr>
                                  )}
                              </tbody>
                          </table>
                      </div>
                  </section>


                <section>
                    <div className="mb-4">
                        <h2 className="text-lg font-bold text-slate-900">
                            Sub-Labels
                        </h2>

                        <p className="text-sm text-slate-500">
                            Revenue shares for direct child labels.
                        </p>
                    </div>

                    <div className="space-y-3">
                        {labels.length ? (
                            labels.map(
                                (item) => (
                                    <ShareRow
                                        key={`label-${item.id}`}
                                        item={item}
                                    />
                                )
                            )
                        ) : (
                            <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">
                                No direct Sub-Labels found.
                            </div>
                        )}
                    </div>
                </section>

                <section>
                    <div className="mb-4">
                        <h2 className="text-lg font-bold text-slate-900">
                            Artists
                        </h2>

                        <p className="text-sm text-slate-500">
                            Revenue shares for artists directly owned by this master.
                        </p>
                    </div>

                    <div className="space-y-3">
                        {artists.length ? (
                            artists.map(
                                (item) => (
                                    <ShareRow
                                        key={`artist-${item.id}`}
                                        item={item}
                                    />
                                )
                            )
                        ) : (
                            <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">
                                No direct Artists found.
                            </div>
                        )}
                    </div>
                </section>
            </div>
        </PanelLayout>
    );
}
