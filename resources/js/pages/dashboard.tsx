import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import {
    BookOpen,
    Brain,
    ChartNoAxesCombined,
    CheckCircle2,
    Database,
    Info,
    ShieldCheck,
    Sparkles,
    Target,
    TrendingUp,
    TriangleAlert,
    Zap,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

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
    } = usePage<PageProps>().props;

    const [isUpdating, setIsUpdating] = useState(false);
    const [previousCapacity, setPreviousCapacity] = useState<number | null>(
        null,
    );

    // Helper function to format trend percentage
    const formatTrend = (trend: number) => {
        const sign = trend >= 0 ? '+' : '';
        return `${sign}${trend.toFixed(1)}%`;
    };

    // Helper function to get trend color classes
    const getTrendColor = (trend: number) => {
        if (trend >= 0) {
            return 'text-green-600 dark:text-green-400';
        }
        return 'text-red-600 dark:text-red-400';
    };

    // Calculate REALISTIC learning capacity based on actual metrics
    const calculateLearningCapacity = () => {
        // 1. Memory Size Score (0-25 points)
        // Based on number of unique dog embeddings stored
        const memoryScore = Math.min(25, (memoryCount / 100) * 25);

        // 2. Breed Diversity Score (0-20 points)
        // How many different breeds we've learned
        const diversityScore = Math.min(20, (uniqueBreedsLearned / 30) * 20);

        // 3. Confidence Improvement Score (0-30 points)
        // Actual improvement in average confidence
        const confidenceImprovementScore = Math.max(
            0,
            Math.min(30, confidenceTrend * 3),
        );

        // 4. Accuracy Gain Score (0-25 points)
        // Real before/after correction accuracy
        const accuracyScore = Math.max(
            0,
            Math.min(25, accuracyImprovement * 2.5),
        );

        return Math.min(
            100,
            memoryScore +
                diversityScore +
                confidenceImprovementScore +
                accuracyScore,
        );
    };

    const learningCapacity = calculateLearningCapacity();

    // Determine learning status based on REAL metrics
    const getLearningStatus = () => {
        if (memoryCount === 0) {
            return {
                label: 'Not Learning Yet',
                color: 'text-gray-600 dark:text-gray-400',
                bgColor: 'bg-gray-100 dark:bg-gray-800',
                description: 'Start correcting breeds to enable learning',
            };
        }
        if (learningCapacity >= 80) {
            return {
                label: 'Highly Trained',
                color: 'text-green-600 dark:text-green-400',
                bgColor: 'bg-green-100 dark:bg-green-950',
                description: 'System shows strong learning progress',
            };
        }
        if (learningCapacity >= 60) {
            return {
                label: 'Actively Learning',
                color: 'text-blue-600 dark:text-blue-400',
                bgColor: 'bg-blue-100 dark:bg-blue-950',
                description: 'Good learning progress detected',
            };
        }
        if (learningCapacity >= 40) {
            return {
                label: 'Building Knowledge',
                color: 'text-yellow-600 dark:text-yellow-400',
                bgColor: 'bg-yellow-100 dark:bg-yellow-950',
                description: 'Learning in progress, needs more data',
            };
        }
        return {
            label: 'Early Training',
            color: 'text-orange-600 dark:text-orange-400',
            bgColor: 'bg-orange-100 dark:bg-orange-950',
            description: 'Just started learning, add more corrections',
        };
    };

    const learningStatus = getLearningStatus();

    // Dynamic progress bar color based on capacity
    const getProgressBarColor = () => {
        if (learningCapacity >= 80)
            return '[&>div]:bg-gradient-to-r [&>div]:from-green-500 [&>div]:to-emerald-600';
        if (learningCapacity >= 60)
            return '[&>div]:bg-gradient-to-r [&>div]:from-blue-500 [&>div]:to-indigo-600';
        if (learningCapacity >= 40)
            return '[&>div]:bg-gradient-to-r [&>div]:from-yellow-500 [&>div]:to-orange-500';
        if (learningCapacity >= 20)
            return '[&>div]:bg-gradient-to-r [&>div]:from-orange-500 [&>div]:to-red-500';
        return '[&>div]:bg-gradient-to-r [&>div]:from-gray-400 [&>div]:to-gray-500';
    };

    // Check for updates every 10 seconds
    useEffect(() => {
        const interval = setInterval(() => {
            const correctionsUntilUpdate = 5 - (correctedBreedCount % 5);

            if (correctionsUntilUpdate === 1) {
                router.reload({
                    only: [
                        'correctedBreedCount',
                        'memoryCount',
                        'uniqueBreedsLearned',
                        'avgConfidence',
                        'confidenceTrend',
                        'memoryHitRate',
                        'accuracyImprovement',
                        'breedCoverage',
                        'accuracyBeforeCorrections',
                        'accuracyAfterCorrections',
                        'lastCorrectionCount',
                    ],

                    onSuccess: () => {
                        if (
                            lastCorrectionCount > 0 &&
                            correctedBreedCount >= lastCorrectionCount + 5
                        ) {
                            setIsUpdating(true);
                            const capacityChange = previousCapacity
                                ? learningCapacity - previousCapacity
                                : 0;

                            toast.success('Learning Progress Updated!', {
                                description: `+5 corrections applied. Learning capacity ${capacityChange > 0 ? 'increased' : 'updated'} to ${learningCapacity.toFixed(1)}%`,
                                icon: (
                                    <Sparkles className="h-4 w-4 text-green-500" />
                                ),
                                duration: 4000,
                            });

                            setTimeout(() => setIsUpdating(false), 1000);
                            setPreviousCapacity(learningCapacity);
                        }
                    },
                });
            }
        }, 10000);

        return () => clearInterval(interval);
    }, [
        correctedBreedCount,
        lastCorrectionCount,
        learningCapacity,
        previousCapacity,
    ]);

    // Store initial capacity
    useEffect(() => {
        if (previousCapacity === null) {
            setPreviousCapacity(learningCapacity);
        }
    }, []);

    const correctionsUntilUpdate = 5 - (correctedBreedCount % 5);

    // Metric descriptions for tooltips
    const metricDescriptions = {
        memoryBank:
            'Total number of dog image embeddings stored in the learning system. Each correction adds a new pattern to memory.',
        breedDiversity:
            'Number of unique dog breeds the system has learned from corrections. Higher diversity improves overall accuracy.',
        avgConfidence:
            'Average prediction confidence for scans this week. Higher values indicate more certain predictions.',
        confidenceTrend:
            'Change in average confidence compared to last week. Positive trends show learning improvement.',
        memoryUsage:
            'Percentage of recent scans that benefited from memory patterns. Shows how often corrections help future predictions.',
        accuracyGain:
            'Real improvement in high-confidence predictions (≥80%) comparing scans before vs after corrections were added.',
        breedCoverage:
            'Ratio of unique breeds learned vs total corrections. Shows learning efficiency and pattern diversity.',
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="flex h-full flex-col gap-6 p-4">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card className="flex flex-row justify-between p-4 dark:bg-neutral-900">
                        <div>
                            <h1 className="text-sm text-gray-600 dark:text-white/80">
                                Total Scans
                            </h1>
                            <div className="flex items-baseline space-x-1">
                                <p className="text-lg font-bold">
                                    {resultCount}
                                </p>
                                <p
                                    className={`text-sm font-bold ${getTrendColor(totalScansWeeklyTrend)}`}
                                >
                                    {formatTrend(totalScansWeeklyTrend)}
                                </p>
                            </div>
                        </div>
                        <div className="flex h-10 w-10 items-center justify-center rounded-md bg-blue-600">
                            <ChartNoAxesCombined className="h-5 w-5 text-white" />
                        </div>
                    </Card>

                    <Card className="flex flex-row justify-between p-4 dark:bg-neutral-900">
                        <div>
                            <h1 className="text-sm text-gray-600 dark:text-white/70">
                                Corrected
                            </h1>
                            <div className="flex items-baseline space-x-1">
                                <p className="text-lg font-bold">
                                    {correctedBreedCount}
                                </p>
                                <p
                                    className={`text-sm font-bold ${getTrendColor(correctedWeeklyTrend)}`}
                                >
                                    {formatTrend(correctedWeeklyTrend)}
                                </p>
                            </div>
                        </div>
                        <div className="flex h-10 w-10 items-center justify-center rounded-md bg-blue-600">
                            <ShieldCheck className="h-5 w-5 text-white" />
                        </div>
                    </Card>

                    <Card className="flex flex-row justify-between p-4 dark:bg-neutral-900">
                        <div>
                            <h1 className="text-sm text-gray-600 dark:text-white/80">
                                High Confidence
                            </h1>
                            <div className="flex items-baseline space-x-1">
                                <p className="text-lg font-bold">
                                    {highConfidenceCount}
                                </p>
                                <p
                                    className={`text-sm font-bold ${getTrendColor(highConfidenceWeeklyTrend)}`}
                                >
                                    {formatTrend(highConfidenceWeeklyTrend)}
                                </p>
                            </div>
                        </div>
                        <div className="flex h-10 w-10 items-center justify-center rounded-md bg-blue-600">
                            <TrendingUp className="h-5 w-5 text-white" />
                        </div>
                    </Card>

                    <Card className="flex flex-row justify-between p-4 dark:bg-neutral-900">
                        <div>
                            <h1 className="text-sm text-gray-600 dark:text-white/80">
                                Low Confidence
                            </h1>
                            <div className="flex items-baseline space-x-1">
                                <p className="text-lg font-bold">
                                    {lowConfidenceCount}
                                </p>
                                <p
                                    className={`text-sm font-bold ${getTrendColor(lowConfidenceWeeklyTrend)}`}
                                >
                                    {formatTrend(lowConfidenceWeeklyTrend)}
                                </p>
                            </div>
                        </div>
                        <div className="flex h-10 w-10 items-center justify-center rounded-md bg-blue-600">
                            <TriangleAlert className="h-5 w-5 text-white" />
                        </div>
                    </Card>
                </div>

                <div className="flex flex-col gap-4 lg:flex-row">
                    {/* Recent Scans Table */}
                    <Card className="flex-1 p-5 dark:bg-neutral-900">
                        <div className="flex items-center justify-between">
                            <div>
                                <h1 className="text-lg font-bold dark:text-white">
                                    Recent Scans
                                </h1>
                                <h1 className="text-sm text-gray-600 dark:text-white/70">
                                    Latest scans processed by the system.
                                </h1>
                            </div>
                        </div>
                        <div className="overflow-x-auto">
                            <Table>
                                <TableCaption>
                                    A list of your recent scans.
                                </TableCaption>
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
                                            <TableCell>
                                                {result.breed}
                                            </TableCell>
                                            <TableCell>
                                                {result.confidence}%
                                            </TableCell>
                                            <TableCell>
                                                {result.confidence >= 80 ? (
                                                    <Badge className="bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-400">
                                                        High Confidence
                                                    </Badge>
                                                ) : result.confidence >= 60 ? (
                                                    <Badge className="bg-yellow-100 text-yellow-700 dark:bg-yellow-950 dark:text-yellow-400">
                                                        Medium Confidence
                                                    </Badge>
                                                ) : result.confidence >= 40 ? (
                                                    <Badge className="bg-orange-100 text-orange-700 dark:bg-orange-950 dark:text-orange-400">
                                                        Low Confidence
                                                    </Badge>
                                                ) : (
                                                    <Badge className="bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-400">
                                                        Very Low Confidence
                                                    </Badge>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </Card>

                    {/* Enhanced Learning Intelligence Card */}
                    <Card className="w-full p-6 lg:w-[28%] dark:bg-neutral-900">
                        <TooltipProvider>
                            <div className="space-y-6">
                                <div className="flex items-center justify-between">
                                    <h1 className="text-lg font-bold dark:text-white">
                                        Learning Intelligence
                                    </h1>
                                    <div
                                        className={`flex h-8 w-8 items-center justify-center rounded-full transition-all duration-500 ${
                                            isUpdating ? 'scale-110' : ''
                                        } ${learningCapacity >= 70 ? 'animate-pulse bg-gradient-to-br from-green-500 to-emerald-600' : 'bg-gradient-to-br from-blue-500 to-purple-600'}`}
                                    >
                                        <Brain className="h-4 w-4 text-white" />
                                    </div>
                                </div>

                                {/* Learning Status */}
                                <div>
                                    <div className="mb-2 flex items-center justify-between">
                                        <p className="text-sm text-gray-700 dark:text-white/80">
                                            Status
                                        </p>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Badge
                                                    className={`${learningStatus.bgColor} ${learningStatus.color} cursor-help border-0 transition-all duration-500 ${
                                                        isUpdating
                                                            ? 'scale-110'
                                                            : ''
                                                    }`}
                                                >
                                                    {learningStatus.label}
                                                </Badge>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <p className="max-w-xs text-xs">
                                                    {learningStatus.description}
                                                </p>
                                            </TooltipContent>
                                        </Tooltip>
                                    </div>
                                    <Progress
                                        value={learningCapacity}
                                        className={`h-2 transition-all duration-1000 ${getProgressBarColor()}`}
                                    />
                                    <div className="mt-1 flex items-center justify-between">
                                        <p className="text-xs text-gray-500 dark:text-gray-400">
                                            {learningCapacity.toFixed(1)}%
                                            Learning Capacity
                                        </p>
                                        {correctionsUntilUpdate < 5 && (
                                            <p className="text-xs font-medium text-blue-600 dark:text-blue-400">
                                                {correctionsUntilUpdate} more to
                                                update
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <hr className="border-gray-300 dark:border-gray-700" />

                                {/* Real Learning Metrics with Tooltips */}
                                <div className="space-y-4">
                                    {/* Memory Bank */}
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <div
                                                className={`flex cursor-help items-center justify-between transition-all duration-500 ${
                                                    isUpdating
                                                        ? '-m-2 scale-105 rounded-lg bg-blue-50 p-2 dark:bg-blue-950/20'
                                                        : ''
                                                }`}
                                            >
                                                <div className="flex items-center gap-2">
                                                    <div className="flex h-8 w-8 items-center justify-center rounded-md bg-blue-100 dark:bg-blue-900/30">
                                                        <Database className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                                    </div>
                                                    <div>
                                                        <div className="flex items-center gap-1">
                                                            <p className="text-xs text-gray-600 dark:text-white/70">
                                                                Memory Bank
                                                            </p>
                                                            <Info className="h-3 w-3 text-gray-400" />
                                                        </div>
                                                        <p className="text-sm font-bold dark:text-white">
                                                            {memoryCount}{' '}
                                                            Patterns
                                                        </p>
                                                    </div>
                                                </div>
                                                {memoryCount > 0 && (
                                                    <Badge className="border-0 bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-400">
                                                        Active
                                                    </Badge>
                                                )}
                                            </div>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p className="max-w-xs text-xs">
                                                {metricDescriptions.memoryBank}
                                            </p>
                                        </TooltipContent>
                                    </Tooltip>

                                    {/* Breed Diversity */}
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <div className="flex cursor-help items-center justify-between">
                                                <div className="flex items-center gap-2">
                                                    <div className="flex h-8 w-8 items-center justify-center rounded-md bg-purple-100 dark:bg-purple-900/30">
                                                        <BookOpen className="h-4 w-4 text-purple-600 dark:text-purple-400" />
                                                    </div>
                                                    <div>
                                                        <div className="flex items-center gap-1">
                                                            <p className="text-xs text-gray-600 dark:text-white/70">
                                                                Breed Diversity
                                                            </p>
                                                            <Info className="h-3 w-3 text-gray-400" />
                                                        </div>
                                                        <p className="text-sm font-bold dark:text-white">
                                                            {
                                                                uniqueBreedsLearned
                                                            }{' '}
                                                            Breeds
                                                        </p>
                                                    </div>
                                                </div>
                                                <span className="text-xs text-gray-500 dark:text-gray-400">
                                                    {breedCoverage.toFixed(0)}%
                                                    eff.
                                                </span>
                                            </div>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p className="max-w-xs text-xs">
                                                {
                                                    metricDescriptions.breedDiversity
                                                }
                                            </p>
                                        </TooltipContent>
                                    </Tooltip>

                                    {/* Average Confidence */}
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <div
                                                className={`flex cursor-help items-center justify-between transition-all duration-500 ${
                                                    isUpdating
                                                        ? '-m-2 scale-105 rounded-lg bg-indigo-50 p-2 dark:bg-indigo-950/20'
                                                        : ''
                                                }`}
                                            >
                                                <div className="flex items-center gap-2">
                                                    <div className="flex h-8 w-8 items-center justify-center rounded-md bg-indigo-100 dark:bg-indigo-900/30">
                                                        <Target className="h-4 w-4 text-indigo-600 dark:text-indigo-400" />
                                                    </div>
                                                    <div>
                                                        <div className="flex items-center gap-1">
                                                            <p className="text-xs text-gray-600 dark:text-white/70">
                                                                Avg Confidence
                                                            </p>
                                                            <Info className="h-3 w-3 text-gray-400" />
                                                        </div>
                                                        <p className="text-sm font-bold dark:text-white">
                                                            {avgConfidence.toFixed(
                                                                1,
                                                            )}
                                                            %
                                                        </p>
                                                    </div>
                                                </div>
                                                {confidenceTrend !== 0 && (
                                                    <span
                                                        className={`text-xs font-medium ${getTrendColor(confidenceTrend)}`}
                                                    >
                                                        {formatTrend(
                                                            confidenceTrend,
                                                        )}
                                                    </span>
                                                )}
                                            </div>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p className="max-w-xs text-xs">
                                                {
                                                    metricDescriptions.avgConfidence
                                                }
                                            </p>
                                        </TooltipContent>
                                    </Tooltip>

                                    {/* Memory Usage Rate */}
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <div className="flex cursor-help items-center justify-between">
                                                <div className="flex items-center gap-2">
                                                    <div className="flex h-8 w-8 items-center justify-center rounded-md bg-cyan-100 dark:bg-cyan-900/30">
                                                        <Zap className="h-4 w-4 text-cyan-600 dark:text-cyan-400" />
                                                    </div>
                                                    <div>
                                                        <div className="flex items-center gap-1">
                                                            <p className="text-xs text-gray-600 dark:text-white/70">
                                                                Memory Usage
                                                            </p>
                                                            <Info className="h-3 w-3 text-gray-400" />
                                                        </div>
                                                        <p className="text-sm font-bold dark:text-white">
                                                            {memoryHitRate.toFixed(
                                                                1,
                                                            )}
                                                            %
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p className="max-w-xs text-xs">
                                                {metricDescriptions.memoryUsage}
                                            </p>
                                        </TooltipContent>
                                    </Tooltip>

                                    {/* Real Accuracy Improvement */}
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <div
                                                className={`flex cursor-help items-center justify-between transition-all duration-500 ${
                                                    isUpdating
                                                        ? '-m-2 scale-105 rounded-lg bg-green-50 p-2 dark:bg-green-950/20'
                                                        : ''
                                                }`}
                                            >
                                                <div className="flex items-center gap-2">
                                                    <div className="flex h-8 w-8 items-center justify-center rounded-md bg-green-100 dark:bg-green-900/30">
                                                        <CheckCircle2 className="h-4 w-4 text-green-600 dark:text-green-400" />
                                                    </div>
                                                    <div>
                                                        <div className="flex items-center gap-1">
                                                            <p className="text-xs text-gray-600 dark:text-white/70">
                                                                Accuracy Gain
                                                            </p>
                                                            <Info className="h-3 w-3 text-gray-400" />
                                                        </div>
                                                        <p className="text-sm font-bold dark:text-white">
                                                            {accuracyImprovement >=
                                                            0
                                                                ? '+'
                                                                : ''}
                                                            {accuracyImprovement.toFixed(
                                                                1,
                                                            )}
                                                            %
                                                        </p>
                                                    </div>
                                                </div>
                                                {recentCorrectionsCount > 0 && (
                                                    <span className="text-xs text-gray-500 dark:text-gray-400">
                                                        {recentCorrectionsCount}{' '}
                                                        this week
                                                    </span>
                                                )}
                                            </div>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p className="max-w-xs text-xs">
                                                {
                                                    metricDescriptions.accuracyGain
                                                }
                                            </p>
                                        </TooltipContent>
                                    </Tooltip>
                                </div>

                                <hr className="border-gray-300 dark:border-gray-700" />

                                {/* Before/After Comparison */}
                                {accuracyBeforeCorrections > 0 && (
                                    <div className="space-y-2 rounded-lg bg-gray-50 p-3 dark:bg-gray-800/50">
                                        <p className="text-xs font-medium text-gray-700 dark:text-gray-300">
                                            Learning Impact
                                        </p>
                                        <div className="flex items-center justify-between text-xs">
                                            <span className="text-gray-600 dark:text-gray-400">
                                                Before Corrections:
                                            </span>
                                            <span className="font-bold dark:text-white">
                                                {accuracyBeforeCorrections.toFixed(
                                                    1,
                                                )}
                                                %
                                            </span>
                                        </div>
                                        <div className="flex items-center justify-between text-xs">
                                            <span className="text-gray-600 dark:text-gray-400">
                                                After Corrections:
                                            </span>
                                            <span className="font-bold text-green-600 dark:text-green-400">
                                                {accuracyAfterCorrections.toFixed(
                                                    1,
                                                )}
                                                %
                                            </span>
                                        </div>
                                        <div className="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                            <div
                                                className="h-full bg-gradient-to-r from-green-500 to-emerald-600 transition-all duration-500"
                                                style={{
                                                    width: `${Math.min(100, accuracyAfterCorrections)}%`,
                                                }}
                                            />
                                        </div>
                                    </div>
                                )}

                                {/* Dynamic Learning Insight */}
                                <div
                                    className={`rounded-lg p-3 transition-all duration-500 ${
                                        learningCapacity >= 80
                                            ? 'bg-green-50 dark:bg-green-950/20'
                                            : learningCapacity >= 60
                                              ? 'bg-blue-50 dark:bg-blue-950/20'
                                              : learningCapacity >= 40
                                                ? 'bg-yellow-50 dark:bg-yellow-950/20'
                                                : 'bg-orange-50 dark:bg-orange-950/20'
                                    }`}
                                >
                                    <p
                                        className={`text-xs ${
                                            learningCapacity >= 80
                                                ? 'text-green-800 dark:text-green-300'
                                                : learningCapacity >= 60
                                                  ? 'text-blue-800 dark:text-blue-300'
                                                  : learningCapacity >= 40
                                                    ? 'text-yellow-800 dark:text-yellow-300'
                                                    : 'text-orange-800 dark:text-orange-300'
                                        }`}
                                    >
                                        {memoryCount === 0
                                            ? '💡 Start correcting breeds to build the learning memory. The system will improve with each correction you provide.'
                                            : learningCapacity >= 80
                                              ? `🎯 Excellent! System has learned ${uniqueBreedsLearned} breeds from ${memoryCount} patterns. Accuracy improved by ${accuracyImprovement.toFixed(1)}% after corrections.`
                                              : learningCapacity >= 60
                                                ? `📈 Good progress! ${memoryCount} patterns stored, ${uniqueBreedsLearned} breeds learned. ${correctionsUntilUpdate} more correction${correctionsUntilUpdate === 1 ? '' : 's'} to next update.`
                                                : learningCapacity >= 40
                                                  ? `📚 Building knowledge: ${memoryCount} patterns across ${uniqueBreedsLearned} breeds. Add ${correctionsUntilUpdate} more to see improvement.`
                                                  : `🔄 Early stage: ${memoryCount} patterns stored. System needs ${correctionsUntilUpdate} more correction${correctionsUntilUpdate === 1 ? '' : 's'} for next milestone.`}
                                    </p>
                                </div>
                            </div>
                        </TooltipProvider>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
