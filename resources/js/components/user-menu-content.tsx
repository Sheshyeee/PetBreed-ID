import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import { type User } from '@/types';
import { Link, router } from '@inertiajs/react';
import { LogOut, Settings } from 'lucide-react';
import { Card } from './ui/card';

interface UserMenuContentProps {
    user: User;
    inline?: boolean;
}

export function UserMenuContent({
    user,
    inline = false,
}: UserMenuContentProps) {
    const cleanup = useMobileNavigation();

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    if (inline) {
        return (
            <>
                {' '}
                <Card className="p-4">
                    <UserInfo user={user} showEmail={true} />
                    <div className="h-px w-full bg-border" />
                    <div className="mt-[-8px] flex w-full flex-col justify-items-start gap-2 pr-14">
                        <Link
                            href={edit()}
                            as="button"
                            prefetch
                            onClick={cleanup}
                            className="text-[14px]"
                        >
                            <Settings className="mr-1 inline h-5 w-5" />
                            Settings
                        </Link>
                        <Link
                            href={logout()}
                            as="button"
                            onClick={handleLogout}
                            data-test="logout-button"
                            className="text-[14px]"
                        >
                            <LogOut className="mr-1 inline h-5 w-5" />
                            Log out
                        </Link>
                    </div>
                </Card>
            </>
        );
    }
}
