import { Button } from '@/components/ui/button';
import { login } from '@/routes';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    Calendar,
    Heart,
    MapPin,
    PawPrintIcon,
    ShieldCheck,
    Stethoscope,
} from 'lucide-react';
import { useState } from 'react';

function LandingPage() {
    const [open, setOpen] = useState(false);
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
                            <span className="text-violet-500"> breed</span>{' '}
                            instantly
                        </h1>
                        <p className="mx-auto max-w-md text-xs text-white/70 sm:text-sm lg:mx-0">
                            Upload a photo and get accurate breed identification
                            powered by advanced AI technology
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
                                Learn about your dog's breed origins, historical
                                purpose, and cultural significance
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

                {/* Veterinary Verification Badge */}
                <div className="mb-4 flex items-center gap-2 rounded-lg bg-white/10 px-3 py-2 backdrop-blur-sm">
                    <div className="flex h-8 w-8 items-center justify-center rounded-full bg-green-500/20">
                        <ShieldCheck className="h-5 w-5 text-green-400" />
                    </div>
                    <div className="flex-1">
                        <p className="text-xs font-semibold text-white">
                            Veterinary Verified
                        </p>
                        <p className="text-xs text-white/60">
                            Licensed vet reviews all predictions
                        </p>
                    </div>
                </div>

                <h2 className="mb-2 text-base font-bold text-white sm:text-lg">
                    Professional Breed Analysis You Can Trust
                </h2>
                <p className="mb-4 text-xs text-white/70 sm:text-sm">
                    Our AI-powered predictions are reviewed and verified by
                    licensed veterinarians to ensure accuracy and reliability
                    for your peace of mind.
                </p>

                {/* Trust Indicators */}
                <div className="mb-4 space-y-2">
                    <div className="flex items-start gap-2">
                        <Stethoscope className="mt-0.5 h-4 w-4 flex-shrink-0 text-violet-400" />
                        <p className="text-xs text-white/80">
                            All breed identifications validated by certified
                            veterinary professionals
                        </p>
                    </div>
                    <div className="flex items-start gap-2">
                        <ShieldCheck className="mt-0.5 h-4 w-4 flex-shrink-0 text-green-400" />
                        <p className="text-xs text-white/80">
                            Expert oversight ensures reliable results for
                            informed pet care decisions
                        </p>
                    </div>
                </div>

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
    );
}

export default LandingPage;
