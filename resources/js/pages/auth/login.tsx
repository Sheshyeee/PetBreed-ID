import AuthLayout from '@/layouts/auth-layout';
import { usePage } from '@inertiajs/react';
import { CheckCircle2, PawPrint, XCircle } from 'lucide-react';

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}

type Errors = {
    flash?: { error?: string };
};

export default function Login({ status }: LoginProps) {
    const { flash } = usePage<Errors>().props;

    return (
        <AuthLayout title="" description="">
            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600&display=swap');

                .lv-font { font-family: 'Plus Jakarta Sans', sans-serif; }
                .lv-mono  { font-family: 'JetBrains Mono', monospace; }

                .lv-outer {
                    position: fixed;
                    inset: 0;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 16px;
                    z-index: 50;
                }

                /* ── DOT GRID MASK ── */
                .lv-dot-bg {
                    position: absolute;
                    inset: 0;
                    pointer-events: none;
                    background-image: radial-gradient(circle, rgba(16,185,129,0.10) 1px, transparent 1px);
                    background-size: 20px 20px;
                    -webkit-mask-image: radial-gradient(ellipse 100% 100% at 50% 50%, black 0%, transparent 100%);
                    mask-image: radial-gradient(ellipse 100% 100% at 50% 50%, black 0%, transparent 100%);
                }

                /* ── BRAND DOT PULSE ── */
                @keyframes lv-pulse {
                    0%, 100% { opacity: 1; }
                    50%       { opacity: 0.4; }
                }
                .lv-pulse { animation: lv-pulse 2s ease-in-out infinite; }

                /* ══════════════════════════════════════════════════
                   GOOGLE BUTTON — multi-color Google-logo border
                   Uses the 4 Google brand colors as a repeating
                   dashed / segmented border via box-shadow layering.
                   No gradient — pure solid color segments.
                ══════════════════════════════════════════════════ */

                /* Wrapper provides the colored border via padding trick */
                .lv-google-wrap {
                    position: relative;
                    border-radius: 12px;
                    padding: 1.5px;
                    /* Four-color segmented border using background-clip */
                    background:
                        /* Top edge — Google Blue */
                        linear-gradient(90deg, #4285F4 0%, #4285F4 25%, #34A853 25%, #34A853 50%, #FBBC05 50%, #FBBC05 75%, #EA4335 75%, #EA4335 100%) top / 100% 1.5px no-repeat,
                        /* Right edge — Google Green */
                        linear-gradient(180deg, #4285F4 0%, #4285F4 25%, #34A853 25%, #34A853 50%, #FBBC05 50%, #FBBC05 75%, #EA4335 75%, #EA4335 100%) right / 1.5px 100% no-repeat,
                        /* Bottom edge — reversed for visual balance */
                        linear-gradient(270deg, #4285F4 0%, #4285F4 25%, #34A853 25%, #34A853 50%, #FBBC05 50%, #FBBC05 75%, #EA4335 75%, #EA4335 100%) bottom / 100% 1.5px no-repeat,
                        /* Left edge */
                        linear-gradient(0deg, #4285F4 0%, #4285F4 25%, #34A853 25%, #34A853 50%, #FBBC05 50%, #FBBC05 75%, #EA4335 75%, #EA4335 100%) left / 1.5px 100% no-repeat;
                    /* Fill the interior so button sits on top */
                    background-color: transparent;
                    transition: opacity 0.2s, transform 0.2s;
                    /* Subtle glow on hover using the lightest Google color */
                    box-shadow: 0 0 0 0 transparent;
                }

                /* Light mode: slightly muted border opacity */
                .lv-google-wrap {
                    opacity: 0.85;
                }
                .lv-google-wrap:hover {
                    opacity: 1;
                    transform: translateY(-1px);
                    box-shadow: 0 4px 20px rgba(66,133,244,0.15), 0 4px 20px rgba(52,168,83,0.1);
                }
                .lv-google-wrap:active {
                    transform: translateY(0);
                }

                /* The actual button inside — full fill covers the padding gap */
                .lv-google-btn {
                    position: relative;
                    overflow: hidden;
                    width: 100%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 12px;
                    padding: 13px 16px;
                    border-radius: 11px;
                    border: none;
                    font-size: 15px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: background 0.2s;
                    font-family: 'Plus Jakarta Sans', sans-serif;
                    /* Light mode */
                    background: #ffffff;
                    color: #1e293b;
                }

                /* Dark mode overrides */
                .dark .lv-google-wrap {
                    opacity: 0.9;
                }
                .dark .lv-google-wrap:hover {
                    opacity: 1;
                    box-shadow: 0 4px 24px rgba(66,133,244,0.2), 0 4px 24px rgba(52,168,83,0.12);
                }
                .dark .lv-google-btn {
                    background: #111827;
                    color: #f1f5f9;
                }
                .dark .lv-google-btn:hover {
                    background: #1a2234;
                }

                /* Shine sweep */
                .lv-shine {
                    position: absolute;
                    top: 0;
                    left: -100%;
                    width: 40%;
                    height: 100%;
                    background: linear-gradient(to right, transparent, rgba(255,255,255,0.07), transparent);
                    transform: skewX(-18deg);
                    pointer-events: none;
                    transition: left 0.7s ease-in-out;
                }
                .lv-google-wrap:hover .lv-shine { left: 120%; }
            `}</style>

            {/* Neutralise constraining AuthLayout siblings */}
            <div
                style={{ display: 'none' }}
                ref={(el) => {
                    if (!el) return;
                    const parent =
                        el.closest('form, [class]')?.parentElement ??
                        el.parentElement;
                    if (!parent) return;
                    Array.from(parent.children).forEach((child) => {
                        const c = child as HTMLElement;
                        if (
                            !c.classList.contains('lv-outer') &&
                            !c.querySelector('.lv-outer')
                        ) {
                            c.style.cssText = 'display:none!important';
                        }
                    });
                }}
            />

            {/* ── FULL-SCREEN OVERLAY ── */}
            <div className="lv-outer lv-font bg-slate-100 dark:bg-[#050d0a]">
                {/* ╔══════════════════ CARD ══════════════════╗ */}
                <div className="flex min-h-[440px] w-full max-w-[860px] flex-col overflow-hidden rounded-[18px] border border-black/10 shadow-[0_16px_48px_rgba(0,0,0,0.12)] sm:flex-row dark:border-white/[0.08] dark:shadow-[0_24px_64px_rgba(0,0,0,0.7)]">
                    {/* ══════════ LEFT PANEL ══════════ */}
                    <div className="relative flex w-full shrink-0 flex-col items-center justify-center gap-5 overflow-hidden bg-emerald-950 px-8 py-10 sm:w-[300px] dark:bg-[#09201a]">
                        <div className="lv-dot-bg" />
                        <div className="absolute inset-x-0 top-0 h-px bg-emerald-500/30" />
                        <div className="pointer-events-none absolute -top-10 -left-8 h-[150px] w-[150px] rounded-full bg-emerald-500/5 blur-[48px]" />
                        <div className="pointer-events-none absolute -right-5 -bottom-8 h-[110px] w-[110px] rounded-full bg-cyan-500/5 blur-[42px]" />

                        {/* Icon */}
                        <div className="relative flex h-[76px] w-[76px] shrink-0 items-center justify-center">
                            <div className="absolute inset-[-10px] rounded-full border border-emerald-500/20" />
                            <div className="absolute inset-[-20px] rounded-full border border-emerald-500/10" />
                            <div className="relative z-10 flex h-[76px] w-[76px] items-center justify-center rounded-[22px] border border-emerald-500/20 bg-emerald-500/10">
                                <PawPrint
                                    size={30}
                                    className="text-emerald-400"
                                />
                            </div>
                        </div>

                        {/* Copy */}
                        <div className="relative z-10 text-center">
                            <p className="mb-2 text-base leading-snug font-bold tracking-tight text-white">
                                Identify any
                                <br />
                                <span className="text-emerald-400">
                                    dog breed
                                </span>
                            </p>
                            <p className="mx-auto max-w-[150px] text-[11px] leading-relaxed text-white/40">
                                Upload a photo, get results instantly with
                                health insights
                            </p>
                            <div className="mt-5 flex justify-center gap-1.5">
                                <div className="h-1.5 w-4 rounded-full bg-emerald-400" />
                                <div className="h-1.5 w-1.5 rounded-full bg-white/15" />
                                <div className="h-1.5 w-1.5 rounded-full bg-white/15" />
                            </div>
                        </div>
                    </div>

                    {/* ══════════ RIGHT PANEL ══════════ */}
                    <div className="flex min-w-0 flex-1 flex-col justify-center gap-5 bg-white px-8 py-10 sm:px-12 sm:py-12 lg:px-14 dark:bg-[#0D1117]">
                        {/* Alerts */}
                        {status && (
                            <div className="flex items-start gap-2 rounded-[10px] border border-emerald-500/20 bg-emerald-500/10 p-3 text-xs font-medium text-emerald-700 dark:text-emerald-300">
                                <CheckCircle2
                                    size={14}
                                    className="mt-px shrink-0"
                                />
                                {status}
                            </div>
                        )}
                        {flash?.error && (
                            <div className="flex items-start gap-2 rounded-[10px] border border-red-500/20 bg-red-500/10 p-3 text-xs font-medium text-red-700 dark:text-red-300">
                                <XCircle size={14} className="mt-px shrink-0" />
                                {flash.error}
                            </div>
                        )}

                        {/* Brand pill */}
                        <div className="inline-flex w-fit items-center gap-2 rounded-full border border-emerald-500/20 bg-emerald-500/5 px-3 py-1.5">
                            <span className="lv-pulse h-1.5 w-1.5 rounded-full bg-emerald-500" />
                            <span className="lv-mono text-[10px] font-semibold tracking-[0.12em] text-emerald-600 uppercase dark:text-emerald-400">
                                DogLens
                            </span>
                        </div>

                        {/* Title */}
                        <div>
                            <p className="text-[28px] leading-tight font-extrabold tracking-tight text-slate-900 dark:text-slate-50">
                                Welcome back
                            </p>
                            <p className="mt-1.5 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                                Sign in to access your breed analysis
                            </p>
                        </div>

                        {/* ── GOOGLE BUTTON with 4-color segmented border ── */}
                        <div className="lv-google-wrap">
                            <button
                                className="lv-google-btn"
                                onClick={() =>
                                    (window.location.href = '/auth/google')
                                }
                            >
                                <span className="lv-shine" />
                                <svg
                                    width="18"
                                    height="18"
                                    viewBox="0 0 24 24"
                                    xmlns="http://www.w3.org/2000/svg"
                                    className="shrink-0"
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
                        <div className="flex items-center gap-3">
                            <div className="h-px flex-1 bg-slate-200 dark:bg-white/[0.07]" />
                            <span className="lv-mono text-[9px] tracking-[0.12em] whitespace-nowrap text-slate-400 uppercase dark:text-white/20">
                                secured · oauth 2.0
                            </span>
                            <div className="h-px flex-1 bg-slate-200 dark:bg-white/[0.07]" />
                        </div>

                        {/* Footer */}
                        <p className="text-center text-[11px] leading-relaxed text-slate-400 dark:text-white/25">
                            By signing in you agree to our terms &amp; privacy
                            policy
                        </p>
                    </div>
                </div>
            </div>
        </AuthLayout>
    );
}
