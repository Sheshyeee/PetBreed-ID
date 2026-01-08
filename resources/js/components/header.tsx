import { dashboard, login } from '@/routes';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';
import { useEffect, useState } from 'react';
import AppearanceToggleDropdown from './appearance-dropdown';

export default function Header() {
    const { auth } = usePage<SharedData>().props;
    const { appearance } = useAppearance();

    const [resolvedTheme, setResolvedTheme] = useState<'light' | 'dark'>(
        'light',
    );

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

    const appearanceLabel =
        appearance === 'system'
            ? `System (${resolvedTheme === 'dark' ? 'Dark' : 'Light'})`
            : appearance === 'dark'
              ? 'Dark Mode'
              : 'Light Mode';
    const page = usePage();

    return (
        <header className="text-md w-full border-2 not-has-[nav]:hidden">
            <nav className="flex items-center justify-evenly gap-[900px] p-4">
                <Link href="/">
                    <h1 className="text-black dark:text-white">PetBreedID</h1>
                </Link>

                <div className="flex items-center gap-3">
                    {auth.user ? (
                        <Link
                            href={dashboard()}
                            className="inline-block rounded-sm border border-[#19140035] px-5 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#1915014a] dark:border-[#3E3E3A] dark:text-[#EDEDEC] dark:hover:border-[#62605b]"
                        >
                            Dashboard
                        </Link>
                    ) : (
                        <>
                            <AppearanceToggleDropdown className="dark:text-white" />

                            {page.url === '/' && (
                                <Button variant="outline">
                                    <Link href={login()}>Vet Portal</Link>
                                </Button>
                            )}
                        </>
                    )}
                </div>
            </nav>
        </header>
    );
}
