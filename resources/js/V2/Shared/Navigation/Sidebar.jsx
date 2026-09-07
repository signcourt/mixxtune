import { Link, usePage } from "@inertiajs/react";

import { useEffect, useMemo, useState } from "react";

import { getPanelNavigation } from "@/V2/Shared/Config/panelNavigation";

const normalizePath = (url = "") =>
    url.split("?")[0].replace(/\/+$/, "") || "/";

const getStatusQuery = () => {
    if (typeof window === "undefined") {
        return "";
    }

    return new URLSearchParams(window.location.search).get("status") ?? "";
};

const permissionAllowed = (item, permissions) => {
    if (!item?.permission) {
        return true;
    }

    if (!Array.isArray(permissions)) {
        return false;
    }

    if (permissions.includes("*")) {
        return true;
    }

    return permissions.includes(item.permission);
};

const filterNavigationItem = (item, permissions) => {
    if (!permissionAllowed(item, permissions)) {
        return null;
    }

    if (!Array.isArray(item.children)) {
        return item;
    }

    const allowedChildren = item.children.filter((child) =>
        permissionAllowed(child, permissions),
    );

    return {
        ...item,
        children: allowedChildren,
    };
};

export default function Sidebar({
    role = "artist",
    permissions = [],
    notificationCount = 0,
    brandName = "BACKSTAGE",
    brandSubtitle = "MIXX TUNE",
    logoUrl = null,
    mobileOpen = false,
    onMobileClose = () => {},
}) {
    const { url } = usePage();

    const [collapsed, setCollapsed] = useState(false);

    const [openMenus, setOpenMenus] = useState({});

    const [navigationOrder, setNavigationOrder] = useState(() => {
        if (typeof window === "undefined") {
            return [];
        }

        try {
            const key =
                `mixxtune_sidebar_order_${String(role || "artist")}`;

            const saved = window.localStorage.getItem(key);

            if (!saved) {
                return [];
            }

            const parsed = JSON.parse(saved);

            return Array.isArray(parsed)
                ? parsed.map(String)
                : [];
        } catch {
            return [];
        }
    });

    const [draggedItemId, setDraggedItemId] = useState(null);
    const [dropTarget, setDropTarget] = useState(null);

    const currentPath = normalizePath(url);

    const currentStatus = getStatusQuery();

    const navigation = useMemo(
        () =>
            getPanelNavigation(role)
                .map((item) => filterNavigationItem(item, permissions))
                .filter(Boolean)
                .filter(
                    (item) =>
                        ![
                            "notifications",
                            "settings",
                            "user-access",
                            "user_access",
                            "artists",
                            "revenue-sharing",
                            "kyc-profile",
                        ].includes(String(item.id ?? "").toLowerCase()) &&
                        ![
                            "Notifications",
                            "Settings",
                            "User Access",
                            "Artists",
                            "Revenue Sharing",
                            "KYC & Profile",
                        ].includes(item.label),
                ),
        [role, permissions],
    );

    const orderedNavigation = useMemo(() => {
        if (!navigationOrder.length) {
            return navigation;
        }

        const result = [];

        navigationOrder.forEach((id) => {
            const item = navigation.find(
                (entry) => String(entry.id) === String(id),
            );

            if (item) {
                result.push(item);
            }
        });

        navigation.forEach((item) => {
            if (
                !result.some(
                    (entry) =>
                        String(entry.id) === String(item.id),
                )
            ) {
                result.push(item);
            }
        });

        return result;
    }, [navigation, navigationOrder]);

    useEffect(() => {
        const ids = navigation.map((item) => String(item.id));

        setNavigationOrder((current) => {
            const valid = current.filter((id) =>
                ids.includes(String(id)),
            );

            const missing = ids.filter(
                (id) => !valid.includes(id),
            );

            const next = [...valid, ...missing];

            try {
                const key =
                    `mixxtune_sidebar_order_${String(role || "artist")}`;

                window.localStorage.setItem(
                    key,
                    JSON.stringify(next),
                );
            } catch {
                // Ignore localStorage errors.
            }

            return next;
        });
    }, [navigation, role]);

    const saveNavigationOrder = (next) => {
        setNavigationOrder(next);

        try {
            const key =
                `mixxtune_sidebar_order_${String(role || "artist")}`;

            window.localStorage.setItem(
                key,
                JSON.stringify(next),
            );
        } catch {
            // Ignore localStorage errors.
        }
    };

    const moveNavigationItem = (itemId, direction) => {
        const ids = orderedNavigation.map((item) =>
            String(item.id),
        );

        const index = ids.indexOf(String(itemId));

        if (index === -1) {
            return;
        }

        const target =
            direction === "up"
                ? index - 1
                : index + 1;

        if (target < 0 || target >= ids.length) {
            return;
        }

        const next = [...ids];

        [next[index], next[target]] = [
            next[target],
            next[index],
        ];

        saveNavigationOrder(next);
    };

    const handleSidebarDragStart = (event, itemId) => {
        const id = String(itemId);

        setDraggedItemId(id);

        event.dataTransfer.effectAllowed = "move";
        event.dataTransfer.setData("text/plain", id);
    };

    const handleSidebarDragOver = (event, targetId) => {
        event.preventDefault();

        event.dataTransfer.dropEffect = "move";

        if (String(draggedItemId) === String(targetId)) {
            setDropTarget(null);
            return;
        }

        const rect = event.currentTarget.getBoundingClientRect();
        const middle = rect.top + rect.height / 2;

        setDropTarget({
            id: String(targetId),
            position: event.clientY < middle ? "before" : "after",
        });
    };

    const handleSidebarDrop = (event, targetId) => {
        event.preventDefault();

        const sourceId =
            event.dataTransfer.getData("text/plain") ||
            draggedItemId;

        if (!sourceId) {
            setDropTarget(null);
            return;
        }

        const ids = orderedNavigation.map((item) =>
            String(item.id),
        );

        const sourceIndex = ids.indexOf(
            String(sourceId),
        );

        const targetIndex = ids.indexOf(
            String(targetId),
        );

        if (
            sourceIndex === -1 ||
            targetIndex === -1 ||
            sourceIndex === targetIndex
        ) {
            setDraggedItemId(null);
            setDropTarget(null);
            return;
        }

        const next = [...ids];

        const [moved] = next.splice(sourceIndex, 1);

        let insertIndex = targetIndex;

        if (sourceIndex < targetIndex) {
            insertIndex -= 1;
        }

        if (
            dropTarget?.id === String(targetId) &&
            dropTarget.position === "after"
        ) {
            insertIndex += 1;
        }

        insertIndex = Math.max(
            0,
            Math.min(insertIndex, next.length),
        );

        next.splice(insertIndex, 0, moved);

        saveNavigationOrder(next);

        setDraggedItemId(null);
        setDropTarget(null);
    };

    const handleSidebarDragEnd = () => {
        setDraggedItemId(null);
        setDropTarget(null);
    };

    useEffect(() => {
        const activeParent = orderedNavigation.find((item) =>
            item.children?.some((child) =>
                isChildActive(child, currentPath, currentStatus),
            ),
        );

        if (activeParent) {
            setOpenMenus((current) => ({
                ...current,
                [activeParent.id]: true,
            }));
        }
    }, [currentPath, currentStatus, orderedNavigation]);

    const toggleSubmenu = (id) => {
        setOpenMenus((current) => {
            const isCurrentlyOpen = Boolean(current[id]);

            if (isCurrentlyOpen) {
                return {};
            }

            return {
                [id]: true,
            };
        });
    };

    const sidebarWidth = collapsed ? "lg:w-[88px]" : "lg:w-[272px]";

    return (
        <>
            {mobileOpen && (
                <button
                    type="button"
                    aria-label="Close sidebar"
                    onClick={onMobileClose}
                    className="fixed inset-0 z-40 bg-slate-950/60 lg:hidden"
                />
            )}

            <aside
                className={[
                    "fixed inset-y-0 left-0 z-50 flex w-[272px] flex-col border-r border-white/10 bg-gradient-to-b from-[#080d18] via-[#0b111d] to-[#101724] text-white shadow-2xl transition-all duration-300",
                    sidebarWidth,
                    mobileOpen
                        ? "translate-x-0"
                        : "-translate-x-full lg:translate-x-0",
                ].join(" ")}
            >
                <div
                    className={[
                        "flex h-24 items-center border-b border-white/10 px-5",
                        collapsed ? "justify-center" : "gap-3",
                    ].join(" ")}
                >
                    <div className="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-violet-500/50 bg-slate-900 shadow-lg">
                        {logoUrl ? (
                            <img
                                src={logoUrl}
                                alt={brandName}
                                className="h-full w-full object-cover"
                            />
                        ) : (
                            <span className="text-sm font-bold text-violet-300">
                                MT
                            </span>
                        )}
                    </div>

                    {!collapsed && (
                        <div className="min-w-0">
                            <div className="truncate text-lg font-bold tracking-wide">
                                {brandName}
                            </div>

                            <div className="mt-0.5 truncate text-[10px] font-semibold tracking-[0.3em] text-slate-400">
                                {brandSubtitle}
                            </div>
                        </div>
                    )}
                </div>

                <nav className="flex-1 overflow-y-auto px-3 py-5">
                    <div className="space-y-1.5">
                        {orderedNavigation.map((item, index) => (
                            <div
                                key={item.id}
                                draggable={!collapsed}
                                onDragStart={(event) =>
                                    handleSidebarDragStart(
                                        event,
                                        item.id,
                                    )
                                }
                                onDragOver={(event) =>
                                    handleSidebarDragOver(
                                        event,
                                        item.id,
                                    )
                                }
                                onDrop={(event) =>
                                    handleSidebarDrop(
                                        event,
                                        item.id,
                                    )
                                }
                                onDragEnd={handleSidebarDragEnd}
                                className={[
                                    "group relative rounded-xl transition-all duration-150",
                                    String(draggedItemId) ===
                                        String(item.id)
                                        ? "opacity-40 ring-1 ring-violet-500/70"
                                        : "",
                                    dropTarget?.id === String(item.id) &&
                                        dropTarget.position === "before"
                                        ? "before:pointer-events-none before:absolute before:left-1 before:right-1 before:-top-1 before:z-50 before:h-0.5 before:rounded-full before:bg-violet-500 before:shadow-[0_0_8px_rgba(139,92,246,0.9)]"
                                        : "",
                                    dropTarget?.id === String(item.id) &&
                                        dropTarget.position === "after"
                                        ? "after:pointer-events-none after:absolute after:left-1 after:right-1 after:-bottom-1 after:z-50 after:h-0.5 after:rounded-full after:bg-violet-500 after:shadow-[0_0_8px_rgba(139,92,246,0.9)]"
                                        : "",
                                ].join(" ")}
                            >
                                <SidebarItem
                                    item={item}
                                    collapsed={collapsed}
                                    open={Boolean(
                                        openMenus[item.id],
                                    )}
                                    currentPath={currentPath}
                                    currentStatus={currentStatus}
                                    notificationCount={
                                        notificationCount
                                    }
                                    onToggle={() =>
                                        toggleSubmenu(item.id)
                                    }
                                    onNavigate={onMobileClose}
                                />

                            </div>
                        ))}
                    </div>
                </nav>

                <div className="border-t border-white/10 p-3">
                    <button
                        type="button"
                        onClick={() => setCollapsed((value) => !value)}
                        className="flex w-full items-center justify-center rounded-xl px-3 py-3 text-violet-400 transition hover:bg-white/5 hover:text-violet-300"
                        title={
                            collapsed ? "Expand sidebar" : "Collapse sidebar"
                        }
                    >
                        <span
                            className={[
                                "text-xl transition-transform duration-300",
                                collapsed ? "rotate-180" : "",
                            ].join(" ")}
                        >
                            ‹
                        </span>
                    </button>
                </div>
            </aside>
        </>
    );
}

function SidebarItem({
    item,
    collapsed,
    open,
    currentPath,
    currentStatus,
    notificationCount,
    onToggle,
    onNavigate,
}) {
    const hasChildren =
        Array.isArray(item.children) && item.children.length > 0;

    const active =
        isItemActive(item, currentPath) ||
        item.children?.some((child) =>
            isChildActive(child, currentPath, currentStatus),
        );

    const badge = item.badgeKey === "notifications" ? notificationCount : 0;

    if (hasChildren) {
        return (
            <div>
                <button
                    type="button"
                    onClick={onToggle}
                    title={collapsed ? item.label : undefined}
                    className={[
                        "group flex w-full items-center rounded-xl py-3 transition-all duration-200",
                        collapsed ? "justify-center px-2" : "gap-3 px-3",
                        active
                            ? "bg-gradient-to-r from-violet-600 to-purple-700 text-white shadow-lg shadow-violet-950/30"
                            : "text-slate-300 hover:bg-white/10 hover:text-white",
                    ].join(" ")}
                >
                    <NavIcon icon={item.icon} active={active} />

                    {!collapsed && (
                        <>
                            <span className="min-w-0 flex-1 truncate text-left text-sm font-medium">
                                {item.label}
                            </span>

                            <span
                                className={[
                                    "text-xs transition-transform duration-200",
                                    open ? "rotate-180" : "",
                                ].join(" ")}
                            >
                                ⌄
                            </span>
                        </>
                    )}
                </button>

                {!collapsed && open && (
                    <div className="ml-7 mt-2 border-l border-slate-700/80 pl-4">
                        <div className="space-y-1">
                            {item.children.map((child) => {
                                const childActive = isChildActive(
                                    child,
                                    currentPath,
                                    currentStatus,
                                );

                                return (
                                    <Link
                                        key={child.id}
                                        href={child.href}
                                        onClick={onNavigate}
                                        className={[
                                            "group flex items-center gap-3 rounded-lg px-2 py-2 text-sm transition",
                                            childActive
                                                ? "font-semibold text-violet-400"
                                                : "text-slate-400 hover:text-white",
                                        ].join(" ")}
                                    >
                                        <span
                                            className={[
                                                "h-2 w-2 rounded-full border",
                                                childActive
                                                    ? "border-violet-500 bg-violet-500"
                                                    : "border-slate-600 bg-transparent group-hover:border-slate-400",
                                            ].join(" ")}
                                        />

                                        <span className="truncate">
                                            {child.label}
                                        </span>
                                    </Link>
                                );
                            })}
                        </div>
                    </div>
                )}
            </div>
        );
    }

    /*
     * Dashboard uses a full browser navigation.
     *
     * Dashboard routes are valid server-side routes, but
     * client-side Inertia navigation can fail when moving
     * back to the dashboard from some panel pages.
     */
    if (item.id === "dashboard") {
        return (
            <a
                href={item.href}
                onClick={(event) => {
                    event.preventDefault();

                    window.location.assign(item.href);
                }}
                title={collapsed ? item.label : undefined}
                className={[
                    "group flex items-center rounded-xl py-3 transition-all duration-200",
                    collapsed ? "justify-center px-2" : "gap-3 px-3",
                    active
                        ? "bg-gradient-to-r from-violet-600 to-purple-700 text-white shadow-lg shadow-violet-950/30"
                        : "text-slate-300 hover:bg-white/10 hover:text-white",
                ].join(" ")}
            >
                <NavIcon
                    icon={item.icon}
                    active={active}
                />

                {!collapsed && (
                    <>
                        <span className="min-w-0 flex-1 truncate text-sm font-medium">
                            {item.label}
                        </span>

                        {Number(badge) > 0 && (
                            <span className="flex min-w-7 items-center justify-center rounded-full bg-violet-700 px-2 py-1 text-xs font-bold text-white">
                                {Number(badge) > 99
                                    ? "99+"
                                    : badge}
                            </span>
                        )}
                    </>
                )}
            </a>
        );
    }


    /*
     * Analytics uses a full browser navigation.
     *
     * The Analytics route/page is valid server-side,
     * but entering it through client-side Inertia
     * navigation can fail from some panel pages.
     */
    if (item.id === "analytics") {
        return (
            <a
                href={item.href}
                onClick={(event) => {
                    event.preventDefault();

                    window.location.assign(item.href);
                }}
                title={collapsed ? item.label : undefined}
                className={[
                    "group flex items-center rounded-xl py-3 transition-all duration-200",
                    collapsed ? "justify-center px-2" : "gap-3 px-3",
                    active
                        ? "bg-gradient-to-r from-violet-600 to-purple-700 text-white shadow-lg shadow-violet-950/30"
                        : "text-slate-300 hover:bg-white/10 hover:text-white",
                ].join(" ")}
            >
                <NavIcon
                    icon={item.icon}
                    active={active}
                />

                {!collapsed && (
                    <>
                        <span className="min-w-0 flex-1 truncate text-sm font-medium">
                            {item.label}
                        </span>

                        {Number(badge) > 0 && (
                            <span className="flex min-w-7 items-center justify-center rounded-full bg-violet-700 px-2 py-1 text-xs font-bold text-white">
                                {Number(badge) > 99
                                    ? "99+"
                                    : badge}
                            </span>
                        )}
                    </>
                )}
            </a>
        );
    }

    /*
     * Unmapped Revenue uses a full browser navigation.
     * The page itself works correctly when opened directly,
     * but client-side Inertia navigation can fail to enter it.
     */
    if (item.id === "unmapped-revenue") {
        return (
            <a
                href={item.href}
                onClick={(event) => {
                    event.preventDefault();

                    window.location.assign(
                        "/super-admin/unmapped-revenue",
                    );
                }}
                title={collapsed ? item.label : undefined}
                className={[
                    "group flex items-center rounded-xl py-3 transition-all duration-200",
                    collapsed ? "justify-center px-2" : "gap-3 px-3",
                    active
                        ? "bg-gradient-to-r from-violet-600 to-purple-700 text-white shadow-lg shadow-violet-950/30"
                        : "text-slate-300 hover:bg-white/10 hover:text-white",
                ].join(" ")}
            >
                <NavIcon
                    icon={item.icon}
                    active={active}
                />

                {!collapsed && (
                    <>
                        <span className="min-w-0 flex-1 truncate text-sm font-medium">
                            {item.label}
                        </span>

                        {Number(badge) > 0 && (
                            <span className="flex min-w-7 items-center justify-center rounded-full bg-violet-700 px-2 py-1 text-xs font-bold text-white">
                                {Number(badge) > 99
                                    ? "99+"
                                    : badge}
                            </span>
                        )}
                    </>
                )}

                {collapsed && Number(badge) > 0 && (
                    <span className="absolute ml-8 mt-[-28px] flex h-5 min-w-5 items-center justify-center rounded-full bg-violet-600 px-1 text-[10px] font-bold">
                        {Number(badge) > 9
                            ? "9+"
                            : badge}
                    </span>
                )}
            </a>
        );
    }

    return (
        <Link
            href={item.href}
            onClick={onNavigate}
            title={collapsed ? item.label : undefined}
            className={[
                "group flex items-center rounded-xl py-3 transition-all duration-200",
                collapsed ? "justify-center px-2" : "gap-3 px-3",
                active
                    ? "bg-gradient-to-r from-violet-600 to-purple-700 text-white shadow-lg shadow-violet-950/30"
                    : "text-slate-300 hover:bg-white/10 hover:text-white",
            ].join(" ")}
        >
            <NavIcon icon={item.icon} active={active} />

            {!collapsed && (
                <>
                    <span className="min-w-0 flex-1 truncate text-sm font-medium">
                        {item.label}
                    </span>

                    {Number(badge) > 0 && (
                        <span className="flex min-w-7 items-center justify-center rounded-full bg-violet-700 px-2 py-1 text-xs font-bold text-white">
                            {Number(badge) > 99 ? "99+" : badge}
                        </span>
                    )}
                </>
            )}

            {collapsed && Number(badge) > 0 && (
                <span className="absolute ml-8 mt-[-28px] flex h-5 min-w-5 items-center justify-center rounded-full bg-violet-600 px-1 text-[10px] font-bold">
                    {Number(badge) > 9 ? "9+" : badge}
                </span>
            )}
        </Link>
    );
}

function NavIcon({ icon: Icon, active }) {
    return (
        <span
            className={[
                "flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border transition",
                active
                    ? "border-white/20 bg-white/15 text-white"
                    : "border-transparent text-slate-300 group-hover:text-white",
            ].join(" ")}
        >
            {Icon ? (
                <Icon size={21} strokeWidth={1.8} aria-hidden="true" />
            ) : (
                <span className="h-5 w-5 rounded border border-current" />
            )}
        </span>
    );
}

function isItemActive(item, currentPath) {
    const itemPath = normalizePath(item.href);

    if (item.exact) {
        return currentPath === itemPath;
    }

    if (item.id === "releases") {
        return (
            currentPath === itemPath || currentPath.startsWith(`${itemPath}/`)
        );
    }

    if (item.id === "unmapped-revenue") {
        return currentPath === "/super-admin/unmapped-revenue";
    }

    return currentPath === itemPath || currentPath.startsWith(`${itemPath}/`);
}

function isChildActive(child, currentPath, currentStatus) {
    const childPath = normalizePath(child.href);

    if (currentPath !== childPath) {
        return false;
    }

    if (child.queryStatus) {
        return currentStatus === child.queryStatus;
    }

    if (child.id === "releases-all") {
        return currentStatus === "";
    }

    return true;
}
