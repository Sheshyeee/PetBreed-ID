import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    BarChart3,
    Brain,
    ChevronRight,
    ClipboardList,
    Database,
    GraduationCap,
    LineChart,
    Minus,
    Sparkles,
    Target,
} from 'lucide-react';
import React, { useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
];

type Result = { scan_id: string; breed: string; confidence: number };
type HeatmapDay = {
    date: string;
    count: number;
    week: number;
    day_of_week: number;
    label: string;
    is_today: boolean;
};
type HeatmapSummary = {
    active_days: number;
    total_in_range: number;
    current_streak: number;
    best_day_count: number;
    best_day_label: string;
};
type MemoryChip = {
    breed: string;
    times_taught: number;
    first_taught: string;
    days_ago: number;
    level: 'new' | 'learning' | 'trained' | 'expert';
};
type TimelineDay = {
    day: string;
    date: string;
    is_today: boolean;
    corrections: number;
    total_scans: number;
    high_confidence: number;
    high_conf_rate: number;
    total_corrections_to_date: number;
};

type PageProps = {
    results?: Result[];
    correctedBreedCount: number;
    resultCount: number;
    pendingReviewCount: number;
    lowConfidenceCount: number;
    highConfidenceCount: number;
    totalScansWeeklyTrend?: number;
    correctedWeeklyTrend?: number;
    highConfidenceWeeklyTrend?: number;
    memoryCount?: number;
    avgConfidence?: number;
    confidenceTrend?: number;
    memoryHitRate?: number;
    accuracyImprovement?: number;
    learningTimeline?: TimelineDay[];
    learningHeatmap?: HeatmapDay[];
    heatmapSummary?: HeatmapSummary;
    breedMemoryWall?: MemoryChip[];
};

/* ── level config ─────────────────────────────────────────────────────────── */
const LEVEL_CFG = {
    expert: {
        bg: 'bg-emerald-500/15',
        border: 'border-emerald-500/30',
        text: 'text-emerald-300',
        dot: '#10b981',
        label: 'Expert',
    },
    trained: {
        bg: 'bg-green-500/10',
        border: 'border-green-500/25',
        text: 'text-green-300',
        dot: '#22c55e',
        label: 'Trained',
    },
    learning: {
        bg: 'bg-blue-500/10',
        border: 'border-blue-500/25',
        text: 'text-blue-300',
        dot: '#3b82f6',
        label: 'Learning',
    },
    new: {
        bg: 'bg-amber-500/10',
        border: 'border-amber-500/25',
        text: 'text-amber-300',
        dot: '#f59e0b',
        label: 'New',
    },
};

/* ── heatmap colours (GitHub green palette) ──────────────────────────────── */
function heatColor(count: number, max: number, isDark: boolean): string {
    if (count === 0) return isDark ? '#21262d' : '#ebedf0';
    const r = Math.min(count / Math.max(max, 1), 1);
    if (isDark) {
        if (r < 0.25) return '#0e4429';
        if (r < 0.5) return '#006d32';
        if (r < 0.75) return '#26a641';
        return '#39d353';
    } else {
        if (r < 0.25) return '#9be9a8';
        if (r < 0.5) return '#40c463';
        if (r < 0.75) return '#30a14e';
        return '#216e39';
    }
}

/* ── CompactHeatmap ───────────────────────────────────────────────────────── */
function CompactHeatmap({ days }: { days: HeatmapDay[] }) {
    const [hovered, setHovered] = useState<HeatmapDay | null>(null);
    const [tipPos, setTipPos] = useState({ x: 0, y: 0 });
    const [isDark, setIsDark] = useState(true);

    useEffect(() => {
        const mq = window.matchMedia('(prefers-color-scheme: dark)');
        // also check html class (Tailwind dark mode)
        const check = () =>
            setIsDark(
                document.documentElement.classList.contains('dark') ||
                    mq.matches,
            );
        check();
        const obs = new MutationObserver(check);
        obs.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class'],
        });
        mq.addEventListener('change', check);
        return () => {
            obs.disconnect();
            mq.removeEventListener('change', check);
        };
    }, []);

    /* Build lookup */
    const byDate: Record<string, HeatmapDay> = {};
    days.forEach((d) => {
        byDate[d.date] = d;
    });

    /*
     * GitHub algorithm:
     * - The LAST column = the week containing TODAY (rightmost).
     * - The FIRST column = 11 weeks before that (leftmost).
     * - Each column starts on SUNDAY (row 0) and ends on SATURDAY (row 6).
     * - We work BACKWARDS from today to find the Sunday that starts col 11,
     *   then fill all 12 × 7 cells from that anchor forward.
     */
    const todayDate = new Date();
    todayDate.setHours(0, 0, 0, 0);
    const todayISO = todayDate.toISOString().slice(0, 10);

    // Sunday of this week (the start of column 11)
    const lastColSunday = new Date(todayDate);
    lastColSunday.setDate(todayDate.getDate() - todayDate.getDay()); // rewind to Sunday

    // Sunday of column 0  = 11 weeks before lastColSunday
    const anchor = new Date(lastColSunday);
    anchor.setDate(lastColSunday.getDate() - 11 * 7);

    const COLS = 12,
        ROWS = 7;
    const grid: (HeatmapDay | null)[][] = Array.from({ length: COLS }, () =>
        Array(ROWS).fill(null),
    );
    const monthLabels: string[] = Array(COLS).fill('');
    let lastMonth = '';

    for (let col = 0; col < COLS; col++) {
        for (let row = 0; row < ROWS; row++) {
            const cell = new Date(anchor);
            cell.setDate(anchor.getDate() + col * 7 + row);
            if (cell > todayDate) continue; // skip future
            const iso = cell.toISOString().slice(0, 10);
            grid[col][row] = byDate[iso] ?? {
                date: iso,
                count: 0,
                week: col,
                day_of_week: row,
                label: cell.toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric',
                }),
                is_today: iso === todayISO,
            };
            // Month label on first cell of a new month in this column
            if (row === 0) {
                const m = cell.toLocaleString('en-US', { month: 'short' });
                if (m !== lastMonth) {
                    monthLabels[col] = m;
                    lastMonth = m;
                }
            }
        }
    }

    const maxCount = Math.max(...days.map((d) => d.count), 1);
    const DOW_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    const emptyColor = isDark ? '#21262d' : '#ebedf0';

    return (
        <div className="relative w-full select-none">
            {/* Tooltip */}
            {hovered && (
                <div
                    className="pointer-events-none fixed z-50 rounded-lg border border-black/10 bg-white px-2.5 py-2 text-xs shadow-xl dark:border-white/10 dark:bg-neutral-900"
                    style={{
                        left: tipPos.x + 12,
                        top: tipPos.y - 8,
                        minWidth: 138,
                    }}
                >
                    <p className="font-bold text-neutral-900 dark:text-white">
                        {hovered.is_today ? '📅 Today' : hovered.label}
                    </p>
                    <p
                        className={
                            hovered.count > 0
                                ? 'text-emerald-600 dark:text-emerald-400'
                                : 'text-neutral-400 dark:text-white/30'
                        }
                    >
                        {hovered.count > 0
                            ? `${hovered.count} correction${hovered.count !== 1 ? 's' : ''}`
                            : 'No activity'}
                    </p>
                </div>
            )}

            <div className="flex w-full items-start" style={{ gap: 4 }}>
                {/* Day-of-week labels */}
                <div
                    className="flex shrink-0 flex-col"
                    style={{ gap: 2, marginTop: 16, width: 28 }}
                >
                    {DOW_LABELS.map((l, i) => (
                        <div
                            key={i}
                            className="text-right text-[9px] leading-none text-neutral-400 dark:text-white/25"
                            style={{ height: 13, lineHeight: '13px' }}
                        >
                            {i % 2 === 0 ? l : ''}
                        </div>
                    ))}
                </div>

                {/* Month labels + cells */}
                <div
                    className="flex min-w-0 flex-1 flex-col"
                    style={{ gap: 2 }}
                >
                    {/* Month row */}
                    <div className="flex w-full" style={{ gap: 2 }}>
                        {monthLabels.map((m, i) => (
                            <div
                                key={i}
                                className="flex-1 overflow-hidden text-[9px] leading-none text-neutral-400 dark:text-white/30"
                                style={{ height: 14 }}
                            >
                                {m}
                            </div>
                        ))}
                    </div>
                    {/* Cell columns */}
                    <div className="flex w-full" style={{ gap: 2 }}>
                        {Array.from({ length: COLS }, (_, col) => (
                            <div
                                key={col}
                                className="flex flex-1 flex-col"
                                style={{ gap: 2 }}
                            >
                                {Array.from({ length: ROWS }, (_, row) => {
                                    const c = grid[col][row];
                                    return (
                                        <div
                                            key={row}
                                            className="w-full rounded-sm"
                                            style={{
                                                height: 13,
                                                background: c
                                                    ? heatColor(
                                                          c.count,
                                                          maxCount,
                                                          isDark,
                                                      )
                                                    : emptyColor,
                                                outline: c?.is_today
                                                    ? '2px solid #6366f1'
                                                    : undefined,
                                                outlineOffset: c?.is_today
                                                    ? '1px'
                                                    : undefined,
                                                cursor: c
                                                    ? 'default'
                                                    : undefined,
                                            }}
                                            onMouseEnter={
                                                c
                                                    ? (e) => {
                                                          setHovered(c);
                                                          setTipPos({
                                                              x: e.clientX,
                                                              y: e.clientY,
                                                          });
                                                      }
                                                    : undefined
                                            }
                                            onMouseMove={
                                                c
                                                    ? (e) =>
                                                          setTipPos({
                                                              x: e.clientX,
                                                              y: e.clientY,
                                                          })
                                                    : undefined
                                            }
                                            onMouseLeave={() =>
                                                setHovered(null)
                                            }
                                        />
                                    );
                                })}
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            {/* Legend */}
            <div className="mt-3 flex items-center gap-1.5">
                <span className="text-[9px] text-neutral-400 dark:text-white/25">
                    Less
                </span>
                {[0, 0.2, 0.5, 0.75, 1].map((r, i) => (
                    <div
                        key={i}
                        style={{
                            width: 11,
                            height: 11,
                            borderRadius: 2,
                            background:
                                r === 0
                                    ? emptyColor
                                    : heatColor(r * maxCount, maxCount, isDark),
                        }}
                    />
                ))}
                <span className="text-[9px] text-neutral-400 dark:text-white/25">
                    More
                </span>
            </div>
        </div>
    );
}

/* ── MemoryChipCard ───────────────────────────────────────────────────────── */
function MemoryChipCard({ chip }: { chip: MemoryChip }) {
    const cfg = LEVEL_CFG[chip.level];
    const [show, setShow] = useState(false);
    return (
        <div className="relative">
            <div
                className={`flex cursor-default items-center gap-1.5 rounded-full border px-2.5 py-1 transition-all duration-150 hover:brightness-110 ${cfg.bg} ${cfg.border}`}
                onMouseEnter={() => setShow(true)}
                onMouseLeave={() => setShow(false)}
            >
                <span className="relative flex h-1.5 w-1.5 shrink-0">
                    {(chip.level === 'trained' || chip.level === 'expert') && (
                        <span
                            className="absolute inline-flex h-full w-full animate-ping rounded-full opacity-40"
                            style={{
                                backgroundColor: cfg.dot,
                                animationDuration: '2.5s',
                            }}
                        />
                    )}
                    <span
                        className="relative inline-flex h-1.5 w-1.5 rounded-full"
                        style={{ backgroundColor: cfg.dot }}
                    />
                </span>
                <span
                    className={`max-w-[96px] truncate text-[11px] leading-none font-semibold ${cfg.text}`}
                >
                    {chip.breed}
                </span>
                {chip.times_taught > 1 && (
                    <span
                        className={`text-[9px] leading-none font-black opacity-70 ${cfg.text}`}
                    >
                        ×{chip.times_taught}
                    </span>
                )}
            </div>
            {show && (
                <div className="pointer-events-none absolute bottom-full left-1/2 z-50 mb-1.5 w-36 -translate-x-1/2 rounded-xl border border-black/8 bg-white p-2 text-xs shadow-xl dark:border-white/10 dark:bg-neutral-900">
                    <p className="mb-0.5 font-black text-neutral-900 dark:text-white">
                        {chip.breed}
                    </p>
                    <p className={`text-[11px] ${cfg.text}`}>{cfg.label}</p>
                    <p className="text-neutral-500 dark:text-white/40">
                        Taught {chip.times_taught}× by vet
                    </p>
                    <p className="text-neutral-400 dark:text-white/25">
                        Since {chip.first_taught}
                    </p>
                    {chip.days_ago === 0 && (
                        <p className="mt-0.5 text-indigo-500 dark:text-indigo-400">
                            ✨ Added today
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}

/* ── Card wrapper with beautiful gradient bg ──────────────────────────────── */
function StatCard({
    accent,
    children,
}: {
    accent: string;
    children: React.ReactNode;
}) {
    return (
        <div
            className="group relative overflow-hidden rounded-2xl border border-neutral-200/60 bg-white p-5 shadow-sm transition-shadow hover:shadow-md dark:border-white/[0.06] dark:bg-neutral-900"
            style={{ background: undefined }}
        >
            {/* subtle gradient wash */}
            <div
                className="pointer-events-none absolute inset-0 opacity-[0.04] dark:opacity-[0.07]"
                style={{
                    background: `radial-gradient(ellipse at top right, ${accent} 0%, transparent 70%)`,
                }}
            />
            {/* bottom edge accent line */}
            <div
                className="pointer-events-none absolute bottom-0 left-0 h-[2px] w-full opacity-30"
                style={{
                    background: `linear-gradient(to right, transparent, ${accent}, transparent)`,
                }}
            />
            {children}
        </div>
    );
}

/* ── Main Dashboard ───────────────────────────────────────────────────────── */
export default function Dashboard() {
    const {
        results,
        correctedBreedCount,
        resultCount,
        pendingReviewCount = 0,
        totalScansWeeklyTrend = 0,
        correctedWeeklyTrend = 0,
        memoryCount = 0,
        avgConfidence = 0,
        confidenceTrend = 0,
        memoryHitRate = 0,
        accuracyImprovement = 0,
        learningHeatmap = [],
        heatmapSummary,
        breedMemoryWall = [],
    } = usePage<PageProps>().props;

    const fmt = (t: number) => `${t >= 0 ? '+' : ''}${t.toFixed(1)}%`;
    const tIcon = (t: number) =>
        t > 0 ? (
            <ArrowUp className="h-3 w-3" />
        ) : t < 0 ? (
            <ArrowDown className="h-3 w-3" />
        ) : (
            <Minus className="h-3 w-3" />
        );
    const tClr = (t: number, inv = false) => {
        if (inv)
            return t < 0
                ? 'text-emerald-500 dark:text-emerald-400'
                : t > 0
                  ? 'text-red-500 dark:text-red-400'
                  : 'text-neutral-400';
        return t > 0
            ? 'text-emerald-500 dark:text-emerald-400'
            : t < 0
              ? 'text-red-500 dark:text-red-400'
              : 'text-neutral-400';
    };

    const expertChips = breedMemoryWall.filter((c) => c.level === 'expert');
    const trainedChips = breedMemoryWall.filter((c) => c.level === 'trained');
    const learningChips = breedMemoryWall.filter((c) => c.level === 'learning');
    const newChips = breedMemoryWall.filter((c) => c.level === 'new');

    useEffect(() => {
        const iv = setInterval(
            () =>
                router.reload({
                    only: [
                        'breedMemoryWall',
                        'learningHeatmap',
                        'heatmapSummary',
                        'correctedBreedCount',
                    ],
                }),
            30000,
        );
        return () => clearInterval(iv);
    }, []);

    /* ── 4 top metric cards ─────────────────────────────────────────────── */
    const metricCards = [
        {
            label: 'Total Scans',
            value: resultCount,
            trend: totalScansWeeklyTrend,
            Icon: BarChart3,
            accent: '#3b82f6',
            inv: false,
        },
        {
            label: 'Corrections Made',
            value: correctedBreedCount,
            trend: correctedWeeklyTrend,
            Icon: GraduationCap,
            accent: '#8b5cf6',
            inv: false,
        },
        {
            label: 'Avg Confidence',
            value: avgConfidence,
            trend: confidenceTrend,
            Icon: Brain,
            accent: '#10b981',
            inv: false,
            suffix: '%',
        },
        {
            label: 'Pending Review',
            value: pendingReviewCount,
            trend: -correctedWeeklyTrend,
            Icon: ClipboardList,
            accent: '#f59e0b',
            inv: true,
        },
    ] as {
        label: string;
        value: number;
        trend: number;
        Icon: React.ElementType;
        accent: string;
        inv: boolean;
        suffix?: string;
    }[];

    /* ── 3 mini stat cards ──────────────────────────────────────────────── */
    const miniCards = [
        {
            label: 'Learning Progress',
            val: `${accuracyImprovement.toFixed(0)}/100`,
            sub: 'Composite score',
            Icon: Target,
            a: '#10b981',
        },
        {
            label: 'Memory Usage Rate',
            val: `${memoryHitRate.toFixed(1)}%`,
            sub: `${memoryCount} patterns stored`,
            Icon: Database,
            a: '#8b5cf6',
        },
        {
            label: 'Avg Confidence',
            val: `${avgConfidence.toFixed(1)}%`,
            sub: `${confidenceTrend >= 0 ? '+' : ''}${confidenceTrend.toFixed(1)}% this week`,
            Icon: LineChart,
            a: '#3b82f6',
        },
    ] as const;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="AI Learning Dashboard" />

            <div className="flex h-full flex-col gap-5 p-4 md:p-6">
                {/* ── 4 metric cards ──────────────────────────────────── */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {metricCards.map(
                        ({
                            label,
                            value,
                            trend,
                            Icon,
                            accent,
                            inv,
                            suffix,
                        }) => (
                            <StatCard key={label} accent={accent}>
                                <div className="flex items-start justify-between">
                                    <div>
                                        <p className="text-sm font-medium text-neutral-500 dark:text-white/40">
                                            {label}
                                        </p>
                                        <div className="mt-2 flex items-baseline gap-2">
                                            <p className="text-3xl font-black tracking-tight text-neutral-900 dark:text-white">
                                                {suffix
                                                    ? `${value.toFixed(1)}${suffix}`
                                                    : value}
                                            </p>
                                            <div
                                                className={`flex items-center gap-0.5 text-xs font-semibold ${tClr(trend, inv)}`}
                                            >
                                                {tIcon(trend)}
                                                <span>{fmt(trend)}</span>
                                            </div>
                                        </div>
                                        <p className="mt-1 text-[11px] text-neutral-400 dark:text-white/20">
                                            vs last week
                                        </p>
                                    </div>
                                    <div
                                        className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
                                        style={{
                                            background: `${accent}18`,
                                            border: `1px solid ${accent}30`,
                                        }}
                                    >
                                        <Icon
                                            className="h-5 w-5"
                                            style={{ color: accent }}
                                        />
                                    </div>
                                </div>
                            </StatCard>
                        ),
                    )}
                </div>

                {/* ── 3 mini stats ────────────────────────────────────── */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    {miniCards.map(({ label, val, sub, Icon, a }) => (
                        <StatCard key={label} accent={a}>
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-neutral-500 dark:text-white/40">
                                        {label}
                                    </p>
                                    <p className="mt-1 text-2xl font-black tracking-tight text-neutral-900 dark:text-white">
                                        {val}
                                    </p>
                                    <p className="mt-0.5 text-[11px] text-neutral-400 dark:text-white/25">
                                        {sub}
                                    </p>
                                </div>
                                <div
                                    className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full"
                                    style={{
                                        background: `${a}18`,
                                        border: `1px solid ${a}28`,
                                    }}
                                >
                                    <Icon
                                        className="h-5 w-5"
                                        style={{ color: a }}
                                    />
                                </div>
                            </div>
                        </StatCard>
                    ))}
                </div>

                {/* ── AI Training Activity ─────────────────────────────── */}
                <div className="overflow-hidden rounded-2xl border border-neutral-200/60 bg-white shadow-sm dark:border-white/[0.06] dark:bg-neutral-900">
                    {/* header */}
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-neutral-100 px-5 py-4 dark:border-white/[0.06]">
                        <div className="flex items-center gap-3">
                            <div className="flex h-9 w-9 items-center justify-center rounded-xl border border-violet-200 bg-violet-50 dark:border-violet-500/25 dark:bg-violet-500/15">
                                <Sparkles className="h-4 w-4 text-violet-500 dark:text-violet-400" />
                            </div>
                            <div>
                                <h2 className="font-bold text-neutral-900 dark:text-white">
                                    AI Training Activity
                                </h2>
                                <p className="text-xs text-neutral-400 dark:text-white/35">
                                    12-week correction history &amp; breed
                                    memory
                                </p>
                            </div>
                        </div>
                        {heatmapSummary && (
                            <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                                <span className="text-neutral-500 dark:text-white/30">
                                    <span className="font-bold text-neutral-900 dark:text-white">
                                        {heatmapSummary.total_in_range}
                                    </span>{' '}
                                    corrections
                                </span>
                                <span className="text-neutral-300 dark:text-white/15">
                                    ·
                                </span>
                                <span className="text-neutral-500 dark:text-white/30">
                                    <span className="font-bold text-neutral-900 dark:text-white">
                                        {heatmapSummary.active_days}
                                    </span>{' '}
                                    active days
                                </span>
                                {heatmapSummary.current_streak > 0 && (
                                    <>
                                        <span className="text-neutral-300 dark:text-white/15">
                                            ·
                                        </span>
                                        <span className="font-semibold text-orange-500 dark:text-orange-400">
                                            🔥 {heatmapSummary.current_streak}
                                            -day streak
                                        </span>
                                    </>
                                )}
                            </div>
                        )}
                    </div>

                    {/* 50/50 body */}
                    <div className="flex flex-col lg:flex-row">
                        {/* Heatmap */}
                        <div className="w-full p-5 lg:w-1/2">
                            <p className="mb-3 text-[10px] font-semibold tracking-wider text-neutral-400 uppercase dark:text-white/30">
                                Correction Heatmap
                            </p>
                            {learningHeatmap.length > 0 ? (
                                <CompactHeatmap days={learningHeatmap} />
                            ) : (
                                <p className="text-xs text-neutral-400 dark:text-white/20">
                                    No data yet
                                </p>
                            )}
                        </div>

                        {/* Divider */}
                        <div className="hidden w-px bg-neutral-100 lg:block dark:bg-white/[0.06]" />
                        <div className="mx-5 h-px bg-neutral-100 lg:hidden dark:bg-white/[0.06]" />

                        {/* Memory Wall */}
                        <div className="w-full p-5 lg:w-1/2">
                            <div className="mb-3 flex flex-wrap items-center gap-2">
                                <p className="text-[10px] font-semibold tracking-wider text-neutral-400 uppercase dark:text-white/30">
                                    Breed Memory Wall
                                </p>
                                <span className="rounded-full border border-violet-200 bg-violet-50 px-2 py-0.5 text-[10px] font-bold text-violet-600 dark:border-violet-500/25 dark:bg-violet-500/10 dark:text-violet-300">
                                    {breedMemoryWall.length} breeds
                                </span>
                                <div className="ml-auto flex flex-wrap items-center gap-2">
                                    {Object.entries(LEVEL_CFG)
                                        .reverse()
                                        .map(([k, c]) => (
                                            <div
                                                key={k}
                                                className="flex items-center gap-1"
                                            >
                                                <span
                                                    className="h-1.5 w-1.5 rounded-full"
                                                    style={{
                                                        backgroundColor: c.dot,
                                                    }}
                                                />
                                                <span className="text-[9px] text-neutral-400 dark:text-white/25">
                                                    {c.label}
                                                </span>
                                            </div>
                                        ))}
                                </div>
                            </div>

                            {breedMemoryWall.length === 0 ? (
                                <p className="text-xs text-neutral-400 dark:text-white/20">
                                    No breeds yet — submit a correction to
                                    populate memory
                                </p>
                            ) : (
                                <div className="flex flex-col gap-2">
                                    {expertChips.length > 0 && (
                                        <div className="flex flex-wrap gap-1.5">
                                            {expertChips.map((c) => (
                                                <MemoryChipCard
                                                    key={c.breed}
                                                    chip={c}
                                                />
                                            ))}
                                        </div>
                                    )}
                                    {trainedChips.length > 0 && (
                                        <div className="flex flex-wrap gap-1.5">
                                            {trainedChips.map((c) => (
                                                <MemoryChipCard
                                                    key={c.breed}
                                                    chip={c}
                                                />
                                            ))}
                                        </div>
                                    )}
                                    {learningChips.length > 0 && (
                                        <div className="flex flex-wrap gap-1.5">
                                            {learningChips.map((c) => (
                                                <MemoryChipCard
                                                    key={c.breed}
                                                    chip={c}
                                                />
                                            ))}
                                        </div>
                                    )}
                                    {newChips.length > 0 && (
                                        <div className="flex flex-wrap gap-1.5">
                                            {newChips.map((c) => (
                                                <MemoryChipCard
                                                    key={c.breed}
                                                    chip={c}
                                                />
                                            ))}
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* ── Recent Scans ─────────────────────────────────────── */}
                <div className="overflow-hidden rounded-2xl border border-neutral-200/60 bg-white shadow-sm dark:border-white/[0.06] dark:bg-neutral-900">
                    <div className="flex items-center justify-between border-b border-neutral-100 px-6 py-4 dark:border-white/[0.06]">
                        <div>
                            <h2 className="font-bold text-neutral-900 dark:text-white">
                                Recent Scans
                            </h2>
                            <p className="text-xs text-neutral-400 dark:text-white/35">
                                Latest AI predictions
                            </p>
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => router.visit('/model/scan-results')}
                            className="border-neutral-200 text-neutral-500 hover:border-neutral-300 hover:text-neutral-700 dark:border-white/10 dark:bg-white/4 dark:text-white/50 dark:hover:bg-white/8 dark:hover:text-white"
                        >
                            View All <ChevronRight className="ml-1 h-4 w-4" />
                        </Button>
                    </div>
                    <div className="overflow-x-auto px-5">
                        <Table>
                            <TableHeader>
                                <TableRow className="border-neutral-100 dark:border-white/5">
                                    {[
                                        'Scan ID',
                                        'Breed',
                                        'Confidence',
                                        'Status',
                                    ].map((h) => (
                                        <TableHead
                                            key={h}
                                            className="text-neutral-400 dark:text-white/30"
                                        >
                                            {h}
                                        </TableHead>
                                    ))}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {results?.map((r) => (
                                    <TableRow
                                        key={r.scan_id}
                                        className="border-neutral-100 dark:border-white/5"
                                    >
                                        <TableCell className="font-mono text-xs text-neutral-400 dark:text-white/30">
                                            {r.scan_id}
                                        </TableCell>
                                        <TableCell className="font-semibold text-neutral-800 dark:text-white">
                                            {r.breed}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                <div className="h-1.5 w-20 overflow-hidden rounded-full bg-neutral-100 dark:bg-white/8">
                                                    <div
                                                        className="h-full rounded-full"
                                                        style={{
                                                            width: `${r.confidence}%`,
                                                            background:
                                                                r.confidence >=
                                                                80
                                                                    ? '#10b981'
                                                                    : r.confidence >=
                                                                        60
                                                                      ? '#3b82f6'
                                                                      : r.confidence >=
                                                                          40
                                                                        ? '#f59e0b'
                                                                        : '#ef4444',
                                                        }}
                                                    />
                                                </div>
                                                <span className="text-sm font-bold text-neutral-600 dark:text-white/60">
                                                    {r.confidence}%
                                                </span>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {(
                                                [
                                                    [
                                                        80,
                                                        'High',
                                                        'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/12 dark:text-emerald-400 dark:border-emerald-500/20',
                                                    ],
                                                    [
                                                        60,
                                                        'Medium',
                                                        'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-500/12 dark:text-blue-400 dark:border-blue-500/20',
                                                    ],
                                                    [
                                                        40,
                                                        'Low',
                                                        'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/12 dark:text-amber-400 dark:border-amber-500/20',
                                                    ],
                                                    [
                                                        0,
                                                        'Very Low',
                                                        'bg-red-50 text-red-700 border-red-200 dark:bg-red-500/12 dark:text-red-400 dark:border-red-500/20',
                                                    ],
                                                ] as [number, string, string][]
                                            )
                                                .map(([thresh, label, cls]) =>
                                                    r.confidence >= thresh ? (
                                                        <span
                                                            key={label}
                                                            className={`rounded-full border px-2.5 py-0.5 text-xs font-semibold ${cls}`}
                                                        >
                                                            {label}
                                                        </span>
                                                    ) : null,
                                                )
                                                .find(Boolean)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
