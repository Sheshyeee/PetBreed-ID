import Header from '@/components/header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    Brain,
    CheckCircle2,
    Clock,
    Globe,
    Sparkles,
} from 'lucide-react';

type PredictionResult = {
    breed: string;
    confidence: number;
};

type Result = {
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
    // NEW: Learning indicators
    prediction_method?: string;
    is_exact_match?: boolean;
    has_admin_correction?: boolean;
};

type PageProps = {
    results?: Result;
};

const ScanResults = () => {
    const { results } = usePage<PageProps>().props;

    // Determine if this scan benefited from learning
    const isMemoryAssisted =
        results?.prediction_method === 'admin_corrected' ||
        results?.is_exact_match ||
        results?.has_admin_correction;

    const showLearningBadge =
        results?.confidence === 100 && results?.has_admin_correction;

    // FIXED: Better filtering and validation of predictions
    const filteredPredictions =
        results?.top_predictions?.filter((prediction) => {
            // Remove invalid entries
            if (!prediction || !prediction.breed) return false;

            const breedLower = prediction.breed.toLowerCase().trim();

            // Filter out placeholder/invalid breeds
            const invalidBreeds = [
                'other breeds',
                'other breed',
                'alternative 1',
                'alternative 2',
                'alternative 3',
                'alternative',
                'unknown',
                'mixed breed', // Only if you want to exclude generic mixed breed
            ];

            if (invalidBreeds.includes(breedLower)) return false;

            // Must have positive confidence
            if (!prediction.confidence || prediction.confidence <= 0)
                return false;

            // Don't show if it's the same as the primary breed
            if (
                results?.breed &&
                breedLower === results.breed.toLowerCase().trim()
            ) {
                return false;
            }

            return true;
        }) || [];

    // Sort by confidence descending
    const sortedPredictions = [...filteredPredictions].sort(
        (a, b) => (b.confidence || 0) - (a.confidence || 0),
    );

    // Take top 3 alternatives
    const topAlternatives = sortedPredictions.slice(0, 3);

    return (
        <div className="min-h-screen bg-gray-50 dark:bg-gray-950">
            <Header />
            <main className="mx-auto mt-[-45px] w-full max-w-7xl px-8 pt-4 pb-8 sm:px-10 lg:px-8">
                {/* Page Header */}
                <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-lg font-bold text-gray-900 sm:text-3xl dark:text-white">
                            Scan Results
                        </h1>
                        <p className="mt-[-5px] text-sm text-gray-600 dark:text-gray-400">
                            Here's what we found about your pet
                        </p>
                    </div>
                    <Link href="/scan">
                        <Button className="w-full sm:w-auto">New Scan</Button>
                    </Link>
                </div>

                {/* Primary Result Card */}
                <Card className="flex flex-col gap-8 border-cyan-200 bg-cyan-50 p-8 sm:p-10 lg:flex-row lg:items-center lg:p-12 dark:border-cyan-800 dark:bg-cyan-950/40">
                    <div className="mx-auto w-full max-w-[220px] shrink-0 sm:max-w-[260px] lg:mx-0 lg:w-[220px] xl:w-[240px]">
                        <img
                            src={results?.image}
                            alt="Pet"
                            className="h-auto w-full rounded-2xl shadow-xl ring-1 ring-black/5 dark:ring-white/10"
                        />
                    </div>
                    <div className="flex-1 space-y-5">
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge
                                variant="secondary"
                                className="bg-cyan-600 px-3 py-1 text-sm font-medium text-white hover:bg-cyan-700 dark:bg-cyan-500 dark:hover:bg-cyan-600"
                            >
                                Primary Match
                            </Badge>

                            {/* Learning Indicator Badges */}
                            {showLearningBadge && (
                                <Badge className="gap-1.5 border-0 bg-gradient-to-r from-purple-600 to-pink-600 px-3 py-1 text-sm font-medium text-white hover:from-purple-700 hover:to-pink-700">
                                    <Brain className="h-3.5 w-3.5" />
                                    Learned Recognition
                                </Badge>
                            )}

                            {results?.is_exact_match && !showLearningBadge && (
                                <Badge className="gap-1.5 border-0 bg-gradient-to-r from-blue-600 to-indigo-600 px-3 py-1 text-sm font-medium text-white hover:from-blue-700 hover:to-indigo-700">
                                    <CheckCircle2 className="h-3.5 w-3.5" />
                                    Memory Match
                                </Badge>
                            )}
                        </div>

                        <h2 className="text-lg font-bold text-gray-900 sm:text-4xl dark:text-white">
                            {results?.breed}
                        </h2>

                        <div className="flex w-full max-w-md justify-between lg:w-[400px]">
                            <p className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                Confidence Score
                            </p>
                            <p className="text-sm font-bold text-gray-900 dark:text-white">
                                {Math.round(results?.confidence ?? 0)}%
                            </p>
                        </div>

                        <Progress
                            value={results?.confidence ?? 0}
                            className={`h-3 w-full max-w-md lg:w-[400px] ${
                                results?.confidence === 100
                                    ? '[&>div]:bg-gradient-to-r [&>div]:from-purple-600 [&>div]:to-pink-600'
                                    : '[&>div]:bg-cyan-600'
                            }`}
                        />

                        {/* Learning Explanation */}
                        {showLearningBadge && (
                            <div className="rounded-xl border border-purple-200 bg-purple-50 p-4 dark:border-purple-800 dark:bg-purple-950/50">
                                <div className="flex items-start gap-3">
                                    <Sparkles className="mt-0.5 h-5 w-5 text-purple-600 dark:text-purple-400" />
                                    <p className="text-sm leading-relaxed text-purple-900 dark:text-purple-100">
                                        <strong className="font-semibold">
                                            Perfect Match!
                                        </strong>{' '}
                                        Our system recognized this exact dog
                                        from previous corrections. This is proof
                                        that admin corrections are making the AI
                                        smarter!
                                    </p>
                                </div>
                            </div>
                        )}

                        <p className="text-sm leading-relaxed text-gray-700 dark:text-gray-300">
                            {results?.description}
                        </p>
                    </div>
                </Card>

                {/* FIXED: Top Possible Breeds - Only show if there are valid alternatives */}
                {topAlternatives.length > 0 && (
                    <Card className="mt-6 border-gray-200 bg-white p-8 sm:p-10 lg:p-12 dark:border-gray-800 dark:bg-gray-900">
                        <h2 className="mb-6 text-lg font-bold text-gray-900 sm:text-3xl dark:text-white">
                            Other Possible Breeds
                        </h2>
                        <div className="space-y-4">
                            {topAlternatives.map((prediction, index) => (
                                <Card
                                    key={`${prediction.breed}-${index}`}
                                    className="border-violet-200 bg-violet-50 p-6 transition-all hover:border-violet-300 hover:shadow-sm dark:border-violet-800 dark:bg-violet-950/30 dark:hover:border-violet-700"
                                >
                                    <div className="flex items-center gap-5">
                                        <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl border border-violet-300 bg-white text-xl font-bold text-violet-700 shadow-sm dark:border-violet-700 dark:bg-gray-800 dark:text-violet-300">
                                            {index + 1}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <h3 className="mb-3 text-lg font-semibold text-gray-900 sm:text-xl dark:text-white">
                                                {prediction.breed}
                                            </h3>
                                            <div className="flex items-center gap-4">
                                                <Progress
                                                    value={
                                                        prediction.confidence
                                                    }
                                                    className="h-2.5 flex-1 bg-violet-200 lg:max-w-[450px] dark:bg-violet-900/50 [&>div]:bg-violet-600 dark:[&>div]:bg-violet-500"
                                                />
                                                <p className="shrink-0 text-base font-bold text-gray-900 dark:text-white">
                                                    {Math.round(
                                                        prediction.confidence,
                                                    )}
                                                    %
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </Card>
                            ))}
                        </div>
                    </Card>
                )}

                {/* Show message when only one confident prediction */}
                {topAlternatives.length === 0 &&
                    results?.confidence &&
                    results.confidence >= 80 && (
                        <Card className="mt-6 border-gray-200 bg-white p-8 dark:border-gray-800 dark:bg-gray-900">
                            <div className="flex items-center justify-center gap-3 text-center">
                                <CheckCircle2 className="h-6 w-6 text-green-600 dark:text-green-400" />
                                <p className="text-base text-gray-700 dark:text-gray-300">
                                    <strong className="font-semibold text-gray-900 dark:text-white">
                                        High Confidence Identification
                                    </strong>
                                    {' - '}
                                    Our system is very confident about this
                                    breed identification.
                                </p>
                            </div>
                        </Card>
                    )}

                <h2 className="mt-8 mb-6 text-lg font-bold text-gray-900 sm:text-3xl dark:text-white">
                    Explore More Insights
                </h2>

                <div className="mb-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <Card className="flex flex-col gap-6 border-gray-200 bg-white p-8 transition-all hover:border-blue-300 hover:shadow-lg sm:col-span-2 lg:col-span-1 dark:border-gray-800 dark:bg-gray-900 dark:hover:border-blue-600">
                        <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-950 dark:to-blue-900">
                            <Globe className="h-8 w-8 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div className="flex-1">
                            <h3 className="mb-2 text-xl font-bold text-gray-900 dark:text-white">
                                Origin History
                            </h3>
                            <p className="text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                                Discover the history and origin of your pet's
                                breed
                            </p>
                        </div>
                        <Button
                            variant="outline"
                            asChild
                            className="w-full border-gray-300 text-base font-medium hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                        >
                            <Link
                                href={`/origin?id=${results?.scan_id}`}
                                className="flex items-center justify-center gap-2"
                            >
                                <span>Explore History</span>
                                <Sparkles size={18} />
                            </Link>
                        </Button>
                    </Card>

                    <Card className="flex flex-col gap-6 border-gray-200 bg-white p-8 transition-all hover:border-pink-300 hover:shadow-lg dark:border-gray-800 dark:bg-gray-900 dark:hover:border-pink-600">
                        <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-pink-50 to-pink-100 dark:from-pink-950 dark:to-pink-900">
                            <Activity className="h-8 w-8 text-pink-600 dark:text-pink-400" />
                        </div>
                        <div className="flex-1">
                            <h3 className="mb-2 text-xl font-bold text-gray-900 dark:text-white">
                                Health Risk
                            </h3>
                            <p className="text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                                Learn about breed-specific health considerations
                            </p>
                        </div>
                        <Button
                            variant="outline"
                            asChild
                            className="w-full border-gray-300 text-base font-medium hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                        >
                            <Link
                                href={`/health-risk?id=${results?.scan_id}`}
                                className="flex items-center justify-center gap-2"
                            >
                                <span>View Risk</span>
                                <Sparkles size={18} />
                            </Link>
                        </Button>
                    </Card>

                    <Card className="flex flex-col gap-6 border-gray-200 bg-white p-8 transition-all hover:border-violet-300 hover:shadow-lg dark:border-gray-800 dark:bg-gray-900 dark:hover:border-violet-600">
                        <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-50 to-violet-100 dark:from-violet-950 dark:to-violet-900">
                            <Clock className="h-8 w-8 text-violet-600 dark:text-violet-400" />
                        </div>
                        <div className="flex-1">
                            <h3 className="mb-2 text-xl font-bold text-gray-900 dark:text-white">
                                Future Appearance
                            </h3>
                            <p className="text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                                See how your pet will look as they age over the
                                years
                            </p>
                        </div>
                        <Button
                            variant="outline"
                            asChild
                            className="w-full border-gray-300 text-base font-medium hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                        >
                            <Link
                                href="/simulation"
                                className="flex items-center justify-center gap-2"
                            >
                                <span>View Simulation</span>
                                <Sparkles size={18} />
                            </Link>
                        </Button>
                    </Card>
                </div>
            </main>
        </div>
    );
};

export default ScanResults;
