#!/usr/bin/env bash

set -Eeuo pipefail

cd /var/www/backstage-distribution

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="v2/backups/v2-dsp-stores/$STAMP"

CONTROLLER="app/Http/Controllers/V2/Admin/DistributionStoreController.php"
PAGE="resources/js/Pages/V2/Admin/Stores/Index.jsx"
ROUTES="routes/web.php"
PANEL_ROUTES="resources/js/V2/Shared/Config/panelRoutes.js"

mkdir -p \
    "$BACKUP" \
    "$(dirname "$CONTROLLER")" \
    "$(dirname "$PAGE")"

for FILE in \
    "$CONTROLLER" \
    "$PAGE" \
    "$ROUTES" \
    "$PANEL_ROUTES"
do
    if [ -f "$FILE" ]; then
        mkdir -p "$BACKUP/$(dirname "$FILE")"
        cp -a "$FILE" "$BACKUP/$FILE"
    fi
done

echo "=================================================="
echo "INSTALLING V2 DSP STORES MODULE"
echo "=================================================="

echo "[1/6] Creating V2 DSP Stores controller..."

cat > "$CONTROLLER" <<'PHP'
<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\DistributionStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DistributionStoreController extends Controller
{
    private function authorizeAdmin(
        Request $request
    ): void {
        abort_unless(
            in_array(
                $request->user()?->role,
                ['admin', 'super_admin'],
                true
            ),
            403,
            'Admin access required.'
        );
    }

    public function index(
        Request $request
    ): Response {
        $this->authorizeAdmin($request);

        $stores = DistributionStore::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render(
            'V2/Admin/Stores/Index',
            [
                'role' =>
                    $request->user()->role,

                'stores' =>
                    $stores,

                'stats' => [
                    'total' =>
                        $stores->count(),

                    'active' =>
                        $stores
                            ->where(
                                'is_active',
                                true
                            )
                            ->count(),

                    'inactive' =>
                        $stores
                            ->where(
                                'is_active',
                                false
                            )
                            ->count(),

                    'default_selected' =>
                        $stores
                            ->where(
                                'default_selected',
                                true
                            )
                            ->count(),
                ],
            ]
        );
    }

    public function store(
        Request $request
    ): RedirectResponse {
        $this->authorizeAdmin($request);

        $validated =
            $this->validateStore($request);

        $logoPath = null;

        if ($request->hasFile('logo')) {
            $logoPath =
                $request
                    ->file('logo')
                    ->store(
                        'distribution-stores',
                        'public'
                    );
        }

        $nextOrder =
            (
                DistributionStore::query()
                    ->max('sort_order')
                ?? 0
            ) + 1;

        DistributionStore::create([
            'name' =>
                $validated['name'],

            'slug' =>
                $validated['slug']
                ?: Str::slug(
                    $validated['name']
                ),

            'logo_path' =>
                $logoPath,

            'is_active' =>
                $validated['is_active'],

            'default_selected' =>
                $validated[
                    'default_selected'
                ],

            'sort_order' =>
                $validated['sort_order']
                ?? $nextOrder,
        ]);

        return back()->with(
            'success',
            'DSP added successfully.'
        );
    }

    public function update(
        Request $request,
        DistributionStore $distributionStore
    ): RedirectResponse {
        $this->authorizeAdmin($request);

        $validated =
            $this->validateStore(
                $request,
                $distributionStore
            );

        if ($request->hasFile('logo')) {
            if (
                $distributionStore->logo_path
            ) {
                Storage::disk('public')
                    ->delete(
                        $distributionStore
                            ->logo_path
                    );
            }

            $validated['logo_path'] =
                $request
                    ->file('logo')
                    ->store(
                        'distribution-stores',
                        'public'
                    );
        }

        unset($validated['logo']);

        $validated['slug'] =
            $validated['slug']
            ?: Str::slug(
                $validated['name']
            );

        $distributionStore->update(
            $validated
        );

        return back()->with(
            'success',
            'DSP updated successfully.'
        );
    }

    public function toggle(
        Request $request,
        DistributionStore $distributionStore
    ): RedirectResponse {
        $this->authorizeAdmin($request);

        $distributionStore->update([
            'is_active' =>
                !$distributionStore
                    ->is_active,
        ]);

        return back()->with(
            'success',
            $distributionStore->is_active
                ? 'DSP activated successfully.'
                : 'DSP deactivated successfully.'
        );
    }

    public function destroy(
        Request $request,
        DistributionStore $distributionStore
    ): RedirectResponse {
        $this->authorizeAdmin($request);

        if (
            $distributionStore->logo_path
        ) {
            Storage::disk('public')
                ->delete(
                    $distributionStore
                        ->logo_path
                );
        }

        $distributionStore->delete();

        return back()->with(
            'success',
            'DSP deleted successfully.'
        );
    }

    private function validateStore(
        Request $request,
        ?DistributionStore $store = null
    ): array {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique(
                    'distribution_stores',
                    'name'
                )->ignore(
                    $store?->id
                ),
            ],

            'slug' => [
                'nullable',
                'string',
                'max:150',
                Rule::unique(
                    'distribution_stores',
                    'slug'
                )->ignore(
                    $store?->id
                ),
            ],

            'logo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp,svg',
                'max:5120',
            ],

            'is_active' => [
                'required',
                'boolean',
            ],

            'default_selected' => [
                'required',
                'boolean',
            ],

            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],
        ]);
    }
}
PHP

echo "[2/6] Creating V2 DSP Stores page..."

cat > "$PAGE" <<'JSX'
import {
    Head,
    router,
    useForm,
} from '@inertiajs/react';
import {
    useMemo,
    useState,
} from 'react';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

const emptyStore = {
    name: '',
    slug: '',
    logo: null,
    is_active: true,
    default_selected: true,
    sort_order: 0,
};

const inputClass =
    'w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-100';

export default function Index({
    role = 'super_admin',
    stores = [],
    stats = {},
}) {
    const [search, setSearch] =
        useState('');

    const [
        editingStore,
        setEditingStore,
    ] = useState(null);

    const [
        formVisible,
        setFormVisible,
    ] = useState(false);

    const {
        data,
        setData,
        post,
        processing,
        errors,
        reset,
        clearErrors,
    } = useForm(emptyStore);

    const filteredStores =
        useMemo(() => {
            const query =
                search
                    .trim()
                    .toLowerCase();

            if (!query) {
                return stores;
            }

            return stores.filter(
                (store) =>
                    `${store.name} ${store.slug}`
                        .toLowerCase()
                        .includes(query)
            );
        }, [stores, search]);

    const openAdd = () => {
        clearErrors();
        setEditingStore(null);

        setData({
            ...emptyStore,
            sort_order:
                stores.length + 1,
        });

        setFormVisible(true);
    };

    const openEdit = (store) => {
        clearErrors();

        setEditingStore(store);

        setData({
            name: store.name ?? '',
            slug: store.slug ?? '',
            logo: null,
            is_active: Boolean(
                store.is_active
            ),
            default_selected: Boolean(
                store.default_selected
            ),
            sort_order: Number(
                store.sort_order ?? 0
            ),
        });

        setFormVisible(true);
    };

    const closeForm = () => {
        clearErrors();
        reset();
        setEditingStore(null);
        setFormVisible(false);
    };

    const submit = (event) => {
        event.preventDefault();

        const url = editingStore
            ? `/v2/admin/stores/${editingStore.id}`
            : '/v2/admin/stores';

        post(url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: closeForm,
        });
    };

    const toggleStore = (store) => {
        router.post(
            `/v2/admin/stores/${store.id}/toggle`,
            {},
            {
                preserveScroll: true,
            }
        );
    };

    const deleteStore = (store) => {
        if (
            !window.confirm(
                `Delete "${store.name}"?`
            )
        ) {
            return;
        }

        router.delete(
            `/v2/admin/stores/${store.id}`,
            {
                preserveScroll: true,
            }
        );
    };

    return (
        <PanelLayout
            role={role}
            title="DSP Stores"
            subtitle="Manage distribution platforms"
        >
            <Head title="DSP Stores" />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">
                            DSP Stores
                        </h1>

                        <p className="mt-1 text-sm text-slate-500">
                            Add and manage digital distribution platforms.
                        </p>
                    </div>

                    <button
                        type="button"
                        onClick={openAdd}
                        className="rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white hover:bg-violet-700"
                    >
                        + Add DSP
                    </button>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Stat
                        label="Total DSPs"
                        value={
                            stats.total ?? 0
                        }
                    />

                    <Stat
                        label="Active"
                        value={
                            stats.active ?? 0
                        }
                    />

                    <Stat
                        label="Inactive"
                        value={
                            stats.inactive ?? 0
                        }
                    />

                    <Stat
                        label="Auto Selected"
                        value={
                            stats.default_selected ??
                            0
                        }
                    />
                </div>

                {formVisible && (
                    <form
                        onSubmit={submit}
                        className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                    >
                        <div className="flex items-center justify-between">
                            <h2 className="text-lg font-bold text-slate-900">
                                {editingStore
                                    ? 'Edit DSP'
                                    : 'Add DSP'}
                            </h2>

                            <button
                                type="button"
                                onClick={
                                    closeForm
                                }
                                className="text-sm font-semibold text-slate-500"
                            >
                                Close
                            </button>
                        </div>

                        <div className="mt-6 grid gap-5 md:grid-cols-2">
                            <Field
                                label="DSP Name"
                                error={
                                    errors.name
                                }
                            >
                                <input
                                    value={
                                        data.name
                                    }
                                    onChange={(
                                        event
                                    ) =>
                                        setData(
                                            'name',
                                            event
                                                .target
                                                .value
                                        )
                                    }
                                    className={
                                        inputClass
                                    }
                                    placeholder="Spotify"
                                />
                            </Field>

                            <Field
                                label="Slug"
                                error={
                                    errors.slug
                                }
                            >
                                <input
                                    value={
                                        data.slug
                                    }
                                    onChange={(
                                        event
                                    ) =>
                                        setData(
                                            'slug',
                                            event
                                                .target
                                                .value
                                        )
                                    }
                                    className={
                                        inputClass
                                    }
                                    placeholder="spotify"
                                />
                            </Field>

                            <Field
                                label="DSP Logo"
                                error={
                                    errors.logo
                                }
                            >
                                <input
                                    type="file"
                                    accept="image/*,.svg"
                                    className={
                                        inputClass
                                    }
                                    onChange={(
                                        event
                                    ) =>
                                        setData(
                                            'logo',
                                            event
                                                .target
                                                .files?.[0] ??
                                                null
                                        )
                                    }
                                />
                            </Field>

                            <Field
                                label="Display Order"
                                error={
                                    errors.sort_order
                                }
                            >
                                <input
                                    type="number"
                                    min="0"
                                    value={
                                        data.sort_order
                                    }
                                    onChange={(
                                        event
                                    ) =>
                                        setData(
                                            'sort_order',
                                            Number(
                                                event
                                                    .target
                                                    .value
                                            )
                                        )
                                    }
                                    className={
                                        inputClass
                                    }
                                />
                            </Field>
                        </div>

                        <div className="mt-5 grid gap-4 sm:grid-cols-2">
                            <Checkbox
                                label="Active DSP"
                                checked={
                                    data.is_active
                                }
                                onChange={(
                                    value
                                ) =>
                                    setData(
                                        'is_active',
                                        value
                                    )
                                }
                            />

                            <Checkbox
                                label="Default Selected"
                                checked={
                                    data.default_selected
                                }
                                onChange={(
                                    value
                                ) =>
                                    setData(
                                        'default_selected',
                                        value
                                    )
                                }
                            />
                        </div>

                        <div className="mt-6 flex justify-end">
                            <button
                                type="submit"
                                disabled={
                                    processing
                                }
                                className="rounded-xl bg-slate-900 px-6 py-3 text-sm font-semibold text-white disabled:opacity-40"
                            >
                                {processing
                                    ? 'Saving...'
                                    : editingStore
                                      ? 'Update DSP'
                                      : 'Add DSP'}
                            </button>
                        </div>
                    </form>
                )}

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex flex-col gap-4 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 className="font-bold text-slate-900">
                                Distribution Stores
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                {
                                    filteredStores.length
                                }{' '}
                                stores shown
                            </p>
                        </div>

                        <input
                            type="search"
                            value={search}
                            onChange={(
                                event
                            ) =>
                                setSearch(
                                    event.target
                                        .value
                                )
                            }
                            placeholder="Search DSP..."
                            className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm sm:max-w-sm"
                        />
                    </div>

                    {filteredStores.length ===
                    0 ? (
                        <div className="px-5 py-16 text-center text-sm text-slate-500">
                            No DSP stores found.
                        </div>
                    ) : (
                        <div className="divide-y divide-slate-100">
                            {filteredStores.map(
                                (store) => (
                                    <div
                                        key={
                                            store.id
                                        }
                                        className="grid gap-4 px-5 py-4 lg:grid-cols-[60px_minmax(180px,1fr)_100px_120px_120px_auto] lg:items-center"
                                    >
                                        <div className="flex h-11 w-11 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                                            {store.logo_path ? (
                                                <img
                                                    src={`/storage/${store.logo_path}`}
                                                    alt={
                                                        store.name
                                                    }
                                                    className="h-full w-full object-contain p-2"
                                                />
                                            ) : (
                                                <span className="font-bold text-slate-500">
                                                    {store.name
                                                        ?.slice(
                                                            0,
                                                            1
                                                        )
                                                        .toUpperCase()}
                                                </span>
                                            )}
                                        </div>

                                        <div>
                                            <div className="font-semibold text-slate-900">
                                                {
                                                    store.name
                                                }
                                            </div>

                                            <div className="text-xs text-slate-500">
                                                {
                                                    store.slug
                                                }
                                            </div>
                                        </div>

                                        <div className="text-sm text-slate-600">
                                            #
                                            {
                                                store.sort_order
                                            }
                                        </div>

                                        <Status
                                            active={
                                                store.is_active
                                            }
                                        />

                                        <div className="text-sm text-slate-600">
                                            {store.default_selected
                                                ? 'Auto Select'
                                                : 'Manual'}
                                        </div>

                                        <div className="flex flex-wrap gap-2 lg:justify-end">
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    openEdit(
                                                        store
                                                    )
                                                }
                                                className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold"
                                            >
                                                Edit
                                            </button>

                                            <button
                                                type="button"
                                                onClick={() =>
                                                    toggleStore(
                                                        store
                                                    )
                                                }
                                                className="rounded-lg bg-amber-500 px-3 py-2 text-xs font-semibold text-white"
                                            >
                                                {store.is_active
                                                    ? 'Deactivate'
                                                    : 'Activate'}
                                            </button>

                                            <button
                                                type="button"
                                                onClick={() =>
                                                    deleteStore(
                                                        store
                                                    )
                                                }
                                                className="rounded-lg bg-red-600 px-3 py-2 text-xs font-semibold text-white"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                )
                            )}
                        </div>
                    )}
                </div>
            </div>
        </PanelLayout>
    );
}

function Stat({ label, value }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="text-sm text-slate-500">
                {label}
            </div>

            <div className="mt-2 text-3xl font-bold text-slate-900">
                {value}
            </div>
        </div>
    );
}

function Field({
    label,
    error,
    children,
}) {
    return (
        <label className="block">
            <span className="mb-2 block text-sm font-semibold text-slate-700">
                {label}
            </span>

            {children}

            {error && (
                <span className="mt-1 block text-sm text-red-600">
                    {error}
                </span>
            )}
        </label>
    );
}

function Checkbox({
    label,
    checked,
    onChange,
}) {
    return (
        <label className="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
            <input
                type="checkbox"
                checked={checked}
                onChange={(event) =>
                    onChange(
                        event.target.checked
                    )
                }
            />

            <span className="text-sm font-semibold text-slate-800">
                {label}
            </span>
        </label>
    );
}

function Status({ active }) {
    return (
        <span
            className={`inline-flex w-fit rounded-full px-3 py-1 text-xs font-semibold ${
                active
                    ? 'bg-emerald-50 text-emerald-700'
                    : 'bg-slate-100 text-slate-600'
            }`}
        >
            {active
                ? 'Active'
                : 'Inactive'}
        </span>
    );
}
JSX

echo "[3/6] Adding V2 DSP routes..."

python3 - <<'PY'
from pathlib import Path

path = Path("routes/web.php")
text = path.read_text()

marker = "v2.admin.stores.index"

if marker not in text:
    text += r"""

Route::middleware(['auth', 'verified'])
    ->prefix('v2/admin/stores')
    ->name('v2.admin.stores.')
    ->group(function () {
        Route::get(
            '/',
            [\App\Http\Controllers\V2\Admin\DistributionStoreController::class, 'index']
        )->name('index');

        Route::post(
            '/',
            [\App\Http\Controllers\V2\Admin\DistributionStoreController::class, 'store']
        )->name('store');

        Route::post(
            '/{distributionStore}',
            [\App\Http\Controllers\V2\Admin\DistributionStoreController::class, 'update']
        )->name('update');

        Route::post(
            '/{distributionStore}/toggle',
            [\App\Http\Controllers\V2\Admin\DistributionStoreController::class, 'toggle']
        )->name('toggle');

        Route::delete(
            '/{distributionStore}',
            [\App\Http\Controllers\V2\Admin\DistributionStoreController::class, 'destroy']
        )->name('destroy');
    });
"""

    path.write_text(text)
    print("V2 DSP routes added.")
else:
    print("V2 DSP routes already present.")
PY

echo "[4/6] Updating V2 sidebar route..."

python3 - <<'PY'
from pathlib import Path

path = Path(
    "resources/js/V2/Shared/Config/panelRoutes.js"
)

text = path.read_text()

text = text.replace(
    "stores:\n            '/settings/distribution-stores',",
    "stores:\n            '/v2/admin/stores',"
)

path.write_text(text)

print("V2 DSP sidebar route connected.")
PY

echo "[5/6] Syntax and route checks..."

php -l "$CONTROLLER"
php -l "$ROUTES"

php artisan optimize:clear

php artisan route:list |
grep "v2/admin/stores"

echo "[6/6] Building frontend..."

npm run build

php artisan optimize:clear

echo ""
echo "=================================================="
echo "V2 DSP STORES MODULE INSTALLED"
echo "=================================================="

echo "Open:"
echo "https://admin.mixxtune.com/v2/admin/stores"

echo ""
echo "Existing DSP count:"

php artisan tinker --execute="
echo \App\Models\DistributionStore::query()
    ->count()
    .PHP_EOL;
"

echo ""
echo "Backup:"
echo "$BACKUP"
