import AuthLayout from '@/layouts/auth-layout';
import { usePage } from '@inertiajs/react';
import { CheckCircle2, Shield } from 'lucide-react';

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}

type Errors = {
    flash?: {
        error?: string;
    };
};

export default function Login({
    status,
    canResetPassword,
    canRegister,
}: LoginProps) {
    const { flash } = usePage<Errors>().props;

    return (
        <AuthLayout
            title="Dog Breed Identification System"
            description="Sign in to access professional breed analysis"
        >
            <div className="flex flex-col gap-6">
                {/* Status Message */}
                {status && (
                    <div className="flex items-start gap-3 rounded-xl border border-green-200 bg-gradient-to-r from-green-50 to-emerald-50 p-4 shadow-sm dark:border-green-900/50 dark:from-green-900/20 dark:to-emerald-900/20">
                        <CheckCircle2 className="h-5 w-5 flex-shrink-0 text-green-600 dark:text-green-400" />
                        <p className="text-sm text-green-800 dark:text-green-300">
                            {status}
                        </p>
                    </div>
                )}

                {/* Error Message */}
                {flash?.error && (
                    <div className="flex items-start gap-3 rounded-xl border border-red-200 bg-gradient-to-r from-red-50 to-rose-50 p-4 shadow-sm dark:border-red-900/50 dark:from-red-900/20 dark:to-rose-900/20">
                        <svg
                            className="h-5 w-5 flex-shrink-0 text-red-600 dark:text-red-400"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth={2}
                                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                        <p className="text-sm text-red-800 dark:text-red-300">
                            {flash.error}
                        </p>
                    </div>
                )}

                {/* Google Sign-In Button */}
                <div className="mt-2">
                    <button
                        onClick={() => (window.location.href = '/auth/google')}
                        className="group relative flex w-full items-center justify-center gap-3 overflow-hidden rounded-xl border-2 border-gray-200 bg-white px-6 py-4 text-base font-semibold text-gray-900 shadow-md transition-all duration-200 hover:-translate-y-0.5 hover:border-gray-300 hover:shadow-lg focus:ring-4 focus:ring-violet-500/20 focus:outline-none dark:border-gray-700 dark:bg-neutral-900 dark:text-white dark:hover:border-gray-600"
                    >
                        {/* Subtle gradient overlay on hover */}
                        <div className="absolute inset-0 bg-gradient-to-r from-violet-500/0 via-purple-500/0 to-violet-500/0 opacity-0 transition-opacity duration-300 group-hover:opacity-5" />

                        {/* Google "G" Logo SVG */}
                        <svg
                            className="h-6 w-6 flex-shrink-0"
                            viewBox="0 0 24 24"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path
                                d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                                fill="#4285F4"
                            />
                            <path
                                d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                                fill="#34A853"
                            />
                            <path
                                d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"
                                fill="#FBBC05"
                            />
                            <path
                                d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
                                fill="#EA4335"
                            />
                        </svg>
                        <span className="relative">Continue with Google</span>
                    </button>

                    {/* Trust Indicators */}
                    <div className="mt-6 space-y-3">
                        <div className="flex items-center justify-center gap-6 text-xs text-gray-500 dark:text-gray-400">
                            <div className="flex items-center gap-1.5">
                                <Shield className="h-4 w-4 text-violet-500" />
                                <span>Secure Login</span>
                            </div>
                            <div className="h-4 w-px bg-gray-300 dark:bg-gray-600" />
                            <div className="flex items-center gap-1.5">
                                <CheckCircle2 className="h-4 w-4 text-green-500" />
                                <span>Vet Verified</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthLayout>
    );
}
