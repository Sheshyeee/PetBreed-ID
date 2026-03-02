import Header from '@/components/header';
import { Link, router } from '@inertiajs/react';
import {
    Calendar,
    Camera,
    ChevronRight,
    Clock,
    Download,
    Filter,
    History,
    QrCode,
    Search,
    Shield,
    Smartphone,
    Trash2,
    TrendingUp,
    X,
    Zap,
} from 'lucide-react';
import { useState } from 'react';

interface Scan {
    id: number;
    scan_id: string;
    image: string;
    breed: string;
    confidence: number;
    date: string;
    status: 'pending' | 'verified';
}
interface User {
    name: string;
    email: string;
    avatar?: string;
}
interface Props {
    mockScans: Scan[];
    user: User;
}

export default function ScanHistory({ mockScans, user }: Props) {
    const [showQRModal, setShowQRModal] = useState(false);
    const [search, setSearch] = useState('');
    const [filter, setFilter] = useState<'all' | 'verified' | 'pending'>('all');
    const [deletingId, setDeletingId] = useState<number | null>(null);

    const handleDelete = (id: number) => {
        setDeletingId(id);
        router.delete(`/scanhistory/${id}`, {
            preserveScroll: true,
            onSuccess: () => setDeletingId(null),
            onError: () => {
                setDeletingId(null);
                alert('Failed to delete. Please try again.');
            },
        });
    };

    const filtered = mockScans.filter(
        (s) =>
            s.breed.toLowerCase().includes(search.toLowerCase()) &&
            (filter === 'all' || s.status === filter),
    );

    const verifiedCount = mockScans.filter(
        (s) => s.status === 'verified',
    ).length;
    const pendingCount = mockScans.filter((s) => s.status === 'pending').length;
    const avgConfidence =
        mockScans.length > 0
            ? Math.round(
                  mockScans.reduce((a, s) => a + s.confidence, 0) /
                      mockScans.length,
              )
            : 0;

    return (
        <>
            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Space+Mono:wght@400;700&family=DM+Sans:wght@300;400;500&family=IBM+Plex+Sans:wght@300;400;500;600;700&display=swap');

                @keyframes sh-bar-fill   { from { width: 0 } }
                @keyframes sh-fade-up    { from { opacity:0; transform:translateY(20px) } to { opacity:1; transform:translateY(0) } }
                @keyframes sh-modal-pop  { from { opacity:0; transform:scale(.94) translateY(10px) } to { opacity:1; transform:scale(1) translateY(0) } }
                @keyframes sh-ring       { 0%{transform:scale(1);opacity:.6} 100%{transform:scale(1.5);opacity:0} }
                @keyframes sh-blink      { 0%,100%{opacity:1} 50%{opacity:.3} }
                @keyframes sh-glow       { 0%,100%{transform:translate(0,0) scale(1)} 33%{transform:translate(30px,-20px) scale(1.06)} 66%{transform:translate(-20px,15px) scale(.96)} }
                @keyframes sh-scan-line  { 0%{top:0;opacity:.7} 100%{top:100%;opacity:0} }
                @keyframes sh-fab-ring   { 0%{transform:scale(1);opacity:.7} 100%{transform:scale(1.34);opacity:0} }

                .sh-font-syne  { font-family: 'Syne', sans-serif; }
                .sh-font-mono  { font-family: 'Space Mono', monospace; }
                .sh-font-dm    { font-family: 'DM Sans', sans-serif; }
                .sh-font-ibm   { font-family: 'IBM Plex Sans', sans-serif; }

                .sh-grid-bg {
                    position:fixed; inset:0; pointer-events:none; z-index:0;
                    background-image: linear-gradient(rgba(16,185,129,.04) 1px, transparent 1px),
                                      linear-gradient(90deg, rgba(16,185,129,.04) 1px, transparent 1px);
                    background-size: 48px 48px;
                    mask-image: radial-gradient(ellipse 80% 60% at 50% 0%, black, transparent);
                }

                .sh-orb-1 {
                    position:fixed; border-radius:50%; filter:blur(80px); pointer-events:none; z-index:0;
                    width:500px; height:500px; background:rgba(16,185,129,.04);
                    top:-200px; left:-150px;
                    animation: sh-glow 20s ease-in-out infinite;
                }
                .sh-orb-2 {
                    position:fixed; border-radius:50%; filter:blur(80px); pointer-events:none; z-index:0;
                    width:350px; height:350px; background:rgba(6,182,212,.035);
                    bottom:-100px; right:-80px;
                    animation: sh-glow 25s ease-in-out infinite reverse;
                }

                .sh-fade-up     { animation: sh-fade-up .5s ease both; }
                .sh-modal-pop   { animation: sh-modal-pop .28s cubic-bezier(.16,1,.3,1) both; }
                .sh-bar-fill    { animation: sh-bar-fill 1.6s cubic-bezier(.16,1,.3,1) forwards; }
                .sh-conf-fill   { animation: sh-bar-fill 1.5s cubic-bezier(.16,1,.3,1) forwards; }
                .sh-blink-dot   { animation: sh-blink 1.8s ease-in-out infinite; }
                .sh-ring-expand { animation: sh-ring 2.5s ease-out infinite; }

                .sh-fab::before {
                    content:''; position:absolute; inset:-4px; border-radius:17px;
                    border:1.5px solid rgba(16,185,129,.25);
                    animation: sh-fab-ring 2.5s ease-out infinite;
                }
                .sh-scan-line-anim { animation: sh-scan-line 1.4s linear infinite; }

                .sh-card:hover .sh-scan-line-el { opacity:1; animation: sh-scan-line 1.4s linear infinite; }
                .sh-scan-line-el { opacity: 0; transition: opacity .1s; }

                .sh-card:hover .sh-hud-corner { opacity: 1; }
                .sh-hud-corner { opacity: 0; transition: opacity .25s; }

                .sh-card:hover .sh-del-overlay { opacity: 1; }
                .sh-del-overlay { opacity: 0; transition: opacity .2s; }

                .sh-nsb::-webkit-scrollbar { display:none; }
                .sh-nsb { scrollbar-width: none; }

                /* Stats strip */
                .sh-stat-card {
                    position: relative;
                    padding: 20px 22px 18px;
                    border-right: 1px solid;
                    border-bottom: 1px solid;
                    transition: background .15s;
                }
                .sh-stat-card:hover { background: rgba(16,185,129,.025); }
                .sh-stat-num {
                    font-family: 'IBM Plex Sans', sans-serif;
                    font-weight: 600;
                    font-size: clamp(1.75rem, 3.5vw, 2.25rem);
                    line-height: 1;
                    letter-spacing: -0.02em;
                    color: #0f172a;
                }
                :is(.dark) .sh-stat-num { color: #f1f5f9; }
                .sh-stat-label {
                    font-family: 'IBM Plex Sans', sans-serif;
                    font-size: 10px;
                    font-weight: 500;
                    letter-spacing: .08em;
                    text-transform: uppercase;
                    color: #94a3b8;
                    margin-bottom: 10px;
                }
                .sh-stat-sub {
                    font-family: 'IBM Plex Sans', sans-serif;
                    font-size: 11px;
                    font-weight: 400;
                    color: #94a3b8;
                    margin-top: 4px;
                }
                :is(.dark) .sh-stat-sub { color: #475569; }
                .sh-stat-bar-track {
                    position: absolute;
                    bottom: 0; left: 0; right: 0;
                    height: 2px;
                    background: #f1f5f9;
                }
                :is(.dark) .sh-stat-bar-track { background: rgba(255,255,255,.05); }
                .sh-stat-bar-fill {
                    height: 100%;
                    background: #10b981;
                    animation: sh-bar-fill 1.6s cubic-bezier(.16,1,.3,1) forwards;
                }
                .sh-stat-icon {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    width: 28px;
                    height: 28px;
                    border-radius: 7px;
                    border: 1px solid rgba(16,185,129,.18);
                    background: rgba(16,185,129,.07);
                    color: #10b981;
                    margin-bottom: 14px;
                }
            `}</style>

            <div className="sh-font-dm relative min-h-screen overflow-x-hidden bg-slate-50 text-slate-700 dark:bg-[#070A0E] dark:text-slate-200">
                {/* Background elements */}
                <div className="pointer-events-none fixed inset-0 z-0 bg-[radial-gradient(ellipse_60%_40%_at_20%_10%,rgba(16,185,129,.07)_0%,transparent_70%),radial-gradient(ellipse_50%_35%_at_80%_85%,rgba(6,182,212,.05)_0%,transparent_70%)]" />
                <div className="sh-grid-bg hidden dark:block" />
                <div className="sh-orb-1 hidden dark:block" />
                <div className="sh-orb-2 hidden dark:block" />

                <div className="relative z-20">
                    <Header />
                </div>

                {/* QR Modal */}
                {showQRModal && (
                    <div
                        className="fixed inset-0 z-[60] flex items-center justify-center bg-black/75 p-4 backdrop-blur-xl"
                        onClick={() => setShowQRModal(false)}
                    >
                        <div
                            className="sh-modal-pop relative w-full max-w-[340px] overflow-hidden rounded-[22px] border border-slate-200 bg-white p-6 shadow-2xl dark:border-white/[.08] dark:bg-[#0e1218]"
                            onClick={(e) => e.stopPropagation()}
                        >
                            <div className="absolute top-0 right-0 left-0 h-px bg-gradient-to-r from-transparent via-emerald-500 to-transparent opacity-50" />

                            <button
                                onClick={() => setShowQRModal(false)}
                                className="absolute top-2.5 right-2.5 flex h-6 w-6 items-center justify-center rounded-lg bg-slate-100 text-slate-400 hover:bg-slate-200 dark:bg-white/[.06] dark:hover:bg-white/10"
                            >
                                <X size={12} />
                            </button>

                            <div className="mb-[18px] text-center">
                                <div className="mx-auto mb-3 flex h-10 w-10 items-center justify-center rounded-[11px] border border-emerald-500/20 bg-emerald-500/10 text-emerald-500">
                                    <Smartphone size={18} />
                                </div>
                                <div className="sh-font-syne mb-0.5 text-base font-extrabold text-slate-900 dark:text-white">
                                    Install Mobile App
                                </div>
                                <div className="text-[11px] text-slate-400">
                                    Scan to download the Android app
                                </div>
                            </div>

                            <div className="mb-[18px] flex justify-center">
                                <div className="rounded-xl bg-white p-2 shadow-lg">
                                    <img
                                        src="/doglens_apk_qr.jpeg"
                                        alt="QR"
                                        className="block h-28 w-28"
                                    />
                                </div>
                            </div>

                            <div className="mb-4 overflow-hidden rounded-[10px] border border-slate-200 dark:border-white/[.07]">
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
                                        className="flex items-center gap-2 border-b border-slate-100 bg-slate-50 px-3 py-2 text-[11px] text-slate-600 last:border-none dark:border-white/[.05] dark:bg-white/[.02] dark:text-slate-400"
                                    >
                                        <div className="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-[5px] bg-emerald-500/10 text-emerald-500">
                                            {f.icon}
                                        </div>
                                        {f.t}
                                    </div>
                                ))}
                            </div>

                            <button
                                onClick={() => setShowQRModal(false)}
                                className="sh-font-syne w-full rounded-[10px] bg-gradient-to-r from-emerald-500 to-cyan-500 py-3 text-xs font-extrabold text-black"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                )}

                {/* FAB */}
                <button
                    className="sh-fab fixed right-[18px] bottom-[18px] z-40 flex h-11 w-11 items-center justify-center rounded-[13px] bg-emerald-500 text-black shadow-lg shadow-emerald-500/25 transition-transform hover:scale-105 active:scale-95"
                    onClick={() => setShowQRModal(true)}
                >
                    <QrCode size={17} />
                </button>

                {/* PAGE BODY */}
                <div className="relative z-10 mx-auto mt-[-20px] max-w-[1280px] px-8 pb-20">
                    {/* HERO */}
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 py-5 dark:border-white/[.05]">
                        <div className="flex items-center gap-3">
                            <div className="sh-font-mono inline-flex items-center gap-1.5 rounded-full border border-emerald-500/20 bg-emerald-500/[.07] px-2.5 py-1 text-[9px] font-bold tracking-[.18em] text-emerald-600 uppercase dark:border-emerald-500/18 dark:text-emerald-400">
                                <span className="sh-blink-dot h-1.5 w-1.5 rounded-full bg-emerald-500" />
                                Scan Records
                            </div>
                            <h1 className="sh-font-syne m-0 text-xl leading-none font-extrabold tracking-tight text-slate-900 sm:text-2xl dark:text-white">
                                Scan{' '}
                                <span className="bg-gradient-to-br from-emerald-500 to-cyan-500 bg-clip-text text-transparent">
                                    History
                                </span>
                            </h1>
                        </div>
                        <Link
                            href="/scan"
                            className="sh-font-syne inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-emerald-500 to-cyan-500 px-4 py-2 text-[12px] font-bold whitespace-nowrap text-black no-underline shadow-md shadow-emerald-500/20 transition-all hover:-translate-y-0.5"
                        >
                            <Zap size={12} />
                            New Scan
                            <ChevronRight size={11} className="opacity-60" />
                        </Link>
                    </div>

                    {/* STATS STRIP */}
                    <div className="mt-7 mb-7 grid grid-cols-2 overflow-hidden rounded-2xl border border-slate-200 bg-white sm:grid-cols-4 dark:border-white/[.06] dark:bg-[#0d1117]">
                        {[
                            {
                                icon: <History size={13} />,
                                lbl: 'Total Scans',
                                val: mockScans.length,
                                sub: 'All time',
                                bar: 100,
                            },
                            {
                                icon: <Shield size={13} />,
                                lbl: 'Verified',
                                val: verifiedCount,
                                sub: 'By licensed vets',
                                bar: mockScans.length
                                    ? (verifiedCount / mockScans.length) * 100
                                    : 0,
                            },
                            {
                                icon: <Clock size={13} />,
                                lbl: 'Pending',
                                val: pendingCount,
                                sub: 'Awaiting review',
                                bar: mockScans.length
                                    ? (pendingCount / mockScans.length) * 100
                                    : 0,
                            },
                            {
                                icon: <TrendingUp size={13} />,
                                lbl: 'Avg. Confidence',
                                val: `${avgConfidence}%`,
                                sub: 'Accuracy score',
                                bar: avgConfidence,
                            },
                        ].map((s, i) => (
                            <div
                                key={i}
                                className={`sh-stat-card sh-fade-up border-slate-100 dark:border-white/[.05] sm:[&:last-child]:border-r-0 [&:nth-child(2n)]:border-r-0 sm:[&:nth-child(2n)]:border-r [&:nth-child(n+3)]:border-b-0 sm:[&:nth-child(n+3)]:border-b`}
                                style={{ animationDelay: `${i * 0.08}s` }}
                            >
                                <div className="sh-stat-icon">{s.icon}</div>
                                <div className="sh-stat-label">{s.lbl}</div>
                                <div className="sh-stat-num">{s.val}</div>
                                <div className="sh-stat-sub">{s.sub}</div>
                                <div className="sh-stat-bar-track">
                                    <div
                                        className="sh-stat-bar-fill"
                                        style={{
                                            width: `${s.bar}%`,
                                            animationDelay: `${i * 0.12 + 0.3}s`,
                                        }}
                                    />
                                </div>
                            </div>
                        ))}
                    </div>

                    {/* VET BANNER */}
                    <div className="mb-7 flex items-start gap-3.5 rounded-[14px] border border-slate-200 bg-slate-50 p-4 dark:border-white/[.06] dark:bg-white/[.025]">
                        <div className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-[9px] border border-slate-200 bg-white text-slate-500 dark:border-white/[.07] dark:bg-white/[.05] dark:text-slate-400">
                            <Shield size={14} />
                        </div>
                        <div>
                            <div className="sh-font-ibm font-600 mb-0.5 text-[12px] font-semibold text-slate-700 dark:text-slate-300">
                                Veterinarian Verification
                            </div>
                            <div className="sh-font-ibm text-[11px] leading-relaxed text-slate-400 dark:text-slate-500">
                                All system breed identifications can be reviewed
                                by licensed veterinarians. Verified scans are
                                confirmed by professional vets, while pending
                                scans await review.
                            </div>
                        </div>
                    </div>

                    {/* TOOLBAR */}
                    <div className="mb-6 flex flex-wrap items-center gap-2">
                        <div className="relative min-w-[180px] flex-1">
                            <Search
                                size={13}
                                className="pointer-events-none absolute top-1/2 left-[11px] -translate-y-1/2 text-slate-300 dark:text-slate-600"
                            />
                            <input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search breeds…"
                                className="sh-font-dm w-full rounded-[10px] border border-slate-200 bg-white py-[10px] pr-3 pl-9 text-[13px] text-slate-700 transition-all outline-none placeholder:text-slate-300 focus:border-emerald-500/35 focus:bg-emerald-500/[.02] dark:border-white/[.08] dark:bg-white/[.04] dark:text-slate-200 dark:placeholder:text-slate-600 dark:focus:border-emerald-500/35 dark:focus:bg-emerald-500/[.04]"
                            />
                        </div>
                        {(['all', 'verified', 'pending'] as const).map((f) => (
                            <button
                                key={f}
                                onClick={() => setFilter(f)}
                                className={`sh-font-mono flex items-center gap-[5px] rounded-[9px] border px-[13px] py-[9px] text-[9px] font-bold tracking-[.06em] whitespace-nowrap uppercase transition-all ${
                                    filter === f
                                        ? 'border-emerald-500/35 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                        : 'border-slate-200 bg-white text-slate-400 hover:border-emerald-500/25 hover:bg-emerald-500/[.04] hover:text-emerald-600 dark:border-white/[.07] dark:bg-white/[.03] dark:text-slate-500 dark:hover:text-emerald-400'
                                }`}
                            >
                                <Filter size={9} />
                                {f.charAt(0).toUpperCase() + f.slice(1)}
                            </button>
                        ))}
                    </div>

                    {/* EMPTY: no scans */}
                    {mockScans.length === 0 && (
                        <div className="relative overflow-hidden rounded-[20px] border border-slate-200 bg-white/60 px-5 py-[60px] text-center backdrop-blur-sm dark:border-white/[.06] dark:bg-white/[.02]">
                            <div className="relative mx-auto mb-4 inline-flex h-[72px] w-[72px] items-center justify-center rounded-full border border-emerald-500/15 bg-emerald-500/[.07] text-emerald-500">
                                <div className="sh-ring-expand absolute inset-[-10px] rounded-full border border-emerald-500/[.08]" />
                                <Calendar size={28} />
                            </div>
                            <div className="sh-font-syne mb-1.5 text-xl font-extrabold text-slate-900 dark:text-white">
                                No scans yet
                            </div>
                            <div className="mb-5 text-[12px] text-slate-400">
                                Start by scanning your first pet!
                            </div>
                            <Link
                                href="/scan"
                                className="sh-font-syne inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-emerald-500 to-cyan-500 px-5 py-[11px] text-[13px] font-extrabold text-black no-underline"
                            >
                                <Zap size={13} />
                                Scan Your Pet
                            </Link>
                        </div>
                    )}

                    {/* EMPTY: no results */}
                    {mockScans.length > 0 && filtered.length === 0 && (
                        <div className="relative overflow-hidden rounded-[20px] border border-slate-200 bg-white/60 px-5 py-[60px] text-center backdrop-blur-sm dark:border-white/[.06] dark:bg-white/[.02]">
                            <div className="relative mx-auto mb-4 inline-flex h-[72px] w-[72px] items-center justify-center rounded-full border border-emerald-500/15 bg-emerald-500/[.07] text-emerald-500">
                                <div className="sh-ring-expand absolute inset-[-10px] rounded-full border border-emerald-500/[.08]" />
                                <Search size={26} />
                            </div>
                            <div className="sh-font-syne mb-1.5 text-xl font-extrabold text-slate-900 dark:text-white">
                                No results found
                            </div>
                            <div className="text-[12px] text-slate-400">
                                Try adjusting your search or filter.
                            </div>
                        </div>
                    )}

                    {/* SCAN GRID */}
                    {filtered.length > 0 && (
                        <div className="columns-1 gap-[18px] sm:columns-2 lg:columns-3">
                            {filtered.map((scan, idx) => (
                                <div
                                    key={scan.id}
                                    className="sh-card sh-fade-up group mb-[18px] inline-block w-full overflow-hidden rounded-[18px] border border-slate-200 bg-white shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-emerald-500/25 hover:shadow-xl hover:shadow-emerald-500/[.07] dark:border-white/[.07] dark:bg-white/[.025] dark:shadow-none"
                                    style={{ animationDelay: `${idx * 0.04}s` }}
                                >
                                    {/* Image */}
                                    <div className="relative h-[200px] overflow-hidden sm:h-[220px]">
                                        <img
                                            src={scan.image}
                                            alt={scan.breed}
                                            className="h-full w-full object-cover transition-transform duration-[600ms] group-hover:scale-[1.07]"
                                        />

                                        <div
                                            className="sh-scan-line-el pointer-events-none absolute top-0 right-0 left-0 z-[4] h-[2px] bg-gradient-to-r from-transparent via-emerald-500/70 to-transparent"
                                            style={{ position: 'absolute' }}
                                        />

                                        <div className="absolute inset-0 bg-gradient-to-t from-black/85 via-black/20 to-transparent" />

                                        {[
                                            'top-[7px] left-[7px] border-t-2 border-l-2',
                                            'top-[7px] right-[7px] border-t-2 border-r-2',
                                            'bottom-[7px] left-[7px] border-b-2 border-l-2',
                                            'bottom-[7px] right-[7px] border-b-2 border-r-2',
                                        ].map((cls, i) => (
                                            <div
                                                key={i}
                                                className={`sh-hud-corner pointer-events-none absolute z-[3] h-[13px] w-[13px] border-emerald-500/60 ${cls}`}
                                            />
                                        ))}

                                        <div
                                            className={`sh-font-mono absolute top-[9px] left-[9px] z-[4] rounded-[5px] px-2 py-[3px] text-[8px] font-bold tracking-[.08em] uppercase backdrop-blur-sm ${
                                                scan.status === 'verified'
                                                    ? 'border border-emerald-500/35 bg-emerald-500/20 text-emerald-400'
                                                    : 'border border-amber-500/30 bg-amber-500/15 text-amber-400'
                                            }`}
                                        >
                                            {scan.status === 'verified'
                                                ? '✓ Verified'
                                                : '⏳ Pending'}
                                        </div>

                                        <div className="sh-del-overlay absolute top-[9px] right-[9px] z-[5]">
                                            <button
                                                onClick={() =>
                                                    handleDelete(scan.id)
                                                }
                                                disabled={
                                                    deletingId === scan.id
                                                }
                                                className="flex h-7 w-7 items-center justify-center rounded-[7px] border border-red-500/50 bg-red-500/70 text-white backdrop-blur-sm transition-colors hover:bg-red-500/90 disabled:cursor-not-allowed disabled:opacity-40"
                                            >
                                                <Trash2 size={11} />
                                            </button>
                                        </div>

                                        <div className="sh-font-mono absolute right-[9px] bottom-[9px] z-[4] rounded-[5px] bg-emerald-500 px-2 py-0.5 text-[10px] font-bold text-black">
                                            {scan.confidence}%
                                        </div>
                                    </div>

                                    {/* Card body */}
                                    <div className="px-4 pt-3.5 pb-3">
                                        <div className="sh-font-syne mb-2.5 text-[15px] leading-tight font-bold text-slate-900 dark:text-white">
                                            {scan.breed}
                                        </div>
                                        <div className="mb-1.5 flex items-center justify-between">
                                            <span className="sh-font-mono text-[8px] font-bold tracking-[.1em] text-slate-400 uppercase dark:text-slate-500">
                                                Confidence
                                            </span>
                                            <span className="sh-font-mono text-[10px] font-bold text-emerald-600 dark:text-emerald-400">
                                                {scan.confidence}%
                                            </span>
                                        </div>
                                        <div className="mb-3 h-[3px] overflow-hidden rounded-full bg-slate-100 dark:bg-white/[.07]">
                                            <div
                                                className="sh-conf-fill h-full rounded-full bg-emerald-500"
                                                style={{
                                                    width: `${scan.confidence}%`,
                                                }}
                                            />
                                        </div>
                                    </div>

                                    {/* Card footer */}
                                    <div className="flex items-center justify-between border-t border-slate-100 px-4 py-2.5 dark:border-white/[.05]">
                                        <div>
                                            <div className="sh-font-mono mb-0.5 flex items-center gap-[5px] text-[9px] text-slate-300 dark:text-slate-600">
                                                <Calendar size={9} />
                                                {scan.date}
                                            </div>
                                            <div className="sh-font-mono max-w-[110px] overflow-hidden text-[8px] text-ellipsis whitespace-nowrap text-slate-200 dark:text-slate-700">
                                                {scan.scan_id}
                                            </div>
                                        </div>
                                        <button
                                            onClick={() =>
                                                handleDelete(scan.id)
                                            }
                                            disabled={deletingId === scan.id}
                                            className="sh-font-mono flex items-center gap-1 rounded-[7px] border border-red-500/15 bg-transparent px-[10px] py-[5px] text-[9px] font-bold tracking-[.04em] text-red-400/55 transition-all hover:border-red-500/35 hover:bg-red-500/[.05] hover:text-red-500 disabled:cursor-not-allowed disabled:opacity-35"
                                        >
                                            <Trash2 size={9} />
                                            {deletingId === scan.id
                                                ? 'Deleting…'
                                                : 'Delete'}
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
