import Header from '@/components/header';
import { Link } from '@inertiajs/react';
import {
    ArrowLeft,
    ChevronRight,
    Globe,
    History,
    MapPin,
    Scan as ScanIcon,
} from 'lucide-react';
import { FC, useState } from 'react';

interface TimelineItem {
    year: string;
    event: string;
}
interface HistoryDetail {
    title: string;
    content: string;
}
interface OriginData {
    country: string;
    country_code: string;
    region: string;
    description: string;
    timeline: TimelineItem[];
    details: HistoryDetail[];
}
interface ScanResult {
    id?: number;
    scan_id: string;
    breed: string;
    origin_history: string | OriginData;
}
interface Props {
    results: ScanResult;
}

const ViewOrigin: FC<Props> = ({ results }) => {
    let originData: OriginData = {
        country: 'Unknown',
        country_code: '',
        region: 'Unknown Region',
        description: 'Origin details unavailable.',
        timeline: [],
        details: [],
    };
    try {
        if (typeof results?.origin_history === 'string')
            originData = JSON.parse(results.origin_history);
        else if (
            typeof results?.origin_history === 'object' &&
            results?.origin_history !== null
        )
            originData = results.origin_history as OriginData;
    } catch (e) {
        console.error('Failed to parse origin history', e);
    }

    const { country, country_code, region, description, timeline, details } =
        originData;
    const flagUrl = country_code
        ? `https://flagcdn.com/w160/${country_code.toLowerCase()}.png`
        : null;
    const [openDetail, setOpenDetail] = useState<number | null>(null);

    const Panel = ({
        icon,
        title,
        children,
        accent = 'emerald',
    }: {
        icon: React.ReactNode;
        title: string;
        children: React.ReactNode;
        accent?: 'emerald' | 'cyan';
    }) => (
        <div className="vo-panel relative flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-white/[.07] dark:bg-[#131720]">
            <div
                className={`flex flex-shrink-0 items-center gap-2 border-b px-4 py-2.5 ${accent === 'cyan' ? 'border-cyan-100 bg-cyan-50/60 dark:border-cyan-500/10 dark:bg-cyan-500/[.04]' : 'border-slate-200 bg-slate-50/80 dark:border-white/[.06] dark:bg-white/[.025]'}`}
            >
                <div
                    className={`flex h-5 w-5 items-center justify-center rounded-md border ${accent === 'cyan' ? 'border-cyan-500/20 bg-cyan-500/10 text-cyan-600 dark:text-cyan-400' : 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'}`}
                >
                    {icon}
                </div>
                <span className="vo-mono text-[10px] font-bold tracking-[.12em] text-slate-600 uppercase dark:text-slate-400">
                    {title}
                </span>
            </div>
            {children}
        </div>
    );

    return (
        <>
            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap');
                @keyframes vo-faderise { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
                @keyframes vo-sweep    { 0%{top:-100%} 100%{top:100%} }
                @keyframes vo-dpulse   { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(1.5);opacity:.45} }
                @keyframes vo-huddim   { 0%,88%,100%{opacity:1} 94%{opacity:.12} }
                @keyframes vo-beam     { from{top:-3px} to{top:calc(100%+3px)} }
                .vo-root { font-family:'Plus Jakarta Sans',sans-serif; }
                .vo-mono { font-family:'JetBrains Mono',monospace !important; }
                .vo-dotgrid { position:fixed;inset:0;pointer-events:none;z-index:0;background-image:radial-gradient(circle,rgba(16,185,129,.07) 1px,transparent 1px);background-size:28px 28px;-webkit-mask-image:radial-gradient(ellipse 80% 55% at 50% 0%,black 0%,transparent 100%);mask-image:radial-gradient(ellipse 80% 55% at 50% 0%,black 0%,transparent 100%); }
                .dark .vo-dotgrid { background-image:radial-gradient(circle,rgba(16,185,129,.055) 1px,transparent 1px); }
                .vo-panel::before { content:'';position:absolute;top:0;left:0;right:0;height:1.5px;background:linear-gradient(90deg,transparent,#10b981 45%,#06b6d4 55%,transparent);opacity:.3; }
                .vo-fu  { animation:vo-faderise .45s cubic-bezier(.16,1,.3,1) both; }
                .vo-fu1 { animation-delay:.07s } .vo-fu2 { animation-delay:.14s } .vo-fu3 { animation-delay:.21s } .vo-fu4 { animation-delay:.28s }
                .vo-sweep { position:absolute;left:0;top:-100%;width:100%;height:100%;background:linear-gradient(180deg,transparent 0%,rgba(6,182,212,.05) 46%,rgba(6,182,212,.13) 50%,rgba(6,182,212,.05) 54%,transparent 100%);animation:vo-sweep 4s ease-in-out infinite;pointer-events:none;z-index:3; }
                .vo-hc { position:absolute;width:12px;height:12px;border-color:rgba(6,182,212,.65);border-style:solid;z-index:4;pointer-events:none; }
                .vo-htl{top:6px;left:6px;border-width:2px 0 0 2px} .vo-htr{top:6px;right:6px;border-width:2px 2px 0 0} .vo-hbl{bottom:6px;left:6px;border-width:0 0 2px 2px} .vo-hbr{bottom:6px;right:6px;border-width:0 2px 2px 0}
                .vo-hudblink { animation:vo-huddim 3s ease-in-out infinite; }
                .vo-nsb::-webkit-scrollbar{display:none} .vo-nsb{scrollbar-width:none}
                .vo-timeline-line { position:relative;margin-left:1rem;border-left:2px solid; }
                .vo-dot { position:absolute;left:-20px;top:5px;width:14px;height:14px;border-radius:50%;border:2px solid white;background:#06b6d4;box-shadow:0 0 8px rgba(6,182,212,.6); }
                .dark .vo-dot { border-color:#131720; }
                .vo-accord-btn { transition:background .15s; }
                .vo-accord-btn:hover { background:rgba(6,182,212,.04); }
                .dark .vo-accord-btn:hover { background:rgba(6,182,212,.06); }
            `}</style>

            <div className="vo-root flex min-h-screen flex-col bg-slate-50 dark:bg-[#080B0F]">
                <div className="pointer-events-none fixed top-[-120px] left-[-60px] z-0 h-[260px] w-[440px] rounded-full bg-cyan-400/[.035] blur-[85px]" />
                <div className="pointer-events-none fixed right-0 bottom-0 z-0 h-[220px] w-[360px] rounded-full bg-emerald-400/[.025] blur-[90px]" />
                <div className="vo-dotgrid" />
                <div className="relative z-20 flex-shrink-0">
                    <Header />
                </div>

                <div className="vo-nsb relative z-10 mt-[-10px] flex-1 overflow-y-auto px-4">
                    <div className="mx-auto max-w-[1100px] px-4 py-6 sm:px-6 lg:px-4">
                        {/* ── PAGE HEADER ── */}
                        <div className="vo-fu mb-6 flex flex-wrap items-center justify-between gap-4">
                            <div className="flex items-center gap-3">
                                <Link
                                    href={
                                        results?.id
                                            ? `/scan-results/${results.id}`
                                            : '/scan-results'
                                    }
                                    className="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition-all hover:border-cyan-500/30 hover:text-cyan-600 dark:border-white/[.07] dark:bg-[#131720] dark:text-slate-400 dark:hover:text-cyan-400"
                                >
                                    <ArrowLeft size={15} />
                                </Link>
                                <div>
                                    <div className="mb-1 inline-flex items-center gap-2 rounded-full border border-cyan-500/20 bg-cyan-500/[.08] px-2.5 py-0.5">
                                        <span
                                            className="h-1.5 w-1.5 rounded-full bg-cyan-500 shadow-[0_0_6px_#06b6d4]"
                                            style={{
                                                animation:
                                                    'vo-dpulse 2s ease-in-out infinite',
                                            }}
                                        />
                                        <span className="vo-mono text-[9px] font-semibold tracking-[.12em] text-cyan-700 uppercase dark:text-cyan-400">
                                            Origin &amp; Heritage
                                        </span>
                                    </div>
                                    <h1 className="text-xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                                        {results?.breed || 'Breed'}{' '}
                                        <span className="bg-gradient-to-r from-cyan-500 to-emerald-500 bg-clip-text text-transparent">
                                            Origin History
                                        </span>
                                    </h1>
                                    <p className="mt-0.5 text-sm text-slate-600 dark:text-slate-400">
                                        Explore the heritage and evolution of
                                        this breed
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <Link
                                    href="/scanhistory"
                                    className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-[13px] font-semibold text-slate-600 no-underline transition-all hover:border-emerald-500/30 hover:text-emerald-600 dark:border-white/[.07] dark:bg-[#131720] dark:text-slate-400 dark:hover:text-emerald-400"
                                >
                                    <History size={13} /> History
                                </Link>
                                <Link
                                    href="/scan"
                                    className="inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-3.5 py-2 text-[13px] font-bold text-black no-underline shadow-lg shadow-emerald-500/20 transition-all hover:-translate-y-0.5 hover:bg-emerald-400"
                                >
                                    <ScanIcon size={13} /> New Scan{' '}
                                    <ChevronRight
                                        size={11}
                                        className="opacity-60"
                                    />
                                </Link>
                            </div>
                        </div>

                        {/* ── HERO ORIGIN CARD ── */}
                        <div className="vo-fu vo-fu1 mb-5">
                            <div className="vo-panel relative overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-white/[.07] dark:bg-[#131720]">
                                {/* Terminal bar */}
                                <div className="flex flex-shrink-0 items-center gap-3 border-b border-slate-200 bg-slate-50 px-4 py-2.5 dark:border-white/[.06] dark:bg-[#0D1117]">
                                    <span className="vo-mono ml-1 text-[10px] text-slate-500 select-none dark:text-slate-500">
                                        doglens://origin/
                                        {results?.scan_id?.slice(0, 8)}
                                    </span>
                                    <div className="vo-mono ml-auto flex items-center gap-1.5 text-[10px] text-cyan-600 select-none dark:text-cyan-400">
                                        <span
                                            className="h-1.5 w-1.5 rounded-full bg-cyan-500 shadow-[0_0_5px_#06b6d4]"
                                            style={{
                                                animation:
                                                    'vo-dpulse 2s infinite',
                                            }}
                                        />
                                        GEO DATA LOADED
                                    </div>
                                </div>

                                <div className="flex flex-col md:flex-row">
                                    {/* Flag column */}
                                    <div className="relative flex flex-col items-center justify-center gap-5 border-b border-slate-100 bg-gradient-to-br from-cyan-50/60 to-slate-50 p-8 md:w-[280px] md:flex-shrink-0 md:border-r md:border-b-0 dark:border-white/[.05] dark:from-cyan-500/[.04] dark:to-transparent">
                                        <div className="relative overflow-hidden rounded-2xl shadow-xl ring-2 ring-cyan-500/20">
                                            {flagUrl ? (
                                                <img
                                                    src={flagUrl}
                                                    alt={`${country} flag`}
                                                    className="h-auto w-36 object-cover"
                                                />
                                            ) : (
                                                <div className="flex h-24 w-36 items-center justify-center rounded-2xl bg-slate-100 dark:bg-white/[.05]">
                                                    <Globe
                                                        size={28}
                                                        className="text-slate-400"
                                                    />
                                                </div>
                                            )}
                                            <div className="vo-sweep" />
                                        </div>
                                        <div className="text-center">
                                            <p className="text-lg font-extrabold text-slate-900 dark:text-white">
                                                {country}
                                            </p>
                                            <p className="vo-mono mt-0.5 text-[10px] tracking-[.08em] text-cyan-600 dark:text-cyan-400">
                                                {region}
                                            </p>
                                        </div>
                                    </div>

                                    {/* Info column */}
                                    <div className="flex-1 p-6 sm:p-8">
                                        <div className="mb-5 flex items-start gap-3">
                                            <div className="mt-0.5 flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl border border-cyan-500/20 bg-cyan-500/10">
                                                <MapPin
                                                    size={16}
                                                    className="text-cyan-600 dark:text-cyan-400"
                                                />
                                            </div>
                                            <div>
                                                <p className="vo-mono mb-1 text-[9px] tracking-[.12em] text-slate-500 uppercase dark:text-slate-500">
                                                    Country of Origin
                                                </p>
                                                <h2 className="text-2xl font-extrabold text-slate-900 dark:text-white">
                                                    {country}
                                                </h2>
                                                <p className="mt-0.5 text-sm font-semibold text-cyan-600 dark:text-cyan-400">
                                                    {region}
                                                </p>
                                            </div>
                                        </div>

                                        <div className="rounded-xl border border-slate-100 bg-slate-50/60 p-4 dark:border-white/[.05] dark:bg-white/[.03]">
                                            <p className="vo-mono mb-2 text-[9px] tracking-[.12em] text-slate-500 uppercase dark:text-slate-500">
                                                Breed Overview
                                            </p>
                                            <p className="text-sm leading-relaxed text-slate-700 dark:text-slate-300">
                                                {description}
                                            </p>
                                        </div>

                                        {/* Quick stats */}
                                        <div className="mt-4 grid grid-cols-2 gap-3">
                                            {[
                                                {
                                                    label: 'Country',
                                                    value: country,
                                                },
                                                {
                                                    label: 'Region',
                                                    value: region,
                                                },
                                            ].map((stat, i) => (
                                                <div
                                                    key={i}
                                                    className="flex flex-col gap-0.5 rounded-xl border border-slate-100 bg-slate-50 px-3 py-2.5 dark:border-white/[.04] dark:bg-white/[.025]"
                                                >
                                                    <span className="vo-mono text-[9px] tracking-[.1em] text-slate-500 uppercase dark:text-slate-500">
                                                        {stat.label}
                                                    </span>
                                                    <span className="text-[13px] font-bold text-slate-800 dark:text-slate-200">
                                                        {stat.value}
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* ── TIMELINE + DETAILS GRID ── */}
                        <div className="grid grid-cols-1 gap-5 lg:grid-cols-[1fr_340px]">
                            {/* Timeline */}
                            <div className="vo-fu vo-fu2">
                                <Panel
                                    icon={
                                        <span className="text-[11px]">📅</span>
                                    }
                                    title="History Timeline"
                                    accent="cyan"
                                >
                                    <div className="p-6 sm:p-7">
                                        {timeline.length > 0 ? (
                                           
                                            <div className="vo-timeline-line space-y-7 border-cyan-200 pl-8 dark:border-cyan-500/20">
                                                {timeline.map((item, i) => (
                                                    <div
                                                        key={i}
                                                        className="relative"
                                                    >
                                                        <div className="vo-dot" />
                                                        <div className="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:gap-5">
                                                           
                                                            <span className="vo-mono min-w-[130px] flex-shrink-0 text-[11px] font-bold text-cyan-700 dark:text-cyan-400">
                                                                {item.year}
                                                            </span>
                                                            <p className="text-sm leading-relaxed text-slate-700 dark:text-slate-300">
                                                                {item.event}
                                                            </p>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        ) : (
                                            <div className="flex items-center justify-center py-10 text-sm text-slate-500 italic dark:text-slate-500">
                                                No timeline data available.
                                            </div>
                                        )}
                                    </div>
                                </Panel>
                            </div>

                            {/* Detailed history accordion */}
                            <div className="vo-fu vo-fu3">
                                <Panel
                                    icon={<Globe size={11} />}
                                    title="Detailed History"
                                    accent="emerald"
                                >
                                    <div className="flex flex-col">
                                        {details.length > 0 ? (
                                            details.map((d, i) => (
                                                <div
                                                    key={i}
                                                    className={`border-b border-slate-100 last:border-0 dark:border-white/[.05]`}
                                                >
                                                    <button
                                                        onClick={() =>
                                                            setOpenDetail(
                                                                openDetail === i
                                                                    ? null
                                                                    : i,
                                                            )
                                                        }
                                                        className="vo-accord-btn flex w-full items-center justify-between gap-3 px-5 py-4 text-left"
                                                    >
                                                        <span className="text-[13px] font-bold text-slate-800 dark:text-white">
                                                            {d.title}
                                                        </span>
                                                        <span
                                                            className={`flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full border text-[11px] font-bold transition-all ${openDetail === i ? 'rotate-45 border-emerald-500/35 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'border-slate-200 text-slate-500 dark:border-white/[.1] dark:text-slate-400'}`}
                                                        >
                                                            +
                                                        </span>
                                                    </button>
                                                    {openDetail === i && (
                                                        <div className="px-5 pb-4">
                                                            <p className="text-[12px] leading-relaxed text-slate-600 dark:text-slate-400">
                                                                {d.content}
                                                            </p>
                                                        </div>
                                                    )}
                                                </div>
                                            ))
                                        ) : (
                                            <p className="p-5 text-sm text-slate-500 italic dark:text-slate-500">
                                                Detailed history unavailable.
                                            </p>
                                        )}
                                    </div>
                                </Panel>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
};

export default ViewOrigin;
