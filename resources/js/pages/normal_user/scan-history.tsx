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
                @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap');

                @keyframes sh-barfill  { from{width:0} }
                @keyframes sh-faderise { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
                @keyframes sh-dpulse   { 0%,100%{transform:scale(1);box-shadow:0 0 8px #10b981} 50%{transform:scale(1.3);box-shadow:0 0 16px #10b981} }
                @keyframes sh-ringpop  { 0%{transform:scale(.9);opacity:.65} 70%,100%{transform:scale(1.1);opacity:0} }
                @keyframes sh-ticker   { from{opacity:.25} to{opacity:1} }
                @keyframes sh-huddim   { 0%,88%,100%{opacity:1} 94%{opacity:.12} }
                @keyframes sh-fabring  { 0%{transform:scale(1);opacity:.7} 100%{transform:scale(1.32);opacity:0} }
                @keyframes sh-modalup  { from{transform:translateY(14px) scale(.97);opacity:0} to{transform:translateY(0) scale(1);opacity:1} }
                @keyframes sh-cardup   { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
                @keyframes sh-gd       { from{transform:translateX(0) scale(1)} to{transform:translateX(40px) scale(1.07)} }
                @keyframes sh-confbar  { from{width:0} }

                .sh-root { font-family:'Plus Jakarta Sans',sans-serif; }
                .sh-root * { box-sizing:border-box; }
                .sh-mono { font-family:'JetBrains Mono',monospace !important; }

                /* dot-grid bg (matches scan page) */
                .sh-dotgrid {
                    position:fixed; inset:0; pointer-events:none; z-index:0;
                    background-image:radial-gradient(circle,rgba(16,185,129,.08) 1px,transparent 1px);
                    background-size:28px 28px;
                    -webkit-mask-image:radial-gradient(ellipse 80% 55% at 50% 0%,black 0%,transparent 100%);
                    mask-image:radial-gradient(ellipse 80% 55% at 50% 0%,black 0%,transparent 100%);
                }
                .dark .sh-dotgrid { background-image:radial-gradient(circle,rgba(16,185,129,.06) 1px,transparent 1px); }

                /* scan-card entrance */
                .sh-card { animation:sh-cardup .38s ease both; }

                /* stat card hover accent line */
                .sh-stat::before { content:''; position:absolute; top:0; left:0; right:0; height:1.5px; background:linear-gradient(90deg,transparent,#10b981 45%,#06b6d4 55%,transparent); opacity:0; transition:opacity .3s; }
                .sh-stat:hover::before { opacity:.5; }

                /* scan card top line */
                .sh-scancard::before { content:''; position:absolute; top:0; left:0; right:0; height:1.5px; background:linear-gradient(90deg,transparent,#10b981,transparent); opacity:0; transition:opacity .3s; z-index:3; }
                .sh-scancard:hover::before { opacity:.65; }

                /* HUD corner brackets on image */
                .sh-hc  { position:absolute; width:15px; height:15px; border-color:#10b981; border-style:solid; opacity:0; transition:opacity .25s; z-index:2; }
                .sh-scancard:hover .sh-hc { opacity:.75; }
                .sh-htl { top:7px;    left:7px;  border-width:2px 0 0 2px; }
                .sh-htr { top:7px;    right:7px; border-width:2px 2px 0 0; }
                .sh-hbl { bottom:7px; left:7px;  border-width:0 0 2px 2px; }
                .sh-hbr { bottom:7px; right:7px; border-width:0 2px 2px 0; }

                /* confidence bar animation */
                .sh-confbar { animation:sh-confbar 1.4s ease-out forwards; }

                /* stat bar animation */
                .sh-statbar { animation:sh-barfill 1.7s ease-out forwards; }

                /* new scan button shimmer */
                .sh-newscan { position:relative; overflow:hidden; }
                .sh-newscan::before { content:''; position:absolute; top:0; left:-100%; width:55%; height:100%; background:linear-gradient(90deg,transparent,rgba(255,255,255,.22),transparent); transform:skewX(-18deg); transition:left .5s; }
                .sh-newscan:hover::before { left:160%; }

                /* FAB ring */
                .sh-fab::before { content:''; position:absolute; inset:-4px; border-radius:15px; border:1.5px solid rgba(16,185,129,.27); animation:sh-fabring 2.1s ease-out infinite; }

                /* empty state ring */
                .sh-empty-ring::before { content:''; position:absolute; inset:-8px; border-radius:50%; border:1px solid rgba(16,185,129,.1); animation:sh-ringpop 2.4s ease-out infinite; }

                /* misc */
                .sh-ticker   { animation:sh-ticker 1.3s ease-in-out infinite alternate; }
                .sh-hudblink { animation:sh-huddim 3s ease-in-out infinite; }
                .sh-fu       { animation:sh-faderise .44s cubic-bezier(.16,1,.3,1) both; }
                .sh-modalup  { animation:sh-modalup .28s cubic-bezier(.16,1,.3,1) both; }
                .sh-nsb::-webkit-scrollbar { display:none; }
                .sh-nsb { scrollbar-width:none; }
            `}</style>

            <div className="sh-root min-h-screen bg-slate-50 transition-colors duration-300 dark:bg-[#080B0F]">
                {/* ambient glows */}
                <div
                    className="pointer-events-none fixed top-[-140px] left-[-70px] z-0 h-[260px] w-[460px] rounded-full bg-emerald-400/[.042] blur-[85px]"
                    style={{
                        animation: 'sh-gd 14s ease-in-out infinite alternate',
                    }}
                />
                <div
                    className="pointer-events-none fixed top-[-90px] right-[-40px] z-0 h-[210px] w-[340px] rounded-full bg-cyan-400/[.028] blur-[85px]"
                    style={{
                        animation:
                            'sh-gd 14s ease-in-out infinite alternate-reverse',
                    }}
                />
                <div className="sh-dotgrid" />

                {/* header */}
                <div className="relative z-20">
                    <Header />
                </div>

                {/* QR Modal */}
                {showQRModal && (
                    <div
                        className="fixed inset-0 z-50 flex items-center justify-center bg-black/65 p-4 backdrop-blur-xl"
                        onClick={() => setShowQRModal(false)}
                    >
                        <div
                            className="sh-modalup relative w-full max-w-sm overflow-hidden rounded-2xl border border-slate-200 bg-white p-7 shadow-2xl dark:border-white/[.08] dark:bg-[#131720]"
                            onClick={(e) => e.stopPropagation()}
                        >
                            <button
                                onClick={() => setShowQRModal(false)}
                                className="absolute top-3 right-3 flex h-7 w-7 items-center justify-center rounded-lg bg-slate-100 text-slate-400 transition-colors hover:bg-slate-200 dark:bg-white/[.06] dark:hover:bg-white/10"
                            >
                                <X size={13} />
                            </button>
                            <div className="mb-5 text-center">
                                <div className="mx-auto mb-3 flex h-11 w-11 items-center justify-center rounded-xl border border-emerald-500/20 bg-emerald-500/10">
                                    <Smartphone
                                        size={20}
                                        className="text-emerald-500"
                                    />
                                </div>
                                <h2 className="text-lg font-bold text-slate-900 dark:text-white">
                                    Install Mobile App
                                </h2>
                                <p className="mt-1 text-xs text-slate-400">
                                    Scan to download the Android app
                                </p>
                            </div>
                            <div className="mb-5 flex justify-center">
                                <div className="rounded-xl bg-white p-2.5 shadow-lg">
                                    <img
                                        src="/doglens_apk_qr.jpeg"
                                        alt="QR"
                                        className="block h-32 w-32"
                                    />
                                </div>
                            </div>
                            <div className="mb-4 overflow-hidden rounded-xl border border-slate-200 dark:border-white/[.07]">
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
                                        className="flex items-center gap-2.5 border-b border-slate-100 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-600 last:border-none dark:border-white/[.05] dark:bg-white/[.02] dark:text-slate-400"
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
                                className="w-full rounded-xl bg-emerald-500 py-3 text-sm font-bold text-black transition-colors hover:bg-emerald-400"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                )}

                {/* FAB */}
                <button
                    onClick={() => setShowQRModal(true)}
                    className="sh-fab fixed right-5 bottom-5 z-40 flex h-11 w-11 items-center justify-center rounded-[13px] bg-emerald-500 text-black shadow-xl shadow-emerald-500/25 transition-all hover:scale-105 hover:bg-emerald-400 active:scale-95"
                >
                    <QrCode size={17} />
                </button>

                {/* ── PAGE BODY ── */}
                <div className="relative z-10 mx-auto max-w-[1280px] px-4 py-8 sm:px-6">
                    {/* Page header */}
                    <div className="sh-fu mb-8 flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div className="mb-2 inline-flex items-center gap-2 rounded-full border border-emerald-500/20 bg-emerald-500/[.08] px-2.5 py-1">
                                <span
                                    className="h-1.5 w-1.5 rounded-full bg-emerald-500"
                                    style={{
                                        animation:
                                            'sh-dpulse 2s ease-in-out infinite',
                                    }}
                                />
                                <span className="sh-mono text-[10px] font-semibold tracking-[.12em] text-emerald-600 uppercase dark:text-emerald-400">
                                    Scan Records
                                </span>
                            </div>
                            <h1 className="text-[1.75rem] leading-none font-extrabold tracking-tight text-slate-900 sm:text-4xl dark:text-white">
                                My Scan History
                            </h1>
                            <p className="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                                View and manage your pet breed identification
                                scans.
                            </p>
                        </div>
                        <Link
                            href="/scan"
                            className="sh-newscan inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-4 py-2.5 text-[13px] font-bold text-black no-underline shadow-lg shadow-emerald-500/20 transition-all hover:-translate-y-0.5 hover:bg-emerald-400"
                        >
                            <Zap size={14} /> New Scan{' '}
                            <ChevronRight size={12} className="opacity-50" />
                        </Link>
                    </div>

                    {/* Stats row */}
                    <div className="sh-fu mb-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
                        {[
                            {
                                icon: <History size={14} />,
                                lbl: 'Total Scans',
                                val: mockScans.length,
                                sub: 'All time',
                                bar: 100,
                            },
                            {
                                icon: <Shield size={14} />,
                                lbl: 'Verified',
                                val: verifiedCount,
                                sub: 'By licensed vets',
                                bar: mockScans.length
                                    ? (verifiedCount / mockScans.length) * 100
                                    : 0,
                            },
                            {
                                icon: <Clock size={14} />,
                                lbl: 'Pending',
                                val: pendingCount,
                                sub: 'Awaiting review',
                                bar: mockScans.length
                                    ? (pendingCount / mockScans.length) * 100
                                    : 0,
                            },
                            {
                                icon: <TrendingUp size={14} />,
                                lbl: 'Avg Confidence',
                                val: `${avgConfidence}%`,
                                sub: 'Accuracy score',
                                bar: avgConfidence,
                            },
                        ].map((s, i) => (
                            <div
                                key={i}
                                className="sh-stat relative overflow-hidden rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm transition-all hover:-translate-y-0.5 hover:border-emerald-500/30 hover:shadow-md dark:border-white/[.07] dark:bg-[#131720]"
                                style={{ animationDelay: `${i * 0.07}s` }}
                            >
                                <div className="mb-3 flex h-8 w-8 items-center justify-center rounded-xl border border-emerald-500/20 bg-emerald-500/10 text-emerald-500">
                                    {s.icon}
                                </div>
                                <div className="sh-mono mb-0.5 text-[9px] font-semibold tracking-[.11em] text-slate-400 uppercase dark:text-slate-500">
                                    {s.lbl}
                                </div>
                                <div className="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                                    {s.val}
                                </div>
                                <div className="sh-mono mt-0.5 text-[10px] text-slate-400 dark:text-slate-500">
                                    {s.sub}
                                </div>
                                <div className="absolute right-0 bottom-0 left-0 h-[3px] bg-slate-100 dark:bg-white/[.06]">
                                    <div
                                        className="sh-statbar h-full rounded-full bg-gradient-to-r from-emerald-500 to-cyan-500 shadow-[0_0_6px_#10b981]"
                                        style={{
                                            width: `${s.bar}%`,
                                            animationDelay: `${i * 0.12}s`,
                                        }}
                                    />
                                </div>
                            </div>
                        ))}
                    </div>

                    {/* Vet banner */}
                    <div className="sh-fu mb-6 flex gap-3 rounded-2xl border border-cyan-200/60 bg-cyan-50/60 p-4 dark:border-cyan-500/15 dark:bg-cyan-500/[.04]">
                        <div className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-xl border border-cyan-500/20 bg-cyan-500/10 text-cyan-600 dark:text-cyan-400">
                            <Shield size={14} />
                        </div>
                        <div>
                            <p className="text-[13px] font-bold text-cyan-700 dark:text-cyan-400">
                                Veterinarian Verification
                            </p>
                            <p className="mt-0.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                                All system breed identifications can be reviewed
                                by licensed veterinarians. Verified scans are
                                confirmed by professional vets, while pending
                                scans await review.
                            </p>
                        </div>
                    </div>

                    {/* Toolbar */}
                    <div className="sh-fu mb-6 flex flex-wrap items-center gap-2.5">
                        {/* search */}
                        <div className="relative min-w-[200px] flex-1">
                            <Search
                                size={14}
                                className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-slate-400 dark:text-slate-500"
                            />
                            <input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search breeds…"
                                className="w-full rounded-xl border border-slate-200 bg-white py-2.5 pr-3 pl-9 text-[13px] text-slate-700 transition-all outline-none placeholder:text-slate-400 focus:border-emerald-500/50 focus:ring-2 focus:ring-emerald-500/15 dark:border-white/[.07] dark:bg-[#131720] dark:text-slate-200 dark:placeholder:text-slate-600 dark:focus:border-emerald-500/40"
                            />
                        </div>
                        {/* filter pills */}
                        {(['all', 'verified', 'pending'] as const).map((f) => (
                            <button
                                key={f}
                                onClick={() => setFilter(f)}
                                className={`flex items-center gap-1.5 rounded-xl border px-3.5 py-2.5 text-[12px] font-semibold transition-all ${
                                    filter === f
                                        ? 'border-emerald-500/35 bg-emerald-500/[.08] text-emerald-600 shadow-[0_0_14px_rgba(16,185,129,.12)] dark:border-emerald-500/30 dark:bg-emerald-500/[.1] dark:text-emerald-400'
                                        : 'border-slate-200 bg-white text-slate-500 hover:border-emerald-500/25 hover:bg-emerald-500/[.03] hover:text-emerald-600 dark:border-white/[.07] dark:bg-[#131720] dark:text-slate-400 dark:hover:text-emerald-400'
                                }`}
                            >
                                <Filter size={11} />
                                {f.charAt(0).toUpperCase() + f.slice(1)}
                            </button>
                        ))}
                    </div>

                    {/* Empty: no scans at all */}
                    {mockScans.length === 0 && (
                        <div className="relative overflow-hidden rounded-2xl border border-slate-200 bg-white py-20 text-center shadow-sm before:absolute before:inset-x-0 before:top-0 before:h-[1.5px] before:bg-gradient-to-r before:from-transparent before:via-emerald-500 before:to-transparent before:opacity-30 dark:border-white/[.07] dark:bg-[#131720]">
                            <div className="sh-empty-ring relative mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full border border-emerald-500/20 bg-emerald-500/10 text-emerald-500">
                                <Calendar size={26} />
                            </div>
                            <h3 className="text-xl font-bold text-slate-800 dark:text-white">
                                No scans yet
                            </h3>
                            <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">
                                Start by scanning your first pet!
                            </p>
                            <Link
                                href="/scan"
                                className="sh-newscan mt-6 inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-5 py-2.5 text-[13px] font-bold text-black no-underline transition-all hover:-translate-y-0.5 hover:bg-emerald-400"
                            >
                                <Zap size={14} /> Scan Your Pet
                            </Link>
                        </div>
                    )}

                    {/* No results from search/filter */}
                    {mockScans.length > 0 && filtered.length === 0 && (
                        <div className="relative overflow-hidden rounded-2xl border border-slate-200 bg-white py-16 text-center shadow-sm dark:border-white/[.07] dark:bg-[#131720]">
                            <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-slate-400 dark:border-white/[.07] dark:bg-white/[.04]">
                                <Search size={22} />
                            </div>
                            <h3 className="text-lg font-bold text-slate-800 dark:text-white">
                                No results found
                            </h3>
                            <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                Try adjusting your search or filter.
                            </p>
                        </div>
                    )}

                    {/* Scan grid */}
                    {filtered.length > 0 && (
                        <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                            {filtered.map((scan, idx) => (
                                <div
                                    key={scan.id}
                                    className="sh-card sh-scancard group relative overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition-all hover:-translate-y-1 hover:border-emerald-500/30 hover:shadow-lg hover:shadow-emerald-500/[.08] dark:border-white/[.07] dark:bg-[#131720]"
                                    style={{ animationDelay: `${idx * 0.04}s` }}
                                >
                                    {/* Image */}
                                    <div className="relative h-44 overflow-hidden">
                                        <img
                                            src={scan.image}
                                            alt={scan.breed}
                                            className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                                        />
                                        {/* gradient overlay */}
                                        <div className="absolute inset-0 bg-gradient-to-t from-black/55 via-transparent to-transparent" />
                                        {/* HUD corners */}
                                        {[
                                            ['tl', 'top-2 left-2'],
                                            ['tr', 'top-2 right-2'],
                                            ['bl', 'bottom-2 left-2'],
                                            ['br', 'bottom-2 right-2'],
                                        ].map(([k]) => (
                                            <div
                                                key={k}
                                                className={`sh-hc sh-h${k}`}
                                            />
                                        ))}
                                        {/* confidence badge */}
                                        <div className="sh-mono absolute right-2 bottom-2 rounded-md bg-emerald-500 px-2 py-0.5 text-[11px] font-bold text-black">
                                            {scan.confidence}%
                                        </div>
                                        {/* delete overlay */}
                                        <div className="absolute top-2 right-2 opacity-0 transition-opacity group-hover:opacity-100">
                                            <button
                                                onClick={() =>
                                                    handleDelete(scan.id)
                                                }
                                                disabled={
                                                    deletingId === scan.id
                                                }
                                                className="flex h-7 w-7 items-center justify-center rounded-lg border border-red-500/40 bg-red-500/80 text-white backdrop-blur-sm transition-all hover:bg-red-500 disabled:opacity-40"
                                            >
                                                <Trash2 size={12} />
                                            </button>
                                        </div>
                                    </div>

                                    {/* Body */}
                                    <div className="p-4">
                                        {/* breed + badge */}
                                        <div className="mb-3 flex items-start justify-between gap-2">
                                            <h3 className="text-[15px] leading-tight font-bold text-slate-800 dark:text-white">
                                                {scan.breed}
                                            </h3>
                                            <span
                                                className={`inline-flex flex-shrink-0 items-center gap-1 rounded-full px-2.5 py-0.5 text-[10px] font-bold tracking-wide ${
                                                    scan.status === 'verified'
                                                        ? 'border border-emerald-500/25 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                                        : 'border border-amber-500/25 bg-amber-500/10 text-amber-600 dark:text-amber-400'
                                                }`}
                                            >
                                                {scan.status === 'verified' ? (
                                                    <>
                                                        <svg
                                                            width="8"
                                                            height="8"
                                                            viewBox="0 0 24 24"
                                                            fill="none"
                                                            stroke="currentColor"
                                                            strokeWidth="3"
                                                        >
                                                            <polyline points="20 6 9 17 4 12" />
                                                        </svg>
                                                        Verified
                                                    </>
                                                ) : (
                                                    <>
                                                        <svg
                                                            width="8"
                                                            height="8"
                                                            viewBox="0 0 24 24"
                                                            fill="none"
                                                            stroke="currentColor"
                                                            strokeWidth="3"
                                                        >
                                                            <circle
                                                                cx="12"
                                                                cy="12"
                                                                r="10"
                                                            />
                                                            <polyline points="12 6 12 12 16 14" />
                                                        </svg>
                                                        Pending
                                                    </>
                                                )}
                                            </span>
                                        </div>

                                        {/* confidence bar */}
                                        <div className="mb-3">
                                            <div className="mb-1.5 flex items-center justify-between">
                                                <span className="sh-mono text-[9px] font-semibold tracking-[.1em] text-slate-400 uppercase dark:text-slate-500">
                                                    Confidence
                                                </span>
                                                <span className="sh-mono text-[11px] font-bold text-emerald-600 dark:text-emerald-400">
                                                    {scan.confidence}%
                                                </span>
                                            </div>
                                            <div className="h-[3px] overflow-hidden rounded-full bg-slate-100 dark:bg-white/[.07]">
                                                <div
                                                    className="sh-confbar h-full rounded-full bg-gradient-to-r from-emerald-500 to-cyan-500 shadow-[0_0_5px_#10b981]"
                                                    style={{
                                                        width: `${scan.confidence}%`,
                                                    }}
                                                />
                                            </div>
                                        </div>

                                        {/* date */}
                                        <div className="flex items-center gap-1.5 text-[11px] text-slate-400 dark:text-slate-500">
                                            <Calendar size={11} />
                                            <span className="sh-mono">
                                                {scan.date}
                                            </span>
                                        </div>
                                    </div>

                                    {/* Footer */}
                                    <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3 dark:border-white/[.05]">
                                        <span className="sh-mono max-w-[140px] truncate text-[9px] text-slate-300 dark:text-slate-600">
                                            {scan.scan_id}
                                        </span>
                                        <button
                                            onClick={() =>
                                                handleDelete(scan.id)
                                            }
                                            disabled={deletingId === scan.id}
                                            className="flex items-center gap-1.5 rounded-lg border border-slate-200 bg-transparent px-2.5 py-1.5 text-[11px] font-semibold text-red-500 transition-all hover:border-red-500/30 hover:bg-red-500/[.05] disabled:cursor-not-allowed disabled:opacity-40 dark:border-white/[.07] dark:hover:border-red-500/25"
                                        >
                                            <Trash2 size={11} />
                                            {deletingId === scan.id
                                                ? 'Deleting…'
                                                : 'Delete'}
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    {/* bottom padding for FAB clearance */}
                    <div className="h-20" />
                </div>
            </div>
        </>
    );
}
