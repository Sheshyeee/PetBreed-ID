import { login } from '@/routes';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    Calendar,
    Camera,
    ChevronRight,
    Download,
    Heart,
    MapPin,
    PawPrintIcon,
    QrCode,
    Scan,
    ShieldCheck,
    Smartphone,
    Target,
    TrendingUp,
    X,
    Zap,
} from 'lucide-react';
import { useState } from 'react';

function LandingPage() {
    const [open, setOpen] = useState(false);
    const [showQRModal, setShowQRModal] = useState(false);
    const { auth } = usePage<SharedData>().props;

    const allowedEmails = [
        'modeltraining2000@gmail.com',
        'jrbd2022-8800-57025@bicol-u.edu.ph',
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
                @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap');

                @keyframes lp-beam    { from{top:-3px} to{top:calc(100%+3px)} }
                @keyframes lp-dpulse  { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(1.5);opacity:.5} }
                @keyframes lp-ring    { 0%{transform:scale(.86);opacity:.6} 70%,100%{transform:scale(1.16);opacity:0} }
                @keyframes lp-sweep   { 0%{top:-100%} 100%{top:100%} }
                @keyframes lp-faderise{ from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
                @keyframes lp-modalup { from{transform:translateY(14px) scale(.97);opacity:0} to{transform:translateY(0) scale(1);opacity:1} }
                @keyframes lp-fabring { 0%{transform:scale(1);opacity:.7} 100%{transform:scale(1.34);opacity:0} }
                @keyframes lp-huddim  { 0%,88%,100%{opacity:1} 94%{opacity:.12} }
                @keyframes lp-ticker  { from{opacity:.26} to{opacity:1} }
                @keyframes lp-barfill { from{width:0} }
                @keyframes lp-shimmer { 0%{background-position:200% center} 100%{background-position:-200% center} }
                @keyframes lp-scan    { 0%,100%{opacity:.4} 50%{opacity:1} }

                .lp-root { font-family:'Plus Jakarta Sans',sans-serif; }
                .lp-mono { font-family:'JetBrains Mono',monospace !important; }

                .lp-dotgrid {
                    position:fixed; inset:0; pointer-events:none; z-index:0;
                    background-image:radial-gradient(circle,rgba(16,185,129,.07) 1px,transparent 1px);
                    background-size:28px 28px;
                    -webkit-mask-image:radial-gradient(ellipse 80% 55% at 50% 0%,black 0%,transparent 100%);
                    mask-image:radial-gradient(ellipse 80% 55% at 50% 0%,black 0%,transparent 100%);
                }

                .lp-panel::before {
                    content:''; position:absolute; top:0; left:0; right:0; height:1.5px;
                    background:linear-gradient(90deg,transparent,#10b981 45%,#06b6d4 55%,transparent); opacity:.3;
                }

                .lp-card-beam { position:absolute; left:0; top:-3px; width:100%; height:2px; background:linear-gradient(90deg,transparent,#10b981,transparent); filter:blur(1px); opacity:0; pointer-events:none; z-index:2; transition:opacity .25s; }
                .lp-card:hover .lp-card-beam { opacity:1; animation:lp-beam 2s linear infinite; }

                .lp-sweep { position:absolute; left:0; top:-100%; width:100%; height:100%; background:linear-gradient(180deg,transparent 0%,rgba(16,185,129,.04) 46%,rgba(16,185,129,.12) 50%,rgba(16,185,129,.04) 54%,transparent 100%); animation:lp-sweep 4s ease-in-out infinite; pointer-events:none; z-index:2; }

                .lp-fab::before { content:''; position:absolute; inset:-4px; border-radius:50%; border:1.5px solid rgba(16,185,129,.27); animation:lp-fabring 2.1s ease-out infinite; }
                .lp-hudblink { animation:lp-huddim 3s ease-in-out infinite; }
                .lp-ticker { animation:lp-ticker 1.3s ease-in-out infinite alternate; }
                .lp-fu { animation:lp-faderise .5s cubic-bezier(.16,1,.3,1) both; }
                .lp-barfill { animation:lp-barfill 1.5s ease-out forwards; }
                .lp-modalup { animation:lp-modalup .28s cubic-bezier(.16,1,.3,1) both; }
                .lp-nsb::-webkit-scrollbar { display:none; }
                .lp-nsb { scrollbar-width:none; }

                .lp-shimmer-text {
                    background: linear-gradient(90deg, #10b981, #06b6d4, #10b981);
                    background-size: 200% auto;
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    background-clip: text;
                    animation: lp-shimmer 3s linear infinite;
                }

                .lp-hc { position:absolute; width:12px; height:12px; border-color:#10b981; border-style:solid; z-index:4; pointer-events:none; }
                .lp-htl { top:7px; left:7px; border-width:2px 0 0 2px; }
                .lp-htr { top:7px; right:7px; border-width:2px 2px 0 0; }
                .lp-hbl { bottom:7px; left:7px; border-width:0 0 2px 2px; }
                .lp-hbr { bottom:7px; right:7px; border-width:0 2px 2px 0; }

                .lp-shim { position:relative; overflow:hidden; }
                .lp-shim::before { content:''; position:absolute; top:0; left:-100%; width:50%; height:100%; background:linear-gradient(90deg,transparent,rgba(255,255,255,.15),transparent); transform:skewX(-18deg); transition:left .6s; }
                .lp-shim:hover::before { left:160%; }

                .lp-scanline { position:absolute; inset:0; pointer-events:none; z-index:2; background:repeating-linear-gradient(0deg,transparent,transparent 2px,rgba(0,0,0,.015) 2px,rgba(0,0,0,.015) 4px); }
            `}</style>

            <div className="lp-root">
                {/* Background Effects */}
                <div className="pointer-events-none fixed top-[-140px] left-[-70px] z-0 h-[260px] w-[460px] rounded-full bg-emerald-400/[.042] blur-[85px]" />
                <div className="pointer-events-none fixed top-[-90px] right-[-40px] z-0 h-[210px] w-[340px] rounded-full bg-cyan-400/[.028] blur-[85px]" />
                <div className="pointer-events-none fixed bottom-[-100px] left-[30%] z-0 h-[200px] w-[380px] rounded-full bg-emerald-500/[.022] blur-[90px]" />
                <div className="lp-dotgrid" />

                {/* QR Modal */}
                {showQRModal && (
                    <div
                        className="fixed inset-0 z-50 flex items-center justify-center bg-black/65 p-4 backdrop-blur-xl"
                        onClick={() => setShowQRModal(false)}
                    >
                        <div
                            className="lp-modalup lp-panel relative w-full max-w-sm overflow-hidden rounded-2xl border border-white/[.08] bg-[#131720] p-7 shadow-2xl"
                            onClick={(e) => e.stopPropagation()}
                        >
                            {/* Terminal bar */}
                            <div className="absolute top-0 right-0 left-0 flex items-center gap-3 border-b border-white/[.06] bg-[#0D1117] px-4 py-2.5">
                                <div className="flex gap-1.5">
                                    <div className="h-2.5 w-2.5 rounded-full bg-[#FF5F57]" />
                                    <div className="h-2.5 w-2.5 rounded-full bg-[#FEBC2E]" />
                                    <div className="lp-hudblink h-2.5 w-2.5 rounded-full bg-emerald-500 shadow-[0_0_5px_#10b981]" />
                                </div>
                                <span className="lp-mono ml-1 text-[10px] text-slate-500 select-none">
                                    doglens://install
                                </span>
                                <button
                                    onClick={() => setShowQRModal(false)}
                                    className="ml-auto flex h-5 w-5 items-center justify-center rounded text-slate-500 hover:text-slate-300"
                                >
                                    <X size={12} />
                                </button>
                            </div>

                            <div className="mt-9 text-center">
                                <div className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl border border-emerald-500/20 bg-emerald-500/10">
                                    <Smartphone
                                        size={22}
                                        className="text-emerald-500"
                                    />
                                </div>
                                <h2 className="text-lg font-bold text-white">
                                    Install Mobile App
                                </h2>
                                <p className="mt-1 text-xs text-slate-400">
                                    Scan QR to download the Android app
                                </p>
                            </div>

                            <div className="my-5 flex justify-center">
                                <div className="rounded-xl bg-white p-2.5 shadow-lg shadow-emerald-500/10">
                                    <img
                                        src="/doglens_apk_qr.jpeg"
                                        alt="QR"
                                        className="block h-36 w-36"
                                    />
                                </div>
                            </div>

                            <div className="mb-4 overflow-hidden rounded-xl border border-white/[.07]">
                                {[
                                    {
                                        icon: <Download size={11} />,
                                        t: 'Fast & Easy Installation',
                                    },
                                    {
                                        icon: <Smartphone size={11} />,
                                        t: 'Available on Android',
                                    },
                                    {
                                        icon: <Camera size={11} />,
                                        t: 'All Features On-The-Go',
                                    },
                                ].map((f, i) => (
                                    <div
                                        key={i}
                                        className="flex items-center gap-2.5 border-b border-white/[.05] bg-white/[.02] px-3.5 py-2.5 text-sm text-slate-300 last:border-none"
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
                                className="lp-shim w-full rounded-xl bg-emerald-500 py-3 text-sm font-bold text-black transition-colors hover:bg-emerald-400"
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
                >
                    <QrCode size={17} />
                </button>

                {/* ── MAIN LAYOUT ── */}
                <div className="lp-fu relative z-10 flex w-full flex-col gap-4 lg:flex-row">
                    <div className="flex w-full flex-col gap-4">
                        {/* ── HERO SECTION ── */}
                        <div className="lp-panel relative flex h-auto w-full flex-col items-center justify-between gap-4 overflow-hidden rounded-2xl border border-white/[.07] bg-[#0D1117] p-6 sm:p-8 lg:h-[300px] lg:flex-row lg:gap-3">
                            {/* Terminal bar */}
                            <div className="absolute top-0 right-0 left-0 flex items-center gap-3 border-b border-white/[.06] bg-black/40 px-4 py-2.5">
                                <div className="flex gap-1.5">
                                    <div className="h-2 w-2 rounded-full bg-[#FF5F57]" />
                                    <div className="h-2 w-2 rounded-full bg-[#FEBC2E]" />
                                    <div className="lp-hudblink h-2 w-2 rounded-full bg-emerald-500 shadow-[0_0_5px_#10b981]" />
                                </div>
                                <span className="lp-mono ml-1 text-[10px] text-slate-500 select-none">
                                    doglens://home
                                </span>
                                <div className="lp-mono ml-auto flex items-center gap-1.5 text-[10px] text-emerald-400 select-none">
                                    <span
                                        className="h-1.5 w-1.5 rounded-full bg-emerald-500 shadow-[0_0_5px_#10b981]"
                                        style={{
                                            animation: 'lp-dpulse 2s infinite',
                                        }}
                                    />
                                    SYSTEM ONLINE
                                </div>
                            </div>

                            {/* Scan line overlay */}
                            <div className="lp-scanline" />
                            <div className="lp-sweep" />

                            {/* Corner brackets */}
                            {['lp-htl', 'lp-htr', 'lp-hbl', 'lp-hbr'].map(
                                (c) => (
                                    <div key={c} className={`lp-hc ${c}`} />
                                ),
                            )}

                            <div className="mt-8 flex h-auto flex-1 flex-col justify-center gap-3 text-center lg:h-[270px] lg:gap-4 lg:text-left">
                                <div className="mx-auto inline-flex w-fit items-center gap-2 rounded-full border border-emerald-500/20 bg-emerald-500/[.08] px-2.5 py-1 lg:mx-0">
                                    <span
                                        className="h-1.5 w-1.5 rounded-full bg-emerald-500 shadow-[0_0_6px_#10b981]"
                                        style={{
                                            animation:
                                                'lp-dpulse 2s ease-in-out infinite',
                                        }}
                                    />
                                    <span className="lp-mono text-[10px] font-semibold tracking-[.12em] text-emerald-400 uppercase">
                                        AI Breed Detection
                                    </span>
                                </div>

                                <h1 className="text-2xl font-extrabold tracking-tight text-white sm:text-3xl lg:text-3xl">
                                    Identify dog{' '}
                                    <span className="lp-shimmer-text">
                                        breed
                                    </span>{' '}
                                    instantly
                                </h1>
                                <p className="mx-auto max-w-md text-xs text-slate-400 sm:text-sm lg:mx-0">
                                    Upload a photo and get accurate breed
                                    identification powered by advanced AI
                                    technology
                                </p>
                                <Link href={getScanLink()}>
                                    <button
                                        className="lp-shim inline-flex w-[240px] items-center justify-center gap-2 rounded-xl bg-emerald-500 px-5 py-3 text-sm font-bold text-black shadow-lg shadow-emerald-500/25 transition-all hover:-translate-y-0.5 hover:bg-emerald-400 active:scale-95"
                                        onClick={() => setOpen(false)}
                                    >
                                        <Scan size={15} /> Scan Pet Now
                                        <ChevronRight
                                            size={13}
                                            className="opacity-60"
                                        />
                                    </button>
                                </Link>
                            </div>

                            <div className="ml-8 hidden lg:block">
                                <div className="relative">
                                    <div className="absolute inset-0 rounded-xl bg-emerald-500/10 blur-xl" />
                                    <div className="relative overflow-hidden rounded-xl border border-emerald-500/20 bg-black/40">
                                        <img
                                            src="/paww.png"
                                            alt="Dog"
                                            className="mt-[80px] h-[120px] w-[120px] rounded-xl object-cover opacity-90"
                                        />
                                        <div className="lp-scanline absolute inset-0" />
                                        {[
                                            'lp-htl',
                                            'lp-htr',
                                            'lp-hbl',
                                            'lp-hbr',
                                        ].map((c) => (
                                            <div
                                                key={c}
                                                className={`lp-hc ${c}`}
                                            />
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* ── FEATURE CARDS ── */}
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {/* Growth Simulation */}
                            <div className="lp-panel lp-card group relative flex min-h-[150px] flex-col justify-between overflow-hidden rounded-2xl border border-white/[.07] bg-[#131720] p-4 transition-all hover:border-emerald-500/25 sm:p-6 lg:min-h-[170px]">
                                <div className="lp-card-beam" />
                                <div>
                                    <div className="lp-mono mb-2 flex items-center gap-2 text-[10px] font-semibold tracking-[.12em] text-emerald-500/70 uppercase">
                                        <span className="h-1 w-1 rounded-full bg-emerald-500" />
                                        Feature 01
                                    </div>
                                    <h3 className="mb-2 text-sm font-bold text-white">
                                        Growth Simulation
                                    </h3>
                                    <p className="text-xs text-slate-400">
                                        Visualize how your dog will look through
                                        different life stages from puppy to
                                        senior
                                    </p>
                                </div>
                                <button className="mt-4 flex w-fit items-center gap-2 rounded-xl border border-emerald-500/20 bg-emerald-500/[.08] px-3 py-1.5 text-xs font-semibold text-emerald-400 transition-all hover:bg-emerald-500/[.14]">
                                    <Calendar className="h-3 w-3" />
                                    See simulation
                                    <ChevronRight
                                        size={10}
                                        className="opacity-50"
                                    />
                                </button>
                            </div>

                            {/* Health Risk */}
                            <div className="lp-panel lp-card group relative flex min-h-[150px] flex-col justify-between overflow-hidden rounded-2xl border border-white/[.07] bg-[#131720] p-4 transition-all hover:border-emerald-500/25 sm:p-6 lg:min-h-[170px]">
                                <div className="lp-card-beam" />
                                <div>
                                    <div className="lp-mono mb-2 flex items-center gap-2 text-[10px] font-semibold tracking-[.12em] text-cyan-500/70 uppercase">
                                        <span className="h-1 w-1 rounded-full bg-cyan-500" />
                                        Feature 02
                                    </div>
                                    <h3 className="mb-2 text-sm font-bold text-white">
                                        Health Risk Analysis
                                    </h3>
                                    <p className="text-xs text-slate-400">
                                        Discover breed-specific health risks and
                                        get preventive care recommendations
                                    </p>
                                </div>
                                <button className="mt-4 flex w-fit items-center gap-2 rounded-xl border border-cyan-500/20 bg-cyan-500/[.07] px-3 py-1.5 text-xs font-semibold text-cyan-400 transition-all hover:bg-cyan-500/[.14]">
                                    <Heart className="h-3 w-3" />
                                    View health risks
                                    <ChevronRight
                                        size={10}
                                        className="opacity-50"
                                    />
                                </button>
                            </div>

                            {/* Origin */}
                            <div className="lp-panel lp-card group relative flex min-h-[150px] flex-col justify-between overflow-hidden rounded-2xl border border-white/[.07] bg-[#131720] p-4 transition-all hover:border-emerald-500/25 sm:col-span-2 sm:p-6 lg:col-span-1 lg:min-h-[170px]">
                                <div className="lp-card-beam" />
                                <div>
                                    <div className="lp-mono mb-2 flex items-center gap-2 text-[10px] font-semibold tracking-[.12em] text-emerald-500/70 uppercase">
                                        <span className="h-1 w-1 rounded-full bg-emerald-500" />
                                        Feature 03
                                    </div>
                                    <h3 className="mb-2 text-sm font-bold text-white">
                                        Origin & History
                                    </h3>
                                    <p className="text-xs text-slate-400">
                                        Learn about your dog's breed origins,
                                        historical purpose, and cultural
                                        significance
                                    </p>
                                </div>
                                <button className="mt-4 flex w-fit items-center gap-2 rounded-xl border border-emerald-500/20 bg-emerald-500/[.08] px-3 py-1.5 text-xs font-semibold text-emerald-400 transition-all hover:bg-emerald-500/[.14]">
                                    <MapPin className="h-3 w-3" />
                                    Explore history
                                    <ChevronRight
                                        size={10}
                                        className="opacity-50"
                                    />
                                </button>
                            </div>
                        </div>

                        {/* ── STATS ROW ── */}
                        <div className="grid grid-cols-3 gap-3">
                            {[
                                {
                                    icon: <Target size={13} />,
                                    label: 'Total Scans',
                                    value: '12,400+',
                                },
                                {
                                    icon: <Zap size={13} />,
                                    label: 'Avg Speed',
                                    value: '~1.2s',
                                },
                                {
                                    icon: <TrendingUp size={13} />,
                                    label: 'Accuracy',
                                    value: '94.7%',
                                },
                            ].map((s, i) => (
                                <div
                                    key={i}
                                    className="flex flex-col items-center justify-center gap-1.5 rounded-2xl border border-white/[.06] bg-[#131720] px-3 py-3.5 text-center transition-all hover:border-emerald-500/20"
                                >
                                    <span className="text-emerald-500">
                                        {s.icon}
                                    </span>
                                    <span className="lp-mono text-base font-bold text-white">
                                        {s.value}
                                    </span>
                                    <span className="lp-mono text-[9px] tracking-[.1em] text-slate-500 uppercase">
                                        {s.label}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* ── SIDE CARD ── */}
                    <div className="lp-panel lp-card group relative w-full overflow-hidden rounded-2xl border border-white/[.07] bg-[#131720] p-4 transition-all hover:border-emerald-500/20 sm:p-6 lg:w-[400px]">
                        <div className="lp-card-beam" />

                        {/* Terminal bar */}
                        <div className="absolute top-0 right-0 left-0 flex items-center gap-2 border-b border-white/[.06] bg-black/30 px-3.5 py-2">
                            <div className="flex gap-1.5">
                                <div className="h-2 w-2 rounded-full bg-[#FF5F57]" />
                                <div className="h-2 w-2 rounded-full bg-[#FEBC2E]" />
                                <div className="lp-hudblink h-2 w-2 rounded-full bg-emerald-500 shadow-[0_0_5px_#10b981]" />
                            </div>
                            <span className="lp-mono ml-1 text-[10px] text-slate-500 select-none">
                                doglens://profile
                            </span>
                        </div>

                        <div className="relative mt-8 mb-4 overflow-hidden rounded-xl border border-emerald-500/15">
                            <img
                                src="/dog1.png"
                                className="block h-[200px] w-full object-cover opacity-90 sm:h-[250px]"
                                alt="Dog breed identification"
                            />
                            <div className="lp-scanline absolute inset-0" />
                            <div className="lp-sweep" />
                            {['lp-htl', 'lp-htr', 'lp-hbl', 'lp-hbr'].map(
                                (c) => (
                                    <div key={c} className={`lp-hc ${c}`} />
                                ),
                            )}
                            <div className="absolute right-2.5 bottom-2.5 left-2.5 flex items-center justify-between">
                                <span className="lp-mono rounded border border-emerald-500/20 bg-black/70 px-2 py-0.5 text-[9px] font-medium tracking-[.1em] text-emerald-400 backdrop-blur-sm">
                                    SCANNING
                                </span>
                                <span className="lp-mono rounded border border-emerald-500/15 bg-black/70 px-2 py-0.5 text-[8px] text-emerald-400 backdrop-blur-sm">
                                    AI ACTIVE
                                </span>
                            </div>
                        </div>

                        <div className="mb-1 flex items-center gap-2">
                            <div className="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-md border border-emerald-500/20 bg-emerald-500/10 text-emerald-500">
                                <Activity size={10} />
                            </div>
                            <span className="lp-mono text-[10px] font-bold tracking-[.12em] text-slate-400 uppercase">
                                Analysis Engine
                            </span>
                        </div>

                        <h2 className="mb-3 text-base font-bold text-white">
                            Professional Breed Analysis You Can Trust
                        </h2>

                        {/* Vet badge */}
                        <div className="mb-3 flex items-center gap-2.5 rounded-xl border border-emerald-500/15 bg-emerald-500/[.05] px-3 py-2.5">
                            <div className="flex h-8 w-8 items-center justify-center rounded-lg border border-emerald-500/20 bg-emerald-500/10">
                                <ShieldCheck className="h-4 w-4 text-emerald-500" />
                            </div>
                            <div className="flex-1">
                                <p className="text-xs font-bold text-white">
                                    Veterinary Verified
                                </p>
                                <p className="text-[11px] text-slate-400">
                                    Licensed vet reviews predictions
                                </p>
                            </div>
                            <div className="lp-ticker h-1.5 w-1.5 rounded-full bg-emerald-500 shadow-[0_0_5px_#10b981]" />
                        </div>

                        <div className="mb-4 border-t border-white/[.06] pt-3">
                            {[
                                {
                                    icon: <Zap size={10} />,
                                    text: 'Results in ~1.2 seconds',
                                },
                                {
                                    icon: <PawPrintIcon size={10} />,
                                    text: 'Supports 120+ dog breeds',
                                },
                                {
                                    icon: <ShieldCheck size={10} />,
                                    text: 'Vet-reviewed confidence score',
                                },
                            ].map((item, i) => (
                                <div
                                    key={i}
                                    className="flex items-center gap-2 py-1.5"
                                >
                                    <span className="text-emerald-500">
                                        {item.icon}
                                    </span>
                                    <span className="text-xs text-slate-400">
                                        {item.text}
                                    </span>
                                </div>
                            ))}
                        </div>

                        <Link href={login()}>
                            <button
                                className="lp-shim w-full rounded-xl border border-emerald-500/30 bg-white/[.04] py-3 text-sm font-bold text-white transition-all hover:border-emerald-500/60 hover:bg-emerald-500/[.08]"
                                onClick={() => setOpen(false)}
                            >
                                Get Started Now
                            </button>
                        </Link>
                    </div>
                </div>
            </div>
        </>
    );
}

export default LandingPage;
