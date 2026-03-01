import AuthLayout from '@/layouts/auth-layout';
import { usePage } from '@inertiajs/react';
import {
    CheckCircle2,
    PawPrint,
    Shield,
    Sparkles,
    XCircle,
} from 'lucide-react';

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

                @keyframes lg-pulse  { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(1.6);opacity:.3} }
                @keyframes lg-ring   { 0%{transform:scale(.82);opacity:.7} 70%,100%{transform:scale(1.22);opacity:0} }
                @keyframes lg-orbit  { from{transform:rotate(0deg) translateX(32px) rotate(0deg)} to{transform:rotate(360deg) translateX(32px) rotate(-360deg)} }
                @keyframes lg-orbit2 { from{transform:rotate(180deg) translateX(32px) rotate(-180deg)} to{transform:rotate(540deg) translateX(32px) rotate(-540deg)} }
                @keyframes lg-fadein { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
                @keyframes lg-sweep  { 0%{transform:translateX(-100%)} 100%{transform:translateX(350%)} }
                @keyframes lg-glow   { 0%,100%{box-shadow:0 0 12px rgba(16,185,129,.25)} 50%{box-shadow:0 0 28px rgba(16,185,129,.5),0 0 50px rgba(16,185,129,.15)} }
                @keyframes lg-paw    { 0%,100%{transform:scale(1) rotate(0deg)} 25%{transform:scale(1.1) rotate(-6deg)} 75%{transform:scale(1.1) rotate(6deg)} }
                @keyframes lg-shimmer{ 0%{left:-100%} 100%{left:160%} }
                @keyframes lg-slide  { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)} }

                .lg-root { font-family:'Plus Jakarta Sans',sans-serif; }
                .lg-mono { font-family:'JetBrains Mono',monospace !important; }

                .lg-card-glow { animation:lg-glow 2.5s ease-in-out infinite; }
                .lg-paw  { animation:lg-paw 2.2s ease-in-out infinite; }
                .lg-fu   { animation:lg-fadein .45s cubic-bezier(.16,1,.3,1) both; }
                .lg-fu2  { animation:lg-fadein .45s cubic-bezier(.16,1,.3,1) .1s both; }
                .lg-fu3  { animation:lg-fadein .45s cubic-bezier(.16,1,.3,1) .2s both; }
                .lg-slide{ animation:lg-slide .3s ease both; }

                .lg-btn {
                    position:relative; overflow:hidden;
                    transition:transform .2s, box-shadow .2s, border-color .2s;
                }
                .lg-btn::before {
                    content:''; position:absolute; top:0; left:-100%; width:45%;
                    height:100%; background:linear-gradient(90deg,transparent,rgba(255,255,255,.09),transparent);
                    transform:skewX(-18deg); transition:left .55s;
                }
                .lg-btn:hover::before { left:160%; }
                .lg-btn:hover { transform:translateY(-1px); box-shadow:0 8px 28px rgba(16,185,129,.18); }
                .lg-btn:active { transform:translateY(0); }
            `}</style>

            <div className="lg-root flex flex-col gap-5">
                {/* Status */}
                {status && (
                    <div className="lg-slide flex items-start gap-3 rounded-xl border border-emerald-500/25 bg-emerald-500/[.07] p-3.5">
                        <CheckCircle2
                            size={15}
                            className="mt-0.5 flex-shrink-0 text-emerald-400"
                        />
                        <p className="text-sm text-emerald-300">{status}</p>
                    </div>
                )}

                {/* Error */}
                {flash?.error && (
                    <div className="lg-slide flex items-start gap-3 rounded-xl border border-red-500/25 bg-red-500/[.07] p-3.5">
                        <XCircle
                            size={15}
                            className="mt-0.5 flex-shrink-0 text-red-400"
                        />
                        <p className="text-sm text-red-300">{flash.error}</p>
                    </div>
                )}

                {/* ── ICON + HEADLINE ── */}
                <div className="lg-fu flex flex-col items-center gap-4 pt-2 pb-1 text-center">
                    {/* Orbiting icon */}
                    <div className="relative flex h-16 w-16 items-center justify-center">
                        <div
                            className="absolute inset-[-10px] rounded-full border border-emerald-500/15"
                            style={{
                                animation: 'lg-ring 2.6s ease-out infinite',
                            }}
                        />
                        <div
                            className="border-emerald-500/07 absolute inset-[-20px] rounded-full border"
                            style={{
                                animation: 'lg-ring 2.6s ease-out infinite .9s',
                            }}
                        />
                        {/* Orbiting dots */}
                        <div
                            style={{
                                position: 'absolute',
                                width: 6,
                                height: 6,
                                borderRadius: '50%',
                                background: '#10b981',
                                boxShadow: '0 0 8px #10b981',
                                animation: 'lg-orbit 3.2s linear infinite',
                                top: '50%',
                                left: '50%',
                                marginTop: -3,
                                marginLeft: -3,
                            }}
                        />
                        <div
                            style={{
                                position: 'absolute',
                                width: 5,
                                height: 5,
                                borderRadius: '50%',
                                background: '#06b6d4',
                                boxShadow: '0 0 7px #06b6d4',
                                animation: 'lg-orbit2 3.2s linear infinite',
                                top: '50%',
                                left: '50%',
                                marginTop: -2.5,
                                marginLeft: -2.5,
                            }}
                        />
                        {/* Center */}
                        <div className="lg-card-glow relative flex h-16 w-16 items-center justify-center rounded-full border border-emerald-500/25 bg-gradient-to-br from-emerald-500/20 to-cyan-500/10">
                            <PawPrint className="lg-paw h-7 w-7 text-emerald-400" />
                        </div>
                    </div>

                    <div>
                        <h1 className="text-xl font-extrabold tracking-tight text-white">
                            Welcome to{' '}
                            <span className="bg-gradient-to-r from-emerald-400 to-cyan-400 bg-clip-text text-transparent">
                                DogLens
                            </span>
                        </h1>
                        <p className="mt-1 text-xs text-slate-500">
                            Sign in to start identifying breeds instantly
                        </p>
                    </div>

                    {/* Live badge */}
                    <div className="flex items-center gap-2 rounded-full border border-emerald-500/20 bg-emerald-500/[.08] px-3 py-1.5">
                        <span
                            className="h-1.5 w-1.5 rounded-full bg-emerald-400 shadow-[0_0_6px_#10b981]"
                            style={{
                                animation: 'lg-pulse 2s ease-in-out infinite',
                            }}
                        />
                        <span className="lg-mono text-[10px] font-semibold tracking-[.12em] text-emerald-400 uppercase">
                            AI System Online
                        </span>
                    </div>
                </div>

                {/* ── GOOGLE BUTTON ── */}
                <div className="lg-fu2">
                    <button
                        onClick={() => (window.location.href = '/auth/google')}
                        className="lg-btn group flex w-full items-center justify-center gap-3 rounded-xl border border-white/[.1] bg-white/[.05] px-5 py-3.5 text-sm font-bold text-white focus:ring-2 focus:ring-emerald-500/30 focus:outline-none"
                    >
                        {/* Google G */}
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

                {/* ── DIVIDER ── */}
                <div className="lg-fu3 flex items-center gap-3">
                    <div className="h-px flex-1 bg-white/[.07]" />
                    <span className="lg-mono text-[9px] tracking-[.14em] text-slate-600 uppercase">
                        Secured by OAuth 2.0
                    </span>
                    <div className="h-px flex-1 bg-white/[.07]" />
                </div>

                {/* ── TRUST INDICATORS ── */}
                <div className="lg-fu3 overflow-hidden rounded-xl border border-white/[.06] bg-white/[.02]">
                    {[
                        {
                            icon: (
                                <Shield
                                    size={13}
                                    className="text-emerald-400"
                                />
                            ),
                            label: 'Secure Login',
                            sub: 'Google OAuth 2.0 protected',
                        },
                        {
                            icon: (
                                <CheckCircle2
                                    size={13}
                                    className="text-cyan-400"
                                />
                            ),
                            label: 'Vet Verified',
                            sub: 'Licensed veterinary review',
                        },
                        {
                            icon: (
                                <Sparkles
                                    size={13}
                                    className="text-violet-400"
                                />
                            ),
                            label: 'AI Powered',
                            sub: '95%+ breed accuracy',
                        },
                    ].map((item, i) => (
                        <div
                            key={i}
                            className={`flex items-center gap-3 px-4 py-3 ${i < 2 ? 'border-b border-white/[.05]' : ''}`}
                        >
                            <div className="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-lg border border-white/[.08] bg-white/[.04]">
                                {item.icon}
                            </div>
                            <div className="min-w-0 flex-1">
                                <p className="text-[12px] font-semibold text-slate-300">
                                    {item.label}
                                </p>
                                <p className="text-[10px] text-slate-600">
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
