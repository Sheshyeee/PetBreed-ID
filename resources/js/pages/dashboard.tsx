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
    Flame,
    GraduationCap,
    LineChart,
    Minus,
    Sparkles,
    Target,
    Trophy,
    Zap,
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

/* ─────────────────── heatmap cell colours ───────────────────────────────── */
function heatColor(count: number, max: number): string {
    if (count === 0) return 'rgba(255,255,255,0.04)';
    const ratio = Math.min(count / Math.max(max, 1), 1);
    // emerald ramp: light → vibrant
    if (ratio < 0.25) return 'rgba(16,185,129,0.20)';
    if (ratio < 0.5) return 'rgba(16,185,129,0.40)';
    if (ratio < 0.75) return 'rgba(16,185,129,0.65)';
    return 'rgba(16,185,129,0.90)';
}

/* ─────────────────── HeatmapGrid ───────────────────────────────────────── */
function HeatmapGrid({
    days,
    summary,
}: {
    days: HeatmapDay[];
    summary: HeatmapSummary;
}) {
    const [hovered, setHovered] = useState<HeatmapDay | null>(null);
    const [tipPos, setTipPos] = useState({ x: 0, y: 0 });

    // Build a 7×12 grid (rows = day-of-week Sun–Sat, cols = weeks 0–11)
    const maxCount = Math.max(...days.map((d) => d.count), 1);
    const grid: (HeatmapDay | null)[][] = Array.from({ length: 7 }, () =>
        Array(12).fill(null),
    );
    days.forEach((d) => {
        grid[d.day_of_week][d.week] = d;
    });

    const DAY_LABELS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    // Month labels above columns — derive from first day of each week
    const weekMonths: string[] = Array(12).fill('');
    let lastMonth = '';
    days.forEach((d) => {
        const m = new Date(d.date).toLocaleString('en-US', { month: 'short' });
        if (m !== lastMonth) {
            weekMonths[d.week] = m;
            lastMonth = m;
        }
    });

    return (
        <div className="relative">
            {/* Tooltip */}
            {hovered && (
                <div
                    className="pointer-events-none fixed z-50 rounded-xl border border-white/15 bg-gray-900/97 px-3 py-2.5 text-xs shadow-2xl backdrop-blur-md"
                    style={{
                        left: tipPos.x + 14,
                        top: tipPos.y - 10,
                        minWidth: 148,
                    }}
                >
                    <p className="mb-1 font-black text-white">
                        {hovered.is_today ? '📅 Today' : hovered.label}
                    </p>
                    <p
                        className={
                            hovered.count > 0
                                ? 'text-emerald-400'
                                : 'text-white/25'
                        }
                    >
                        {hovered.count > 0
                            ? `${hovered.count} correction${hovered.count !== 1 ? 's' : ''}`
                            : 'No activity'}
                    </p>
                </div>
            )}

            {/* Month labels */}
            <div className="mb-1 flex pl-8">
                {weekMonths.map((m, i) => (
                    <div key={i} className="flex-1 text-[10px] text-white/25">
                        {m}
                    </div>
                ))}
            </div>

            {/* Grid */}
            <div className="flex gap-0.5 pl-8">
                {/* Week columns */}
                {Array.from({ length: 12 }, (_, week) => (
                    <div key={week} className="flex flex-1 flex-col gap-0.5">
                        {Array.from({ length: 7 }, (_, dow) => {
                            const cell = grid[dow][week];
                            return (
                                <div
                                    key={dow}
                                    className="aspect-square w-full cursor-default rounded-sm transition-all duration-150 hover:ring-1 hover:ring-white/30"
                                    style={{
                                        background: cell
                                            ? heatColor(cell.count, maxCount)
                                            : 'rgba(255,255,255,0.03)',
                                        boxShadow: cell?.is_today
                                            ? '0 0 0 1.5px #6366f1'
                                            : undefined,
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
                                    onMouseLeave={() => setHovered(null)}
                                />
                            );
                        })}
                    </div>
                ))}
            </div>

            {/* Day-of-week labels (absolute left) */}
            <div className="absolute top-6 left-0 flex flex-col gap-0.5">
                {DAY_LABELS.map((l, i) => (
                    <div
                        key={i}
                        className="flex h-full items-center text-[9px] leading-none text-white/20"
                        style={{ height: 'calc((100% - 1.25rem) / 7)' }}
                    >
                        {i % 2 === 0 ? l : ''}
                    </div>
                ))}
            </div>

            {/* Legend */}
            <div className="mt-3 flex items-center justify-between">
                <span className="text-[10px] text-white/20">Less</span>
                <div className="flex items-center gap-1">
                    {[0, 0.2, 0.45, 0.7, 1].map((r, i) => (
                        <div
                            key={i}
                            className="h-2.5 w-2.5 rounded-sm"
                            style={{
                                background:
                                    r === 0
                                        ? 'rgba(255,255,255,0.04)'
                                        : `rgba(16,185,129,${r * 0.9 + 0.1})`,
                            }}
                        />
                    ))}
                </div>
                <span className="text-[10px] text-white/20">More</span>
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
                className={`group flex cursor-default items-center gap-2 rounded-xl border px-3 py-2 transition-all duration-200 hover:brightness-110 ${cfg.bg} ${cfg.border}`}
                onMouseEnter={() => setShow(true)}
                onMouseLeave={() => setShow(false)}
            >
                {/* Pulsing dot for well-trained */}
                <span className="relative flex h-2 w-2 shrink-0">
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
                        className="relative inline-flex h-2 w-2 rounded-full"
                        style={{ backgroundColor: cfg.dot }}
                    />
                </span>
                <span
                    className={`truncate text-xs leading-none font-semibold ${cfg.text}`}
                >
                    {chip.breed}
                </span>
                {chip.times_taught > 1 && (
                    <span
                        className={`ml-auto shrink-0 rounded-full px-1.5 py-0.5 text-[9px] leading-none font-black ${cfg.bg} ${cfg.text}`}
                    >
                        ×{chip.times_taught}
                    </span>
                )}
            </div>
            {show && (
                <div className="pointer-events-none absolute bottom-full left-1/2 z-50 mb-2 w-40 -translate-x-1/2 rounded-xl border border-white/12 bg-gray-900/97 p-2.5 text-xs shadow-2xl backdrop-blur-md">
                    <p className="mb-1 font-black text-white">{chip.breed}</p>
                    <p className={cfg.text}>{cfg.label}</p>
                    <p className="text-white/40">
                        Taught {chip.times_taught}× by vet
                    </p>
                    <p className="text-white/25">First: {chip.first_taught}</p>
                    {chip.days_ago === 0 && (
                        <p className="mt-0.5 text-indigo-400">
                            ✨ Added today!
                        </p>
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

    // Group memory wall by level
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
                        AI TRAINING ACTIVITY — Heatmap + Memory Wall
                ═══════════════════════════════════════════════════════════ */}
                <div className="overflow-hidden rounded-2xl border border-white/8 bg-gray-950">
                    {/* ── Section header ────────────────────────────────── */}
                    <div
                        className="relative overflow-hidden border-b border-white/8 p-6"
                        style={{
                            background:
                                'linear-gradient(135deg,#0f172a 0%,#064e3b 60%,#0f172a 100%)',
                        }}
                    >
                        <div className="pointer-events-none absolute top-0 left-1/4 h-48 w-48 rounded-full bg-emerald-600/8 blur-3xl" />
                        <div className="pointer-events-none absolute right-1/3 bottom-0 h-32 w-32 rounded-full bg-teal-500/8 blur-2xl" />

                        <div className="relative flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            {/* Title */}
                            <div className="flex items-center gap-4">
                                <div
                                    className="relative flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl border border-emerald-500/35"
                                    style={{
                                        background:
                                            'linear-gradient(135deg,#10b98118,#06b6d410)',
                                        boxShadow: '0 0 20px #10b98133',
                                    }}
                                >
                                    <Activity className="h-7 w-7 text-emerald-400" />
                                    <div
                                        className="absolute inset-0 animate-ping rounded-2xl opacity-10"
                                        style={{
                                            border: '2px solid #10b981',
                                            animationDuration: '4s',
                                        }}
                                    />
                                </div>
                                <div>
                                    <h2 className="text-xl font-black tracking-tight text-white">
                                        AI Training Activity
                                    </h2>
                                    <p className="mt-0.5 text-sm text-white/35">
                                        Every correction{' '}
                                        <span className="text-emerald-400">
                                            colours a square
                                        </span>{' '}
                                        — watch the AI's memory grow over 12
                                        weeks
                                    </p>
                                </div>
                            </div>

                            {/* Summary stat pills */}
                            {heatmapSummary && (
                                <div className="flex shrink-0 flex-wrap items-center gap-2">
                                    <div className="rounded-xl border border-emerald-500/25 bg-emerald-500/10 px-4 py-2 text-center">
                                        <p className="text-xl font-black text-emerald-400">
                                            {heatmapSummary.total_in_range}
                                        </p>
                                        <p className="text-[10px] font-semibold tracking-wider text-emerald-400/50 uppercase">
                                            12-Week Total
                                        </p>
                                    </div>
                                    <div className="rounded-xl border border-white/8 bg-white/4 px-4 py-2 text-center">
                                        <p className="text-xl font-black text-white">
                                            {heatmapSummary.active_days}
                                        </p>
                                        <p className="text-[10px] font-semibold tracking-wider text-white/25 uppercase">
                                            Active Days
                                        </p>
                                    </div>
                                    <div className="rounded-xl border border-orange-500/25 bg-orange-500/10 px-4 py-2 text-center">
                                        <div className="flex items-center gap-1">
                                            <Flame className="h-4 w-4 text-orange-400" />
                                            <p className="text-xl font-black text-orange-300">
                                                {heatmapSummary.current_streak}
                                            </p>
                                        </div>
                                        <p className="text-[10px] font-semibold tracking-wider text-orange-400/50 uppercase">
                                            Day Streak
                                        </p>
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Best day badge */}
                        {heatmapSummary &&
                            heatmapSummary.best_day_count > 0 && (
                                <div className="mt-4 inline-flex items-center gap-2 rounded-full border border-emerald-500/25 bg-emerald-500/8 px-3 py-1.5">
                                    <Trophy className="h-3 w-3 text-emerald-400" />
                                    <span className="text-xs font-medium text-emerald-300">
                                        Best day:{' '}
                                        <strong>
                                            {heatmapSummary.best_day_count}{' '}
                                            corrections
                                        </strong>
                                        <span className="ml-1 text-emerald-400/50">
                                            on {heatmapSummary.best_day_label}
                                        </span>
                                    </span>
                                </div>
                            )}
                    </div>

                    {/* ── Training Heatmap ──────────────────────────────── */}
                    <div className="border-b border-white/6 p-6">
                        <div className="mb-4 flex items-center gap-2">
                            <Zap className="h-4 w-4 text-emerald-400" />
                            <h3 className="text-sm font-bold text-white">
                                12-Week Correction Heatmap
                            </h3>
                            <span className="ml-auto text-[10px] text-white/20">
                                Like GitHub contributions — darker = more
                                corrections that day
                            </span>
                        </div>
                        {learningHeatmap.length > 0 ? (
                            <HeatmapGrid
                                days={learningHeatmap}
                                summary={
                                    heatmapSummary ?? {
                                        active_days: 0,
                                        total_in_range: 0,
                                        current_streak: 0,
                                        best_day_count: 0,
                                        best_day_label: '',
                                    }
                                }
                            />
                        ) : (
                            <p className="py-8 text-center text-sm text-white/20">
                                No heatmap data — submit corrections to start
                                building history
                            </p>
                        )}
                    </div>

                    {/* ── Breed Memory Wall ─────────────────────────────── */}
                    <div className="p-6">
                        <div className="mb-4 flex flex-wrap items-center gap-3">
                            <div className="flex items-center gap-2">
                                <Sparkles className="h-4 w-4 text-violet-400" />
                                <h3 className="text-sm font-bold text-white">
                                    Breed Memory Wall
                                </h3>
                            </div>
                            <span className="rounded-full border border-violet-500/25 bg-violet-500/10 px-2.5 py-0.5 text-xs font-bold text-violet-300">
                                {breedMemoryWall.length} breed
                                {breedMemoryWall.length !== 1 ? 's' : ''} stored
                            </span>
                            <span className="ml-auto text-[10px] text-white/20">
                                Hover a chip for details
                            </span>
                        </div>

                        {breedMemoryWall.length === 0 ? (
                            <p className="py-8 text-center text-sm text-white/20">
                                No breeds in memory yet — submit your first
                                correction to add a breed
                            </p>
                        ) : (
                            <div className="space-y-4">
                                {/* Level legend */}
                                <div className="flex flex-wrap gap-3 rounded-xl border border-white/6 bg-white/[0.02] p-3">
                                    {Object.entries(LEVEL_CFG)
                                        .reverse()
                                        .map(([key, cfg]) => (
                                            <div
                                                key={key}
                                                className="flex items-center gap-1.5"
                                            >
                                                <span
                                                    className="h-2 w-2 rounded-full"
                                                    style={{
                                                        backgroundColor:
                                                            cfg.dot,
                                                    }}
                                                />
                                                <span className="text-[11px] text-white/35">
                                                    {cfg.label}
                                                </span>
                                            </div>
                                        ))}
                                    <span className="ml-auto text-[10px] text-white/15">
                                        expert ≥5× · trained ≥3× · learning ≥2×
                                        · new = 1×
                                    </span>
                                </div>

                                {/* Expert row */}
                                {expertChips.length > 0 && (
                                    <div>
                                        <p className="mb-2 text-[10px] font-bold tracking-widest text-emerald-500/50 uppercase">
                                            Expert ({expertChips.length})
                                        </p>
                                        <div className="flex flex-wrap gap-2">
                                            {expertChips.map((c) => (
                                                <MemoryChipCard
                                                    key={c.breed}
                                                    chip={c}
                                                />
                                            ))}
                                        </div>
                                    </div>
                                )}

                                {/* Trained row */}
                                {trainedChips.length > 0 && (
                                    <div>
                                        <p className="mb-2 text-[10px] font-bold tracking-widest text-green-500/50 uppercase">
                                            Trained ({trainedChips.length})
                                        </p>
                                        <div className="flex flex-wrap gap-2">
                                            {trainedChips.map((c) => (
                                                <MemoryChipCard
                                                    key={c.breed}
                                                    chip={c}
                                                />
                                            ))}
                                        </div>
                                    </div>
                                )}

                                {/* Learning row */}
                                {learningChips.length > 0 && (
                                    <div>
                                        <p className="mb-2 text-[10px] font-bold tracking-widest text-blue-500/50 uppercase">
                                            Learning ({learningChips.length})
                                        </p>
                                        <div className="flex flex-wrap gap-2">
                                            {learningChips.map((c) => (
                                                <MemoryChipCard
                                                    key={c.breed}
                                                    chip={c}
                                                />
                                            ))}
                                        </div>
                                    </div>
                                )}

                                {/* New row */}
                                {newChips.length > 0 && (
                                    <div>
                                        <p className="mb-2 text-[10px] font-bold tracking-widest text-amber-500/50 uppercase">
                                            New ({newChips.length})
                                        </p>
                                        <div className="flex flex-wrap gap-2">
                                            {newChips.map((c) => (
                                                <MemoryChipCard
                                                    key={c.breed}
                                                    chip={c}
                                                />
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}
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
                                            {[
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
                                            ]
                                                .map(([thresh, label, cls]) =>
                                                    r.confidence >=
                                                    (thresh as number) ? (
                                                        <span
                                                            key={
                                                                label as string
                                                            }
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
