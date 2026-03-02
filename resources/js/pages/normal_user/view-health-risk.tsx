import Header from '@/components/header';
import { Link } from '@inertiajs/react';
import {
    Activity,
    ArrowLeft,
    CheckCircle2,
    ChevronRight,
    Heart,
    History,
    Scan as ScanIcon,
    Shield,
    TriangleAlert,
} from 'lucide-react';
import { FC, useMemo } from 'react';
import {
    PolarAngleAxis,
    PolarGrid,
    PolarRadiusAxis,
    Radar,
    RadarChart,
    ResponsiveContainer,
} from 'recharts';

interface HealthConcern {
    name: string;
    risk_level: string;
    description: string;
    prevention: string;
}
interface Screening {
    name: string;
    description: string;
}
interface HealthRisksData {
    concerns: HealthConcern[];
    screenings: Screening[];
    lifespan: string;
    care_tips: string[];
}
interface ScanResult {
    id?: number;
    scan_id: string;
    breed: string;
    health_risks: string | HealthRisksData;
    created_at: string;
}
interface Props {
    results: ScanResult;
}

const ViewHealthRisk: FC<Props> = ({ results }) => {
    let healthData: HealthRisksData = {
        concerns: [],
        screenings: [],
        lifespan: 'Unknown',
        care_tips: [],
    };
    try {
        if (typeof results?.health_risks === 'string')
            healthData = JSON.parse(results.health_risks);
        else if (
            typeof results?.health_risks === 'object' &&
            results?.health_risks !== null
        )
            healthData = results.health_risks as HealthRisksData;
    } catch (e) {
        console.error('Failed to parse health risks JSON:', e);
    }

    const {
        concerns = [],
        screenings = [],
        lifespan = 'Unknown',
        care_tips = [],
    } = healthData;

    const getRisk = (risk: string) => {
        const r = risk.toLowerCase();
        if (r.includes('high'))
            return {
                badge: 'border-red-200 bg-red-50 text-red-700 dark:border-red-500/25 dark:bg-red-500/[.08] dark:text-red-300',
                dot: 'bg-red-500 shadow-[0_0_6px_rgba(239,68,68,.7)]',
                bar: '#ef4444',
                barBg: 'rgba(239,68,68,.12)',
                score: 80,
                label: 'High Risk',
            };
        if (r.includes('moderate'))
            return {
                badge: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/25 dark:bg-amber-500/[.08] dark:text-amber-300',
                dot: 'bg-amber-500 shadow-[0_0_6px_rgba(245,158,11,.7)]',
                bar: '#f59e0b',
                barBg: 'rgba(245,158,11,.12)',
                score: 55,
                label: 'Moderate',
            };
        return {
            badge: 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-500/25 dark:bg-blue-500/[.08] dark:text-blue-300',
            dot: 'bg-blue-500 shadow-[0_0_6px_rgba(59,130,246,.7)]',
            bar: '#3b82f6',
            barBg: 'rgba(59,130,246,.12)',
            score: 30,
            label: 'Low Risk',
        };
    };

    const radarData = useMemo(() => {
        if (!concerns || concerns.length === 0)
            return [{ category: 'No Data', value: 0 }];
        return concerns.slice(0, 8).map((c) => ({
            category: c.name,
            value: getRisk(c.risk_level).score,
        }));
    }, [concerns]);

    const Panel = ({
        icon,
        title,
        children,
        accent = 'emerald',
    }: {
        icon: React.ReactNode;
        title: string;
        children: React.ReactNode;
        accent?: 'emerald' | 'pink' | 'cyan';
    }) => (
        <div className="vhr-panel relative flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-white/[.07] dark:bg-[#131720]">
            <div
                className={`flex flex-shrink-0 items-center gap-2 border-b px-4 py-2.5 ${accent === 'pink' ? 'border-pink-100 bg-pink-50/60 dark:border-pink-500/10 dark:bg-pink-500/[.04]' : accent === 'cyan' ? 'border-cyan-100 bg-cyan-50/60 dark:border-cyan-500/10 dark:bg-cyan-500/[.04]' : 'border-slate-200 bg-slate-50/80 dark:border-white/[.06] dark:bg-white/[.025]'}`}
            >
                <div
                    className={`flex h-5 w-5 items-center justify-center rounded-md border ${accent === 'pink' ? 'border-pink-500/20 bg-pink-500/10 text-pink-600 dark:text-pink-400' : accent === 'cyan' ? 'border-cyan-500/20 bg-cyan-500/10 text-cyan-600 dark:text-cyan-400' : 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'}`}
                >
                    {icon}
                </div>
                <span className="vhr-mono text-[10px] font-bold tracking-[.12em] text-slate-600 uppercase dark:text-slate-400">
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
                @keyframes vhr-faderise { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
                @keyframes vhr-sweep    { 0%{top:-100%} 100%{top:100%} }
                @keyframes vhr-dpulse   { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(1.5);opacity:.45} }
                @keyframes vhr-huddim   { 0%,88%,100%{opacity:1} 94%{opacity:.12} }
                @keyframes vhr-barfill  { from{width:0} }
                @keyframes vhr-pulse-ring { 0%{transform:scale(1);opacity:.6} 100%{transform:scale(1.35);opacity:0} }
                .vhr-root { font-family:'Plus Jakarta Sans',sans-serif; }
                .vhr-mono { font-family:'JetBrains Mono',monospace !important; }
                .vhr-dotgrid { position:fixed;inset:0;pointer-events:none;z-index:0;background-image:radial-gradient(circle,rgba(16,185,129,.07) 1px,transparent 1px);background-size:28px 28px;-webkit-mask-image:radial-gradient(ellipse 80% 55% at 50% 0%,black 0%,transparent 100%);mask-image:radial-gradient(ellipse 80% 55% at 50% 0%,black 0%,transparent 100%); }
                .dark .vhr-dotgrid { background-image:radial-gradient(circle,rgba(16,185,129,.055) 1px,transparent 1px); }
                .vhr-panel::before { content:'';position:absolute;top:0;left:0;right:0;height:1.5px;background:linear-gradient(90deg,transparent,#10b981 45%,#ec4899 55%,transparent);opacity:.3; }
                .vhr-fu  { animation:vhr-faderise .45s cubic-bezier(.16,1,.3,1) both; }
                .vhr-fu1 { animation-delay:.07s } .vhr-fu2 { animation-delay:.14s } .vhr-fu3 { animation-delay:.21s } .vhr-fu4 { animation-delay:.28s }
                .vhr-barfill { animation:vhr-barfill 1.4s cubic-bezier(.16,1,.3,1) forwards; }
                .vhr-hudblink { animation:vhr-huddim 3s ease-in-out infinite; }
                .vhr-ring { animation:vhr-pulse-ring 2.2s ease-out infinite; }
                .vhr-nsb::-webkit-scrollbar{display:none} .vhr-nsb{scrollbar-width:none}
                .vhr-card { transition:transform .2s, box-shadow .2s; }
                .vhr-card:hover { transform:translateY(-2px); }
                .vhr-card:hover { box-shadow:0 12px 32px rgba(0,0,0,.08); }
                .dark .vhr-card:hover { box-shadow:0 12px 32px rgba(0,0,0,.35); }
            `}</style>

            <div className="vhr-root flex min-h-screen flex-col bg-slate-50 dark:bg-[#080B0F]">
                <div className="pointer-events-none fixed top-[-120px] left-[-60px] z-0 h-[260px] w-[440px] rounded-full bg-pink-400/[.03] blur-[85px]" />
                <div className="pointer-events-none fixed right-0 bottom-0 z-0 h-[220px] w-[360px] rounded-full bg-emerald-400/[.025] blur-[90px]" />
                <div className="vhr-dotgrid" />
                <div className="relative z-20 flex-shrink-0">
                    <Header />
                </div>

                <div className="vhr-nsb relative z-10 mt-[-10px] flex-1 overflow-y-auto">
                    <div className="mx-auto max-w-[1100px] px-4 py-6 sm:px-6 lg:px-4">
                        {/* ── PAGE HEADER ── */}
                        <div className="vhr-fu mb-5 flex flex-wrap items-center justify-between gap-4">
                            <div className="flex items-center gap-3">
                                <Link
                                    href={
                                        results?.id
                                            ? `/scan-results/${results.id}`
                                            : '/scan-results'
                                    }
                                    className="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition-all hover:border-pink-500/30 hover:text-pink-600 dark:border-white/[.07] dark:bg-[#131720] dark:text-slate-400 dark:hover:text-pink-400"
                                >
                                    <ArrowLeft size={15} />
                                </Link>
                                <div>
                                    <div className="mb-1 inline-flex items-center gap-2 rounded-full border border-pink-500/20 bg-pink-500/[.07] px-2.5 py-0.5">
                                        <span
                                            className="h-1.5 w-1.5 rounded-full bg-pink-500 shadow-[0_0_6px_#ec4899]"
                                            style={{
                                                animation:
                                                    'vhr-dpulse 2s ease-in-out infinite',
                                            }}
                                        />
                                        <span className="vhr-mono text-[9px] font-semibold tracking-[.12em] text-pink-700 uppercase dark:text-pink-400">
                                            Health Analysis
                                        </span>
                                    </div>
                                    <h1 className="text-xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                                        Health Risk{' '}
                                        <span className="bg-gradient-to-r from-pink-500 to-rose-500 bg-clip-text text-transparent">
                                            Visualization
                                        </span>
                                    </h1>
                                    <p className="mt-0.5 text-sm text-slate-600 dark:text-slate-400">
                                        Breed-specific health considerations for{' '}
                                        <strong className="font-semibold text-slate-800 dark:text-slate-200">
                                            {results?.breed || 'your dog'}
                                        </strong>
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

                        {/* ── DISCLAIMER ── */}
                        <div className="vhr-fu vhr-fu1 mb-5 flex items-start gap-3.5 rounded-2xl border border-red-200 bg-red-50 p-4 dark:border-red-500/20 dark:bg-red-500/[.06]">
                            <TriangleAlert
                                size={16}
                                className="mt-0.5 flex-shrink-0 text-red-500"
                            />
                            <div>
                                <p className="text-[13px] font-bold text-red-700 dark:text-red-300">
                                    Medical Disclaimer
                                </p>
                                <p className="mt-0.5 text-xs leading-relaxed text-red-700/80 dark:text-red-400/80">
                                    This information is for educational purposes
                                    only and is not a medical diagnosis. Always
                                    consult with a licensed veterinarian for
                                    proper medical advice specific to your pet.
                                </p>
                            </div>
                        </div>

                        {/* ── TOP ROW: Radar + Lifespan ── */}
                        <div className="vhr-fu vhr-fu2 mb-5 grid grid-cols-1 gap-5 lg:grid-cols-[1fr_220px]">
                            {/* Radar */}
                            <Panel
                                icon={<Activity size={11} />}
                                title="Breed Risk Profile"
                            >
                                <div className="p-5 sm:p-6">
                                    <ResponsiveContainer
                                        width="100%"
                                        height={300}
                                    >
                                        <RadarChart data={radarData}>
                                            <defs>
                                                <linearGradient
                                                    id="vhrGrad"
                                                    x1="0"
                                                    y1="0"
                                                    x2="0"
                                                    y2="1"
                                                >
                                                    <stop
                                                        offset="0%"
                                                        stopColor="#ec4899"
                                                        stopOpacity={0.75}
                                                    />
                                                    <stop
                                                        offset="100%"
                                                        stopColor="#f43f5e"
                                                        stopOpacity={0.2}
                                                    />
                                                </linearGradient>
                                            </defs>
                                            <PolarGrid
                                                stroke="#cbd5e1"
                                                strokeWidth={1}
                                                strokeDasharray="3 3"
                                            />
                                            <PolarAngleAxis
                                                dataKey="category"
                                                tick={{
                                                    fill: '#475569',
                                                    fontSize: 11,
                                                    fontWeight: 500,
                                                }}
                                            />
                                            <PolarRadiusAxis
                                                angle={90}
                                                domain={[0, 100]}
                                                tick={{
                                                    fill: '#94a3b8',
                                                    fontSize: 10,
                                                }}
                                                axisLine={false}
                                            />
                                            <Radar
                                                name="Risk"
                                                dataKey="value"
                                                stroke="#ec4899"
                                                strokeWidth={2.5}
                                                fill="url(#vhrGrad)"
                                                fillOpacity={0.6}
                                            />
                                        </RadarChart>
                                    </ResponsiveContainer>
                                    <p className="vhr-mono text-center text-[9px] tracking-[.1em] text-slate-500 dark:text-slate-500">
                                        Higher values = more common concerns for
                                        this breed
                                    </p>
                                </div>
                            </Panel>

                            {/* Lifespan + Care Tips */}
                            <div className="flex flex-col gap-4">
                                <Panel
                                    icon={<Heart size={11} />}
                                    title="Avg Lifespan"
                                    accent="pink"
                                >
                                    <div className="flex flex-col items-center justify-center gap-3 p-6">
                                        <div className="relative flex h-24 w-24 items-center justify-center">
                                            <div className="vhr-ring absolute inset-0 rounded-full border-2 border-pink-400/30" />
                                            <div className="flex h-full w-full flex-col items-center justify-center rounded-full border-4 border-pink-200 bg-pink-50 shadow-lg shadow-pink-500/10 dark:border-pink-500/25 dark:bg-pink-500/[.07]">
                                                <span className="text-2xl leading-none font-extrabold text-pink-600 dark:text-pink-400">
                                                    {lifespan}
                                                </span>
                                                <span className="vhr-mono text-[9px] tracking-[.08em] text-pink-600/70 uppercase dark:text-pink-400/70">
                                                    yrs
                                                </span>
                                            </div>
                                        </div>
                                        <p className="text-[12px] font-semibold text-slate-700 dark:text-slate-300">
                                            Average Lifespan
                                        </p>
                                    </div>
                                </Panel>

                                {care_tips.length > 0 && (
                                    <Panel
                                        icon={<CheckCircle2 size={11} />}
                                        title="Care Tips"
                                    >
                                        <ul className="flex flex-col gap-1 p-4">
                                            {care_tips.map((tip, i) => (
                                                <li
                                                    key={i}
                                                    className="flex items-start gap-2"
                                                >
                                                    <CheckCircle2
                                                        size={11}
                                                        className="mt-0.5 flex-shrink-0 text-emerald-500"
                                                    />
                                                    <span className="text-[11px] leading-relaxed text-slate-700 dark:text-slate-300">
                                                        {tip}
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                    </Panel>
                                )}
                            </div>
                        </div>

                        {/* ── BOTTOM ROW: Health Concerns (left) + Screenings (right) ── */}
                        <div className="vhr-fu vhr-fu3 mb-5 grid grid-cols-1 gap-5 lg:grid-cols-2">
                            {/* ── HEALTH CONCERNS ── */}
                            <div>
                                <div className="mb-3 flex items-center gap-2">
                                    <div className="flex h-5 w-5 items-center justify-center rounded-md border border-pink-500/20 bg-pink-500/10 text-pink-600 dark:text-pink-400">
                                        <TriangleAlert size={10} />
                                    </div>
                                    <span className="vhr-mono text-[10px] font-bold tracking-[.12em] text-slate-600 uppercase dark:text-slate-400">
                                        Common Health Concerns
                                    </span>
                                    <span className="vhr-mono ml-auto rounded-full border border-slate-200 bg-slate-50 px-2.5 py-0.5 text-[9px] text-slate-600 dark:border-white/[.07] dark:bg-white/[.03] dark:text-slate-400">
                                        {concerns.length} identified
                                    </span>
                                </div>
                                {concerns.length > 0 ? (
                                    <div className="flex flex-col gap-4">
                                        {concerns.map((c, i) => {
                                            const rc = getRisk(c.risk_level);
                                            return (
                                                <div
                                                    key={i}
                                                    className="vhr-card vhr-panel relative overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-white/[.07] dark:bg-[#131720]"
                                                >
                                                    <div
                                                        className="absolute top-0 right-0 left-0 h-[2.5px]"
                                                        style={{
                                                            background: `linear-gradient(90deg,transparent,${rc.bar} 30%,${rc.bar} 70%,transparent)`,
                                                            opacity: 0.55,
                                                        }}
                                                    />
                                                    <div className="p-5">
                                                        <div className="mb-3 flex items-start justify-between gap-2.5">
                                                            <div className="flex items-center gap-2.5">
                                                                <div
                                                                    className={`h-2.5 w-2.5 flex-shrink-0 rounded-full ${rc.dot}`}
                                                                />
                                                                <h3 className="text-[14px] font-bold text-slate-900 dark:text-white">
                                                                    {c.name}
                                                                </h3>
                                                            </div>
                                                            <span
                                                                className={`vhr-mono flex-shrink-0 rounded-full border px-2 py-0.5 text-[9px] font-bold tracking-[.06em] uppercase ${rc.badge}`}
                                                            >
                                                                {c.risk_level}
                                                            </span>
                                                        </div>
                                                        <div
                                                            className="mb-4 h-1.5 w-full overflow-hidden rounded-full"
                                                            style={{
                                                                background:
                                                                    rc.barBg,
                                                            }}
                                                        >
                                                            <div
                                                                className="vhr-barfill h-full rounded-full"
                                                                style={{
                                                                    width: `${rc.score}%`,
                                                                    background:
                                                                        rc.bar,
                                                                    animationDelay: `${i * 0.1}s`,
                                                                }}
                                                            />
                                                        </div>
                                                        <div className="mb-3 space-y-1">
                                                            <p className="vhr-mono text-[9px] tracking-[.1em] text-slate-500 uppercase dark:text-slate-500">
                                                                Description
                                                            </p>
                                                            <p className="text-[12px] leading-relaxed text-slate-700 dark:text-slate-300">
                                                                {c.description}
                                                            </p>
                                                        </div>
                                                        <div className="rounded-xl border border-slate-100 bg-slate-50/60 p-3 dark:border-white/[.05] dark:bg-white/[.03]">
                                                            <p className="vhr-mono mb-1 text-[9px] tracking-[.1em] text-slate-500 uppercase dark:text-slate-500">
                                                                Prevention &
                                                                Management
                                                            </p>
                                                            <p className="text-[12px] leading-relaxed text-slate-600 dark:text-slate-400">
                                                                {c.prevention}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                ) : (
                                    <p className="text-sm text-slate-500 italic dark:text-slate-500">
                                        No specific health concerns generated
                                        for this scan.
                                    </p>
                                )}
                            </div>

                            {/* ── SCREENINGS ── */}
                            {screenings.length > 0 && (
                                <div className="vhr-fu vhr-fu4">
                                    <Panel
                                        icon={<Shield size={11} />}
                                        title="Recommended Screenings"
                                        accent="cyan"
                                    >
                                        <div className="flex flex-col gap-4 p-5">
                                            {screenings.map((s, i) => (
                                                <div
                                                    key={i}
                                                    className="rounded-xl border border-slate-100 bg-slate-50/60 p-4 dark:border-white/[.05] dark:bg-white/[.03]"
                                                >
                                                    <p className="mb-1 text-[13px] font-bold text-slate-800 dark:text-white">
                                                        {s.name}
                                                    </p>
                                                    <p className="text-[12px] leading-relaxed text-slate-600 dark:text-slate-400">
                                                        {s.description}
                                                    </p>
                                                </div>
                                            ))}
                                        </div>
                                    </Panel>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
};

export default ViewHealthRisk;
