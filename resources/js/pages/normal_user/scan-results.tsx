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
    prediction_method?: string; // 'exact_match', 'admin_corrected', 'memory_assisted', etc.
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

    return (
        <div className="min-h-screen bg-[#FDFDFC] dark:bg-[#0a0a0a]">
            <Header />
            <main className="mx-auto mt-[-20px] w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-20 xl:px-32">
                {/* Page Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-xl font-bold sm:text-xl dark:text-white">
                            Scan Results
                        </h1>
                        <p className="text-sm text-gray-600 dark:text-white/70">
                            Here's what we found about your pet
                        </p>
                    </div>
                    <Link href="/scan" className="hidden sm:block">
                        <Button>New Scan</Button>
                    </Link>
                </div>

                {/* Primary Result Card */}
                <Card className="mt-6 flex flex-col gap-6 bg-cyan-50 p-6 outline-1 outline-cyan-200 sm:p-8 lg:flex-row lg:items-center lg:p-10 dark:bg-cyan-950 dark:outline-cyan-800">
                    <div className="mx-auto w-full max-w-[200px] shrink-0 sm:max-w-[250px] lg:mx-0 lg:w-[180px] xl:w-[200px]">
                        <img
                            src={`/storage/${results?.image}`}
                            alt="Pet"
                            className="h-auto w-full rounded-2xl shadow-lg"
                        />
                    </div>
                    <div className="flex-1 space-y-3">
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge
                                variant="secondary"
                                className="bg-cyan-700 text-white dark:bg-cyan-600"
                            >
                                Primary Match
                            </Badge>

                            {/* NEW: Learning Indicator Badges */}
                            {showLearningBadge && (
                                <Badge className="gap-1 border-0 bg-gradient-to-r from-purple-500 to-pink-600 text-white">
                                    <Brain className="h-3 w-3" />
                                    Learned Recognition
                                </Badge>
                            )}

                            {results?.is_exact_match && !showLearningBadge && (
                                <Badge className="gap-1 border-0 bg-gradient-to-r from-blue-500 to-indigo-600 text-white">
                                    <CheckCircle2 className="h-3 w-3" />
                                    Memory Match
                                </Badge>
                            )}
                        </div>

                        <h2 className="text-2xl font-bold sm:text-3xl lg:text-xl xl:text-xl dark:text-white">
                            {results?.breed}
                        </h2>

                        <div className="flex w-full max-w-md justify-between lg:w-[350px] xl:w-[400px]">
                            <p className="text-sm text-gray-600 dark:text-gray-300">
                                Confidence Score
                            </p>
                            <p className="text-sm font-semibold dark:text-white">
                                {Math.round(results?.confidence ?? 0)}%
                            </p>
                        </div>

                        <Progress
                            value={results?.confidence ?? 0}
                            className={`h-3 w-full max-w-md lg:w-[350px] xl:w-[400px] ${
                                results?.confidence === 100
                                    ? '[&>div]:bg-gradient-to-r [&>div]:from-purple-500 [&>div]:to-pink-600'
                                    : '[&>div]:bg-blue-500'
                            }`}
                        />

                        {/* NEW: Learning Explanation */}
                        {showLearningBadge && (
                            <div className="rounded-lg border border-purple-200 bg-purple-50 p-3 dark:border-purple-800 dark:bg-purple-950/20">
                                <div className="flex items-start gap-2">
                                    <Sparkles className="mt-0.5 h-4 w-4 text-purple-600 dark:text-purple-400" />
                                    <p className="text-xs text-purple-800 dark:text-purple-300">
                                        <strong>Perfect Match!</strong> Our
                                        system recognized this exact dog from
                                        previous corrections. This is proof that
                                        admin corrections are making the AI
                                        smarter!
                                    </p>
                                </div>
                            </div>
                        )}

                        <p className="text-sm leading-relaxed text-gray-600 dark:text-gray-300">
                            {results?.description}
                        </p>
                    </div>
                </Card>

                {/* Rest of the page remains the same ... */}
                <Card className="mt-5 p-6 sm:p-8 lg:p-10">
                    <h2 className="text-md mb-4 font-bold sm:text-xl dark:text-white">
                        Top Possible Breeds
                    </h2>
                    <div className="space-y-4">
                        {results?.top_predictions
                            ?.slice(0, 3)
                            .map((prediction, index) => (
                                <Card
                                    key={index}
                                    className="bg-violet-50 p-5 outline outline-1 outline-violet-100 sm:p-6 dark:bg-violet-950 dark:outline-violet-800"
                                >
                                    <div className="flex items-center gap-4 sm:gap-5">
                                        <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border bg-white text-base font-bold sm:h-14 sm:w-14 sm:rounded-2xl dark:bg-gray-900 dark:text-white">
                                            {index + 1}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <h3 className="sm:text-md mb-2 text-base font-semibold dark:text-white">
                                                {prediction.breed}
                                            </h3>
                                            <div className="flex items-center gap-3 sm:gap-4">
                                                <Progress
                                                    value={
                                                        prediction.confidence
                                                    }
                                                    className="h-2 flex-1 lg:max-w-[400px] [&>div]:bg-blue-500"
                                                />
                                                <p className="shrink-0 text-sm font-semibold dark:text-white">
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

                <h2 className="text-md mt-8 mb-6 font-bold sm:text-xl dark:text-white">
                    Explore More Insights
                </h2>

                <div className="mb-6 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <Card className="flex flex-col gap-6 p-6 transition-all hover:border-blue-400 hover:shadow-md sm:col-span-2 sm:p-8 lg:col-span-1">
                        <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-200 p-4">
                            <Globe color="#042381" size={32} />
                        </div>
                        <h3 className="text-md font-bold dark:text-white">
                            Origin History
                        </h3>
                        <p className="flex-1 text-sm text-gray-500 dark:text-gray-400">
                            Discover the history and origin of your pet's breed
                        </p>
                        <Button variant="outline" asChild className="w-full">
                            <Link
                                href={`/origin?id=${results?.scan_id}`}
                                className="flex items-center justify-center gap-2"
                            >
                                <span>Explore History</span>
                                <Sparkles size={16} />
                            </Link>
                        </Button>
                    </Card>

                    <Card className="flex flex-col gap-6 p-6 transition-all hover:border-red-400 hover:shadow-md sm:p-8">
                        <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-pink-200 p-4">
                            <Activity color="#750056" size={32} />
                        </div>
                        <h3 className="text-md font-bold dark:text-white">
                            Health Risk
                        </h3>
                        <p className="flex-1 text-sm text-gray-500 dark:text-gray-400">
                            Learn about breed-specific health considerations
                        </p>
                        <Button variant="outline" asChild className="w-full">
                            <Link
                                href={`/health-risk?id=${results?.scan_id}`}
                                className="flex items-center justify-center gap-2"
                            >
                                <span>View Risk</span>
                                <Sparkles size={16} />
                            </Link>
                        </Button>
                    </Card>

                    <Card className="flex flex-col gap-6 p-6 transition-all hover:border-violet-400 hover:shadow-md sm:p-8">
                        <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-violet-200 p-4">
                            <Clock color="#3f005c" size={32} />
                        </div>
                        <h3 className="text-md font-bold dark:text-white">
                            Future Appearance
                        </h3>
                        <p className="flex-1 text-sm text-gray-500 dark:text-gray-400">
                            See how your pet will look as they age over the
                            years
                        </p>
                        <Button variant="outline" asChild className="w-full">
                            <Link
                                href="/simulation"
                                className="flex items-center justify-center gap-2"
                            >
                                <span>View Simulation</span>
                                <Sparkles size={16} />
                            </Link>
                        </Button>
                    </Card>
                </div>

                <div className="mb-10 sm:hidden">
                    <Link href="/scan" className="block">
                        <Button className="w-full">New Scan</Button>
                    </Link>
                </div>
            </main>
        </div>
    );
};

export default ScanResults;
