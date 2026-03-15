import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { type BreadcrumbItem as BreadcrumbItemType } from '@/types';
import { router } from '@inertiajs/react';
import {
    Bell,
    CalendarCheck,
    CalendarDays,
    CalendarX,
    CheckCircle2,
    Trash2,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import AppearanceToggleDropdown from './appearance-dropdown';

type AdminNotification = {
    id: number;
    type:
        | 'appointment_accepted'
        | 'appointment_rejected'
        | 'user_appointment_request';
    message: string;
    breed: string;
    scan_id: string;
    result_id?: number;
    appointment_date: string;
    appointment_time: string;
    vet_name: string;
    rejection_reason?: string;
    is_read: boolean;
    created_at: string;
};

function getCsrf(): string {
    return decodeURIComponent(
        document.cookie
            .split('; ')
            .find((r) => r.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );
}

function NotifRow({
    notif,
    onRead,
    onDelete,
}: {
    notif: AdminNotification;
    onRead: (id: number) => void;
    onDelete: (id: number) => void;
}) {
    const isAccepted = notif.type === 'appointment_accepted';
    const isRejected = notif.type === 'appointment_rejected';
    const isUserReq = notif.type === 'user_appointment_request';

    const iconBg = isAccepted
        ? 'bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400'
        : isRejected
          ? 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400'
          : 'bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400';

    const IconEl = isAccepted
        ? CalendarCheck
        : isRejected
          ? CalendarX
          : CalendarDays;

    const title = isAccepted
        ? '✓ Appointment Accepted'
        : isRejected
          ? '✕ Appointment Declined'
          : '📋 New Appointment Request';

    const handleClick = () => {
        onRead(notif.id);
        if (isUserReq) {
            router.visit('/model/appointmentspage');
        } else if (notif.result_id) {
            router.visit(`/model/review-dog/${notif.result_id}`);
        } else {
            router.visit('/model/scan-results');
        }
    };

    return (
        <div
            className={`group relative flex w-full items-start gap-3 px-4 py-3 transition-colors hover:bg-gray-50 dark:hover:bg-white/5 ${!notif.is_read ? 'bg-blue-50/60 dark:bg-blue-900/10' : ''}`}
        >
            {/* Clickable area */}
            <button
                type="button"
                onClick={handleClick}
                className="flex min-w-0 flex-1 items-start gap-3 text-left"
            >
                <div
                    className={`mt-0.5 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full ${iconBg}`}
                >
                    <IconEl size={15} />
                </div>
                <div className="min-w-0 flex-1">
                    <p className="text-xs font-semibold text-gray-900 dark:text-white">
                        {title}
                    </p>
                    {isUserReq ? (
                        <>
                            <p className="mt-0.5 text-xs text-gray-600 dark:text-gray-300">
                                {notif.message}
                            </p>
                            <p className="text-xs text-gray-400 dark:text-gray-500">
                                Preferred: {notif.appointment_date} at{' '}
                                {notif.appointment_time}
                            </p>
                        </>
                    ) : (
                        <>
                            <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                <span className="font-medium text-gray-700 dark:text-gray-300">
                                    {notif.breed}
                                </span>
                                {' · '}
                                {notif.appointment_date} at{' '}
                                {notif.appointment_time}
                            </p>
                            <p className="text-xs text-gray-400 dark:text-gray-500">
                                Vet: {notif.vet_name}
                            </p>
                            {isRejected && notif.rejection_reason && (
                                <p className="mt-1 rounded bg-red-50 px-2 py-0.5 text-xs text-red-600 italic dark:bg-red-900/20 dark:text-red-400">
                                    "{notif.rejection_reason}"
                                </p>
                            )}
                        </>
                    )}
                    <p className="mt-1 text-[10px] text-gray-400 dark:text-gray-600">
                        {new Date(notif.created_at).toLocaleString()}
                    </p>
                </div>
                {!notif.is_read && (
                    <span className="mt-1 h-2 w-2 flex-shrink-0 rounded-full bg-blue-500" />
                )}
            </button>

            {/* Delete button */}
            <button
                type="button"
                onClick={(e) => {
                    e.stopPropagation();
                    onDelete(notif.id);
                }}
                className="mt-1 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-md text-gray-300 opacity-0 transition-all group-hover:opacity-100 hover:bg-red-50 hover:text-red-500 dark:text-gray-600 dark:hover:bg-red-900/20 dark:hover:text-red-400"
                title="Delete notification"
            >
                <Trash2 size={12} />
            </button>
        </div>
    );
}

function AdminNotificationBell() {
    const [open, setOpen] = useState(false);
    const [notifications, setNotifications] = useState<AdminNotification[]>([]);
    const [unread, setUnread] = useState(0);
    const panelRef = useRef<HTMLDivElement>(null);
    const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);

    const fetchNotifications = async () => {
        try {
            const res = await fetch('/admin/notifications', {
                headers: { Accept: 'application/json' },
            });
            if (!res.ok) return;
            const data = await res.json();
            setNotifications(data.notifications ?? []);
            setUnread(data.unread_count ?? 0);
        } catch {
            /* silent */
        }
    };

    useEffect(() => {
        fetchNotifications();
        intervalRef.current = setInterval(fetchNotifications, 30_000);
        return () => {
            if (intervalRef.current) clearInterval(intervalRef.current);
        };
    }, []);

    useEffect(() => {
        const handler = (e: MouseEvent) => {
            if (
                panelRef.current &&
                !panelRef.current.contains(e.target as Node)
            )
                setOpen(false);
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, []);

    const markRead = async (id: number) => {
        try {
            const res = await fetch(`/admin/notifications/${id}/read`, {
                method: 'POST',
                headers: {
                    'X-XSRF-TOKEN': getCsrf(),
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
            });
            if (res.ok) {
                setNotifications((prev) =>
                    prev.map((n) =>
                        n.id === id ? { ...n, is_read: true } : n,
                    ),
                );
                setUnread((c) => Math.max(0, c - 1));
            }
        } catch {
            /* silent */
        }
    };

    const markAllRead = async () => {
        try {
            const res = await fetch('/admin/notifications/mark-all-read', {
                method: 'POST',
                headers: {
                    'X-XSRF-TOKEN': getCsrf(),
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
            });
            if (res.ok) {
                setNotifications((prev) =>
                    prev.map((n) => ({ ...n, is_read: true })),
                );
                setUnread(0);
            }
        } catch {
            /* silent */
        }
    };

    const deleteNotif = async (id: number) => {
        try {
            const res = await fetch(`/admin/notifications/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-XSRF-TOKEN': getCsrf(),
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
            });
            if (res.ok) {
                const removed = notifications.find((n) => n.id === id);
                setNotifications((prev) => prev.filter((n) => n.id !== id));
                if (removed && !removed.is_read)
                    setUnread((c) => Math.max(0, c - 1));
            }
        } catch {
            /* silent */
        }
    };

    return (
        <div ref={panelRef} className="relative">
            <button
                type="button"
                onClick={() => setOpen((o) => !o)}
                className="relative flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-white/10 dark:hover:text-white"
                aria-label="Notifications"
            >
                <Bell size={18} />
                {unread > 0 && (
                    <span className="absolute top-1 right-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[9px] font-bold text-white">
                        {unread > 9 ? '9+' : unread}
                    </span>
                )}
            </button>

            {open && (
                <div className="absolute top-11 right-0 z-50 w-80 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl dark:border-white/10 dark:bg-neutral-900">
                    <div className="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-white/10">
                        <div className="flex items-center gap-2">
                            <Bell
                                size={14}
                                className="text-gray-500 dark:text-gray-400"
                            />
                            <span className="text-sm font-semibold text-gray-900 dark:text-white">
                                Notifications
                            </span>
                            {unread > 0 && (
                                <span className="rounded-full bg-red-100 px-1.5 py-0.5 text-[10px] font-bold text-red-600 dark:bg-red-900/30 dark:text-red-400">
                                    {unread} new
                                </span>
                            )}
                        </div>
                        {unread > 0 && (
                            <button
                                type="button"
                                onClick={markAllRead}
                                className="text-[11px] text-blue-600 hover:underline dark:text-blue-400"
                            >
                                Mark all read
                            </button>
                        )}
                    </div>

                    <div className="max-h-80 divide-y divide-gray-100 overflow-y-auto dark:divide-white/5">
                        {notifications.length === 0 ? (
                            <div className="flex flex-col items-center gap-2 py-10 text-center">
                                <CheckCircle2
                                    size={28}
                                    className="text-gray-300 dark:text-gray-600"
                                />
                                <p className="text-xs text-gray-400 dark:text-gray-500">
                                    No notifications yet
                                </p>
                            </div>
                        ) : (
                            notifications.map((n) => (
                                <NotifRow
                                    key={n.id}
                                    notif={n}
                                    onRead={markRead}
                                    onDelete={deleteNotif}
                                />
                            ))
                        )}
                    </div>

                    {notifications.length > 0 && (
                        <div className="border-t border-gray-100 px-4 py-2.5 dark:border-white/10">
                            <button
                                type="button"
                                onClick={() => {
                                    setOpen(false);
                                    router.visit('/model/appointmentspage');
                                }}
                                className="text-xs text-blue-600 hover:underline dark:text-blue-400"
                            >
                                View all appointments →
                            </button>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return (
        <header className="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex w-full justify-between">
                <div className="flex items-center gap-2">
                    <SidebarTrigger className="-ml-1" />
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </div>
                <div className="flex items-center gap-2 pr-10">
                    <AdminNotificationBell />
                    <AppearanceToggleDropdown className="dark:text-white" />
                </div>
            </div>
        </header>
    );
}
