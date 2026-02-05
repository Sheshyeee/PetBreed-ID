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
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import {
    ChartNoAxesCombined,
    GraduationCap,
    ShieldCheck,
    Sparkles,
    TrendingUp,
    TriangleAlert,
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
    breedLearningProgress?: BreedLearning[]; // NEW
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
        const memoryScore = Math.min(25, (memoryCount / 100) * 25);
        const diversityScore = Math.min(20, (uniqueBreedsLearned / 30) * 20);
        const confidenceImprovementScore = Math.max(
            0,
            Math.min(30, confidenceTrend * 3),
        );
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
                        'breedLearningProgress',
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

    useEffect(() => {
        if (previousCapacity === null) {
            setPreviousCapacity(learningCapacity);
        }
    }, []);

    const correctionsUntilUpdate = 5 - (correctedBreedCount % 5);

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
                {/* Existing Stats Cards */}
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

                {/* Main Content Grid */}
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
                </div>

                {/* NEW: Breed Learning Progress Table */}
                {breedLearningProgress && breedLearningProgress.length > 0 && (
                    <Card className="p-6 dark:bg-neutral-900">
                        <div className="mb-4 flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-purple-500 to-pink-600">
                                    <GraduationCap className="h-5 w-5 text-white" />
                                </div>
                                <div>
                                    <h2 className="text-lg font-bold dark:text-white">
                                        Breed Learning Progress
                                    </h2>
                                    <p className="text-sm text-gray-600 dark:text-gray-400">
                                        Track how well the system learned each
                                        breed from corrections
                                    </p>
                                </div>
                            </div>
                            <Badge className="bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300">
                                Top {breedLearningProgress.length} Breeds
                            </Badge>
                        </div>

                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Breed</TableHead>
                                        <TableHead className="text-center">
                                            Examples
                                        </TableHead>
                                        <TableHead className="text-center">
                                            Corrections
                                        </TableHead>
                                        <TableHead>Success Rate</TableHead>
                                        <TableHead className="text-center">
                                            Avg Confidence
                                        </TableHead>
                                        <TableHead>Learning Time</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {breedLearningProgress.map((breed, idx) => (
                                        <TableRow key={breed.breed}>
                                            <TableCell className="font-medium">
                                                <div className="flex items-center gap-2">
                                                    {idx === 0 && (
                                                        <Sparkles className="h-4 w-4 text-yellow-500" />
                                                    )}
                                                    {breed.breed}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-center">
                                                <Badge
                                                    variant="outline"
                                                    className="font-mono"
                                                >
                                                    {breed.examples_learned}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-center">
                                                <Badge
                                                    variant="outline"
                                                    className="font-mono"
                                                >
                                                    {breed.corrections_made}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <Progress
                                                        value={
                                                            breed.success_rate
                                                        }
                                                        className={`h-2 w-24 ${
                                                            breed.success_rate >=
                                                            80
                                                                ? '[&>div]:bg-green-600'
                                                                : breed.success_rate >=
                                                                    60
                                                                  ? '[&>div]:bg-yellow-500'
                                                                  : '[&>div]:bg-orange-500'
                                                        }`}
                                                    />
                                                    <span className="w-12 text-xs font-semibold">
                                                        {breed.success_rate}%
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-center">
                                                <span
                                                    className={`font-bold ${
                                                        breed.avg_confidence >=
                                                        80
                                                            ? 'text-green-600 dark:text-green-400'
                                                            : breed.avg_confidence >=
                                                                60
                                                              ? 'text-yellow-600 dark:text-yellow-400'
                                                              : 'text-orange-600 dark:text-orange-400'
                                                    }`}
                                                >
                                                    {breed.avg_confidence}%
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                <div className="text-xs text-gray-600 dark:text-gray-400">
                                                    <div>
                                                        {breed.days_learning}{' '}
                                                        days
                                                    </div>
                                                    <div className="text-xs opacity-75">
                                                        Since{' '}
                                                        {breed.first_learned}
                                                    </div>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <div className="mt-4 rounded-lg bg-purple-50 p-3 dark:bg-purple-950/20">
                            <p className="text-xs text-purple-800 dark:text-purple-300">
                                💡 <strong>Success Rate</strong> shows how many
                                recent scans (last 10) got high confidence
                                (≥80%) for each breed. Higher rates mean better
                                learning!
                            </p>
                        </div>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
