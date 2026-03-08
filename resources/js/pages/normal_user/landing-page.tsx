import { login } from '@/routes';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    Calendar,
    Camera,
    ChevronRight,
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

    const allowedEmails = [
        'modeltraining2000@gmail.com',
        'jrbd2022-8800-57025@bicol-u.edu.ph',
        ,
        'dmbc2022-2141-53989@bicol-u.edu.ph',
        'asvermudo@gmail.com',
    ];
    const isAdmin = auth.user && allowedEmails.includes(auth.user.email);

    const getScanLink = () => {
        if (!auth.user) return login();
        if (isAdmin) return '/dashboard';
        return '/scan';
    };

    return (
        <>
            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap');

                @keyframes lp-pulse  { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(1.5);opacity:.4} }
                @keyframes lp-beam   { from{top:-3px} to{top:calc(100%+3px)} }
                @keyframes lp-ring   { 0%{transform:scale(.86);opacity:.6} 70%,100%{transform:scale(1.2);opacity:0} }
                @keyframes lp-sweep  { 0%{transform:translateX(-100%)} 100%{transform:translateX(350%)} }
                @keyframes lp-fadein { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
                @keyframes lp-modal  { from{transform:translateY(14px) scale(.97);opacity:0} to{transform:translateY(0) scale(1);opacity:1} }
                @keyframes lp-fabring{ 0%{transform:scale(1);opacity:.7} 100%{transform:scale(1.34);opacity:0} }
                @keyframes lp-float  { 0%,100%{transform:translateY(0px)} 50%{transform:translateY(-8px)} }
                @keyframes lp-blink  { 0%,88%,100%{opacity:1} 94%{opacity:.15} }

                .lp-root { font-family:'Plus Jakarta Sans',sans-serif; }
                .lp-mono { font-family:'JetBrains Mono',monospace !important; }

                .lp-dotgrid {
                    position:fixed; inset:0; pointer-events:none; z-index:0;
                    background-image:radial-gradient(circle,rgba(16,185,129,.07) 1px,transparent 1px);
                    background-size:28px 28px;
                    -webkit-mask-image:radial-gradient(ellipse 80% 60% at 50% 0%,black 0%,transparent 100%);
                    mask-image:radial-gradient(ellipse 80% 60% at 50% 0%,black 0%,transparent 100%);
                }

                .lp-hero-card { position:relative; overflow:hidden; }
                .lp-hero-card::before {
                    content:''; position:absolute; top:0; left:0; right:0; height:1.5px;
                    background:linear-gradient(90deg,transparent,rgba(255,255,255,.15) 45%,rgba(255,255,255,.1) 55%,transparent); opacity:.4;
                }

                .lp-feat-card { position:relative; overflow:hidden; }
                .lp-feat-card::before {
                    content:''; position:absolute; top:0; left:0; right:0; height:1.5px;
                    background:linear-gradient(90deg,transparent,rgba(255,255,255,.3) 50%,transparent); opacity:.5;
                }

                .lp-shim { position:relative; overflow:hidden; }
                .lp-shim::before { content:''; position:absolute; top:0; left:-100%; width:45%; height:100%; background:linear-gradient(90deg,transparent,rgba(255,255,255,.14),transparent); transform:skewX(-18deg); transition:left .55s; }
                .lp-shim:hover::before { left:160%; }

                .lp-fab::before { content:''; position:absolute; inset:-4px; border-radius:15px; border:1.5px solid rgba(16,185,129,.3); animation:lp-fabring 2.1s ease-out infinite; }

                .lp-beam { position:absolute; left:0; top:-3px; width:100%; height:2px; background:linear-gradient(90deg,transparent,#10b981,transparent); filter:blur(1px); opacity:0; pointer-events:none; transition:opacity .25s; }
                .lp-card-wrap:hover .lp-beam { opacity:1; animation:lp-beam 1.9s linear infinite; }

                .lp-ring1 { position:absolute; inset:-10px; border-radius:50%; border:1px solid rgba(16,185,129,.16); animation:lp-ring 2.6s ease-out infinite; }
                .lp-ring2 { position:absolute; inset:-22px; border-radius:50%; border:1px solid rgba(16,185,129,.07); animation:lp-ring 2.6s ease-out infinite .7s; }

                .lp-fu { animation:lp-fadein .5s cubic-bezier(.16,1,.3,1) both; }
                .lp-fu2 { animation:lp-fadein .5s cubic-bezier(.16,1,.3,1) .12s both; }
                .lp-fu3 { animation:lp-fadein .5s cubic-bezier(.16,1,.3,1) .22s both; }

                .lp-modal-up { animation:lp-modal .28s cubic-bezier(.16,1,.3,1) both; }
                .lp-float { animation:lp-float 4s ease-in-out infinite; }
                .lp-blink { animation:lp-blink 3s ease-in-out infinite; }
            `}</style>

            <div className="lp-root relative">
                {/* Ambient glows */}
                <div className="pointer-events-none fixed top-[-140px] left-[-70px] z-0 h-[300px] w-[500px] rounded-full bg-emerald-400/[.04] blur-[90px]" />
                <div className="pointer-events-none fixed top-[-80px] right-[-40px] z-0 h-[220px] w-[360px] rounded-full bg-cyan-400/[.03] blur-[85px]" />
                <div className="lp-dotgrid" />

                {/* QR Modal */}
                {showQRModal && (
                    <div
                        className="fixed inset-0 z-50 flex items-center justify-center bg-black/65 p-4 backdrop-blur-xl"
                        onClick={() => setShowQRModal(false)}
                    >
                        <div
                            className="lp-modal-up lp-hero-card relative w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-7 shadow-2xl dark:border-white/[.08] dark:bg-[#131720]"
                            onClick={(e) => e.stopPropagation()}
                        >
                            <button
                                onClick={() => setShowQRModal(false)}
                                className="absolute top-3 right-3 flex h-7 w-7 items-center justify-center rounded-lg bg-slate-100 text-slate-500 hover:bg-slate-200 dark:bg-white/[.06] dark:hover:bg-white/10"
                            >
                                <X size={13} />
                            </button>

                            <div className="mb-5 text-center">
                                <div className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl border border-emerald-500/20 bg-emerald-500/10">
                                    <Smartphone
                                        size={22}
                                        className="text-emerald-500"
                                    />
                                </div>
                                <h2 className="text-lg font-bold text-slate-900 dark:text-white">
                                    Install Mobile App
                                </h2>
                                <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    Scan QR to download the Android app
                                </p>
                            </div>

                            <div className="mb-5 flex justify-center">
                                <div className="rounded-xl bg-white p-3 shadow-lg ring-1 ring-slate-200">
                                    <img
                                        src="/qr-DogLens.png"
                                        alt="QR Code"
                                        className="block h-36 w-36"
                                    />
                                </div>
                            </div>

                            <div className="mb-5 overflow-hidden rounded-xl border border-slate-200 dark:border-white/[.07]">
                                {[
                                    {
                                        icon: <Download size={12} />,
                                        t: 'Fast & Easy Installation',
                                    },
                                    {
                                        icon: <Smartphone size={12} />,
                                        t: 'Available on Android',
                                    },
                                    {
                                        icon: <Camera size={12} />,
                                        t: 'All Features On-The-Go',
                                    },
                                ].map((f, i) => (
                                    <div
                                        key={i}
                                        className="flex items-center gap-2.5 border-b border-slate-100 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-700 last:border-none dark:border-white/[.05] dark:bg-white/[.025] dark:text-slate-400"
                                    >
                                        <div className="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-md bg-emerald-500/10 text-emerald-500">
                                            {f.icon}
                                        </div>
                                        {f.t}
                                    </div>
                                ))}
                            </div>

                            <button
                                onClick={() => setShowQRModal(false)}
                                className="lp-shim w-full rounded-xl bg-emerald-500 py-3 text-sm font-bold text-black transition-all hover:bg-emerald-400"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                )}

                {/* Floating QR FAB */}
                <button
                    onClick={() => setShowQRModal(true)}
                    className="lp-fab fixed right-5 bottom-5 z-40 flex h-11 w-11 items-center justify-center rounded-[13px] bg-emerald-500 text-black shadow-xl shadow-emerald-500/25 transition-all hover:scale-105 hover:bg-emerald-400 active:scale-95"
                    title="Install Mobile App"
                >
                    <QrCode size={17} />
                </button>

                {/* ── MAIN LAYOUT ── */}
                <div className="relative z-10 flex w-full flex-col gap-4 lg:flex-row">
                    <div className="flex w-full flex-col gap-4">
                        {/* ── HERO SECTION ── */}
                        <div className="lp-hero-card lp-fu flex h-auto w-full flex-col items-center justify-between gap-4 rounded-2xl bg-[#0C134F] p-6 text-white sm:p-8 lg:h-[300px] lg:flex-row lg:gap-3">
                            {/* Subtle inner glow */}
                            <div className="pointer-events-none absolute inset-0 rounded-2xl bg-gradient-to-br from-white/[.02] via-transparent to-white/[.01]" />
                            <div className="pointer-events-none absolute top-0 right-0 left-0 h-px bg-gradient-to-r from-transparent via-white/20 to-transparent" />

                            <div className="relative flex h-auto flex-1 flex-col justify-center gap-3 text-center lg:h-[270px] lg:gap-4 lg:text-left">
                                {/* Badge — transparent to match bg */}
                                <div className="mx-auto flex w-fit items-center gap-2 rounded-full border border-white/15 bg-white/[.06] px-3 py-1.5 backdrop-blur-sm lg:mx-0">
                                    <span
                                        className="h-1.5 w-1.5 rounded-full bg-white/60"
                                        style={{
                                            animation:
                                                'lp-pulse 2s ease-in-out infinite',
                                        }}
                                    />
                                    <span className="lp-mono text-[10px] font-semibold tracking-[.12em] text-white/70 uppercase">
                                        Breed Detection
                                    </span>
                                </div>

                                <h1 className="text-2xl font-extrabold tracking-tight sm:text-3xl lg:text-3xl">
                                    Identify dog{' '}
                                    <span className="bg-gradient-to-r from-violet-400 to-purple-400 bg-clip-text text-transparent">
                                        breed
                                    </span>{' '}
                                    instantly
                                </h1>

                                <p className="mx-auto max-w-md text-xs leading-relaxed text-white/65 sm:text-sm lg:mx-0">
                                    Upload a photo and get accurate breed
                                    identification powered by advanced AI
                                    technology
                                </p>

                                <Link href={getScanLink()}>
                                    <button
                                        className="lp-shim mx-auto flex w-[240px] items-center justify-center gap-2 rounded-xl border border-white/15 bg-white/[.07] px-5 py-2.5 text-sm font-bold text-white/80 transition-all hover:border-white/25 hover:bg-white/[.12] lg:mx-0"
                                        onClick={() => setOpen(false)}
                                    >
                                        <PawPrintIcon size={15} />
                                        Scan Your Pet
                                        <ChevronRight
                                            size={13}
                                            className="opacity-60"
                                        />
                                    </button>
                                </Link>
                            </div>

                            <div className="lp-float relative ml-8 hidden lg:block">
                                <div
                                    className="absolute inset-[-20px] rounded-full border border-white/[.08]"
                                    style={{
                                        animation:
                                            'lp-ring 3s ease-out infinite',
                                    }}
                                />
                                <img
                                    src="/paww.png"
                                    alt="Dog"
                                    className="mt-[130px] h-[100px] w-[100px] rounded-xl object-cover opacity-90 shadow-xl shadow-black/30"
                                />
                            </div>
                        </div>

                        {/* ── FEATURE CARDS ── */}
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {/* Growth Simulation */}
                            <div className="lp-feat-card lp-fu lp-card-wrap flex min-h-[160px] flex-col justify-between rounded-2xl bg-[#5C469C] p-5 text-white sm:p-6 lg:min-h-[170px]">
                                <div className="lp-beam" />
                                <div className="pointer-events-none absolute inset-0 rounded-2xl bg-gradient-to-br from-white/[.04] to-transparent" />
                                <div className="relative">
                                    <div className="mb-3 flex h-8 w-8 items-center justify-center rounded-lg border border-white/15 bg-white/10">
                                        <Calendar
                                            size={15}
                                            className="text-white/90"
                                        />
                                    </div>
                                    <h3 className="mb-1.5 text-sm font-bold">
                                        Growth Simulation
                                    </h3>
                                    <p className="text-xs leading-relaxed text-white/65">
                                        Visualize how your dog will look through
                                        different life stages from puppy to
                                        senior
                                    </p>
                                </div>
                                <div className="relative mt-4 flex w-fit items-center gap-2 rounded-lg border border-white/15 bg-white/10 px-3 py-1.5 text-xs font-semibold transition-all hover:bg-white/20 sm:px-4">
                                    <Calendar size={12} />
                                    <span className="hidden sm:inline">
                                        See simulation
                                    </span>
                                    <span className="sm:hidden">Simulate</span>
                                    <ChevronRight
                                        size={10}
                                        className="opacity-50"
                                    />
                                </div>
                            </div>

                            {/* Health Risk */}
                            <div className="lp-feat-card lp-fu2 lp-card-wrap flex min-h-[160px] flex-col justify-between rounded-2xl bg-[#5C469C] p-5 text-white sm:p-6 lg:min-h-[170px]">
                                <div className="lp-beam" />
                                <div className="pointer-events-none absolute inset-0 rounded-2xl bg-gradient-to-br from-cyan-400/[.06] to-transparent" />
                                <div className="relative">
                                    <div className="mb-3 flex h-8 w-8 items-center justify-center rounded-lg border border-white/15 bg-white/10">
                                        <Heart
                                            size={15}
                                            className="text-white/90"
                                        />
                                    </div>
                                    <h3 className="mb-1.5 text-sm font-bold">
                                        Health Risk Analysis
                                    </h3>
                                    <p className="text-xs leading-relaxed text-white/65">
                                        Discover breed-specific health risks and
                                        get preventive care recommendations
                                    </p>
                                </div>
                                <div className="relative mt-4 flex w-fit items-center gap-2 rounded-lg border border-white/15 bg-white/10 px-3 py-1.5 text-xs font-semibold transition-all hover:bg-white/20 sm:px-4">
                                    <Heart size={12} />
                                    <span className="hidden sm:inline">
                                        View health risks
                                    </span>
                                    <span className="sm:hidden">
                                        View risks
                                    </span>
                                    <ChevronRight
                                        size={10}
                                        className="opacity-50"
                                    />
                                </div>
                            </div>

                            {/* Origin */}
                            <div className="lp-feat-card lp-fu3 lp-card-wrap flex min-h-[160px] flex-col justify-between rounded-2xl bg-[#5C469C] p-5 text-white sm:col-span-2 sm:p-6 lg:col-span-1 lg:min-h-[170px]">
                                <div className="lp-beam" />
                                <div className="pointer-events-none absolute inset-0 rounded-2xl bg-gradient-to-br from-emerald-400/[.05] to-transparent" />
                                <div className="relative">
                                    <div className="mb-3 flex h-8 w-8 items-center justify-center rounded-lg border border-white/15 bg-white/10">
                                        <MapPin
                                            size={15}
                                            className="text-white/90"
                                        />
                                    </div>
                                    <h3 className="mb-1.5 text-sm font-bold">
                                        Origin & History
                                    </h3>
                                    <p className="text-xs leading-relaxed text-white/65">
                                        Learn about your dog's breed origins,
                                        historical purpose, and cultural
                                        significance
                                    </p>
                                </div>
                                <div className="relative mt-4 flex w-fit items-center gap-2 rounded-lg border border-white/15 bg-white/10 px-3 py-1.5 text-xs font-semibold transition-all hover:bg-white/20 sm:px-4">
                                    <MapPin size={12} />
                                    <span className="hidden sm:inline">
                                        Explore history
                                    </span>
                                    <span className="sm:hidden">Explore</span>
                                    <ChevronRight
                                        size={10}
                                        className="opacity-50"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* ── SIDE CARD ── */}
                    <div className="lp-hero-card lp-fu2 w-full rounded-2xl bg-[#1D267D] p-5 sm:p-6 lg:w-[400px]">
                        <div className="pointer-events-none absolute inset-0 rounded-2xl bg-gradient-to-br from-white/[.02] via-transparent to-white/[.01]" />

                        <div className="relative mb-4 overflow-hidden rounded-xl">
                            <img
                                src="/dog1.png"
                                className="h-[200px] w-full rounded-xl object-cover sm:h-[250px]"
                                alt="Dog breed identification"
                            />
                            {/* Overlay tag — transparent to bg */}
                            <div className="absolute bottom-3 left-3 flex items-center gap-2 rounded-lg border border-white/15 bg-[#1D267D]/75 px-2.5 py-1.5 backdrop-blur-sm">
                                <span className="lp-blink h-1.5 w-1.5 rounded-full bg-white/60" />
                                <span className="lp-mono text-[9px] font-semibold tracking-[.1em] text-white/70 uppercase">
                                    Ready
                                </span>
                            </div>
                        </div>

                        <div className="relative">
                            <h2 className="mb-3 text-base font-bold text-white sm:text-lg">
                                Professional Breed Analysis You Can Trust
                            </h2>

                            {/* Vet badge */}
                            <div className="mb-3 flex items-center gap-3 rounded-xl border border-white/10 bg-white/[.07] px-3 py-2.5 backdrop-blur-sm">
                                <div className="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg border border-white/15 bg-white/10">
                                    <ShieldCheck
                                        size={16}
                                        className="text-white/80"
                                    />
                                </div>
                                <div>
                                    <p className="text-xs font-bold text-white">
                                        Veterinary Verified
                                    </p>
                                    <p className="text-[11px] text-white/55">
                                        Licensed vet reviews predictions
                                    </p>
                                </div>
                            </div>

                            <div className="border-t border-white/15 pt-4">
                                <Link href={login()}>
                                    <button
                                        className="lp-shim w-full rounded-xl border border-white/15 bg-white/[.07] py-3 text-sm font-bold text-white/80 transition-all hover:border-white/25 hover:bg-white/[.12]"
                                        onClick={() => setOpen(false)}
                                    >
                                        Get Started Now
                                    </button>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

export default LandingPage;
