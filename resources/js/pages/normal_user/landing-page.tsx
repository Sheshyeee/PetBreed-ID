import { Button } from '@/components/ui/button';
import { login } from '@/routes';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    Calendar,
    Camera,
    Download,
    Heart,
    MapPin,
    PawPrintIcon,
    QrCode,
    ShieldCheck,
    Smartphone,
    X,
} from 'lucide-react';
import { useState } from 'react';

function LandingPage() {
    const [open, setOpen] = useState(false);
    const [showQRModal, setShowQRModal] = useState(false);
    const { auth } = usePage<SharedData>().props;

    // Check if user is admin
    const allowedEmails = ['modeltraining2000@gmail.com'];
    const isAdmin = auth.user && allowedEmails.includes(auth.user.email);

    // Determine scan button link
    const getScanLink = () => {
        if (!auth.user) {
            return login(); // Not logged in -> go to login
        }
        if (isAdmin) {
            return '/dashboard'; // Admin -> go to dashboard
        }
        return '/scan'; // Regular user -> go to scan directly
    };

    return (
        <>
            {/* QR Code Modal Overlay */}
            {showQRModal && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
                    onClick={() => setShowQRModal(false)}
                >
                    <div
                        className="relative w-full max-w-md rounded-2xl bg-white p-8 shadow-2xl dark:bg-gray-900"
                        onClick={(e) => e.stopPropagation()}
                    >
                        {/* Close Button */}
                        <button
                            onClick={() => setShowQRModal(false)}
                            className="absolute top-4 right-4 rounded-full p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                        >
                            <X size={20} />
                        </button>

                        {/* Header */}
                        <div className="mb-6 text-center">
                            <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br from-orange-500 via-pink-500 to-rose-500 shadow-lg">
                                <Smartphone size={32} className="text-white" />
                            </div>
                            <h2 className="bg-gradient-to-r from-orange-600 to-rose-600 bg-clip-text text-2xl font-bold text-transparent">
                                Install Mobile App
                            </h2>
                            <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                Scan the QR code with your mobile device to
                                download and install the app
                            </p>
                        </div>

                        {/* QR Code */}
                        <div className="mb-6 flex justify-center">
                            <div className="rounded-2xl bg-white p-4 shadow-lg ring-1 ring-gray-200 dark:ring-gray-700">
                                <img
                                    src="/qr-code.png"
                                    alt="QR Code for App Installation"
                                    className="h-48 w-48"
                                />
                            </div>
                        </div>

                        {/* Instructions */}
                        <div className="space-y-3 rounded-xl bg-gradient-to-br from-orange-50 to-rose-50 p-4 dark:from-orange-950/20 dark:to-rose-950/20">
                            <div className="flex items-start gap-3">
                                <div className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-orange-500 to-rose-500 text-sm font-semibold text-white shadow-md">
                                    1
                                </div>
                                <p className="text-sm text-gray-700 dark:text-gray-300">
                                    Open your camera app or QR code scanner
                                </p>
                            </div>
                            <div className="flex items-start gap-3">
                                <div className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-orange-500 to-rose-500 text-sm font-semibold text-white shadow-md">
                                    2
                                </div>
                                <p className="text-sm text-gray-700 dark:text-gray-300">
                                    Point your camera at the QR code above
                                </p>
                            </div>
                            <div className="flex items-start gap-3">
                                <div className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-orange-500 to-rose-500 text-sm font-semibold text-white shadow-md">
                                    3
                                </div>
                                <p className="text-sm text-gray-700 dark:text-gray-300">
                                    Follow the link to download and install the
                                    app
                                </p>
                            </div>
                        </div>

                        {/* Features */}
                        <div className="mt-6 space-y-2">
                            <div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                <Download
                                    size={16}
                                    className="text-orange-600"
                                />
                                <span>Fast & Easy Installation</span>
                            </div>
                            <div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                <Smartphone
                                    size={16}
                                    className="text-pink-600"
                                />
                                <span>Available on iOS & Android</span>
                            </div>
                            <div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                <Camera size={16} className="text-rose-600" />
                                <span>Access All Features On-The-Go</span>
                            </div>
                        </div>

                        {/* Action Button */}
                        <Button
                            onClick={() => setShowQRModal(false)}
                            className="mt-6 w-full bg-gradient-to-r from-orange-500 via-pink-500 to-rose-500 font-semibold hover:from-orange-600 hover:via-pink-600 hover:to-rose-600"
                        >
                            Close
                        </Button>
                    </div>
                </div>
            )}

            {/* Eye-Catching QR Banner - Top Position */}
            <div
                onClick={() => setShowQRModal(true)}
                className="group relative mb-4 animate-pulse cursor-pointer overflow-hidden rounded-xl bg-gradient-to-r from-orange-500 via-pink-500 to-rose-500 p-1 shadow-2xl transition-all hover:scale-[1.02] hover:shadow-orange-500/50"
            >
                <div className="flex items-center justify-between gap-4 rounded-lg bg-gradient-to-r from-orange-600 to-rose-600 p-4 sm:p-5">
                    <div className="flex flex-1 items-center gap-3 sm:gap-4">
                        <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white/20 backdrop-blur-sm sm:h-14 sm:w-14">
                            <QrCode
                                size={28}
                                className="text-white sm:h-8 sm:w-8"
                            />
                        </div>
                        <div className="flex-1">
                            <div className="flex items-center gap-2">
                                <h3 className="text-base font-bold text-white sm:text-lg">
                                    📱 Download Our Mobile App!
                                </h3>
                                <span className="rounded-full bg-white/30 px-2 py-0.5 text-xs font-bold text-white backdrop-blur-sm">
                                    NEW
                                </span>
                            </div>
                            <p className="mt-1 text-xs text-white/90 sm:text-sm">
                                Scan QR code • Install instantly • Identify dogs
                                anywhere
                            </p>
                        </div>
                    </div>
                    <div className="hidden items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-bold text-rose-600 transition-all group-hover:gap-3 sm:flex">
                        <Smartphone size={18} />
                        <span>Get App</span>
                    </div>
                    {/* Mobile button */}
                    <div className="flex shrink-0 items-center justify-center rounded-lg bg-white p-2 sm:hidden">
                        <Smartphone size={20} className="text-rose-600" />
                    </div>
                </div>
                {/* Animated border glow */}
                <div className="absolute inset-0 -z-10 animate-pulse rounded-xl bg-gradient-to-r from-orange-400 via-pink-400 to-rose-400 opacity-50 blur-xl"></div>
            </div>

            <div className="flex w-full flex-col gap-4 lg:flex-row">
                <div className="flex w-full flex-col gap-4">
                    {/* Hero Section */}
                    <div className="flex h-auto w-full flex-col items-center justify-between gap-4 rounded-lg bg-[#0C134F] p-6 text-white sm:p-8 lg:h-[300px] lg:flex-row lg:gap-3">
                        <div className="flex h-auto flex-1 flex-col justify-center gap-3 text-center lg:h-[270px] lg:gap-4 lg:text-left">
                            <button className="mx-auto flex w-fit items-center gap-2 rounded-md bg-white/20 px-4 py-2 text-xs font-medium hover:bg-white/30 lg:mx-0">
                                <PawPrintIcon className="h-4 w-4" />
                            </button>
                            <h1 className="text-2xl font-bold sm:text-3xl lg:text-3xl">
                                Identify dog
                                <span className="text-violet-500">
                                    {' '}
                                    breed
                                </span>{' '}
                                instantly
                            </h1>
                            <p className="mx-auto max-w-md text-xs text-white/70 sm:text-sm lg:mx-0">
                                Upload a photo and get accurate breed
                                identification powered by advanced technology
                            </p>
                            <Link href={getScanLink()}>
                                <Button
                                    variant="outline"
                                    className="w-[300px] text-black dark:bg-white/20 dark:text-white"
                                    onClick={() => setOpen(false)}
                                >
                                    Scan pet
                                </Button>
                            </Link>
                        </div>
                        <div className="ml-8 hidden lg:block">
                            <img
                                src="/paww.png"
                                alt="Dog"
                                className="mt-[130px] h-[100px] w-[100px] rounded-lg object-cover"
                            />
                        </div>
                    </div>

                    {/* Feature Cards */}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div className="flex min-h-[150px] flex-col justify-between rounded-lg bg-[#5C469C] p-4 text-white sm:p-6 lg:min-h-[170px]">
                            <div>
                                <h3 className="mb-2 text-sm font-semibold">
                                    Growth Simulation
                                </h3>
                                <p className="text-xs text-white/70">
                                    Visualize how your dog will look through
                                    different life stages from puppy to senior
                                </p>
                            </div>
                            <div className="mt-4 flex w-fit items-center gap-2 rounded-md bg-white/20 px-3 py-1.5 text-xs font-medium hover:bg-white/30 sm:px-4">
                                <Calendar className="h-3 w-3 sm:h-4 sm:w-4" />
                                <span className="hidden sm:inline">
                                    See simulation
                                </span>
                                <span className="sm:hidden">Simulate</span>
                            </div>
                        </div>

                        <div className="flex min-h-[150px] flex-col justify-between rounded-lg bg-[#5C469C] p-4 text-white sm:p-6 lg:min-h-[170px]">
                            <div>
                                <h3 className="mb-2 text-sm font-semibold">
                                    Health Risk Analysis
                                </h3>
                                <p className="text-xs text-white/70">
                                    Discover breed-specific health risks and get
                                    preventive care recommendations
                                </p>
                            </div>
                            <div className="mt-4 flex w-fit items-center gap-2 rounded-md bg-white/20 px-3 py-1.5 text-xs font-medium hover:bg-white/30 sm:px-4">
                                <Heart className="h-3 w-3 sm:h-4 sm:w-4" />
                                <span className="hidden sm:inline">
                                    View health risks
                                </span>
                                <span className="sm:hidden">View risks</span>
                            </div>
                        </div>

                        <div className="flex min-h-[150px] flex-col justify-between rounded-lg bg-[#5C469C] p-4 text-white sm:col-span-2 sm:p-6 lg:col-span-1 lg:min-h-[170px]">
                            <div>
                                <h3 className="mb-2 text-sm font-semibold">
                                    Origin & History
                                </h3>
                                <p className="text-xs text-white/70">
                                    Learn about your dog's breed origins,
                                    historical purpose, and cultural
                                    significance
                                </p>
                            </div>
                            <div className="mt-4 flex w-fit items-center gap-2 rounded-md bg-white/20 px-3 py-1.5 text-xs font-medium hover:bg-white/30 sm:px-4">
                                <MapPin className="h-3 w-3 sm:h-4 sm:w-4" />
                                <span className="hidden sm:inline">
                                    Explore history
                                </span>
                                <span className="sm:hidden">Explore</span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Side Card - Updated with Veterinary Verification Message */}
                <div className="w-full rounded-lg bg-[#1D267D] p-4 sm:p-6 lg:w-[400px]">
                    <img
                        src="/dog1.png"
                        className="mb-4 h-[200px] w-full rounded-lg object-cover sm:h-[250px]"
                        alt="Dog breed identification"
                    />

                    <h2 className="sm:text-md mb-1 text-base font-bold text-white">
                        Professional Breed Analysis You Can Trust
                    </h2>

                    {/* Veterinary Verification Badge */}
                    <div className="mb-1 flex items-center gap-2 rounded-lg bg-white/10 px-3 py-2 backdrop-blur-sm">
                        <div className="flex h-8 w-8 items-center justify-center rounded-full bg-green-500/20">
                            <ShieldCheck className="h-5 w-5 text-green-400" />
                        </div>
                        <div className="flex-1">
                            <p className="text-xs font-semibold text-white">
                                Veterinary Verified
                            </p>
                            <p className="text-xs text-white/60">
                                Licensed vet reviews predictions
                            </p>
                        </div>
                    </div>

                    {/* Trust Indicators */}

                    <div className="border-t border-white/20 pt-4">
                        <Link href={login()}>
                            <Button
                                variant="outline"
                                className="w-full font-semibold hover:bg-white hover:text-[#1D267D]"
                                onClick={() => setOpen(false)}
                            >
                                Get Started Now
                            </Button>
                        </Link>
                    </div>
                </div>
            </div>
        </>
    );
}

export default LandingPage;
