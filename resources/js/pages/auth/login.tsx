import AuthLayout from '@/layouts/auth-layout';
import { usePage } from '@inertiajs/react';
import { CheckCircle2, PawPrint, Shield, XCircle } from 'lucide-react';

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
            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap');

                @keyframes lg-pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.35;transform:scale(1.5)} }
                @keyframes lg-ring  { 0%{transform:scale(.85);opacity:.6} 70%,100%{transform:scale(1.2);opacity:0} }
                @keyframes lg-paw   { 0%,100%{transform:rotate(0deg)} 30%{transform:rotate(-8deg)} 70%{transform:rotate(8deg)} }
                @keyframes lg-fade  { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }
                @keyframes lg-slide { from{opacity:0;transform:translateY(-5px)} to{opacity:1;transform:translateY(0)} }
                @keyframes lg-sweep { 0%{left:-80%} 100%{left:160%} }

                .lg-root * { box-sizing:border-box; font-family:'Plus Jakarta Sans',sans-serif; }
                .lg-mono  { font-family:'JetBrains Mono',monospace !important; }

                .lg-f1 { animation:lg-fade .4s cubic-bezier(.16,1,.3,1) .05s both; }
                .lg-f2 { animation:lg-fade .4s cubic-bezier(.16,1,.3,1) .15s both; }
                .lg-f3 { animation:lg-fade .4s cubic-bezier(.16,1,.3,1) .25s both; }
                .lg-f4 { animation:lg-fade .4s cubic-bezier(.16,1,.3,1) .35s both; }

                .lg-btn {
                    position:relative; overflow:hidden; width:100%;
                    display:flex; align-items:center; justify-content:center; gap:10px;
                    padding:13px 20px; border-radius:12px;
                    background:#ffffff; border:1.5px solid #e2e8f0;
                    color:#1e293b; font-size:14px; font-weight:700;
                    cursor:pointer; transition:transform .18s, box-shadow .18s, border-color .18s;
                    font-family:'Plus Jakarta Sans',sans-serif;
                }
                .lg-btn:hover { transform:translateY(-1px); box-shadow:0 8px 28px rgba(0,0,0,.1); border-color:#cbd5e1; }
                .lg-btn:active { transform:translateY(0); }
                .lg-btn-shine {
                    position:absolute; top:0; left:-80%; width:40%; height:100%;
                    background:linear-gradient(90deg,transparent,rgba(255,255,255,.6),transparent);
                    transform:skewX(-18deg); pointer-events:none;
                }
                .lg-btn:hover .lg-btn-shine { animation:lg-sweep .55s ease forwards; }

                @media (prefers-color-scheme:dark) {
                    .lg-btn { background:#1e293b; border-color:rgba(255,255,255,.08); color:#f1f5f9; }
                    .lg-btn:hover { box-shadow:0 8px 28px rgba(16,185,129,.1); border-color:rgba(16,185,129,.25); }
                }
            `}</style>

            <div className="lg-root flex flex-col gap-5">
                {status && (
                    <div className="flex items-start gap-2.5 rounded-xl border border-emerald-500/25 bg-emerald-50 p-3.5 dark:bg-emerald-500/10">
                        <CheckCircle2
                            size={14}
                            className="mt-0.5 flex-shrink-0 text-emerald-600 dark:text-emerald-400"
                        />
                        <p className="text-sm font-medium text-emerald-800 dark:text-emerald-300">
                            {status}
                        </p>
                    </div>
                )}
                {flash?.error && (
                    <div className="flex items-start gap-2.5 rounded-xl border border-red-500/25 bg-red-50 p-3.5 dark:bg-red-500/10">
                        <XCircle
                            size={14}
                            className="mt-0.5 flex-shrink-0 text-red-600 dark:text-red-400"
                        />
                        <p className="text-sm font-medium text-red-800 dark:text-red-300">
                            {flash.error}
                        </p>
                    </div>
                )}

                {/* Icon */}
                <div className="lg-f1 flex justify-center pt-2">
                    <div className="relative flex h-16 w-16 items-center justify-center">
                        <div
                            className="absolute inset-[-8px] rounded-full border border-emerald-500/20"
                            style={{
                                animation: 'lg-ring 2.8s ease-out infinite',
                            }}
                        />
                        <div
                            className="border-emerald-500/08 absolute inset-[-18px] rounded-full border"
                            style={{
                                animation: 'lg-ring 2.8s ease-out infinite .9s',
                            }}
                        />
                        <div className="flex h-16 w-16 items-center justify-center rounded-2xl border border-emerald-500/20 bg-emerald-500/10 shadow-[0_0_24px_rgba(16,185,129,.18)]">
                            <PawPrint
                                className="h-8 w-8 text-emerald-500"
                                style={{
                                    animation: 'lg-paw 3s ease-in-out infinite',
                                }}
                            />
                        </div>
                    </div>
                </div>

                {/* Headline */}
                <div className="lg-f2 text-center">
                    <h1 className="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                        Welcome to{' '}
                        <span className="bg-gradient-to-r from-emerald-500 to-cyan-500 bg-clip-text text-transparent">
                            DogLens
                        </span>
                    </h1>
                    <p className="mt-1.5 text-sm text-slate-500 dark:text-slate-400">
                        Identify your dog's breed in seconds
                    </p>
                    <div className="mt-3 inline-flex items-center gap-2 rounded-full border border-emerald-500/20 bg-emerald-500/[.08] px-3 py-1.5">
                        <span
                            className="h-1.5 w-1.5 rounded-full bg-emerald-500 shadow-[0_0_5px_#10b981]"
                            style={{
                                animation: 'lg-pulse 2s ease-in-out infinite',
                            }}
                        />
                        <span className="lg-mono text-[10px] font-semibold tracking-[.12em] text-emerald-600 uppercase dark:text-emerald-400">
                            System Ready
                        </span>
                    </div>
                </div>

                {/* Google button */}
                <div className="lg-f3">
                    <button
                        onClick={() => (window.location.href = '/auth/google')}
                        className="lg-btn"
                    >
                        <span className="lg-btn-shine" />
                        <svg
                            className="h-5 w-5 flex-shrink-0"
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
                        Continue with Google
                    </button>
                </div>

                {/* Divider */}
                <div className="lg-f4 flex items-center gap-3">
                    <div className="h-px flex-1 bg-slate-200 dark:bg-white/[.07]" />
                    <span className="lg-mono text-[9px] tracking-[.14em] text-slate-400 uppercase dark:text-slate-600">
                        secured by oauth 2.0
                    </span>
                    <div className="h-px flex-1 bg-slate-200 dark:bg-white/[.07]" />
                </div>

                {/* Trust strip */}
                <div className="lg-f4 grid grid-cols-2 gap-2.5">
                    {[
                        {
                            icon: (
                                <Shield
                                    size={14}
                                    className="text-emerald-500"
                                />
                            ),
                            label: 'Secure Login',
                            sub: 'OAuth 2.0 protected',
                        },
                        {
                            icon: (
                                <CheckCircle2
                                    size={14}
                                    className="text-cyan-500"
                                />
                            ),
                            label: 'Vet Verified',
                            sub: 'Licensed vet review',
                        },
                    ].map((item, i) => (
                        <div
                            key={i}
                            className="flex items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 dark:border-white/[.06] dark:bg-white/[.025]"
                        >
                            <div className="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white shadow-sm dark:border-white/[.07] dark:bg-white/[.04]">
                                {item.icon}
                            </div>
                            <div>
                                <p className="text-[12px] font-semibold text-slate-700 dark:text-slate-300">
                                    {item.label}
                                </p>
                                <p className="text-[10px] text-slate-400 dark:text-slate-600">
                                    {item.sub}
                                </p>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </AuthLayout>
    );
}
