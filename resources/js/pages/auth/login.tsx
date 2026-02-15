import AuthLayout from '@/layouts/auth-layout';
import { usePage } from '@inertiajs/react';

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
            description="Sign in to access your account"
        >
            <div className="flex flex-col gap-6">
                {/* Status Message */}
                {status && (
                    <div className="rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800 dark:border-green-900/50 dark:bg-green-900/20 dark:text-green-300">
                        {status}
                    </div>
                )}

                {/* Error Message */}
                {flash?.error && (
                    <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-300">
                        {flash.error}
                    </div>
                )}

                {/* Google Sign-In Button */}
                <div className="mt-2">
                    <button
                        onClick={() => (window.location.href = '/auth/google')}
                        className="group relative flex w-full items-center justify-center gap-3 rounded-lg border border-gray-300 bg-white px-8 py-3.5 text-base font-medium text-gray-700 shadow-sm transition-all hover:bg-gray-50 hover:shadow-md focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none dark:border-gray-600 dark:bg-neutral-800 dark:text-gray-200 dark:hover:bg-neutral-700"
                    >
                        {/* Google "G" Logo SVG */}
                        <svg
                            className="h-5 w-5"
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
                        <span>Sign in with Google</span>
                    </button>

                    {/* Security & Privacy Note */}
                    <div className="mt-6 text-center">
                        <p className="text-xs text-gray-500 dark:text-gray-400">
                            Secure connection
                        </p>
                    </div>
                </div>
            </div>
        </AuthLayout>
    );
}
