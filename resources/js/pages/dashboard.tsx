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
    Activity,
    ArrowDown,
    ArrowUp,
    BarChart3,
    CheckCircle2,
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

/* ─────────────────── breadcrumbs ────────────────────────────────────────── */
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
];

/* ─────────────────── types ──────────────────────────────────────────────── */
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

/* ─────────────────── level config ───────────────────────────────────────── */
const LEVEL_CFG = {
    expert: {
        bg: 'bg-emerald-500/15',
        border: 'border-emerald-500/35',
        text: 'text-emerald-300',
        dot: '#10b981',
        label: 'Expert',
    },
    trained: {
        bg: 'bg-green-500/12',
        border: 'border-green-500/30',
        text: 'text-green-300',
        dot: '#22c55e',
        label: 'Trained',
    },
    learning: {
        bg: 'bg-blue-500/12',
        border: 'border-blue-500/30',
        text: 'text-blue-300',
        dot: '#3b82f6',
        label: 'Learning',
    },
    new: {
        bg: 'bg-amber-500/12',
        border: 'border-amber-500/30',
        text: 'text-amber-300',
        dot: '#f59e0b',
        label: 'New',
    },
};

/* ─────────────────── GitHub-style compact heatmap ──────────────────────── */
function heatColor(count: number, max: number): string {
    if (count === 0) return '#161b22';
    const ratio = Math.min(count / Math.max(max, 1), 1);
    if (ratio < 0.25) return '#0e4429';
    if (ratio < 0.5) return '#006d32';
    if (ratio < 0.75) return '#26a641';
    return '#39d353';
}

function CompactHeatmap({ days }: { days: HeatmapDay[] }) {
    const [hovered, setHovered] = useState<HeatmapDay | null>(null);
    const [tipPos, setTipPos] = useState({ x: 0, y: 0 });
    const maxCount = Math.max(...days.map((d) => d.count), 1);

    const DOW = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];

    // Sort oldest→newest. Recalculate week col from scratch so column 0 is
    // always the oldest Sunday-aligned week and column 11 is the latest.
    const sorted = [...days].sort(
        (a, b) => new Date(a.date).getTime() - new Date(b.date).getTime(),
    );

    const sortedGrid: (HeatmapDay | null)[][] = Array.from({ length: 7 }, () =>
        Array(12).fill(null),
    );
    const sortedMonths: string[] = Array(12).fill('');
    let seenM = '';

    if (sorted.length > 0) {
        const oldest = new Date(sorted[0].date).getTime();
        sorted.forEach((d) => {
            const dt = new Date(d.date);
            const dow = dt.getDay(); // 0=Sun … 6=Sat
            const diffMs = dt.getTime() - oldest;
            const weekCol = Math.floor(diffMs / (7 * 86400000)); // 0-based week column

            if (weekCol >= 0 && weekCol < 12) {
                sortedGrid[dow][weekCol] = d;
                const m = dt.toLocaleString('en-US', { month: 'short' });
                if (m !== seenM) {
                    sortedMonths[weekCol] = m;
                    seenM = m;
                }
            }
        });
    }

    return (
        <div className="relative w-full select-none">
            {hovered && (
                <div
                    className="pointer-events-none fixed z-50 rounded-lg border border-white/10 bg-gray-900/97 px-2.5 py-2 text-xs shadow-xl backdrop-blur-sm"
                    style={{
                        left: tipPos.x + 12,
                        top: tipPos.y - 8,
                        minWidth: 128,
                    }}
                >
                    <p className="font-bold text-white">
                        {hovered.is_today ? '📅 Today' : hovered.label}
                    </p>
                    <p
                        className={
                            hovered.count > 0
                                ? 'text-emerald-400'
                                : 'text-white/30'
                        }
                    >
                        {hovered.count > 0
                            ? `${hovered.count} correction${hovered.count !== 1 ? 's' : ''}`
                            : 'No activity'}
                    </p>
                </div>
            )}

            {/* Outer wrapper: day-label column + grid column */}
            <div className="flex w-full items-start gap-1">
                {/* Day-of-week labels — fixed width, sits beside the grid */}
                <div
                    className="flex shrink-0 flex-col pt-4"
                    style={{ gap: 2, width: 14 }}
                >
                    {DOW.map((l, i) => (
                        <div
                            key={i}
                            className="text-right text-[8px] leading-none text-white/20"
                            style={{ height: 11, lineHeight: '11px' }}
                        >
                            {i % 2 === 1 ? l : ''}
                        </div>
                    ))}
                </div>

                {/* Grid + month labels stacked */}
                <div className="flex min-w-0 flex-1 flex-col">
                    {/* Month labels — one per column, flex-1 so they align exactly */}
                    <div className="mb-1 flex w-full" style={{ gap: 2 }}>
                        {sortedMonths.map((m, i) => (
                            <div
                                key={i}
                                className="flex-1 overflow-hidden text-[9px] leading-none text-white/25"
                            >
                                {m}
                            </div>
                        ))}
                    </div>

                    {/* Week columns */}
                    <div className="flex w-full" style={{ gap: 2 }}>
                        {Array.from({ length: 12 }, (_, week) => (
                            <div
                                key={week}
                                className="flex flex-1 flex-col"
                                style={{ gap: 2 }}
                            >
                                {Array.from({ length: 7 }, (_, dow) => {
                                    const cell = sortedGrid[dow][week];
                                    return (
                                        <div
                                            key={dow}
                                            className="w-full"
                                            style={{
                                                height: 11,
                                                borderRadius: 2,
                                                background: cell
                                                    ? heatColor(
                                                          cell.count,
                                                          maxCount,
                                                      )
                                                    : '#161b22',
                                                outline: cell?.is_today
                                                    ? '1.5px solid #6366f1'
                                                    : undefined,
                                                cursor: 'default',
                                            }}
                                            onMouseEnter={
                                                cell
                                                    ? (e) => {
                                                          setHovered(cell);
                                                          setTipPos({
                                                              x: e.clientX,
                                                              y: e.clientY,
                                                          });
                                                      }
                                                    : undefined
                                            }
                                            onMouseMove={
                                                cell
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
            <div className="mt-2 flex items-center gap-1.5">
                <span className="text-[9px] text-white/20">Less</span>
                {['#161b22', '#0e4429', '#006d32', '#26a641', '#39d353'].map(
                    (c, i) => (
                        <div
                            key={i}
                            style={{
                                width: 10,
                                height: 10,
                                borderRadius: 2,
                                background: c,
                            }}
                        />
                    ),
                )}
                <span className="text-[9px] text-white/20">More</span>
            </div>
        </div>
    );
}

/* ─────────────────── MemoryChipCard ─────────────────────────────────────── */
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
                <div className="pointer-events-none absolute bottom-full left-1/2 z-50 mb-1.5 w-36 -translate-x-1/2 rounded-xl border border-white/10 bg-gray-900/97 p-2 text-xs shadow-xl backdrop-blur-md">
                    <p className="mb-0.5 font-black text-white">{chip.breed}</p>
                    <p className={`text-[11px] ${cfg.text}`}>{cfg.label}</p>
                    <p className="text-white/40">
                        Taught {chip.times_taught}× by vet
                    </p>
                    <p className="text-white/25">Since {chip.first_taught}</p>
                    {chip.days_ago === 0 && (
                        <p className="mt-0.5 text-indigo-400">✨ Added today</p>
                    )}
                </div>
            )}
        </div>
    );
}

/* ─────────────────── Main Dashboard ─────────────────────────────────────── */
export default function Dashboard() {
    const {
        results,
        correctedBreedCount,
        resultCount,
        pendingReviewCount = 0,
        highConfidenceCount = 0,
        totalScansWeeklyTrend = 0,
        correctedWeeklyTrend = 0,
        highConfidenceWeeklyTrend = 0,
        memoryCount = 0,
        avgConfidence = 0,
        confidenceTrend = 0,
        memoryHitRate = 0,
        accuracyImprovement = 0,
        learningTimeline = [],
        learningHeatmap = [],
        heatmapSummary,
        breedMemoryWall = [],
    } = usePage<PageProps>().props;

    const [hoveredDay, setHoveredDay] = useState<number | null>(null);

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
                ? 'text-emerald-400'
                : t > 0
                  ? 'text-red-400'
                  : 'text-gray-400';
        return t > 0
            ? 'text-emerald-400'
            : t < 0
              ? 'text-red-400'
              : 'text-gray-400';
    };

    const maxCor = Math.max(...learningTimeline.map((d) => d.corrections), 1);
    const tlTotal = learningTimeline.reduce((s, d) => s + d.corrections, 0);
    const todayEntry = learningTimeline.find((d) => d.is_today);

    const tlStatus = (() => {
        if (!todayEntry) return null;
        if (todayEntry.corrections > 0)
            return {
                text: `✅ ${todayEntry.corrections} correction${todayEntry.corrections !== 1 ? 's' : ''} today — AI is actively learning!`,
                cls: 'bg-emerald-500/10 border-emerald-500/25 text-emerald-300',
            };
        const r3 = learningTimeline
            .slice(-3)
            .reduce((s, d) => s + d.corrections, 0);
        if (r3 > 0)
            return {
                text: `🟡 No corrections today — ${r3} in the last 3 days. Keep going!`,
                cls: 'bg-amber-500/10 border-amber-500/25 text-amber-300',
            };
        return {
            text: '🔴 No recent corrections — review pending scans to keep training the AI.',
            cls: 'bg-red-500/10 border-red-500/25 text-red-300',
        };
    })();

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
                        'learningTimeline',
                    ],
                }),
            30000,
        );
        return () => clearInterval(iv);
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="AI Learning Dashboard" />

            <div className="flex h-full flex-col gap-6 p-4 md:p-6">
                {/* ── 4 metric cards ───────────────────────────────────── */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {(
                        [
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
                                label: 'High Confidence',
                                value: highConfidenceCount,
                                trend: highConfidenceWeeklyTrend,
                                Icon: CheckCircle2,
                                accent: '#10b981',
                                inv: false,
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
                        }[]
                    ).map(({ label, value, trend, Icon, accent, inv }) => (
                        <div
                            key={label}
                            className="relative overflow-hidden rounded-2xl border border-white/8 bg-gray-900 p-5"
                        >
                            <div
                                className="pointer-events-none absolute -top-3 -right-3 h-16 w-16 rounded-full opacity-15 blur-xl"
                                style={{ background: accent }}
                            />
                            <div className="flex items-start justify-between">
                                <div>
                                    <p className="text-sm text-white/40">
                                        {label}
                                    </p>
                                    <div className="mt-2 flex items-baseline gap-2">
                                        <p className="text-3xl font-black text-white">
                                            {value}
                                        </p>
                                        <div
                                            className={`flex items-center gap-0.5 text-sm font-bold ${tClr(trend, inv)}`}
                                        >
                                            {tIcon(trend)}
                                            <span>{fmt(trend)}</span>
                                        </div>
                                    </div>
                                    <p className="mt-0.5 text-xs text-white/20">
                                        vs last week
                                    </p>
                                </div>
                                <div
                                    className="flex h-11 w-11 items-center justify-center rounded-xl"
                                    style={{
                                        background: `${accent}20`,
                                        border: `1px solid ${accent}35`,
                                    }}
                                >
                                    <Icon
                                        className="h-5 w-5"
                                        style={{ color: accent }}
                                    />
                                </div>
                            </div>
                        </div>
                    ))}
                </div>

                {/* ── 3 mini stats ─────────────────────────────────────── */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    {(
                        [
                            {
                                label: 'Learning Progress',
                                val: `${accuracyImprovement.toFixed(0)}/100`,
                                sub: 'Composite score',
                                Icon: Target,
                                a: '#10b981',
                            },
                            {
                                label: 'Avg Confidence',
                                val: `${avgConfidence.toFixed(1)}%`,
                                sub: `${confidenceTrend >= 0 ? '+' : ''}${confidenceTrend.toFixed(1)}% this week`,
                                Icon: LineChart,
                                a: '#3b82f6',
                            },
                            {
                                label: 'Memory Usage Rate',
                                val: `${memoryHitRate.toFixed(1)}%`,
                                sub: `${memoryCount} patterns stored`,
                                Icon: Database,
                                a: '#8b5cf6',
                            },
                        ] as const
                    ).map(({ label, val, sub, Icon, a }) => (
                        <div
                            key={label}
                            className="relative overflow-hidden rounded-2xl border border-white/8 bg-gray-900 p-5"
                        >
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-white/40">
                                        {label}
                                    </p>
                                    <p className="mt-1 text-2xl font-black text-white">
                                        {val}
                                    </p>
                                    <p className="mt-0.5 text-xs text-white/25">
                                        {sub}
                                    </p>
                                </div>
                                <div
                                    className="flex h-12 w-12 items-center justify-center rounded-full"
                                    style={{
                                        background: `${a}20`,
                                        border: `1px solid ${a}30`,
                                    }}
                                >
                                    <Icon
                                        className="h-6 w-6"
                                        style={{ color: a }}
                                    />
                                </div>
                            </div>
                        </div>
                    ))}
                </div>

                {/* ── Learning Pulse ───────────────────────────────────── */}
                <div className="rounded-2xl border border-white/8 bg-gray-900 p-5">
                    <div className="mb-4 flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-xl border border-indigo-500/25 bg-indigo-500/15">
                                <Activity className="h-5 w-5 text-indigo-400" />
                            </div>
                            <div>
                                <h2 className="font-bold text-white">
                                    Learning Pulse
                                </h2>
                                <p className="text-xs text-white/35">
                                    Daily corrections — last 10 days
                                </p>
                            </div>
                        </div>
                        <span className="rounded-full border border-indigo-500/25 bg-indigo-500/10 px-3 py-1 text-xs font-bold text-indigo-300">
                            {tlTotal} total
                        </span>
                    </div>
                    {learningTimeline.length === 0 ? (
                        <p className="py-8 text-center text-sm text-white/20">
                            No activity yet — submit corrections to see activity
                        </p>
                    ) : (
                        <>
                            <div className="flex h-36 items-end gap-2">
                                {learningTimeline.map((d, idx) => {
                                    const h =
                                        d.corrections > 0
                                            ? Math.max(
                                                  8,
                                                  (d.corrections / maxCor) *
                                                      100,
                                              )
                                            : 3;
                                    const hov = hoveredDay === idx;
                                    return (
                                        <div
                                            key={d.date}
                                            className="relative flex flex-1 flex-col items-center"
                                            onMouseEnter={() =>
                                                setHoveredDay(idx)
                                            }
                                            onMouseLeave={() =>
                                                setHoveredDay(null)
                                            }
                                        >
                                            {hov && (
                                                <div
                                                    className="absolute bottom-full z-20 mb-2 w-40 rounded-xl border border-white/12 bg-gray-900/95 p-3 text-xs shadow-2xl backdrop-blur-sm"
                                                    style={{
                                                        left: '50%',
                                                        transform:
                                                            'translateX(-50%)',
                                                    }}
                                                >
                                                    <p className="mb-1.5 font-bold text-white">
                                                        {d.is_today
                                                            ? '📅 Today'
                                                            : d.day}
                                                    </p>
                                                    <div className="space-y-1">
                                                        <div className="flex justify-between">
                                                            <span className="text-white/40">
                                                                Corrections
                                                            </span>
                                                            <span className="font-bold text-indigo-300">
                                                                {d.corrections}
                                                            </span>
                                                        </div>
                                                        <div className="flex justify-between">
                                                            <span className="text-white/40">
                                                                Scans
                                                            </span>
                                                            <span className="font-bold text-blue-300">
                                                                {d.total_scans}
                                                            </span>
                                                        </div>
                                                        <div className="flex justify-between">
                                                            <span className="text-white/40">
                                                                High conf.
                                                            </span>
                                                            <span className="font-bold text-emerald-300">
                                                                {
                                                                    d.high_conf_rate
                                                                }
                                                                %
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            )}
                                            <div
                                                className="w-full rounded-t-md transition-all duration-300"
                                                style={{
                                                    height: `${h}%`,
                                                    minHeight: 3,
                                                    background: d.is_today
                                                        ? 'linear-gradient(to top,#6366f1,#818cf8)'
                                                        : d.corrections > 0
                                                          ? hov
                                                              ? 'linear-gradient(to top,#3b82f6,#60a5fa)'
                                                              : 'rgba(99,102,241,0.4)'
                                                          : 'rgba(255,255,255,0.04)',
                                                    boxShadow:
                                                        d.corrections > 0
                                                            ? '0 0 8px #6366f133'
                                                            : 'none',
                                                }}
                                            />
                                        </div>
                                    );
                                })}
                            </div>
                            <div className="mt-2 flex gap-2">
                                {learningTimeline.map((d) => (
                                    <div
                                        key={d.date}
                                        className="flex flex-1 justify-center"
                                    >
                                        <span
                                            className={
                                                d.is_today
                                                    ? 'font-bold text-indigo-400'
                                                    : 'text-white/20'
                                            }
                                            style={{
                                                fontSize: '0.6rem',
                                                textAlign: 'center',
                                                lineHeight: 1,
                                            }}
                                        >
                                            {d.is_today ? 'Today' : d.day}
                                        </span>
                                    </div>
                                ))}
                            </div>
                            {tlStatus && (
                                <div
                                    className={`mt-4 rounded-lg border px-4 py-2.5 text-xs font-medium ${tlStatus.cls}`}
                                >
                                    {tlStatus.text}
                                </div>
                            )}
                        </>
                    )}
                </div>

                {/* ════════════════════════════════════════════════════════
                        AI TRAINING ACTIVITY
                ═══════════════════════════════════════════════════════════ */}
                <div className="rounded-2xl border border-white/8 bg-gray-900">
                    {/* header */}
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-white/8 px-5 py-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-xl border border-violet-500/25 bg-violet-500/15">
                                <Sparkles className="h-5 w-5 text-violet-400" />
                            </div>
                            <div>
                                <h2 className="font-bold text-white">
                                    AI Training Activity
                                </h2>
                                <p className="text-xs text-white/35">
                                    12-week correction history &amp; breed
                                    memory
                                </p>
                            </div>
                        </div>
                        {heatmapSummary && (
                            <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-white/30">
                                <span>
                                    <span className="font-bold text-white">
                                        {heatmapSummary.total_in_range}
                                    </span>{' '}
                                    corrections
                                </span>
                                <span className="text-white/15">·</span>
                                <span>
                                    <span className="font-bold text-white">
                                        {heatmapSummary.active_days}
                                    </span>{' '}
                                    active days
                                </span>
                                {heatmapSummary.current_streak > 0 && (
                                    <>
                                        <span className="text-white/15">·</span>
                                        <span className="font-bold text-orange-400">
                                            🔥 {heatmapSummary.current_streak}
                                            -day streak
                                        </span>
                                    </>
                                )}
                            </div>
                        )}
                    </div>

                    {/* ── 50 / 50 split body ─────────────────────────────── */}
                    <div className="flex flex-col lg:flex-row">
                        {/* LEFT — heatmap, exactly half */}
                        <div className="w-full p-5 lg:w-1/2">
                            <p className="mb-3 text-[10px] font-semibold tracking-wider text-white/30 uppercase">
                                Correction Heatmap
                            </p>
                            {learningHeatmap.length > 0 ? (
                                <CompactHeatmap days={learningHeatmap} />
                            ) : (
                                <p className="text-xs text-white/20">
                                    No data yet
                                </p>
                            )}
                        </div>

                        {/* Divider */}
                        <div className="hidden w-px bg-white/6 lg:block" />
                        <div className="mx-5 h-px bg-white/6 lg:hidden" />

                        {/* RIGHT — memory wall, exactly half */}
                        <div className="w-full p-5 lg:w-1/2">
                            <div className="mb-3 flex flex-wrap items-center gap-2">
                                <p className="text-[10px] font-semibold tracking-wider text-white/30 uppercase">
                                    Breed Memory Wall
                                </p>
                                <span className="rounded-full border border-violet-500/25 bg-violet-500/10 px-2 py-0.5 text-[10px] font-bold text-violet-300">
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
                                                <span className="text-[9px] text-white/25">
                                                    {c.label}
                                                </span>
                                            </div>
                                        ))}
                                </div>
                            </div>

                            {breedMemoryWall.length === 0 ? (
                                <p className="text-xs text-white/20">
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

                {/* ── Recent Scans table ────────────────────────────────── */}
                <div className="overflow-hidden rounded-2xl border border-white/8 bg-gray-900">
                    <div className="flex items-center justify-between border-b border-white/8 px-6 py-4">
                        <div>
                            <h2 className="font-bold text-white">
                                Recent Scans
                            </h2>
                            <p className="text-xs text-white/35">
                                Latest AI predictions
                            </p>
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => router.visit('/model/scan-results')}
                            className="border-white/10 bg-white/4 text-white/50 hover:bg-white/8 hover:text-white"
                        >
                            View All <ChevronRight className="ml-1 h-4 w-4" />
                        </Button>
                    </div>
                    <div className="overflow-x-auto px-5">
                        <Table>
                            <TableHeader>
                                <TableRow className="border-white/5">
                                    {[
                                        'Scan ID',
                                        'Breed',
                                        'Confidence',
                                        'Status',
                                    ].map((h) => (
                                        <TableHead
                                            key={h}
                                            className="text-white/30"
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
                                        className="border-white/5"
                                    >
                                        <TableCell className="font-mono text-xs text-white/30">
                                            {r.scan_id}
                                        </TableCell>
                                        <TableCell className="font-medium text-white">
                                            {r.breed}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                <div className="h-1.5 w-20 overflow-hidden rounded-full bg-white/8">
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
                                                <span className="text-sm font-bold text-white/60">
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
                                                        'bg-emerald-500/12 text-emerald-400 border-emerald-500/20',
                                                    ],
                                                    [
                                                        60,
                                                        'Medium',
                                                        'bg-blue-500/12 text-blue-400 border-blue-500/20',
                                                    ],
                                                    [
                                                        40,
                                                        'Low',
                                                        'bg-amber-500/12 text-amber-400 border-amber-500/20',
                                                    ],
                                                    [
                                                        0,
                                                        'Very Low',
                                                        'bg-red-500/12 text-red-400 border-red-500/20',
                                                    ],
                                                ] as [number, string, string][]
                                            )
                                                .map(([thresh, label, cls]) =>
                                                    r.confidence >= thresh ? (
                                                        <span
                                                            key={label}
                                                            className={`rounded-full border px-2.5 py-0.5 text-xs font-bold ${cls}`}
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
