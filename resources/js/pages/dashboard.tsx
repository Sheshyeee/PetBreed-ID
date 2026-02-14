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
    AlertCircle,
    ArrowDown,
    ArrowUp,
    BarChart3,
    Brain,
    CheckCircle2,
    ChevronRight,
    Database,
    GraduationCap,
    LineChart,
    Minus,
    Sparkles,
    Target,
    TrendingUp,
    Zap,
} from 'lucide-react';
import { useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

type Result = {
    scan_id: string;
    breed: string;
    confidence: number;
};

type BreedLearning = {
    breed: string;
    examples_learned: number;
    corrections_made: number;
    avg_confidence: number;
    success_rate: number;
    first_learned: string;
    days_learning: number;
    recent_scans: number;
};

type PageProps = {
    results?: Result[];
    correctedBreedCount: number;
    resultCount: number;
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
};

export default function Dashboard() {
    const {
        results,
        correctedBreedCount,
        resultCount,
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
    } = usePage<PageProps>().props;

    const [isUpdating, setIsUpdating] = useState(false);
    const [showLearningInsights, setShowLearningInsights] = useState(true);

    // Format trend with sign
    const formatTrend = (trend: number) => {
        const sign = trend > 0 ? '+' : '';
        return `${sign}${trend.toFixed(1)}%`;
    };

    // Get trend icon
    const getTrendIcon = (trend: number) => {
        if (trend > 0) return <ArrowUp className="h-3 w-3 text-green-600" />;
        if (trend < 0) return <ArrowDown className="h-3 w-3 text-red-600" />;
        return <Minus className="h-3 w-3 text-gray-400" />;
    };

    // Get trend color
    const getTrendColor = (trend: number, inverse = false) => {
        if (inverse) {
            if (trend < 0) return 'text-green-600 dark:text-green-400';
            if (trend > 0) return 'text-red-600 dark:text-red-400';
        } else {
            if (trend > 0) return 'text-green-600 dark:text-green-400';
            if (trend < 0) return 'text-red-600 dark:text-red-400';
        }
        return 'text-gray-600 dark:text-gray-400';
    };

    // Calculate learning health score
    const calculateLearningHealth = () => {
        // Memory size score (0-25 points)
        const memoryScore = Math.min(25, (memoryCount / 100) * 25);

        // Breed diversity score (0-20 points)
        const diversityScore = Math.min(20, (uniqueBreedsLearned / 30) * 20);

        // Accuracy improvement score (0-30 points)
        const accuracyScore = Math.max(
            0,
            Math.min(30, accuracyImprovement * 3),
        );

        // Confidence trend score (0-25 points)
        const confidenceScore = Math.max(
            0,
            Math.min(25, (confidenceTrend + 10) * 2.5),
        );

        return Math.min(
            100,
            memoryScore + diversityScore + accuracyScore + confidenceScore,
        );
    };

    const learningHealth = calculateLearningHealth();

    // Get learning status
    const getLearningStatus = () => {
        if (memoryCount === 0) {
            return {
                status: 'Not Started',
                color: 'text-gray-600',
                bgColor: 'bg-gray-100 dark:bg-gray-800',
                icon: AlertCircle,
                description: 'Start correcting to enable learning',
            };
        }
        if (learningHealth >= 80) {
            return {
                status: 'Excellent',
                color: 'text-green-600',
                bgColor: 'bg-green-100 dark:bg-green-950',
                icon: CheckCircle2,
                description: 'System learning optimally',
            };
        }
        if (learningHealth >= 60) {
            return {
                status: 'Good',
                color: 'text-blue-600',
                bgColor: 'bg-blue-100 dark:bg-blue-950',
                icon: TrendingUp,
                description: 'Strong learning progress',
            };
        }
        if (learningHealth >= 40) {
            return {
                status: 'Fair',
                color: 'text-yellow-600',
                bgColor: 'bg-yellow-100 dark:bg-yellow-950',
                icon: Activity,
                description: 'Learning in progress',
            };
        }
        return {
            status: 'Poor',
            color: 'text-orange-600',
            bgColor: 'bg-orange-100 dark:bg-orange-950',
            icon: AlertCircle,
            description: 'Needs more corrections',
        };
    };

    const learningStatus = getLearningStatus();
    const StatusIcon = learningStatus.icon;

    // Auto-refresh every 30 seconds
    useEffect(() => {
        const interval = setInterval(() => {
            if (correctedBreedCount % 5 === 0) {
                router.reload({
                    only: [
                        'breedLearningProgress',
                        'memoryCount',
                        'uniqueBreedsLearned',
                    ],
                });
            }
        }, 30000);

        return () => clearInterval(interval);
    }, [correctedBreedCount]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Learning Analytics Dashboard" />

            <div className="flex h-full flex-col gap-6 p-4 md:p-6">
                {/* Header Section */}

                {/* Learning Health Score - Hero Section */}
                <Card className="overflow-hidden border-2 border-blue-200 bg-gradient-to-br from-blue-50 to-indigo-50 dark:border-blue-900 dark:from-blue-950/50 dark:to-indigo-950/50">
                    <div className="p-6">
                        <div className="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                            {/* Left: Score */}
                            <div className="flex items-center gap-6">
                                <div className="flex h-24 w-24 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 shadow-lg">
                                    <div className="text-center">
                                        <div className="text-3xl font-bold text-white">
                                            {learningHealth.toFixed(0)}
                                        </div>
                                        <div className="text-xs text-blue-100">
                                            Health
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <h2 className="text-2xl font-bold text-gray-900 dark:text-white">
                                        Learning Health Score
                                    </h2>
                                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        {learningStatus.description}
                                    </p>
                                    <div className="mt-3 flex items-center gap-4 text-sm">
                                        <div className="flex items-center gap-1">
                                            <Brain className="h-4 w-4 text-blue-600" />
                                            <span className="font-semibold text-gray-700 dark:text-gray-300">
                                                {memoryCount} examples learned
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-1">
                                            <GraduationCap className="h-4 w-4 text-indigo-600" />
                                            <span className="font-semibold text-gray-700 dark:text-gray-300">
                                                {uniqueBreedsLearned} breeds
                                                mastered
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Right: Progress Bar */}
                            <div className="w-full md:w-64">
                                <div className="space-y-2">
                                    <div className="flex justify-between text-sm font-medium">
                                        <span className="text-gray-700 dark:text-gray-300">
                                            Learning Progress
                                        </span>
                                        <span className="text-gray-900 dark:text-white">
                                            {learningHealth.toFixed(0)}%
                                        </span>
                                    </div>
                                    <Progress
                                        value={learningHealth}
                                        className="h-3 [&>div]:bg-gradient-to-r [&>div]:from-blue-600 [&>div]:to-indigo-600"
                                    />
                                    <p className="text-xs text-gray-600 dark:text-gray-400">
                                        {learningHealth < 40
                                            ? 'Keep correcting to improve learning'
                                            : learningHealth < 70
                                              ? 'Good progress! Continue teaching'
                                              : 'Excellent! System is well-trained'}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </Card>

                {/* Key Metrics - 4 Column Grid */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {/* Total Scans */}
                    <Card className="p-5 dark:bg-neutral-900">
                        <div className="flex items-start justify-between">
                            <div className="flex-1">
                                <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                    Total Scans
                                </p>
                                <div className="mt-2 flex items-baseline gap-2">
                                    <p className="text-3xl font-bold text-gray-900 dark:text-white">
                                        {resultCount}
                                    </p>
                                    <div
                                        className={`flex items-center gap-0.5 text-sm font-semibold ${getTrendColor(totalScansWeeklyTrend)}`}
                                    >
                                        {getTrendIcon(totalScansWeeklyTrend)}
                                        <span>
                                            {formatTrend(totalScansWeeklyTrend)}
                                        </span>
                                    </div>
                                </div>
                                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    vs last week
                                </p>
                            </div>
                            <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-950">
                                <BarChart3 className="h-6 w-6 text-blue-600 dark:text-blue-400" />
                            </div>
                        </div>
                    </Card>

                    {/* Corrections Made */}
                    <Card className="p-5 dark:bg-neutral-900">
                        <div className="flex items-start justify-between">
                            <div className="flex-1">
                                <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                    Corrections Made
                                </p>
                                <div className="mt-2 flex items-baseline gap-2">
                                    <p className="text-3xl font-bold text-gray-900 dark:text-white">
                                        {correctedBreedCount}
                                    </p>
                                    <div
                                        className={`flex items-center gap-0.5 text-sm font-semibold ${getTrendColor(correctedWeeklyTrend)}`}
                                    >
                                        {getTrendIcon(correctedWeeklyTrend)}
                                        <span>
                                            {formatTrend(correctedWeeklyTrend)}
                                        </span>
                                    </div>
                                </div>
                                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Teaching the AI
                                </p>
                            </div>
                            <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-950">
                                <GraduationCap className="h-6 w-6 text-purple-600 dark:text-purple-400" />
                            </div>
                        </div>
                    </Card>

                    {/* High Confidence */}
                    <Card className="p-5 dark:bg-neutral-900">
                        <div className="flex items-start justify-between">
                            <div className="flex-1">
                                <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                    High Confidence
                                </p>
                                <div className="mt-2 flex items-baseline gap-2">
                                    <p className="text-3xl font-bold text-gray-900 dark:text-white">
                                        {highConfidenceCount}
                                    </p>
                                    <div
                                        className={`flex items-center gap-0.5 text-sm font-semibold ${getTrendColor(highConfidenceWeeklyTrend)}`}
                                    >
                                        {getTrendIcon(
                                            highConfidenceWeeklyTrend,
                                        )}
                                        <span>
                                            {formatTrend(
                                                highConfidenceWeeklyTrend,
                                            )}
                                        </span>
                                    </div>
                                </div>
                                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    ≥80% confidence
                                </p>
                            </div>
                            <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-green-100 dark:bg-green-950">
                                <CheckCircle2 className="h-6 w-6 text-green-600 dark:text-green-400" />
                            </div>
                        </div>
                    </Card>

                    {/* Low Confidence */}
                    <Card className="p-5 dark:bg-neutral-900">
                        <div className="flex items-start justify-between">
                            <div className="flex-1">
                                <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                    Low Confidence
                                </p>
                                <div className="mt-2 flex items-baseline gap-2">
                                    <p className="text-3xl font-bold text-gray-900 dark:text-white">
                                        {lowConfidenceCount}
                                    </p>
                                    <div
                                        className={`flex items-center gap-0.5 text-sm font-semibold ${getTrendColor(lowConfidenceWeeklyTrend, true)}`}
                                    >
                                        {getTrendIcon(lowConfidenceWeeklyTrend)}
                                        <span>
                                            {formatTrend(
                                                lowConfidenceWeeklyTrend,
                                            )}
                                        </span>
                                    </div>
                                </div>
                                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    ≤40% confidence
                                </p>
                            </div>
                            <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-orange-100 dark:bg-orange-950">
                                <AlertCircle className="h-6 w-6 text-orange-600 dark:text-orange-400" />
                            </div>
                        </div>
                    </Card>
                </div>

                {/* Learning Impact Metrics - 3 Column */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    {/* Accuracy Improvement */}
                    <Card className="bg-gradient-to-br from-green-50 to-emerald-50 p-5 dark:from-green-950/30 dark:to-emerald-950/30">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-green-700 dark:text-green-400">
                                    Accuracy Improvement
                                </p>
                                <div className="mt-2 flex items-baseline gap-2">
                                    <p className="text-4xl font-bold text-green-900 dark:text-green-300">
                                        {accuracyImprovement >= 0 ? '+' : ''}
                                        {accuracyImprovement.toFixed(1)}%
                                    </p>
                                </div>
                                <p className="mt-1 text-xs text-green-600 dark:text-green-400">
                                    Before:{' '}
                                    {accuracyBeforeCorrections.toFixed(1)}% →
                                    After: {accuracyAfterCorrections.toFixed(1)}
                                    %
                                </p>
                            </div>
                            <div className="flex h-14 w-14 items-center justify-center rounded-full bg-green-200 dark:bg-green-900">
                                <Target className="h-7 w-7 text-green-700 dark:text-green-300" />
                            </div>
                        </div>
                        <div className="mt-4 rounded-lg bg-white/50 p-3 dark:bg-black/20">
                            <p className="text-xs font-medium text-green-800 dark:text-green-200">
                                💡 <strong>Real Impact:</strong> The system now
                                makes {accuracyImprovement.toFixed(1)}% more
                                high-confidence predictions since you started
                                correcting!
                            </p>
                        </div>
                    </Card>

                    {/* Avg Confidence Trend */}
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
                                      ? 'Confidence dipped this week - keep correcting!'
                                      : 'Confidence is stable.'}
                            </p>
                        </div>
                    </Card>

                    {/* Memory Hit Rate */}
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

                {/* Breed Learning Progress Table */}
                {breedLearningProgress && breedLearningProgress.length > 0 && (
                    <Card className="overflow-hidden dark:bg-neutral-900">
                        <div className="border-b border-gray-200 bg-gradient-to-r from-indigo-50 to-purple-50 p-6 dark:border-gray-800 dark:from-indigo-950/50 dark:to-purple-950/50">
                            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div className="flex items-center gap-4">
                                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-600 to-purple-600 shadow-lg">
                                        <Sparkles className="h-6 w-6 text-white" />
                                    </div>
                                    <div>
                                        <h2 className="text-xl font-bold text-gray-900 dark:text-white">
                                            Breed Mastery Levels
                                        </h2>
                                        <p className="text-sm text-gray-600 dark:text-gray-400">
                                            Track AI learning progress for each
                                            breed
                                        </p>
                                    </div>
                                </div>
                                <Badge className="bg-gradient-to-r from-indigo-600 to-purple-600 px-4 py-1.5 text-white">
                                    {breedLearningProgress.length} Breeds
                                    Learned
                                </Badge>
                            </div>
                        </div>

                        <div className="p-6">
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow className="border-b-2 border-gray-200 dark:border-gray-800">
                                            <TableHead className="font-bold">
                                                Rank
                                            </TableHead>
                                            <TableHead className="font-bold">
                                                Breed Name
                                            </TableHead>
                                            <TableHead className="text-center font-bold">
                                                AI Memory
                                            </TableHead>
                                            <TableHead className="text-center font-bold">
                                                Your Teaching
                                            </TableHead>
                                            <TableHead className="font-bold">
                                                Success Rate
                                            </TableHead>
                                            <TableHead className="text-center font-bold">
                                                Avg Confidence
                                            </TableHead>
                                            <TableHead className="font-bold">
                                                Learning Duration
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {breedLearningProgress.map(
                                            (breed, idx) => {
                                                const masteryLevel =
                                                    breed.success_rate >= 90
                                                        ? 'Master'
                                                        : breed.success_rate >=
                                                            75
                                                          ? 'Expert'
                                                          : breed.success_rate >=
                                                              60
                                                            ? 'Proficient'
                                                            : 'Learning';
                                                const masteryColor =
                                                    breed.success_rate >= 90
                                                        ? 'text-purple-600 bg-purple-100 dark:bg-purple-950 dark:text-purple-300'
                                                        : breed.success_rate >=
                                                            75
                                                          ? 'text-blue-600 bg-blue-100 dark:bg-blue-950 dark:text-blue-300'
                                                          : breed.success_rate >=
                                                              60
                                                            ? 'text-green-600 bg-green-100 dark:bg-green-950 dark:text-green-300'
                                                            : 'text-orange-600 bg-orange-100 dark:bg-orange-950 dark:text-orange-300';

                                                return (
                                                    <TableRow
                                                        key={breed.breed}
                                                        className="hover:bg-gray-50 dark:hover:bg-gray-900/50"
                                                    >
                                                        <TableCell>
                                                            <div className="flex items-center gap-2">
                                                                {idx === 0 && (
                                                                    <div className="flex h-6 w-6 items-center justify-center rounded-full bg-gradient-to-br from-yellow-400 to-orange-500">
                                                                        <span className="text-xs font-bold text-white">
                                                                            1
                                                                        </span>
                                                                    </div>
                                                                )}
                                                                {idx === 1 && (
                                                                    <div className="flex h-6 w-6 items-center justify-center rounded-full bg-gradient-to-br from-gray-300 to-gray-400">
                                                                        <span className="text-xs font-bold text-white">
                                                                            2
                                                                        </span>
                                                                    </div>
                                                                )}
                                                                {idx === 2 && (
                                                                    <div className="flex h-6 w-6 items-center justify-center rounded-full bg-gradient-to-br from-orange-400 to-orange-600">
                                                                        <span className="text-xs font-bold text-white">
                                                                            3
                                                                        </span>
                                                                    </div>
                                                                )}
                                                                {idx > 2 && (
                                                                    <span className="text-sm font-semibold text-gray-500">
                                                                        #
                                                                        {idx +
                                                                            1}
                                                                    </span>
                                                                )}
                                                            </div>
                                                        </TableCell>
                                                        <TableCell>
                                                            <div className="flex items-center gap-2">
                                                                <span className="font-semibold text-gray-900 dark:text-white">
                                                                    {
                                                                        breed.breed
                                                                    }
                                                                </span>
                                                                <Badge
                                                                    className={`text-xs ${masteryColor}`}
                                                                >
                                                                    {
                                                                        masteryLevel
                                                                    }
                                                                </Badge>
                                                            </div>
                                                        </TableCell>
                                                        <TableCell className="text-center">
                                                            <Badge
                                                                variant="outline"
                                                                className="font-mono font-bold"
                                                            >
                                                                {
                                                                    breed.examples_learned
                                                                }{' '}
                                                                examples
                                                            </Badge>
                                                        </TableCell>
                                                        <TableCell className="text-center">
                                                            <Badge
                                                                variant="outline"
                                                                className="font-mono font-bold"
                                                            >
                                                                {
                                                                    breed.corrections_made
                                                                }{' '}
                                                                corrections
                                                            </Badge>
                                                        </TableCell>
                                                        <TableCell>
                                                            <div className="flex items-center gap-3">
                                                                <Progress
                                                                    value={
                                                                        breed.success_rate
                                                                    }
                                                                    className={`h-2.5 w-32 ${
                                                                        breed.success_rate >=
                                                                        80
                                                                            ? '[&>div]:bg-gradient-to-r [&>div]:from-green-500 [&>div]:to-emerald-600'
                                                                            : breed.success_rate >=
                                                                                60
                                                                              ? '[&>div]:bg-gradient-to-r [&>div]:from-blue-500 [&>div]:to-indigo-600'
                                                                              : '[&>div]:bg-gradient-to-r [&>div]:from-yellow-500 [&>div]:to-orange-500'
                                                                    }`}
                                                                />
                                                                <span
                                                                    className={`text-sm font-bold ${
                                                                        breed.success_rate >=
                                                                        80
                                                                            ? 'text-green-600 dark:text-green-400'
                                                                            : breed.success_rate >=
                                                                                60
                                                                              ? 'text-blue-600 dark:text-blue-400'
                                                                              : 'text-orange-600 dark:text-orange-400'
                                                                    }`}
                                                                >
                                                                    {
                                                                        breed.success_rate
                                                                    }
                                                                    %
                                                                </span>
                                                            </div>
                                                        </TableCell>
                                                        <TableCell className="text-center">
                                                            <span
                                                                className={`text-lg font-bold ${
                                                                    breed.avg_confidence >=
                                                                    85
                                                                        ? 'text-green-600 dark:text-green-400'
                                                                        : breed.avg_confidence >=
                                                                            70
                                                                          ? 'text-blue-600 dark:text-blue-400'
                                                                          : 'text-orange-600 dark:text-orange-400'
                                                                }`}
                                                            >
                                                                {
                                                                    breed.avg_confidence
                                                                }
                                                                %
                                                            </span>
                                                        </TableCell>
                                                        <TableCell>
                                                            <div className="text-sm">
                                                                <div className="font-semibold text-gray-900 dark:text-white">
                                                                    {
                                                                        breed.days_learning
                                                                    }{' '}
                                                                    days
                                                                </div>
                                                                <div className="text-xs text-gray-500 dark:text-gray-400">
                                                                    Since{' '}
                                                                    {
                                                                        breed.first_learned
                                                                    }
                                                                </div>
                                                            </div>
                                                        </TableCell>
                                                    </TableRow>
                                                );
                                            },
                                        )}
                                    </TableBody>
                                </Table>
                            </div>

                            {/* Explanation Box */}
                            <div className="mt-6 grid gap-4 rounded-xl bg-gradient-to-br from-indigo-50 to-purple-50 p-6 md:grid-cols-2 dark:from-indigo-950/30 dark:to-purple-950/30">
                                <div className="flex gap-3">
                                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900">
                                        <Zap className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                    </div>
                                    <div>
                                        <h4 className="font-semibold text-gray-900 dark:text-white">
                                            Success Rate
                                        </h4>
                                        <p className="text-sm text-gray-600 dark:text-gray-400">
                                            % of recent scans (last 10) that got
                                            ≥80% confidence. Higher = Better
                                            learning!
                                        </p>
                                    </div>
                                </div>
                                <div className="flex gap-3">
                                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-900">
                                        <Brain className="h-5 w-5 text-purple-600 dark:text-purple-400" />
                                    </div>
                                    <div>
                                        <h4 className="font-semibold text-gray-900 dark:text-white">
                                            AI Memory vs Your Teaching
                                        </h4>
                                        <p className="text-sm text-gray-600 dark:text-gray-400">
                                            AI Memory = patterns learned. Your
                                            Teaching = corrections made. Both
                                            improve accuracy!
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </Card>
                )}

                {/* Recent Scans Table */}
                <Card className="dark:bg-neutral-900">
                    <div className="border-b border-gray-200 px-6 py-0 dark:border-gray-800">
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
                                View All
                                <ChevronRight className="ml-1 h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                    <div className="overflow-x-auto px-5">
                        <Table>
                            <TableHeader>
                                <TableRow className="mx-auto">
                                    <TableHead>Scan ID</TableHead>
                                    <TableHead>Breed</TableHead>
                                    <TableHead>Confidence</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {results?.map((result) => (
                                    <TableRow
                                        key={result.scan_id}
                                        className="mx-auto"
                                    >
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
