import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { type BreadcrumbItem as BreadcrumbItemType } from '@/types';
import AppearanceToggleDropdown from './appearance-dropdown';
import { router } from '@inertiajs/react';
import {
    Bell,
    CalendarCheck,
    CalendarX,
    CheckCircle2,
    XCircle,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

// ─── types ───────────────────────────────────────────────────────────────────
type AdminNotification = {
    id: number;
    type: 'appointment_accepted' | 'appointment_rejected';
    message: string;
    breed: string;
    scan_id: string;
    appointment_date: string;
    appointment_time: string;
    vet_name: string;
    rejection_reason?: string;
    is_read: boolean;
    created_at: string;
};

// ─── single notification row ──────────────────────────────────────────────────
function NotifRow({
    notif,
    onRead,
}: {
    notif: AdminNotification;
    onRead: (id: number) => void;
}) {
    const isAccepted = notif.type === 'appointment_accepted';

    return (
        <button
            type="button"
            onClick={() => {
                onRead(notif.id);
                router.visit(`/model/scan-results`);
            }}
            className={`w-full px-4 py-3 text-left transition-colors hover:bg-gray-50 dark:hover:bg-white/5 ${
                !notif.is_read ? 'bg-blue-50/60 dark:bg-blue-900/10' : ''
            }`}
        >
            <div className="flex items-start gap-3">
                {/* icon */}
                <div
                    className={`mt-0.5 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full ${
                        isAccepted
                            ? 'bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400'
                            : 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400'
                    }`}
                >
                    {isAccepted ? <CalendarCheck size={15} /> : <CalendarX size={15} />}
                </div>

                {/* text */}
                <div className="min-w-0 flex-1">
                    <p className="text-xs font-semibold text-gray-900 dark:text-white">
                        {isAccepted ? '✓ Appointment Accepted' : '✕ Appointment Declined'}
                    </p>
                    <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        <span className="font-medium text-gray-700 dark:text-gray-300">{notif.breed}</span>
                        {' · '}
                        {notif.appointment_date} at {notif.appointment_time}
                    </p>
                    <p className="text-xs text-gray-400 dark:text-gray-500">
                        Vet: {notif.vet_name}
                    </p>
                    {!isAccepted && notif.rejection_reason && (
                        <p className="mt-1 rounded bg-red-50 px-2 py-0.5 text-xs italic text-red-600 dark:bg-red-900/20 dark:text-red-400">
                            "{notif.rejection_reason}"
                        </p>
                    )}
                    <p className="mt-1 text-[10px] text-gray-400 dark:text-gray-600">
                        {new Date(notif.created_at).toLocaleString()}
                    </p>
                </div>

                {/* unread dot */}
                {!notif.is_read && (
                    <span className="mt-1 h-2 w-2 flex-shrink-0 rounded-full bg-blue-500" />
                )}
            </div>
        </button>
    );
}

// ─── notification bell ────────────────────────────────────────────────────────
function AdminNotificationBell() {
    const [open, setOpen] = useState(false);
    const [notifications, setNotifications] = useState<AdminNotification[]>([]);
    const [unread, setUnread] = useState(0);
    const [loading, setLoading] = useState(false);
    const panelRef = useRef<HTMLDivElement>(null);
    const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);

    // fetch from the admin-specific endpoint
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
            // silent fail — don't break the header
        }
    };

    // poll every 30 s
    useEffect(() => {
        fetchNotifications();
        intervalRef.current = setInterval(fetchNotifications, 30_000);
        return () => {
            if (intervalRef.current) clearInterval(intervalRef.current);
        };
    }, []);

    // close on outside click
    useEffect(() => {
        const handler = (e: MouseEvent) => {
            if (panelRef.current && !panelRef.current.contains(e.target as Node)) {
                setOpen(false);
            }
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, []);

    const markRead = async (id: number) => {
        try {
            await fetch(`/admin/notifications/${id}/read`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN':
                        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)
                            ?.content ?? '',
                    Accept: 'application/json',
                },
            });
            setNotifications((prev) =>
                prev.map((n) => (n.id === id ? { ...n, is_read: true } : n)),
            );
            setUnread((c) => Math.max(0, c - 1));
        } catch {
            // silent
        }
    };

    const markAllRead = async () => {
        try {
            await fetch('/admin/notifications/mark-all-read', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN':
                        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)
                            ?.content ?? '',
                    Accept: 'application/json',
                },
            });
            setNotifications((prev) => prev.map((n) => ({ ...n, is_read: true })));
            setUnread(0);
        } catch {
            // silent
        }
    };

    return (
        <div ref={panelRef} className="relative">
            {/* Bell button */}
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

            {/* Dropdown panel */}
            {open && (
                <div className="absolute right-0 top-11 z-50 w-80 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl dark:border-white/10 dark:bg-neutral-900">
                    {/* Header */}
                    <div className="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-white/10">
                        <div className="flex items-center gap-2">
                            <Bell size={14} className="text-gray-500 dark:text-gray-400" />
                            <span className="text-sm font-semibold text-gray-900 dark:text-white">
                                Appointment Responses
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

                    {/* List */}
                    <div className="max-h-80 divide-y divide-gray-100 overflow-y-auto dark:divide-white/5">
                        {notifications.length === 0 ? (
                            <div className="flex flex-col items-center gap-2 py-10 text-center">
                                <CheckCircle2 size={28} className="text-gray-300 dark:text-gray-600" />
                                <p className="text-xs text-gray-400 dark:text-gray-500">
                                    No responses yet
                                </p>
                                <p className="text-[10px] text-gray-300 dark:text-gray-600">
                                    You'll be notified when owners respond to appointments.
                                </p>
                            </div>
                        ) : (
                            notifications.map((n) => (
                                <NotifRow key={n.id} notif={n} onRead={markRead} />
                            ))
                        )}
                    </div>

                    {/* Footer */}
                    {notifications.length > 0 && (
                        <div className="border-t border-gray-100 px-4 py-2.5 dark:border-white/10">
                            <button
                                type="button"
                                onClick={() => {
                                    setOpen(false);
                                    router.visit('/model/scan-results');
                                }}
                                className="text-xs text-blue-600 hover:underline dark:text-blue-400"
                            >
                                View all scan results →
                            </button>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

// ─── main export ──────────────────────────────────────────────────────────────
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