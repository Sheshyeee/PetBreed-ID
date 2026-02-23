import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
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
    ArrowRight,
    ArrowUp,
    BarChart3,
    Brain,
    CheckCircle2,
    ChevronRight,
    ClipboardList,
    Database,
    GraduationCap,
    LineChart,
    Minus,
    Target,
    TrendingUp,
} from 'lucide-react';
import { useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
];

type Result = {
    scan_id: string;
    breed: string;
    confidence: number;
};

type BreedLearning = {
    breed: string;
    ai_guess_breed: string;
    ai_guess_confidence: number;
    event_type: 'corrected' | 'boosted' | 'confirmed';
    status_label: string;
    status_color: 'blue' | 'amber' | 'green';
    times_taught: number;
    examples_in_memory: number;
    first_taught_date: string;
    days_since_taught: number;
    latest_taught_date: string;
    // legacy
    examples_learned: number;
    corrections_made: number;
    avg_confidence: number;
    success_rate: number;
    first_learned: string;
    days_learning: number;
    recent_scans: number;
};

type LearningBreakdown = {
    knowledge_base: number;
    memory_usage: number;
    breed_coverage: number;
    avg_corrections_per_day: number;
    recent_activity: number;
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
    lowConfidenceWeeklyTrend?: number;
    memoryCount?: number;
    uniqueBreedsLearned?: number;
    recentCorrectionsCount?: number;
    avgConfidence?: number;
    confidenceTrend?: number;
    memoryHitRate?: number;
    accuracyImprovement?: number;
    breedCoverage?: number;
    accuracyBeforeCorrections?: number;
    accuracyAfterCorrections?: number;
    lastCorrectionCount?: number;
    breedLearningProgress?: BreedLearning[];
    learningBreakdown?: LearningBreakdown;
    learningTimeline?: TimelineDay[];
};

// ─────────────────────────────────────────────────────────────────────────────
// Confidence pill — simple coloured badge showing a % score
// ─────────────────────────────────────────────────────────────────────────────
function ConfidencePill({ value }: { value: number }) {
    const cls =
        value >= 80
            ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300'
            : value >= 60
              ? 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300'
              : value >= 40
                ? 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300'
                : 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300';
    return (
        <span
            className={`inline-flex items-center rounded-full px-2.5 py-1 text-sm font-black ${cls}`}
        >
            {value}%
        </span>
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// TeachingLogCard — one per corrected breed
// ─────────────────────────────────────────────────────────────────────────────
function TeachingLogCard({
    entry,
    rank,
}: {
    entry: BreedLearning;
    rank: number;
}) {
    const isCorrected = entry.event_type === 'corrected';
    const isBoosted = entry.event_type === 'boosted';
    const isConfirmed = entry.event_type === 'confirmed';

    // Accent colours per event type
    const accentBorder = isCorrected
        ? 'border-blue-300  dark:border-blue-700'
        : isBoosted
          ? 'border-amber-300 dark:border-amber-700'
          : 'border-green-300 dark:border-green-700';

    const accentBg = isCorrected
        ? 'bg-blue-50  dark:bg-blue-950/30'
        : isBoosted
          ? 'bg-amber-50 dark:bg-amber-950/30'
          : 'bg-green-50 dark:bg-green-950/30';

    const badgeCls = isCorrected
        ? 'bg-blue-100  text-blue-700  dark:bg-blue-950  dark:text-blue-300'
        : isBoosted
          ? 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300'
          : 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300';

    const icon = isCorrected ? '🔄' : isBoosted ? '📈' : '✅';

    const rankRings: Record<number, string> = {
        1: 'bg-gradient-to-br from-yellow-400 to-orange-500',
        2: 'bg-gradient-to-br from-gray-300  to-gray-400',
        3: 'bg-gradient-to-br from-orange-400 to-orange-600',
    };

    // The "story" headline — written so ANY non-technical person understands
    const headline = isCorrected ? (
        <>
            AI guessed{' '}
            <span className="font-black text-red-500 dark:text-red-400">
                "{entry.ai_guess_breed}"
            </span>{' '}
            — vet corrected to{' '}
            <span className="font-black text-green-600 dark:text-green-400">
                "{entry.breed}"
            </span>
        </>
    ) : isBoosted ? (
        <>
            AI identified{' '}
            <span className="font-black text-blue-600 dark:text-blue-400">
                "{entry.breed}"
            </span>{' '}
            but wasn't sure — vet confirmed it
        </>
    ) : (
        <>
            AI correctly identified{' '}
            <span className="font-black text-green-600 dark:text-green-400">
                "{entry.breed}"
            </span>{' '}
            — vet verified
        </>
    );
    return (
        <div
            className={`relative overflow-hidden rounded-2xl border-2 ${accentBorder} bg-white shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg dark:bg-neutral-900`}
        >
            {/* Coloured top strip */}
            <div className={`px-4 pt-4 pb-3 ${accentBg}`}>
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2.5">
                        {rank <= 3 ? (
                            <div
                                className={`flex h-7 w-7 items-center justify-center rounded-full text-xs font-black text-white shadow ${rankRings[rank]}`}
                            >
                                {rank}
                            </div>
                        ) : (
                            <span className="text-sm font-bold text-gray-400">
                                #{rank}
                            </span>
                        )}
                        <h3 className="text-base font-black text-gray-900 dark:text-white">
                            {entry.breed}
                        </h3>
                    </div>
                    <span
                        className={`rounded-full px-3 py-0.5 text-xs font-bold ${badgeCls}`}
                    >
                        {icon} {entry.status_label}
                    </span>
                </div>
            </div>

            {/* The story row */}
            <div className="px-4 py-3">
                <p className="text-sm leading-snug text-gray-700 dark:text-gray-300">
                    {headline}
                </p>

                {/* Visual: AI confidence → vet action */}
                {isCorrected && (
                    <div className="mt-3 flex items-center gap-2">
                        <div className="flex flex-col items-center">
                            <span className="mb-1 text-[10px] font-bold tracking-wider text-gray-400 uppercase">
                                AI was
                            </span>
                            <ConfidencePill value={entry.ai_guess_confidence} />
                            <span className="mt-1 max-w-[70px] text-center text-[10px] leading-tight text-gray-400">
                                confident it was "{entry.ai_guess_breed}"
                            </span>
                        </div>
                        <div className="flex flex-1 flex-col items-center">
                            <ArrowRight className="h-5 w-5 text-gray-400" />
                            <span className="mt-0.5 text-[9px] text-gray-400">
                                vet corrected
                            </span>
                        </div>
                        <div className="flex flex-col items-center">
                            <span className="mb-1 text-[10px] font-bold tracking-wider text-gray-400 uppercase">
                                Now stored as
                            </span>
                            <span className="inline-flex h-9 items-center rounded-full bg-green-100 px-3 text-sm font-black text-green-700 dark:bg-green-950 dark:text-green-300">
                                ✓ 100%
                            </span>
                            <span className="mt-1 max-w-[70px] text-center text-[10px] leading-tight text-gray-400">
                                "{entry.breed}" in memory
                            </span>
                        </div>
                    </div>
                )}

                {isBoosted && (
                    <div className="mt-3 flex items-center gap-2">
                        <div className="flex flex-col items-center">
                            <span className="mb-1 text-[10px] font-bold tracking-wider text-gray-400 uppercase">
                                AI confidence
                            </span>
                            <ConfidencePill value={entry.ai_guess_confidence} />
                        </div>
                        <div className="flex flex-1 flex-col items-center">
                            <ArrowRight className="h-5 w-5 text-amber-500" />
                            <span className="mt-0.5 text-[9px] text-gray-400">
                                vet confirmed
                            </span>
                        </div>
                        <div className="flex flex-col items-center">
                            <span className="mb-1 text-[10px] font-bold tracking-wider text-gray-400 uppercase">
                                Now stored as
                            </span>
                            <span className="inline-flex h-9 items-center rounded-full bg-green-100 px-3 text-sm font-black text-green-700 dark:bg-green-950 dark:text-green-300">
                                ✓ 100%
                            </span>
                        </div>
                    </div>
                )}

                {isConfirmed && (
                    <div className="mt-3 flex items-center gap-2">
                        <div className="flex flex-col items-center">
                            <span className="mb-1 text-[10px] font-bold tracking-wider text-gray-400 uppercase">
                                AI confidence
                            </span>
                            <ConfidencePill value={entry.ai_guess_confidence} />
                        </div>
                        <div className="flex flex-1 flex-col items-center">
                            <CheckCircle2 className="h-5 w-5 text-green-500" />
                            <span className="mt-0.5 text-[9px] text-gray-400">
                                vet verified
                            </span>
                        </div>
                        <div className="flex flex-col items-center">
                            <span className="mb-1 text-[10px] font-bold tracking-wider text-gray-400 uppercase">
                                Reinforced
                            </span>
                            <span className="inline-flex h-9 items-center rounded-full bg-green-100 px-3 text-sm font-black text-green-700 dark:bg-green-950 dark:text-green-300">
                                ✓ 100%
                            </span>
                        </div>
                    </div>
                )}
            </div>

            {/* Footer */}
            <div className="flex items-center justify-between border-t border-gray-100 px-4 py-3 dark:border-gray-800">
                <span className="text-xs text-gray-500 dark:text-gray-400">
                    🎓 Taught{' '}
                    <strong className="text-gray-800 dark:text-gray-200">
                        {entry.times_taught}×
                    </strong>
                </span>
                <span className="text-xs text-gray-500 dark:text-gray-400">
                    🧠{' '}
                    <strong className="text-gray-800 dark:text-gray-200">
                        {entry.examples_in_memory}
                    </strong>{' '}
                    example{entry.examples_in_memory !== 1 ? 's' : ''} in memory
                </span>
                <span className="text-xs text-gray-500 dark:text-gray-400">
                    📅 {entry.latest_taught_date}
                </span>
            </div>
        </div>
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// Main Dashboard
// ─────────────────────────────────────────────────────────────────────────────
export default function Dashboard() {
    const {
        results,
        correctedBreedCount,
        resultCount,
        pendingReviewCount = 0,
        lowConfidenceCount,
        highConfidenceCount,
        totalScansWeeklyTrend = 0,
        correctedWeeklyTrend = 0,
        highConfidenceWeeklyTrend = 0,
        lowConfidenceWeeklyTrend = 0,
        memoryCount = 0,
        uniqueBreedsLearned = 0,
        recentCorrectionsCount = 0,
        avgConfidence = 0,
        confidenceTrend = 0,
        memoryHitRate = 0,
        accuracyImprovement = 0,
        breedCoverage = 0,
        accuracyBeforeCorrections = 0,
        accuracyAfterCorrections = 0,
        lastCorrectionCount = 0,
        breedLearningProgress = [],
        learningBreakdown = {
            knowledge_base: 0,
            memory_usage: 0,
            breed_coverage: 0,
            avg_corrections_per_day: 0,
            recent_activity: 0,
        },
        learningTimeline = [],
    } = usePage<PageProps>().props;

    const [hoveredDay, setHoveredDay] = useState<number | null>(null);

    const pendingReviewWeeklyTrend = -correctedWeeklyTrend;
    const formatTrend = (t: number) => `${t > 0 ? '+' : ''}${t.toFixed(1)}%`;
    const getTrendIcon = (t: number) =>
        t > 0 ? (
            <ArrowUp className="h-3 w-3 text-green-600" />
        ) : t < 0 ? (
            <ArrowDown className="h-3 w-3 text-red-600" />
        ) : (
            <Minus className="h-3 w-3 text-gray-400" />
        );
    const getTrendColor = (t: number, inv = false) => {
        if (inv)
            return t < 0
                ? 'text-green-600 dark:text-green-400'
                : t > 0
                  ? 'text-red-600 dark:text-red-400'
                  : 'text-gray-600 dark:text-gray-400';
        return t > 0
            ? 'text-green-600 dark:text-green-400'
            : t < 0
              ? 'text-red-600 dark:text-red-400'
              : 'text-gray-600 dark:text-gray-400';
    };

    useEffect(() => {
        const iv = setInterval(() => {
            if (correctedBreedCount % 5 === 0) {
                router.reload({
                    only: [
                        'breedLearningProgress',
                        'memoryCount',
                        'uniqueBreedsLearned',
                        'learningTimeline',
                    ],
                });
            }
        }, 30000);
        return () => clearInterval(iv);
    }, [correctedBreedCount]);

    // Teaching log summary counts
    const correctedCount = breedLearningProgress.filter(
        (b) => b.event_type === 'corrected',
    ).length;
    const boostedCount = breedLearningProgress.filter(
        (b) => b.event_type === 'boosted',
    ).length;
    const confirmedCount = breedLearningProgress.filter(
        (b) => b.event_type === 'confirmed',
    ).length;

    // Timeline
    const maxCorrections = Math.max(
        ...learningTimeline.map((d) => d.corrections),
        1,
    );
    const timelineTotal = learningTimeline.reduce(
        (s, d) => s + d.corrections,
        0,
    );
    const todayEntry = learningTimeline.find((d) => d.is_today);

    const tlStatus = (() => {
        if (!todayEntry) return null;
        if (todayEntry.corrections > 0)
            return {
                text: `✅ ${todayEntry.corrections} correction${todayEntry.corrections > 1 ? 's' : ''} submitted today — the AI is actively learning!`,
                cls: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200',
            };
        const r3 = learningTimeline
            .slice(-3)
            .reduce((s, d) => s + d.corrections, 0);
        if (r3 > 0)
            return {
                text: `🟡 No corrections today yet — ${r3} submitted in the last 3 days. Keep going!`,
                cls: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
            };
        return {
            text: '🔴 No corrections in the last 3 days — review pending scans to keep training the AI.',
            cls: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200',
        };
    })();

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Learning Analytics Dashboard" />

            <div className="flex h-full flex-col gap-6 p-4 md:p-6">
                {/* ── 4-column key metrics ─────────────────────────────── */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {[
                        {
                            label: 'Total Scans',
                            value: resultCount,
                            trend: totalScansWeeklyTrend,
                            sub: 'vs last week',
                            Icon: BarChart3,
                            bg: 'bg-blue-100 dark:bg-blue-950',
                            ic: 'text-blue-600 dark:text-blue-400',
                        },
                        {
                            label: 'Corrections Made',
                            value: correctedBreedCount,
                            trend: correctedWeeklyTrend,
                            sub: 'Teaching the system',
                            Icon: GraduationCap,
                            bg: 'bg-purple-100 dark:bg-purple-950',
                            ic: 'text-purple-600 dark:text-purple-400',
                        },
                        {
                            label: 'High Confidence',
                            value: highConfidenceCount,
                            trend: highConfidenceWeeklyTrend,
                            sub: '≥80% confidence',
                            Icon: CheckCircle2,
                            bg: 'bg-green-100 dark:bg-green-950',
                            ic: 'text-green-600 dark:text-green-400',
                        },
                        {
                            label: 'Pending Review',
                            value: pendingReviewCount,
                            trend: pendingReviewWeeklyTrend,
                            sub: 'Awaiting correction',
                            Icon: ClipboardList,
                            bg: 'bg-amber-100 dark:bg-amber-950',
                            ic: 'text-amber-600 dark:text-amber-400',
                            inv: true,
                        },
                    ].map(({ label, value, trend, sub, Icon, bg, ic, inv }) => (
                        <Card key={label} className="p-5 dark:bg-neutral-900">
                            <div className="flex items-start justify-between">
                                <div className="flex-1">
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        {label}
                                    </p>
                                    <div className="mt-2 flex items-baseline gap-2">
                                        <p className="text-3xl font-bold text-gray-900 dark:text-white">
                                            {value}
                                        </p>
                                        <div
                                            className={`flex items-center gap-0.5 text-sm font-semibold ${getTrendColor(trend, inv)}`}
                                        >
                                            {getTrendIcon(trend)}
                                            <span>{formatTrend(trend)}</span>
                                        </div>
                                    </div>
                                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {sub}
                                    </p>
                                </div>
                                <div
                                    className={`flex h-12 w-12 items-center justify-center rounded-lg ${bg}`}
                                >
                                    <Icon className={`h-6 w-6 ${ic}`} />
                                </div>
                            </div>
                        </Card>
                    ))}
                </div>

                {/* ── 3-column learning impact ──────────────────────────── */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <Card className="bg-gradient-to-br from-green-50 to-emerald-50 p-5 dark:from-green-950/30 dark:to-emerald-950/30">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-green-700 dark:text-green-400">
                                    Learning Progress Score
                                </p>
                                <div className="mt-2 flex items-baseline gap-2">
                                    <p className="text-4xl font-bold text-green-900 dark:text-green-300">
                                        {accuracyImprovement.toFixed(0)}
                                    </p>
                                    <span className="text-lg text-green-700 dark:text-green-400">
                                        /100
                                    </span>
                                </div>
                                <p className="mt-1 text-xs text-green-600 dark:text-green-400">
                                    {learningBreakdown.knowledge_base}{' '}
                                    corrections ·{' '}
                                    {learningBreakdown.breed_coverage} breeds
                                </p>
                            </div>
                            <div className="flex h-14 w-14 items-center justify-center rounded-full bg-green-200 dark:bg-green-900">
                                <Target className="h-7 w-7 text-green-700 dark:text-green-300" />
                            </div>
                        </div>
                        <div className="mt-4 rounded-lg bg-white/50 p-3 dark:bg-black/20">
                            <p className="text-xs font-medium text-green-800 dark:text-green-200">
                                💡 <strong>Learning Health:</strong> Score of{' '}
                                {accuracyImprovement.toFixed(0)} shows{' '}
                                {accuracyImprovement >= 80
                                    ? 'excellent'
                                    : accuracyImprovement >= 60
                                      ? 'good'
                                      : accuracyImprovement >= 40
                                        ? 'fair'
                                        : 'developing'}{' '}
                                progress!
                            </p>
                        </div>
                    </Card>

                    <Card className="bg-gradient-to-br from-blue-50 to-indigo-50 p-5 dark:from-blue-950/30 dark:to-indigo-950/30">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-blue-700 dark:text-blue-400">
                                    Avg Confidence Score
                                </p>
                                <div className="mt-2 flex items-baseline gap-2">
                                    <p className="text-4xl font-bold text-blue-900 dark:text-blue-300">
                                        {avgConfidence.toFixed(1)}%
                                    </p>
                                    <div
                                        className={`flex items-center gap-0.5 text-lg font-bold ${getTrendColor(confidenceTrend)}`}
                                    >
                                        {getTrendIcon(confidenceTrend)}
                                        <span className="text-base">
                                            {confidenceTrend.toFixed(1)}%
                                        </span>
                                    </div>
                                </div>
                                <p className="mt-1 text-xs text-blue-600 dark:text-blue-400">
                                    This week vs last week
                                </p>
                            </div>
                            <div className="flex h-14 w-14 items-center justify-center rounded-full bg-blue-200 dark:bg-blue-900">
                                <LineChart className="h-7 w-7 text-blue-700 dark:text-blue-300" />
                            </div>
                        </div>
                        <div className="mt-4 rounded-lg bg-white/50 p-3 dark:bg-black/20">
                            <p className="text-xs font-medium text-blue-800 dark:text-blue-200">
                                📈 <strong>Trend:</strong>{' '}
                                {confidenceTrend > 0
                                    ? 'Confidence is rising! Learning is working.'
                                    : confidenceTrend < 0
                                      ? 'Confidence dipped — keep correcting!'
                                      : 'Confidence is stable.'}
                            </p>
                        </div>
                    </Card>

                    <Card className="bg-gradient-to-br from-purple-50 to-pink-50 p-5 dark:from-purple-950/30 dark:to-pink-950/30">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-purple-700 dark:text-purple-400">
                                    Memory Usage Rate
                                </p>
                                <div className="mt-2 flex items-baseline gap-2">
                                    <p className="text-4xl font-bold text-purple-900 dark:text-purple-300">
                                        {memoryHitRate.toFixed(1)}%
                                    </p>
                                </div>
                                <p className="mt-1 text-xs text-purple-600 dark:text-purple-400">
                                    {memoryCount > 0
                                        ? `${memoryCount} patterns stored`
                                        : 'No patterns yet'}
                                </p>
                            </div>
                            <div className="flex h-14 w-14 items-center justify-center rounded-full bg-purple-200 dark:bg-purple-900">
                                <Database className="h-7 w-7 text-purple-700 dark:text-purple-300" />
                            </div>
                        </div>
                        <div className="mt-4 rounded-lg bg-white/50 p-3 dark:bg-black/20">
                            <p className="text-xs font-medium text-purple-800 dark:text-purple-200">
                                🧠 <strong>Memory:</strong>{' '}
                                {memoryHitRate > 50
                                    ? 'Memory is actively helping predictions!'
                                    : 'Add more corrections to boost memory usage.'}
                            </p>
                        </div>
                    </Card>
                </div>

                {/* ── Learning Pulse — 10-day bar chart ────────────────── */}
                <Card className="overflow-hidden dark:bg-neutral-900">
                    <div className="border-b border-gray-200 bg-gradient-to-r from-indigo-50 to-blue-50 p-5 dark:border-gray-800 dark:from-indigo-950/50 dark:to-blue-950/50">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-center gap-3">
                                <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-600 to-blue-600 shadow-md">
                                    <Activity className="h-5 w-5 text-white" />
                                </div>
                                <div>
                                    <h2 className="text-lg font-bold text-gray-900 dark:text-white">
                                        Learning Pulse
                                    </h2>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                        Daily correction activity — last 10 days
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <Badge className="bg-indigo-100 px-3 py-1 text-xs text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                                    {timelineTotal} total in 10 days
                                </Badge>
                                {todayEntry && todayEntry.corrections > 0 && (
                                    <Badge className="bg-green-100 px-3 py-1 text-xs text-green-700 dark:bg-green-950 dark:text-green-300">
                                        🟢 Active today
                                    </Badge>
                                )}
                            </div>
                        </div>
                    </div>
                    <div className="p-5">
                        {learningTimeline.length === 0 ? (
                            <div className="flex h-40 items-center justify-center text-sm text-gray-400">
                                No data yet. Start reviewing scans to see
                                activity here.
                            </div>
                        ) : (
                            <>
                                <div className="relative">
                                    <div className="pointer-events-none absolute inset-x-0 top-0 flex h-40 flex-col justify-between">
                                        {[0, 1, 2].map((i) => (
                                            <div
                                                key={i}
                                                className="w-full border-t border-dashed border-gray-100 dark:border-gray-800"
                                            />
                                        ))}
                                    </div>
                                    <div className="flex h-40 items-end gap-1.5 sm:gap-2">
                                        {learningTimeline.map((d, idx) => {
                                            const h =
                                                d.corrections > 0
                                                    ? Math.max(
                                                          8,
                                                          (d.corrections /
                                                              maxCorrections) *
                                                              100,
                                                      )
                                                    : 4;
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
                                                            className="absolute bottom-full z-20 mb-2 w-44 rounded-xl border border-gray-200 bg-white p-3 shadow-xl dark:border-gray-700 dark:bg-gray-900"
                                                            style={{
                                                                left: '50%',
                                                                transform:
                                                                    'translateX(-50%)',
                                                            }}
                                                        >
                                                            <p className="mb-1.5 text-xs font-bold text-gray-900 dark:text-white">
                                                                {d.is_today
                                                                    ? '📅 Today'
                                                                    : d.day}{' '}
                                                                <span className="font-normal text-gray-400">
                                                                    ({d.date})
                                                                </span>
                                                            </p>
                                                            <div className="space-y-1 text-xs">
                                                                <div className="flex justify-between">
                                                                    <span className="text-indigo-600">
                                                                        📝
                                                                        Corrections
                                                                    </span>
                                                                    <span className="font-bold">
                                                                        {
                                                                            d.corrections
                                                                        }
                                                                    </span>
                                                                </div>
                                                                <div className="flex justify-between">
                                                                    <span className="text-blue-600">
                                                                        🔍 Scans
                                                                    </span>
                                                                    <span className="font-bold">
                                                                        {
                                                                            d.total_scans
                                                                        }
                                                                    </span>
                                                                </div>
                                                                <div className="flex justify-between">
                                                                    <span className="text-green-600">
                                                                        ✅ High
                                                                        conf.
                                                                    </span>
                                                                    <span className="font-bold">
                                                                        {
                                                                            d.high_conf_rate
                                                                        }
                                                                        %
                                                                    </span>
                                                                </div>
                                                                <div className="mt-1 flex justify-between border-t pt-1 dark:border-gray-700">
                                                                    <span className="text-gray-500">
                                                                        Total
                                                                        memory
                                                                    </span>
                                                                    <span className="font-bold text-purple-600">
                                                                        {
                                                                            d.total_corrections_to_date
                                                                        }
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <div className="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-white dark:border-t-gray-900" />
                                                        </div>
                                                    )}
                                                    {d.corrections > 0 && (
                                                        <span
                                                            className={`mb-1 text-xs font-bold ${hov ? 'opacity-0' : 'opacity-100'} ${d.is_today ? 'text-indigo-700 dark:text-indigo-300' : 'text-gray-600 dark:text-gray-400'}`}
                                                        >
                                                            {d.corrections}
                                                        </span>
                                                    )}
                                                    <div
                                                        className={`w-full rounded-t-md transition-all duration-300 ${
                                                            d.is_today
                                                                ? 'bg-gradient-to-t from-indigo-600 to-indigo-400 shadow-md'
                                                                : d.corrections >
                                                                    0
                                                                  ? hov
                                                                      ? 'bg-gradient-to-t from-blue-500 to-blue-300'
                                                                      : 'bg-gradient-to-t from-blue-400 to-blue-200 dark:from-blue-700 dark:to-blue-500'
                                                                  : 'bg-gray-100 dark:bg-gray-800'
                                                        }`}
                                                        style={{
                                                            height: `${h}%`,
                                                            minHeight: '4px',
                                                        }}
                                                    />
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                                <div className="mt-2 flex gap-1.5 sm:gap-2">
                                    {learningTimeline.map((d) => (
                                        <div
                                            key={d.date}
                                            className="flex flex-1 justify-center"
                                        >
                                            <span
                                                className={`text-center leading-tight ${d.is_today ? 'font-bold text-indigo-700 dark:text-indigo-300' : 'text-gray-400 dark:text-gray-500'}`}
                                                style={{ fontSize: '0.62rem' }}
                                            >
                                                {d.is_today ? 'Today' : d.day}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                                {tlStatus && (
                                    <div
                                        className={`mt-4 rounded-lg px-4 py-2.5 text-xs font-medium ${tlStatus.cls}`}
                                    >
                                        {tlStatus.text}
                                    </div>
                                )}
                                <div className="mt-4 flex flex-wrap items-center gap-4 border-t border-gray-100 pt-4 text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                    <span className="flex items-center gap-1.5">
                                        <span className="inline-block h-3 w-3 rounded bg-indigo-500" />
                                        Today
                                    </span>
                                    <span className="flex items-center gap-1.5">
                                        <span className="inline-block h-3 w-3 rounded bg-blue-400" />
                                        Corrections
                                    </span>
                                    <span className="flex items-center gap-1.5">
                                        <span className="inline-block h-3 w-3 rounded bg-gray-200 dark:bg-gray-700" />
                                        No activity
                                    </span>
                                    <span className="ml-auto">
                                        Hover bars for details
                                    </span>
                                </div>
                            </>
                        )}
                    </div>
                </Card>

                {/* ── Vet Teaching Log ─────────────────────────────────── */}
                {breedLearningProgress && breedLearningProgress.length > 0 && (
                    <div>
                        {/* Header */}
                        <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div className="flex items-center gap-3">
                                <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-teal-600 to-emerald-600 shadow-md">
                                    <Brain className="h-5 w-5 text-white" />
                                </div>
                                <div>
                                    <h2 className="text-xl font-bold text-gray-900 dark:text-white">
                                        How the Vet Is Training the AI
                                    </h2>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                        Every correction the vet makes is
                                        permanently stored in the AI's memory
                                    </p>
                                </div>
                            </div>
                            <Badge className="w-fit bg-teal-100 px-4 py-1.5 text-sm font-bold text-teal-700 dark:bg-teal-950 dark:text-teal-300">
                                {breedLearningProgress.length} Breed
                                {breedLearningProgress.length !== 1 ? 's' : ''}{' '}
                                in Memory
                            </Badge>
                        </div>

                        {/* Legend — what each colour means */}
                        <div className="mb-4 grid grid-cols-1 gap-2 sm:grid-cols-3">
                            <div className="flex items-start gap-2.5 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 dark:border-blue-900 dark:bg-blue-950/40">
                                <span className="mt-0.5 text-lg leading-none">
                                    🔄
                                </span>
                                <div>
                                    <p className="text-xs font-bold text-blue-800 dark:text-blue-200">
                                        AI Corrected
                                    </p>
                                    <p className="text-xs text-blue-700 dark:text-blue-300">
                                        AI guessed the wrong breed — vet fixed
                                        it
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-900 dark:bg-amber-950/40">
                                <span className="mt-0.5 text-lg leading-none">
                                    📈
                                </span>
                                <div>
                                    <p className="text-xs font-bold text-amber-800 dark:text-amber-200">
                                        Confidence Boosted
                                    </p>
                                    <p className="text-xs text-amber-700 dark:text-amber-300">
                                        AI was unsure — vet confirmed the breed
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-start gap-2.5 rounded-xl border border-green-200 bg-green-50 px-4 py-3 dark:border-green-900 dark:bg-green-950/40">
                                <span className="mt-0.5 text-lg leading-none">
                                    ✅
                                </span>
                                <div>
                                    <p className="text-xs font-bold text-green-800 dark:text-green-200">
                                        Verified by Vet
                                    </p>
                                    <p className="text-xs text-green-700 dark:text-green-300">
                                        AI was correct — vet verified and
                                        reinforced it
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Cards */}
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            {breedLearningProgress.map((b, idx) => (
                                <TeachingLogCard
                                    key={b.breed}
                                    entry={b}
                                    rank={idx + 1}
                                />
                            ))}
                        </div>

                        {/* Summary strip */}
                        <div className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div className="flex items-center gap-3 rounded-xl bg-blue-50 p-4 dark:bg-blue-950/30">
                                <span className="text-2xl">🔄</span>
                                <div>
                                    <p className="text-2xl font-black text-blue-700 dark:text-blue-300">
                                        {correctedCount}
                                    </p>
                                    <p className="text-xs text-blue-600 dark:text-blue-400">
                                        Wrong guesses corrected
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3 rounded-xl bg-amber-50 p-4 dark:bg-amber-950/30">
                                <span className="text-2xl">📈</span>
                                <div>
                                    <p className="text-2xl font-black text-amber-700 dark:text-amber-300">
                                        {boostedCount}
                                    </p>
                                    <p className="text-xs text-amber-600 dark:text-amber-400">
                                        Uncertain predictions confirmed
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3 rounded-xl bg-green-50 p-4 dark:bg-green-950/30">
                                <TrendingUp className="h-8 w-8 shrink-0 text-green-600 dark:text-green-400" />
                                <div>
                                    <p className="text-2xl font-black text-green-700 dark:text-green-300">
                                        {confirmedCount}
                                    </p>
                                    <p className="text-xs text-green-600 dark:text-green-400">
                                        Correct predictions verified
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* ── Recent Scans ─────────────────────────────────────── */}
                <Card className="dark:bg-neutral-900">
                    <div className="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                        <div className="flex items-center justify-between">
                            <div>
                                <h2 className="text-lg font-bold text-gray-900 dark:text-white">
                                    Recent Scans
                                </h2>
                                <p className="text-sm text-gray-600 dark:text-gray-400">
                                    Latest predictions from the system
                                </p>
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    router.visit('/model/scan-results')
                                }
                            >
                                View All{' '}
                                <ChevronRight className="ml-1 h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                    <div className="overflow-x-auto px-5">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Scan ID</TableHead>
                                    <TableHead>Breed</TableHead>
                                    <TableHead>Confidence</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {results?.map((result) => (
                                    <TableRow key={result.scan_id}>
                                        <TableCell className="font-mono text-xs">
                                            {result.scan_id}
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {result.breed}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                <Progress
                                                    value={result.confidence}
                                                    className="h-2 w-20"
                                                />
                                                <span className="text-sm font-semibold">
                                                    {result.confidence}%
                                                </span>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {result.confidence >= 80 ? (
                                                <Badge className="bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-400">
                                                    High
                                                </Badge>
                                            ) : result.confidence >= 60 ? (
                                                <Badge className="bg-yellow-100 text-yellow-700 dark:bg-yellow-950 dark:text-yellow-400">
                                                    Medium
                                                </Badge>
                                            ) : result.confidence >= 40 ? (
                                                <Badge className="bg-orange-100 text-orange-700 dark:bg-orange-950 dark:text-orange-400">
                                                    Low
                                                </Badge>
                                            ) : (
                                                <Badge className="bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-400">
                                                    Very Low
                                                </Badge>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </Card>
            </div>
        </AppLayout>
    );
}
