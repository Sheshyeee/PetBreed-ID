import Header from '@/components/header';
import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    Brain,
    CheckCircle2,
    ChevronRight,
    Clock,
    Globe,
    History,
    Scan as ScanIcon,
    Sparkles,
    Target,
    TrendingUp,
    Zap,
} from 'lucide-react';

type PredictionResult = {
    breed: string;
    confidence: number;
};

type Result = {
    id?: number;
    scan_id: string;
    image: string;
    breed: string;
    confidence: number;
    top_predictions: PredictionResult[];
    description?: string;
    origin_history?: string;
    health_risks?: string;
    age_simulation?: string;
    created_at?: string;
    updated_at?: string;
    prediction_method?: string;
    is_exact_match?: boolean;
    has_admin_correction?: boolean;
};

type PageProps = {
    results?: Result;
};

const Panel = ({
    icon,
    title,
    children,
}: {
    icon: React.ReactNode;
    title: string;
    children: React.ReactNode;
}) => (
    <div className="sr-panel relative flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-white/[.07] dark:bg-[#131720] dark:shadow-none">
        <div className="flex flex-shrink-0 items-center gap-2 border-b border-slate-200 bg-slate-50/80 px-3 py-2.5 dark:border-white/[.06] dark:bg-white/[.025]">
            <div className="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-md border border-emerald-500/20 bg-emerald-500/10 text-emerald-500">
                {icon}
            </div>
            <span className="sr-mono text-[10px] font-bold tracking-[.12em] text-slate-400 uppercase dark:text-slate-500">
                {title}
            </span>
        </div>
        {children}
    </div>
);

/* ── Confidence Bar ── */
const ConfBar = ({
    value,
    color = 'emerald',
    animate = false,
}: {
    value: number;
    color?: 'emerald' | 'violet' | 'gradient';
    animate?: boolean;
}) => {
    const fill =
        color === 'gradient'
            ? 'background: linear-gradient(90deg,#a855f7,#ec4899)'
            : color === 'violet'
              ? 'background:#7c3aed'
              : 'background:#10b981';
    return (
        <div className="h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-white/[.07]">
            <div
                className={animate ? 'sr-barfill' : ''}
                style={{
                    height: '100%',
                    width: `${value}%`,
                    borderRadius: 9999,
                    ...Object.fromEntries(
                        fill
                            .split(';')
                            .filter(Boolean)
                            .map((s) => {
                                const [k, v] = s
                                    .split(':')
                                    .map((x) => x.trim());
                                return [
                                    k.replace(/-([a-z])/g, (_, c) =>
                                        c.toUpperCase(),
                                    ),
                                    v,
                                ];
                            }),
                    ),
                }}
            />
        </div>
    );
};

const ScanResults = () => {
    const { results } = usePage<PageProps>().props;

    const isMemoryAssisted =
        results?.prediction_method === 'admin_corrected' ||
        results?.is_exact_match ||
        results?.has_admin_correction;

    const showLearningBadge =
        results?.confidence === 100 && results?.has_admin_correction;

    const filteredPredictions =
        results?.top_predictions?.filter((prediction) => {
            if (!prediction || !prediction.breed) return false;
            const breedLower = prediction.breed.toLowerCase().trim();
            const invalidBreeds = [
                'other breeds',
                'other breed',
                'alternative 1',
                'alternative 2',
                'alternative 3',
                'alternative',
                'unknown',
            ];
            if (invalidBreeds.includes(breedLower)) return false;
            if (!prediction.confidence || prediction.confidence <= 0)
                return false;
            if (
                results?.breed &&
                breedLower === results.breed.toLowerCase().trim()
            )
                return false;
            return true;
        }) || [];

    const topAlternatives = [...filteredPredictions]
        .sort((a, b) => (b.confidence || 0) - (a.confidence || 0))
        .slice(0, 3);

    const conf = Math.round(results?.confidence ?? 0);

    const insightCards = [
        {
            href: `/origin?id=${results?.scan_id}`,
            icon: (
                <Globe className="h-7 w-7 text-blue-500 dark:text-blue-400" />
            ),
            label: 'Origin History',
            desc: "Discover the history and origin of your pet's breed",
            accent: 'blue',
            cta: 'Explore History',
        },
        {
            href: `/health-risk?id=${results?.scan_id}`,
            icon: (
                <Activity className="h-7 w-7 text-pink-500 dark:text-pink-400" />
            ),
            label: 'Health Risk',
            desc: 'Learn about breed-specific health considerations',
            accent: 'pink',
            cta: 'View Risks',
        },
        {
            href: `/simulation?scan_id=${results?.scan_id}`,
            icon: (
                <Clock className="h-7 w-7 text-violet-500 dark:text-violet-400" />
            ),
            label: 'Future Appearance',
            desc: 'See how your pet will look as they age over the years',
            accent: 'violet',
            cta: 'View Simulation',
        },
    ];

    const accentMap: Record<string, string> = {
        blue: 'border-blue-500/25 hover:border-blue-500/50 hover:bg-blue-500/[.03]',
        pink: 'border-pink-500/25 hover:border-pink-500/50 hover:bg-pink-500/[.03]',
        violet: 'border-violet-500/25 hover:border-violet-500/50 hover:bg-violet-500/[.03]',
    };
    const btnMap: Record<string, string> = {
        blue: 'bg-blue-500/10 text-blue-600 hover:bg-blue-500/20 dark:text-blue-400',
        pink: 'bg-pink-500/10 text-pink-600 hover:bg-pink-500/20 dark:text-pink-400',
        violet: 'bg-violet-500/10 text-violet-600 hover:bg-violet-500/20 dark:text-violet-400',
    };

    return (
        <>
            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap');

                @keyframes sr-barfill   { from{width:0} }
                @keyframes sr-faderise  { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
                @keyframes sr-dpulse    { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(1.5);opacity:.45} }
                @keyframes sr-sweep     { 0%{top:-100%} 100%{top:100%} }
                @keyframes sr-huddim    { 0%,88%,100%{opacity:1} 94%{opacity:.12} }
                @keyframes sr-glow      { 0%,100%{box-shadow:0 0 8px rgba(16,185,129,.35)} 50%{box-shadow:0 0 20px rgba(16,185,129,.7)} }
                @keyframes sr-beam      { from{top:-3px} to{top:calc(100%+3px)} }
                @keyframes sr-ring      { 0%{transform:scale(.9);opacity:.55} 100%{transform:scale(1.2);opacity:0} }
                @keyframes sr-ticker    { from{opacity:.3} to{opacity:1} }
                @keyframes sr-imglines  { 0%{opacity:.6} 50%{opacity:.2} 100%{opacity:.6} }

                .sr-root { font-family:'Plus Jakarta Sans',sans-serif; }
                .sr-root * { box-sizing:border-box; }
                .sr-mono { font-family:'JetBrains Mono',monospace !important; }

                .sr-dotgrid {
                    position:fixed; inset:0; pointer-events:none; z-index:0;
                    background-image:radial-gradient(circle,rgba(16,185,129,.07) 1px,transparent 1px);
                    background-size:28px 28px;
                    -webkit-mask-image:radial-gradient(ellipse 80% 55% at 50% 0%,black 0%,transparent 100%);
                    mask-image:radial-gradient(ellipse 80% 55% at 50% 0%,black 0%,transparent 100%);
                }
                .dark .sr-dotgrid { background-image:radial-gradient(circle,rgba(16,185,129,.055) 1px,transparent 1px); }

                .sr-panel::before,.sr-maincard::before {
                    content:''; position:absolute; top:0; left:0; right:0; height:1.5px;
                    background:linear-gradient(90deg,transparent,#10b981 45%,#06b6d4 55%,transparent);
                    opacity:.3;
                }

                .sr-fu  { animation:sr-faderise .45s cubic-bezier(.16,1,.3,1) both; }
                .sr-fu1 { animation-delay:.07s; }
                .sr-fu2 { animation-delay:.14s; }
                .sr-fu3 { animation-delay:.21s; }
                .sr-barfill { animation:sr-barfill 1.4s cubic-bezier(.16,1,.3,1) forwards; }
                .sr-hudblink { animation:sr-huddim 3s ease-in-out infinite; }
                .sr-ticker { animation:sr-ticker 1.4s ease-in-out infinite alternate; }
                .sr-nsb::-webkit-scrollbar { display:none; }
                .sr-nsb { scrollbar-width:none; }

                /* image sweep overlay */
                .sr-sweep {
                    position:absolute; left:0; top:-100%; width:100%; height:100%;
                    background:linear-gradient(180deg,transparent 0%,rgba(16,185,129,.04) 46%,rgba(16,185,129,.11) 50%,rgba(16,185,129,.04) 54%,transparent 100%);
                    animation:sr-sweep 3.5s ease-in-out infinite; pointer-events:none; z-index:3;
                }
                .sr-scanlines {
                    position:absolute; inset:0; pointer-events:none; z-index:2;
                    background:repeating-linear-gradient(0deg,transparent,transparent 2px,rgba(0,0,0,.012) 2px,rgba(0,0,0,.012) 4px);
                }

                /* HUD corners */
                .sr-hc  { position:absolute; width:14px; height:14px; border-color:rgba(16,185,129,.7); border-style:solid; z-index:4; pointer-events:none; }
                .sr-htl { top:7px;    left:7px;  border-width:2px 0 0 2px; }
                .sr-htr { top:7px;    right:7px; border-width:2px 2px 0 0; }
                .sr-hbl { bottom:7px; left:7px;  border-width:0 0 2px 2px; }
                .sr-hbr { bottom:7px; right:7px; border-width:0 2px 2px 0; }

                /* rank badge glow */
                .sr-rank { animation:sr-glow 2.4s ease-in-out infinite; }

                /* insight card beam */
                .sr-icard { position:relative; overflow:hidden; }
                .sr-icard::after {
                    content:''; position:absolute; top:0; left:-100%; width:50%; height:100%;
                    background:linear-gradient(90deg,transparent,rgba(255,255,255,.05),transparent);
                    transform:skewX(-18deg); transition:left .55s;
                }
                .sr-icard:hover::after { left:160%; }
            `}</style>

            <div className="sr-root flex min-h-screen flex-col bg-slate-50 transition-colors duration-300 dark:bg-[#080B0F]">
                {/* ambient glows */}
                <div className="pointer-events-none fixed top-[-120px] left-[-60px] z-0 h-[240px] w-[420px] rounded-full bg-emerald-400/[.038] blur-[80px]" />
                <div className="pointer-events-none fixed top-[-80px] right-[-30px] z-0 h-[200px] w-[320px] rounded-full bg-cyan-400/[.025] blur-[80px]" />
                <div className="pointer-events-none fixed bottom-0 left-1/2 z-0 h-[300px] w-[600px] -translate-x-1/2 rounded-full bg-violet-400/[.018] blur-[100px]" />
                <div className="sr-dotgrid" />

                {/* Header */}
                <div className="relative z-20 flex-shrink-0">
                    <Header />
                </div>

                {/* ── MAIN CONTENT ── */}
                <div className="sr-nsb relative z-10 mt-[-10px] flex-1 overflow-y-auto px-4">
                    <div className="mx-auto max-w-[1360px] px-3 py-5 sm:px-5 lg:px-12">
                        {/* ── PAGE HEADER ROW ── */}
                        <div className="sr-fu mb-5 flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <div className="mb-2 inline-flex items-center gap-2 rounded-full border border-emerald-500/20 bg-emerald-500/[.08] px-2.5 py-1">
                                    <span
                                        className="h-1.5 w-1.5 rounded-full bg-emerald-500 shadow-[0_0_6px_#10b981]"
                                        style={{
                                            animation:
                                                'sr-dpulse 2s ease-in-out infinite',
                                        }}
                                    />
                                    <span className="sr-mono text-[10px] font-semibold tracking-[.12em] text-emerald-600 uppercase dark:text-emerald-400">
                                        Analysis Complete
                                    </span>
                                </div>
                                <h1 className="text-[1.5rem] leading-none font-extrabold tracking-tight text-slate-900 dark:text-white">
                                    Scan Results
                                </h1>
                                <p className="mt-1.5 text-sm text-slate-500 dark:text-slate-400">
                                    Here's what we found about your pet
                                </p>
                            </div>
                            <div className="flex items-center gap-2.5">
                                <Link
                                    href="/scanhistory"
                                    className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-[13px] font-semibold text-slate-500 no-underline transition-all hover:border-emerald-500/30 hover:bg-emerald-500/[.03] hover:text-emerald-600 dark:border-white/[.07] dark:bg-[#131720] dark:text-slate-400 dark:hover:text-emerald-400"
                                >
                                    <History size={13} />
                                    History
                                </Link>
                                <Link
                                    href="/scan"
                                    className="inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-3.5 py-2 text-[13px] font-bold text-black no-underline shadow-lg shadow-emerald-500/20 transition-all hover:-translate-y-0.5 hover:bg-emerald-400"
                                >
                                    <ScanIcon size={13} />
                                    New Scan
                                    <ChevronRight
                                        size={11}
                                        className="opacity-60"
                                    />
                                </Link>
                            </div>
                        </div>

                        {/* ── MAIN GRID ── */}
                        <div className="grid grid-cols-1 gap-5 lg:grid-cols-[1fr_280px] xl:grid-cols-[1fr_300px]">
                            {/* ── LEFT COLUMN ── */}
                            <div className="flex flex-col gap-5">
                                {/* ── PRIMARY RESULT CARD ── */}
                                <div className="sr-fu sr-fu1 sr-maincard relative overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-white/[.07] dark:bg-[#131720]">
                                    {/* terminal bar */}
                                    <div className="flex flex-shrink-0 items-center gap-3 border-b border-slate-200 bg-slate-50 px-4 py-2.5 dark:border-white/[.06] dark:bg-[#0D1117]">
                                        <span className="sr-mono ml-1 text-[10px] text-slate-400 select-none dark:text-slate-500">
                                            doglens://results/
                                            {results?.scan_id?.slice(0, 8)}
                                        </span>
                                        <div className="sr-mono ml-auto flex items-center gap-1.5 text-[10px] text-emerald-500 select-none dark:text-emerald-400">
                                            <span className="sr-ticker h-1.5 w-1.5 rounded-full bg-emerald-500 shadow-[0_0_5px_#10b981]" />
                                            MATCH FOUND · {conf}% CONFIDENCE
                                        </div>
                                    </div>

                                    {/* card body */}
                                    <div className="flex flex-col gap-6 p-5 sm:p-7 md:flex-row md:items-start md:gap-8">
                                        {/* image */}
                                        <div className="relative mx-auto w-full max-w-[220px] flex-shrink-0 md:mx-0 md:w-[200px] lg:w-[220px]">
                                            <div className="relative overflow-hidden rounded-2xl border border-emerald-500/25 shadow-xl shadow-emerald-500/10">
                                                <img
                                                    src={results?.image}
                                                    alt="Pet"
                                                    className="block h-auto w-full bg-slate-100 object-cover dark:bg-[#0D1117]"
                                                />
                                                <div className="sr-scanlines" />
                                                <div className="sr-sweep" />
                                                {/* HUD corners */}
                                                {['tl', 'tr', 'bl', 'br'].map(
                                                    (p) => (
                                                        <div
                                                            key={p}
                                                            className={`sr-hc sr-h${p}`}
                                                        />
                                                    ),
                                                )}
                                                {/* bottom label */}
                                                <div className="absolute right-2 bottom-2 left-2 z-[5]">
                                                    <span className="sr-mono rounded border border-emerald-500/20 bg-black/65 px-2 py-0.5 text-[9px] font-medium tracking-[.1em] text-emerald-400 backdrop-blur-sm">
                                                        SCANNED IMAGE
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        {/* info */}
                                        <div className="min-w-0 flex-1 space-y-4">
                                            {/* badges */}
                                            <div className="flex flex-wrap gap-2">
                                                <span className="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/25 bg-emerald-500/10 px-2.5 py-1 text-[11px] font-bold text-emerald-600 dark:text-emerald-400">
                                                    <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                                                    Primary Match
                                                </span>

                                                {showLearningBadge && (
                                                    <span className="inline-flex items-center gap-1.5 rounded-full bg-gradient-to-r from-purple-600 to-pink-600 px-2.5 py-1 text-[11px] font-bold text-white">
                                                        <Brain className="h-3 w-3" />
                                                        Learned Recognition
                                                    </span>
                                                )}
                                                {results?.is_exact_match &&
                                                    !showLearningBadge && (
                                                        <span className="inline-flex items-center gap-1.5 rounded-full bg-gradient-to-r from-blue-600 to-indigo-600 px-2.5 py-1 text-[11px] font-bold text-white">
                                                            <CheckCircle2 className="h-3 w-3" />
                                                            Memory Match
                                                        </span>
                                                    )}
                                            </div>

                                            {/* breed name */}
                                            <div>
                                                <p className="sr-mono mb-1 text-[9px] tracking-[.14em] text-slate-400 uppercase dark:text-slate-500">
                                                    Identified Breed
                                                </p>
                                                <h2 className="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl dark:text-white">
                                                    {results?.breed}
                                                </h2>
                                            </div>

                                            {/* confidence */}
                                            <div className="space-y-2">
                                                <div className="flex items-center justify-between">
                                                    <span className="sr-mono text-[10px] font-semibold tracking-[.12em] text-slate-400 uppercase dark:text-slate-500">
                                                        Confidence Score
                                                    </span>
                                                    <span
                                                        className={`sr-mono text-sm font-bold ${conf === 100 ? 'text-purple-600 dark:text-purple-400' : 'text-emerald-600 dark:text-emerald-400'}`}
                                                    >
                                                        {conf}%
                                                    </span>
                                                </div>
                                                <div className="h-3 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-white/[.07]">
                                                    <div
                                                        className="sr-barfill h-full rounded-full"
                                                        style={{
                                                            width: `${conf}%`,
                                                            background:
                                                                conf === 100
                                                                    ? 'linear-gradient(90deg,#a855f7,#ec4899)'
                                                                    : '#10b981',
                                                        }}
                                                    />
                                                </div>
                                            </div>

                                            {/* learning note */}
                                            {showLearningBadge && (
                                                <div className="flex items-start gap-3 rounded-xl border border-purple-500/20 bg-purple-500/[.06] p-3.5 dark:border-purple-500/15">
                                                    <Sparkles className="mt-0.5 h-4 w-4 flex-shrink-0 text-purple-500" />
                                                    <p className="text-[13px] leading-relaxed text-purple-900 dark:text-purple-200">
                                                        <strong className="font-semibold">
                                                            Perfect Match!
                                                        </strong>{' '}
                                                        Our system recognized
                                                        this exact dog from
                                                        previous corrections.
                                                        Admin corrections are
                                                        making the AI smarter!
                                                    </p>
                                                </div>
                                            )}

                                            {/* description */}
                                            {results?.description && (
                                                <div className="rounded-xl border border-slate-100 bg-slate-50/60 p-3.5 dark:border-white/[.05] dark:bg-white/[.03]">
                                                    <p className="sr-mono mb-1 text-[9px] tracking-[.12em] text-slate-400 uppercase dark:text-slate-500">
                                                        Description
                                                    </p>
                                                    <p className="text-[13px] leading-relaxed text-slate-600 dark:text-gray-300">
                                                        {results.description}
                                                    </p>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </div>

                                {/* ── EXPLORE MORE INSIGHTS ── */}
                                <div className="sr-fu sr-fu2">
                                    <div className="mb-3 flex items-center gap-2">
                                        <div className="flex h-5 w-5 items-center justify-center rounded-md border border-emerald-500/20 bg-emerald-500/10 text-emerald-500">
                                            <Zap size={11} />
                                        </div>
                                        <span className="sr-mono text-[10px] font-bold tracking-[.12em] text-slate-400 uppercase dark:text-slate-500">
                                            Explore More Insights
                                        </span>
                                    </div>
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                        {insightCards.map((card, i) => (
                                            <Link
                                                key={i}
                                                href={card.href}
                                                className={`sr-icard group relative flex flex-col gap-4 rounded-2xl border bg-white p-5 no-underline shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md dark:bg-[#131720] dark:shadow-none ${accentMap[card.accent]}`}
                                            >
                                                <div
                                                    className={`flex h-12 w-12 items-center justify-center rounded-xl bg-${card.accent}-50 dark:bg-${card.accent}-500/10`}
                                                >
                                                    {card.icon}
                                                </div>
                                                <div className="flex-1">
                                                    <h3 className="mb-1 text-[15px] font-bold text-slate-900 dark:text-white">
                                                        {card.label}
                                                    </h3>
                                                    <p className="text-[12px] leading-relaxed text-slate-500 dark:text-slate-400">
                                                        {card.desc}
                                                    </p>
                                                </div>
                                                <div
                                                    className={`inline-flex w-full items-center justify-center gap-1.5 rounded-xl px-3 py-2 text-[12px] font-bold transition-colors ${btnMap[card.accent]}`}
                                                >
                                                    {card.cta}
                                                    <Sparkles size={13} />
                                                </div>
                                            </Link>
                                        ))}
                                    </div>
                                </div>
                            </div>

                            {/* ── RIGHT SIDEBAR ── */}
                            <aside className="sr-fu sr-fu3 flex flex-col gap-4">
                                {/* Scan Summary */}
                                <Panel
                                    icon={<Target size={11} />}
                                    title="Scan Summary"
                                >
                                    <div className="flex flex-col gap-1.5 p-3">
                                        {[
                                            {
                                                label: 'Scan ID',
                                                value:
                                                    results?.scan_id?.slice(
                                                        0,
                                                        12,
                                                    ) + '…',
                                            },

                                            {
                                                label: 'Confidence',
                                                value: `${conf}%`,
                                            },
                                            {
                                                label: 'Alternatives',
                                                value: topAlternatives.length.toString(),
                                            },
                                        ].map((row, i) => (
                                            <div
                                                key={i}
                                                className="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50 px-2.5 py-2 transition-all hover:border-emerald-500/25 hover:bg-emerald-500/[.025] dark:border-white/[.04] dark:bg-white/[.03]"
                                            >
                                                <span className="sr-mono text-[9px] font-medium tracking-[.1em] text-slate-400 uppercase dark:text-slate-500">
                                                    {row.label}
                                                </span>
                                                <span className="sr-mono text-[11px] font-bold text-slate-700 dark:text-slate-200">
                                                    {row.value ?? '—'}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </Panel>

                                {/* Alternative Breeds */}
                                {topAlternatives.length > 0 ? (
                                    <Panel
                                        icon={<TrendingUp size={11} />}
                                        title="Other Possible Breeds"
                                    >
                                        <div className="flex flex-col gap-0.5 p-3">
                                            {topAlternatives.map((pred, i) => (
                                                <div
                                                    key={`${pred.breed}-${i}`}
                                                    className="group flex cursor-default flex-col gap-2 rounded-xl px-2.5 py-3 transition-colors hover:bg-slate-50 dark:hover:bg-white/[.03]"
                                                >
                                                    <div className="flex items-center gap-2.5">
                                                        <div className="sr-rank flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-lg border border-violet-500/30 bg-violet-500/10 text-[10px] font-bold text-violet-600 dark:border-violet-500/20 dark:text-violet-400">
                                                            #{i + 1}
                                                        </div>
                                                        <span
                                                            className="min-w-0 flex-1 truncate text-[12px] font-semibold text-slate-700 dark:text-slate-300"
                                                            title={pred.breed}
                                                        >
                                                            {pred.breed}
                                                        </span>
                                                        <span className="sr-mono flex-shrink-0 text-[10px] font-bold text-violet-600 dark:text-violet-400">
                                                            {Math.round(
                                                                pred.confidence,
                                                            )}
                                                            %
                                                        </span>
                                                    </div>
                                                    <div className="h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-white/[.06]">
                                                        <div
                                                            className="sr-barfill h-full rounded-full bg-violet-500"
                                                            style={{
                                                                width: `${pred.confidence}%`,
                                                                animationDelay: `${i * 0.12}s`,
                                                            }}
                                                        />
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </Panel>
                                ) : conf >= 80 ? (
                                    <Panel
                                        icon={<CheckCircle2 size={11} />}
                                        title="Identification"
                                    >
                                        <div className="flex flex-col items-center gap-3 p-5 text-center">
                                            <div className="flex h-12 w-12 items-center justify-center rounded-full border border-emerald-500/20 bg-emerald-500/10">
                                                <CheckCircle2 className="h-5 w-5 text-emerald-500" />
                                            </div>
                                            <div>
                                                <p className="text-[13px] font-bold text-slate-900 dark:text-white">
                                                    High Confidence
                                                </p>
                                                <p className="mt-1 text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">
                                                    Our system is very confident
                                                    about this breed
                                                    identification.
                                                </p>
                                            </div>
                                        </div>
                                    </Panel>
                                ) : null}

                                {/* Quick Actions */}
                                <Panel
                                    icon={<ScanIcon size={11} />}
                                    title="Quick Actions"
                                >
                                    <div className="flex flex-col gap-1 p-2.5">
                                        <Link
                                            href="/scan"
                                            className="flex items-center gap-2.5 rounded-xl border border-emerald-500/25 bg-emerald-500/[.09] px-3 py-2.5 text-[13px] font-semibold text-emerald-600 no-underline transition-all dark:bg-emerald-500/[.11] dark:text-emerald-400"
                                        >
                                            <ScanIcon size={13} />
                                            <span>New Scan</span>
                                            <ChevronRight
                                                size={11}
                                                className="ml-auto opacity-40"
                                            />
                                        </Link>
                                        <Link
                                            href="/scanhistory"
                                            className="flex items-center gap-2.5 rounded-xl border border-transparent px-3 py-2.5 text-[13px] font-semibold text-slate-500 no-underline transition-all hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-white/[.05] dark:hover:text-slate-200"
                                        >
                                            <History size={13} />
                                            <span>Scan History</span>
                                        </Link>
                                        <Link
                                            href={`/origin?id=${results?.scan_id}`}
                                            className="flex items-center gap-2.5 rounded-xl border border-transparent px-3 py-2.5 text-[13px] font-semibold text-slate-500 no-underline transition-all hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-white/[.05] dark:hover:text-slate-200"
                                        >
                                            <Globe size={13} />
                                            <span>Origin History</span>
                                        </Link>
                                        <Link
                                            href={`/health-risk?id=${results?.scan_id}`}
                                            className="flex items-center gap-2.5 rounded-xl border border-transparent px-3 py-2.5 text-[13px] font-semibold text-slate-500 no-underline transition-all hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-white/[.05] dark:hover:text-slate-200"
                                        >
                                            <Activity size={13} />
                                            <span>Health Risks</span>
                                        </Link>
                                    </div>
                                </Panel>
                            </aside>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
};

export default ScanResults;
