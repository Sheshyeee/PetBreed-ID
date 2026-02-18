import { dashboard } from '@/routes';
import { type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { NavigationMenu } from '@/components/ui/navigation-menu';
import { useAppearance } from '@/hooks/use-appearance';
import { useInitials } from '@/hooks/use-initials';
import { Bell, LogOut, PawPrint } from 'lucide-react';
import { useEffect, useState } from 'react';
import AppearanceToggleDropdown from './appearance-dropdown';
import { Avatar, AvatarFallback, AvatarImage } from './ui/avatar';
import { Badge } from './ui/badge';
import { Card } from './ui/card';
import { ScrollArea } from './ui/scroll-area';

type Notification = {
    id: number;
    type: string;
    title: string;
    message: string;
    data: {
        scan_id: string;
        breed: string;
        original_breed?: string;
        image?: string;
    };
    read: boolean;
    created_at: string;
};

export default function Header() {
    const { auth } = usePage<SharedData>().props;
    const { appearance } = useAppearance();
    const getInitials = useInitials();

    const [resolvedTheme, setResolvedTheme] = useState<'light' | 'dark'>(
        'light',
    );
    const [notifications, setNotifications] = useState<Notification[]>([]);
    const [unreadCount, setUnreadCount] = useState(0);
    const [isLoadingNotifications, setIsLoadingNotifications] = useState(false);
    const [dropdownOpen, setDropdownOpen] = useState(false);

    useEffect(() => {
        if (appearance === 'system') {
            const isDark = window.matchMedia(
                '(prefers-color-scheme: dark)',
            ).matches;
            setResolvedTheme(isDark ? 'dark' : 'light');
        } else {
            setResolvedTheme(appearance);
        }
    }, [appearance]);

    // Fetch unread count on mount and periodically
    useEffect(() => {
        if (auth.user) {
            fetchUnreadCount();
            const interval = setInterval(fetchUnreadCount, 30000); // Every 30 seconds
            return () => clearInterval(interval);
        }
    }, [auth.user]);

    const fetchUnreadCount = async () => {
        try {
            const response = await fetch('/notifications/unread-count');
            const data = await response.json();
            if (data.success) {
                setUnreadCount(data.count);
            }
        } catch (error) {
            console.error('Failed to fetch unread count:', error);
        }
    };

    const fetchNotifications = async () => {
        setIsLoadingNotifications(true);
        try {
            const response = await fetch('/notifications');
            const data = await response.json();
            if (data.success) {
                setNotifications(data.notifications.data || []);
            }
        } catch (error) {
            console.error('Failed to fetch notifications:', error);
        } finally {
            setIsLoadingNotifications(false);
        }
    };

    // FIXED: Use Inertia router for proper CSRF token handling
    const markAsRead = async (id: number) => {
        // Store previous state for rollback
        const previousNotifications = [...notifications];
        const previousUnreadCount = unreadCount;

        // IMMEDIATELY update UI state for instant feedback
        setNotifications((prev) =>
            prev.map((notif) =>
                notif.id === id ? { ...notif, read: true } : notif,
            ),
        );

        // Optimistically update unread count
        setUnreadCount((prev) => Math.max(0, prev - 1));

        // Use Inertia router for CSRF token handling
        router.post(
            `/notifications/${id}/mark-read`,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                only: [], // Don't reload any props
                onError: (errors) => {
                    console.error('Failed to mark as read:', errors);
                    // Rollback on error
                    setNotifications(previousNotifications);
                    setUnreadCount(previousUnreadCount);
                },
                onSuccess: () => {
                    console.log('✓ Notification marked as read');
                    // Refetch count to ensure sync
                    fetchUnreadCount();
                },
            },
        );
    };

    const markAllAsRead = () => {
        // Store previous state for rollback
        const previousNotifications = [...notifications];
        const previousUnreadCount = unreadCount;

        // Optimistically update all notifications
        setNotifications((prev) =>
            prev.map((notif) => ({ ...notif, read: true })),
        );
        setUnreadCount(0);

        // Use Inertia router for CSRF token handling
        router.post(
            '/notifications/mark-all-read',
            {},
            {
                preserveScroll: true,
                preserveState: true,
                only: [], // Don't reload any props
                onError: (errors) => {
                    console.error('Failed to mark all as read:', errors);
                    // Rollback on error
                    setNotifications(previousNotifications);
                    setUnreadCount(previousUnreadCount);
                },
                onSuccess: () => {
                    console.log('✓ All notifications marked as read');
                },
            },
        );
    };

    const formatTimeAgo = (dateString: string) => {
        const date = new Date(dateString);
        const now = new Date();
        const seconds = Math.floor((now.getTime() - date.getTime()) / 1000);

        if (seconds < 60) return 'Just now';
        if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
        if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
        if (seconds < 604800) return `${Math.floor(seconds / 86400)}d ago`;
        return date.toLocaleDateString();
    };

    const page = usePage();

    // Check if user is admin
    const allowedEmails = ['modeltraining2000@gmail.com', 'jrbd2022-8800-57025@bicol-u.edu.ph'];
    const isAdmin = auth.user && allowedEmails.includes(auth.user.email);

    const handleLogout = () => {
        router.post('/logout');
    };

    // Handle notification click - mark as read and navigate
    const handleNotificationClick = (notification: Notification) => {
        // Mark as read if unread (non-blocking)
        if (!notification.read) {
            markAsRead(notification.id);
        }

        // Small delay to let user see the state change, then navigate
        setTimeout(() => {
            setDropdownOpen(false); // Close dropdown
            router.visit('/scanhistory');
        }, 150);
    };

    return (
        <div className="px-4 py-5 sm:px-6 lg:px-25">
            <Card className="w-full border-2 p-2 px-4 sm:px-8 lg:px-12">
                <nav className="flex items-center justify-between gap-2 sm:gap-4">
                    {/* Logo Section */}
                    <div className="flex min-w-0 flex-shrink items-center gap-1">
                        <div className="flex aspect-square size-7 shrink-0 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                            <PawPrint className="size-4 fill-current text-white dark:text-black" />
                        </div>
                        <div className="ml-1 grid min-w-0 flex-1 text-left">
                            <span className="mb-0.5 truncate text-sm leading-tight font-semibold sm:text-base">
                                DogLens
                            </span>
                        </div>
                    </div>

                    {/* Navigation menu for non-logged-in users */}
                    {!auth.user && (
                        <div className="hidden lg:block">
                            <NavigationMenu>
                                {/* ... existing navigation menu ... */}
                            </NavigationMenu>
                        </div>
                    )}

                    {/* Right Side Actions */}
                    <div className="flex shrink-0 items-center gap-1.5 sm:gap-3">
                        {auth.user ? (
                            <>
                                {/* Dashboard button for admin */}
                                {isAdmin && (
                                    <Link
                                        href={dashboard()}
                                        className="hidden rounded-sm border border-[#19140035] px-3 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#1915014a] sm:inline-block lg:px-5 dark:border-[#3E3E3A] dark:text-[#EDEDEC] dark:hover:border-[#62605b]"
                                    >
                                        Dashboard
                                    </Link>
                                )}

                                {/* Appearance Toggle */}
                                <div className="">
                                    <AppearanceToggleDropdown className="dark:text-white" />
                                </div>

                                {/* 🔔 NOTIFICATION BELL */}
                                {!isAdmin && (
                                    <DropdownMenu
                                        open={dropdownOpen}
                                        onOpenChange={(open) => {
                                            setDropdownOpen(open);
                                            if (open) fetchNotifications();
                                        }}
                                    >
                                        <DropdownMenuTrigger asChild>
                                            <button className="relative rounded-full p-1.5 hover:bg-gray-100 focus:outline-none sm:p-2 dark:hover:bg-gray-800">
                                                <Bell className="h-4 w-4 text-gray-700 sm:h-5 sm:w-5 dark:text-gray-300" />
                                                {unreadCount > 0 && (
                                                    <Badge className="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center bg-red-500 p-0 text-[10px] text-white sm:h-5 sm:w-5 sm:text-xs">
                                                        {unreadCount > 9
                                                            ? '9+'
                                                            : unreadCount}
                                                    </Badge>
                                                )}
                                            </button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent
                                            align="end"
                                            className="w-[calc(100vw-2rem)] sm:w-80"
                                        >
                                            <DropdownMenuLabel className="flex items-center justify-between px-3 py-2 sm:px-4">
                                                <span className="text-sm font-semibold sm:text-base">
                                                    Notifications
                                                </span>
                                                {unreadCount > 0 && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-auto p-0 text-xs text-blue-600 hover:text-blue-700"
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            markAllAsRead();
                                                        }}
                                                    >
                                                        Mark all as read
                                                    </Button>
                                                )}
                                            </DropdownMenuLabel>
                                            <DropdownMenuSeparator />
                                            <ScrollArea className="h-[50vh] sm:h-[400px]">
                                                {isLoadingNotifications ? (
                                                    <div className="p-4 text-center text-sm text-gray-500">
                                                        Loading notifications...
                                                    </div>
                                                ) : notifications.length ===
                                                  0 ? (
                                                    <div className="p-4 text-center text-sm text-gray-500">
                                                        No notifications yet
                                                    </div>
                                                ) : (
                                                    notifications.map(
                                                        (notification) => (
                                                            <DropdownMenuItem
                                                                key={
                                                                    notification.id
                                                                }
                                                                className={`flex cursor-pointer flex-col items-start p-2.5 sm:p-3 ${
                                                                    !notification.read
                                                                        ? 'bg-blue-50 dark:bg-blue-950'
                                                                        : ''
                                                                }`}
                                                                onClick={() =>
                                                                    handleNotificationClick(
                                                                        notification,
                                                                    )
                                                                }
                                                            >
                                                                <div className="flex w-full items-start gap-2">
                                                                    {notification
                                                                        .data
                                                                        .image && (
                                                                        <img
                                                                            src={
                                                                                notification
                                                                                    .data
                                                                                    .image
                                                                            }
                                                                            alt="Dog"
                                                                            className="h-8 w-8 shrink-0 rounded object-cover sm:h-10 sm:w-10"
                                                                        />
                                                                    )}
                                                                    <div className="min-w-0 flex-1">
                                                                        <p className="text-xs font-medium sm:text-sm">
                                                                            {
                                                                                notification.title
                                                                            }
                                                                        </p>
                                                                        <p className="mt-0.5 text-[11px] text-gray-600 sm:mt-1 sm:text-xs dark:text-gray-400">
                                                                            {
                                                                                notification.message
                                                                            }
                                                                        </p>
                                                                        <p className="mt-0.5 text-[10px] text-gray-500 sm:mt-1 sm:text-xs">
                                                                            {formatTimeAgo(
                                                                                notification.created_at,
                                                                            )}
                                                                        </p>
                                                                    </div>
                                                                    {!notification.read && (
                                                                        <div className="mt-1 h-2 w-2 shrink-0 rounded-full bg-blue-600" />
                                                                    )}
                                                                </div>
                                                            </DropdownMenuItem>
                                                        ),
                                                    )
                                                )}
                                            </ScrollArea>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                )}

                                {/* User Avatar Dropdown */}
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <button className="flex items-center gap-2 rounded-full focus:ring-2 focus:ring-offset-2 focus:outline-none">
                                            <Avatar className="h-7 w-7 cursor-pointer sm:h-8 sm:w-8">
                                                <AvatarImage
                                                    src={auth.user.avatar}
                                                    alt={auth.user.name}
                                                />
                                                <AvatarFallback className="bg-neutral-200 text-xs text-black sm:text-sm dark:bg-neutral-700 dark:text-white">
                                                    {getInitials(
                                                        auth.user.name,
                                                    )}
                                                </AvatarFallback>
                                            </Avatar>
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        align="end"
                                        className="w-52 sm:w-56"
                                    >
                                        <DropdownMenuLabel>
                                            <div className="flex flex-col space-y-1">
                                                <p className="truncate text-sm leading-none font-medium">
                                                    {auth.user.name}
                                                </p>
                                                <p className="truncate text-xs leading-none text-muted-foreground">
                                                    {auth.user.email}
                                                </p>
                                            </div>
                                        </DropdownMenuLabel>
                                        <DropdownMenuSeparator />

                                        <DropdownMenuItem
                                            onClick={handleLogout}
                                            className="cursor-pointer text-red-600 focus:text-red-600"
                                        >
                                            <LogOut className="mr-2 h-4 w-4" />
                                            <span>Log out</span>
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </>
                        ) : (
                            <>
                                <div className="">
                                    <AppearanceToggleDropdown className="dark:text-white" />
                                </div>
                            </>
                        )}
                    </div>
                </nav>
            </Card>
        </div>
    );
}
