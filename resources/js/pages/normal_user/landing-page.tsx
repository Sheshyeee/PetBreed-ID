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
                            <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br from-[#5C469C] to-[#0C134F]">
                                <Smartphone size={32} className="text-white" />
                            </div>
                            <h2 className="text-2xl font-bold text-gray-900 dark:text-white">
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
                                    src="/doglens_apk_qr.jpeg"
                                    alt="QR Code for App Installation"
                                    className="h-48 w-48"
                                />
                            </div>
                        </div>

                        {/* Instructions */}
                        <div className="space-y-3 rounded-xl bg-gradient-to-br from-[#0C134F]/5 to-[#5C469C]/5 p-4 dark:from-[#0C134F]/20 dark:to-[#5C469C]/20">
                            <div className="flex items-start gap-3">
                                <div className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#5C469C] text-sm font-semibold text-white">
                                    1
                                </div>
                                <p className="text-sm text-gray-700 dark:text-gray-300">
                                    Open your camera app or QR code scanner
                                </p>
                            </div>
                            <div className="flex items-start gap-3">
                                <div className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#5C469C] text-sm font-semibold text-white">
                                    2
                                </div>
                                <p className="text-sm text-gray-700 dark:text-gray-300">
                                    Point your camera at the QR code above
                                </p>
                            </div>
                            <div className="flex items-start gap-3">
                                <div className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#5C469C] text-sm font-semibold text-white">
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
                                    className="text-[#5C469C]"
                                />
                                <span>Fast & Easy Installation</span>
                            </div>
                            <div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                <Smartphone
                                    size={16}
                                    className="text-[#5C469C]"
                                />
                                <span>Available on iOS & Android</span>
                            </div>
                            <div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                <Camera size={16} className="text-[#5C469C]" />
                                <span>Access All Features On-The-Go</span>
                            </div>
                        </div>

                        {/* Action Button */}
                        <Button
                            onClick={() => setShowQRModal(false)}
                            className="mt-6 w-full bg-gradient-to-r from-[#5C469C] to-[#0C134F] hover:from-[#4a3880] hover:to-[#0a0f3d]"
                        >
                            Close
                        </Button>
                    </div>
                </div>
            )}

            {/* Floating QR Button */}
            <button
                onClick={() => setShowQRModal(true)}
                className="fixed right-6 bottom-6 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-[#5C469C] to-[#0C134F] text-white shadow-lg transition-all hover:scale-110 hover:shadow-xl"
                title="Install Mobile App"
            >
                <QrCode size={24} />
            </button>

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
