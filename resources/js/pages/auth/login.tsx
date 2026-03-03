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
            {/* Keeping only the font import and the parent header kill-switch hack intact */}
            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600&display=swap');

                .lv-kill-header {
                    display: none !important;
                    visibility: hidden !important;
                    height: 0 !important;
                    overflow: hidden !important;
                    margin: 0 !important;
                    padding: 0 !important;
                }
            `}</style>

            <div
                style={{ display: 'none' }}
                ref={(el) => {
                    if (!el) return;
                    const parent =
                        el.closest('form, [class]')?.parentElement ??
                        el.parentElement;
                    if (!parent) return;
                    Array.from(parent.children).forEach((child) => {
                        const el2 = child as HTMLElement;
                        if (
                            !el2.classList.contains('lv-root') &&
                            !el2.querySelector('.lv-root') &&
                            !el2.querySelector('.lv-alert')
                        ) {
                            el2.style.cssText = 'display:none!important';
                        }
                    });
                }}
            />

            <div className="lv-root font-['Plus_Jakarta_Sans',sans-serif] w-full">
                {status && (
                    <div className="lv-alert flex items-start gap-2 p-3 rounded-[10px] text-xs font-medium mb-3 border border-[#10b981]/20 bg-[#10b981]/10 text-[#059669] dark:text-[#6ee7b7]">
                        <CheckCircle2
                            size={14}
                            style={{ flexShrink: 0, marginTop: 1 }}
                        />
                        {status}
                    </div>
                )}
                {flash?.error && (
                    <div className="lv-alert flex items-start gap-2 p-3 rounded-[10px] text-xs font-medium mb-3 border border-[#ef4444]/20 bg-[#ef4444]/10 text-[#dc2626] dark:text-[#fca5a5]">
                        <XCircle
                            size={14}
                            style={{ flexShrink: 0, marginTop: 1 }}
                        />
                        {flash.error}
                    </div>
                )}

                {/* ✅ Card layout - Made responsive, wider max-w, and better flex handling */}
                <div className="flex flex-col md:flex-row w-full max-w-[900px] mx-auto rounded-[18px] overflow-hidden border border-black/10 bg-white shadow-[0_16px_48px_rgba(0,0,0,0.1)] dark:bg-[#0D1117] dark:border-white/10 dark:shadow-[0_20px_60px_rgba(0,0,0,0.6)]">
                    
                    {/* LEFT SECTION */}
                    <div className="relative overflow-hidden flex flex-col items-center justify-center p-8 md:py-12 gap-5 bg-[#09201a] w-full md:w-[300px] shrink-0">
                        {/* Decorative Background Elements */}
                        <div className="absolute inset-0 pointer-events-none bg-[radial-gradient(circle,rgba(16,185,129,0.10)_1px,transparent_1px)] bg-[size:20px_20px] [mask-image:radial-gradient(ellipse_100%_100%_at_50%_50%,black_0%,transparent_100%)]" />
                        <div className="absolute top-0 inset-x-0 h-px bg-[#10b981]/30" />
                        <div className="absolute -top-10 -left-8 w-[150px] h-[150px] rounded-full bg-[#10b981]/5 blur-[48px] pointer-events-none" />
                        <div className="absolute -bottom-8 -right-5 w-[110px] h-[110px] rounded-full bg-[#06b6d4]/5 blur-[42px] pointer-events-none" />

                        {/* Icon */}
                        <div className="relative w-[76px] h-[76px] flex items-center justify-center">
                            <div className="absolute -inset-[10px] rounded-full border border-[#10b981]/20" />
                            <div className="absolute -inset-[20px] rounded-full border border-[#10b981]/10" />
                            <div className="w-[76px] h-[76px] rounded-[22px] flex items-center justify-center border border-[#10b981]/20 bg-[#10b981]/10 z-10">
                                <PawPrint
                                    size={30}
                                    style={{ color: '#10b981' }}
                                />
                            </div>
                        </div>

                        {/* Left Text */}
                        <div className="text-center z-10">
                            <p className="text-[16px] font-bold text-white leading-tight tracking-tight mb-2">
                                Identify any
                                <br />
                                <span className="text-[#10b981]">dog breed</span>
                            </p>
                            <p className="text-[11px] text-white/40 leading-relaxed max-w-[150px] mx-auto">
                                Upload a photo, get results instantly with health insights
                            </p>
                            
                            {/* Dots */}
                            <div className="flex gap-1.5 justify-center mt-5">
                                <div className="w-4 h-1.5 rounded-full bg-[#10b981]" />
                                <div className="w-1.5 h-1.5 rounded-full bg-white/15" />
                                <div className="w-1.5 h-1.5 rounded-full bg-white/15" />
                            </div>
                        </div>
                    </div>

                    {/* RIGHT SECTION - ✅ Removed min-width, added flex-1 and min-w-0 to fix overflow cutoffs */}
                    <div className="flex-1 min-w-0 flex flex-col justify-center gap-5 p-8 sm:p-12 md:p-16 bg-white dark:bg-[#0D1117]">
                        
                        {/* Brand Pill */}
                        <div className="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-[#10b981]/20 bg-[#10b981]/5 w-fit">
                            <span className="w-1.5 h-1.5 rounded-full bg-[#10b981] animate-pulse" />
                            <span className="font-['JetBrains_Mono',monospace] text-[10px] font-semibold tracking-widest text-[#10b981] uppercase">
                                DogLens
                            </span>
                        </div>

                        {/* Title Wrapper */}
                        <div>
                            <p className="text-2xl sm:text-[28px] font-extrabold tracking-tight text-[#0f172a] dark:text-[#f8fafc] leading-tight break-words">
                                Welcome back
                            </p>
                            <p className="text-[13px] sm:text-sm text-[#94a3b8] leading-relaxed mt-1.5 break-words">
                                Sign in to access your breed analysis
                            </p>
                        </div>

                        {/* Google Button */}
                        <button
                            onClick={() => (window.location.href = '/auth/google')}
                            className="group relative overflow-hidden w-full flex items-center justify-center gap-3 px-4 py-3.5 rounded-[11px] bg-white border-[1.5px] border-[#e2e8f0] text-[#1e293b] text-[15px] font-semibold transition-all duration-200 shadow-sm hover:-translate-y-[1px] hover:shadow-md hover:border-[#cbd5e1] active:translate-y-0 dark:bg-[#111827] dark:border-white/10 dark:text-[#f1f5f9] dark:hover:border-[#10b981]/30 dark:shadow-none"
                        >
                            {/* Tailwind shine effect conversion */}
                            <span className="absolute top-0 -left-[100%] w-[40%] h-full bg-gradient-to-r from-transparent via-black/5 dark:via-white/10 to-transparent -skew-x-[18deg] pointer-events-none transition-all duration-700 ease-in-out group-hover:left-[120%]" />
                            
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

                        {/* Divider */}
                        <div className="flex items-center gap-3 my-1">
                            <div className="flex-1 h-px bg-[#f1f5f9] dark:bg-white/10" />
                            <span className="font-['JetBrains_Mono',monospace] text-[9px] tracking-[0.12em] text-[#cbd5e1] dark:text-white/20 uppercase whitespace-nowrap">
                                secured · oauth 2.0
                            </span>
                            <div className="flex-1 h-px bg-[#f1f5f9] dark:bg-white/10" />
                        </div>

                        {/* Note */}
                        <p className="text-[11px] sm:text-xs text-[#94a3b8] dark:text-white/30 text-center leading-relaxed">
                            By signing in you agree to our terms &amp; privacy
                            policy
                        </p>
                    </div>
                </div>
            </div>
        </AuthLayout>
    );
}