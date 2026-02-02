import { dashboard, login } from '@/routes';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import {
    NavigationMenu,
    NavigationMenuContent,
    NavigationMenuItem,
    NavigationMenuList,
    NavigationMenuTrigger,
} from '@/components/ui/navigation-menu';
import { Sheet, SheetContent, SheetTrigger } from '@/components/ui/sheet';
import { useAppearance } from '@/hooks/use-appearance';
import { Menu, PawPrint } from 'lucide-react';
import { useEffect, useState } from 'react';
import AppearanceToggleDropdown from './appearance-dropdown';
import { Card } from './ui/card';

export default function Header() {
    const { auth } = usePage<SharedData>().props;
    const { appearance } = useAppearance();
    const [open, setOpen] = useState(false);

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

    const page = usePage();

    return (
        <div className="px-4 py-5 sm:px-6 lg:px-25">
            <Card className="text-md w-full border-2 p-2 px-4 sm:px-8 lg:px-12">
                <nav className="flex items-center justify-between gap-2 sm:gap-4">
                    {/* Logo */}
                    <Link href="/" className="flex shrink-0 items-center gap-1">
                        <div className="flex aspect-square size-7 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                            <PawPrint className="size-4 fill-current text-white dark:text-black" />
                        </div>
                        <div className="text-md ml-1 grid flex-1 text-left">
                            <span className="mb-0.5 truncate text-sm leading-tight font-semibold sm:text-base">
                                Pet Breed ID
                            </span>
                        </div>
                    </Link>

                    {/* Desktop Navigation */}
                    <div className="hidden lg:block">
                        <NavigationMenu>
                            <NavigationMenuList>
                                <NavigationMenuItem>
                                    <NavigationMenuTrigger>
                                        Features
                                    </NavigationMenuTrigger>
                                    <NavigationMenuContent>
                                        <div className="grid w-[400px] gap-3 p-4">
                                            <div className="block space-y-1 rounded-md p-3 hover:bg-accent">
                                                <div className="font-medium">
                                                    Breed Identification
                                                </div>
                                                <p className="text-sm text-muted-foreground">
                                                    Instantly identify your
                                                    dog's breed from a photo
                                                </p>
                                            </div>
                                            <div className="block space-y-1 rounded-md p-3 hover:bg-accent">
                                                <div className="font-medium">
                                                    Growth Simulation
                                                </div>
                                                <p className="text-sm text-muted-foreground">
                                                    See how your dog will look
                                                    through the years
                                                </p>
                                            </div>
                                            <div className="block space-y-1 rounded-md p-3 hover:bg-accent">
                                                <div className="font-medium">
                                                    Health Risk Analysis
                                                </div>
                                                <p className="text-sm text-muted-foreground">
                                                    Get breed-specific health
                                                    insights and care tips
                                                </p>
                                            </div>
                                            <div className="block space-y-1 rounded-md p-3 hover:bg-accent">
                                                <div className="font-medium">
                                                    Breed History
                                                </div>
                                                <p className="text-sm text-muted-foreground">
                                                    Discover your dog's origins
                                                    and heritage
                                                </p>
                                            </div>
                                        </div>
                                    </NavigationMenuContent>
                                </NavigationMenuItem>

                                <NavigationMenuItem>
                                    <NavigationMenuTrigger>
                                        Resources
                                    </NavigationMenuTrigger>
                                    <NavigationMenuContent>
                                        <div className="grid w-[300px] gap-3 p-4">
                                            <div className="block space-y-1 rounded-md p-3 hover:bg-accent">
                                                <div className="font-medium">
                                                    Breed Database
                                                </div>
                                                <p className="text-sm text-muted-foreground">
                                                    Access information on 100+
                                                    dog breeds
                                                </p>
                                            </div>
                                            <div className="block space-y-1 rounded-md p-3 hover:bg-accent">
                                                <div className="font-medium">
                                                    Training Tips
                                                </div>
                                                <p className="text-sm text-muted-foreground">
                                                    Breed-specific training
                                                    recommendations
                                                </p>
                                            </div>
                                            <div className="block space-y-1 rounded-md p-3 hover:bg-accent">
                                                <div className="font-medium">
                                                    Nutrition Guide
                                                </div>
                                                <p className="text-sm text-muted-foreground">
                                                    Dietary needs for different
                                                    breeds
                                                </p>
                                            </div>
                                        </div>
                                    </NavigationMenuContent>
                                </NavigationMenuItem>

                                <NavigationMenuItem>
                                    <NavigationMenuTrigger>
                                        About
                                    </NavigationMenuTrigger>
                                    <NavigationMenuContent>
                                        <div className="w-[300px] p-4">
                                            <div className="space-y-3">
                                                <div className="block space-y-1 rounded-md p-3 hover:bg-accent">
                                                    <div className="font-medium">
                                                        How It Works
                                                    </div>
                                                    <p className="text-sm text-muted-foreground">
                                                        Learn about our breed
                                                        identification process
                                                    </p>
                                                </div>
                                                <div className="block space-y-1 rounded-md p-3 hover:bg-accent">
                                                    <div className="font-medium">
                                                        Accuracy & Data
                                                    </div>
                                                    <p className="text-sm text-muted-foreground">
                                                        Our commitment to
                                                        reliable breed
                                                        information
                                                    </p>
                                                </div>
                                                <div className="block space-y-1 rounded-md p-3 hover:bg-accent">
                                                    <div className="font-medium">
                                                        For Veterinarians
                                                    </div>
                                                    <p className="text-sm text-muted-foreground">
                                                        Professional tools for
                                                        animal healthcare
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </NavigationMenuContent>
                                </NavigationMenuItem>
                            </NavigationMenuList>
                        </NavigationMenu>
                    </div>

                    {/* Right Side Actions */}
                    <div className="flex items-center gap-2 sm:gap-3">
                        {auth.user ? (
                            <Link
                                href={dashboard()}
                                className="inline-block rounded-sm border border-[#19140035] px-3 py-1.5 text-xs leading-normal text-[#1b1b18] hover:border-[#1915014a] sm:px-5 sm:text-sm dark:border-[#3E3E3A] dark:text-[#EDEDEC] dark:hover:border-[#62605b]"
                            >
                                Dashboard
                            </Link>
                        ) : (
                            <>
                                {/* Theme Toggle - Always visible */}
                                <AppearanceToggleDropdown className="dark:text-white" />

                                {/* Vet Portal - Desktop only */}
                                {page.url === '/' && (
                                    <Button
                                        variant="outline"
                                        className="hidden h-[30px] px-4 text-sm lg:inline-flex"
                                    >
                                        <Link href={login()}>Vet Portal</Link>
                                    </Button>
                                )}
                            </>
                        )}

                        {/* Mobile Menu */}
                        <Sheet open={open} onOpenChange={setOpen}>
                            <SheetTrigger asChild className="lg:hidden">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="h-8 w-8"
                                >
                                    <Menu className="h-5 w-5" />
                                </Button>
                            </SheetTrigger>
                            <SheetContent
                                side="right"
                                className="w-[300px] overflow-y-auto sm:w-[400px]"
                            >
                                <div className="mt-6 flex flex-col gap-6">
                                    {/* Features */}
                                    <div>
                                        <h3 className="mb-3 font-semibold">
                                            Features
                                        </h3>
                                        <div className="space-y-3">
                                            <div className="space-y-1 rounded-md p-3 hover:bg-accent">
                                                <div className="text-sm font-medium">
                                                    Breed Identification
                                                </div>
                                                <p className="text-xs text-muted-foreground">
                                                    Instantly identify your
                                                    dog's breed
                                                </p>
                                            </div>
                                            <div className="space-y-1 rounded-md p-3 hover:bg-accent">
                                                <div className="text-sm font-medium">
                                                    Growth Simulation
                                                </div>
                                                <p className="text-xs text-muted-foreground">
                                                    See how your dog will look
                                                    through the years
                                                </p>
                                            </div>
                                            <div className="space-y-1 rounded-md p-3 hover:bg-accent">
                                                <div className="text-sm font-medium">
                                                    Health Risk Analysis
                                                </div>
                                                <p className="text-xs text-muted-foreground">
                                                    Get breed-specific health
                                                    insights
                                                </p>
                                            </div>
                                            <div className="space-y-1 rounded-md p-3 hover:bg-accent">
                                                <div className="text-sm font-medium">
                                                    Breed History
                                                </div>
                                                <p className="text-xs text-muted-foreground">
                                                    Discover your dog's origins
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Resources */}
                                    <div>
                                        <h3 className="mb-3 font-semibold">
                                            Resources
                                        </h3>
                                        <div className="space-y-3">
                                            <div className="space-y-1 rounded-md p-3 hover:bg-accent">
                                                <div className="text-sm font-medium">
                                                    Breed Database
                                                </div>
                                                <p className="text-xs text-muted-foreground">
                                                    100+ dog breeds information
                                                </p>
                                            </div>
                                            <div className="space-y-1 rounded-md p-3 hover:bg-accent">
                                                <div className="text-sm font-medium">
                                                    Training Tips
                                                </div>
                                                <p className="text-xs text-muted-foreground">
                                                    Breed-specific training
                                                </p>
                                            </div>
                                            <div className="space-y-1 rounded-md p-3 hover:bg-accent">
                                                <div className="text-sm font-medium">
                                                    Nutrition Guide
                                                </div>
                                                <p className="text-xs text-muted-foreground">
                                                    Dietary needs for breeds
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    {/* About */}
                                    <div>
                                        <h3 className="mb-3 font-semibold">
                                            About
                                        </h3>
                                        <div className="space-y-3">
                                            <div className="space-y-1 rounded-md p-3 hover:bg-accent">
                                                <div className="text-sm font-medium">
                                                    How It Works
                                                </div>
                                                <p className="text-xs text-muted-foreground">
                                                    Learn about our process
                                                </p>
                                            </div>
                                            <div className="space-y-1 rounded-md p-3 hover:bg-accent">
                                                <div className="text-sm font-medium">
                                                    Accuracy & Data
                                                </div>
                                                <p className="text-xs text-muted-foreground">
                                                    Reliable breed information
                                                </p>
                                            </div>
                                            <div className="space-y-1 rounded-md p-3 hover:bg-accent">
                                                <div className="text-sm font-medium">
                                                    For Veterinarians
                                                </div>
                                                <p className="text-xs text-muted-foreground">
                                                    Professional tools
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Vet Portal Button - Mobile only */}
                                    {!auth.user && page.url === '/' && (
                                        <div className="border-t pt-4">
                                            <Button
                                                variant="outline"
                                                className="w-full"
                                                onClick={() => setOpen(false)}
                                            >
                                                <Link href={login()}>
                                                    Vet Portal
                                                </Link>
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            </SheetContent>
                        </Sheet>
                    </div>
                </nav>
            </Card>
        </div>
    );
}
