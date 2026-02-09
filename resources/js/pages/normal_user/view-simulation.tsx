import Header from '@/components/header';
import { Card, CardContent } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Link } from '@inertiajs/react';
import axios from 'axios';
import { AlertCircle, ArrowLeft, Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';

interface SimulationData {
    '1_years': string | null;
    '3_years': string | null;
}

interface ViewSimulationProps {
    breed: string;
    originalImage: string;
    simulations: SimulationData;
    simulation_status?: 'pending' | 'generating' | 'complete' | 'failed';
}

const ViewSimulation: React.FC<ViewSimulationProps> = ({
    breed,
    originalImage,
    simulations: initialSimulations,
    simulation_status: initialStatus = 'pending',
}) => {
    const [simulations, setSimulations] =
        useState<SimulationData>(initialSimulations);
    const [status, setStatus] = useState<string>(initialStatus);
    const [currentOriginalImage, setCurrentOriginalImage] =
        useState<string>(originalImage);
    const [isPolling, setIsPolling] = useState(
        initialStatus !== 'complete' && initialStatus !== 'failed',
    );

    /**
     * FIXED: Now handles both relative paths AND full URLs
     * If the path already starts with http/https, use it as-is
     * Otherwise, assume it's a relative path and prepend /storage/
     */
    const getImageUrl = (path: string | null): string => {
        if (!path) return '/dogpic.jpg';

        // If it's already a full URL (from object storage), use it directly
        if (path.startsWith('http://') || path.startsWith('https://')) {
            return path;
        }

        // Otherwise, it's a relative path (legacy local storage)
        return `/storage/${path}`;
    };

    const hasSimulations =
        simulations && (simulations['1_years'] || simulations['3_years']);

    // Poll for simulation updates
    useEffect(() => {
        // Don't poll if already complete/failed
        if (!isPolling) {
            return;
        }

        // Start polling immediately
        const poll = async () => {
            try {
                const response = await axios.get('/api/simulation-status');
                const data = response.data;

                console.log('Polling response:', data);

                setStatus(data.status);
                setSimulations(data.simulations);

                // Update original image if API returns it (with full URL)
                if (data.original_image) {
                    setCurrentOriginalImage(data.original_image);
                }

                if (data.status === 'complete' || data.status === 'failed') {
                    console.log('Simulation complete, stopping poll');
                    setIsPolling(false);
                }
            } catch (error) {
                console.error('Failed to check simulation status:', error);
            }
        };

        poll();
        const pollInterval = setInterval(poll, 3000);

        return () => {
            clearInterval(pollInterval);
        };
    }, [isPolling]);

    return (
        <div className="min-h-screen bg-[#FDFDFC] dark:bg-[#0a0a0a]">
            <Header />
            <main className="mx-auto mt-[-5px] w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-20 xl:px-32">
                {/* Page Header */}
                <div className="flex items-start gap-3 sm:items-center sm:gap-6">
                    <Link href="/scan-results" className="mt-1 sm:mt-0">
                        <ArrowLeft className="h-5 w-5 text-black dark:text-white" />
                    </Link>
                    <div className="flex-1">
                        <h1 className="text-xl font-bold sm:text-lg dark:text-white">
                            Future Appearance Simulation
                        </h1>
                        <p className="text-xs text-gray-600 sm:text-sm dark:text-white/70">
                            See how your {breed} will look 1 and 3 years from
                            now
                        </p>
                    </div>
                </div>

                {/* Warning Card */}
                <Card className="mt-4 bg-orange-50 p-4 outline-1 outline-orange-200 sm:p-6 sm:pl-8 dark:bg-orange-950 dark:outline-orange-800">
                    <p className="text-xs sm:text-sm">
                        <span className="font-bold text-orange-900 dark:text-orange-400">
                            Note:{' '}
                        </span>
                        <span className="text-orange-800 dark:text-orange-300">
                            This prediction shows your dog 1 and 3 years from
                            today based on current age and breed patterns.
                            Actual aging may vary depending on genetics, health,
                            and environment.
                        </span>
                    </p>
                </Card>

                {/* Loading State */}
                {(status === 'pending' || status === 'generating') &&
                    !hasSimulations && (
                        <Card className="mt-6 p-8">
                            <div className="flex flex-col items-center justify-center gap-4">
                                <Loader2 className="h-12 w-12 animate-spin text-blue-500" />
                                <div className="text-center">
                                    <p className="font-semibold text-gray-800 dark:text-gray-200">
                                        {status === 'pending'
                                            ? 'Analyzing current age and features...'
                                            : 'Generating future appearance predictions...'}
                                    </p>
                                    <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                        Creating personalized age progression
                                        images. This takes 20-40 seconds.
                                    </p>
                                </div>
                            </div>
                        </Card>
                    )}

                {/* Failed State */}
                {status === 'failed' && !hasSimulations && (
                    <Card className="mt-6 bg-red-50 p-8 dark:bg-red-950">
                        <div className="flex flex-col items-center justify-center gap-4">
                            <AlertCircle className="h-12 w-12 text-red-500" />
                            <div className="text-center">
                                <p className="font-semibold text-red-800 dark:text-red-200">
                                    Simulation generation failed
                                </p>
                                <p className="mt-2 text-sm text-red-600 dark:text-red-400">
                                    We couldn't generate the age simulations.
                                    Please try again later.
                                </p>
                                <Link
                                    href="/scan-results"
                                    className="mt-4 inline-block rounded-lg bg-red-600 px-6 py-2 text-sm font-medium text-white hover:bg-red-700"
                                >
                                    Back to Results
                                </Link>
                            </div>
                        </div>
                    </Card>
                )}

                {/* Simulation Tabs */}
                {hasSimulations && (
                    <div className="mt-4 flex w-full flex-col gap-6 sm:mt-6">
                        {status === 'generating' && (
                            <div className="flex items-center gap-2 rounded-lg bg-blue-50 p-3 dark:bg-blue-950">
                                <Loader2 className="h-4 w-4 animate-spin text-blue-600" />
                                <p className="text-sm text-blue-800 dark:text-blue-200">
                                    Still generating remaining images...
                                </p>
                            </div>
                        )}

                        <Tabs defaultValue="first" className="w-full">
                            <TabsList className="grid w-full grid-cols-2">
                                <TabsTrigger
                                    value="first"
                                    className="text-xs sm:text-sm"
                                >
                                    In 1 Year
                                </TabsTrigger>
                                <TabsTrigger
                                    value="second"
                                    className="text-xs sm:text-sm"
                                >
                                    In 3 Years
                                </TabsTrigger>
                            </TabsList>

                            {/* +1 Year Tab */}
                            <TabsContent value="first">
                                <Card>
                                    <CardContent className="p-4 sm:p-6">
                                        <div className="flex flex-col gap-6 lg:flex-row lg:gap-8">
                                            <div className="flex-1">
                                                <h3 className="sm:text-md mb-3 text-base font-semibold dark:text-white">
                                                    Current Appearance
                                                </h3>
                                                <div className="mx-auto w-full max-w-md lg:max-w-none">
                                                    <img
                                                        src={getImageUrl(
                                                            currentOriginalImage,
                                                        )}
                                                        alt="Current appearance"
                                                        className="aspect-square w-full rounded-2xl object-cover shadow-lg"
                                                        onError={(e) => {
                                                            console.error(
                                                                'Failed to load original image:',
                                                                currentOriginalImage,
                                                            );
                                                            e.currentTarget.src =
                                                                '/dogpic.jpg';
                                                        }}
                                                    />
                                                </div>
                                                <p className="mt-3 text-sm text-gray-700 dark:text-gray-300">
                                                    How your dog looks today
                                                </p>
                                            </div>
                                            <div className="flex-1">
                                                <h3 className="sm:text-md mb-3 text-base font-semibold dark:text-white">
                                                    In 1 Year
                                                </h3>
                                                <div className="mx-auto w-full max-w-md lg:max-w-none">
                                                    {simulations['1_years'] ? (
                                                        <img
                                                            src={getImageUrl(
                                                                simulations[
                                                                    '1_years'
                                                                ],
                                                            )}
                                                            alt="Appearance in 1 year"
                                                            className="aspect-square w-full rounded-2xl object-cover shadow-lg"
                                                            onError={(e) => {
                                                                console.error(
                                                                    'Failed to load 1-year simulation:',
                                                                    simulations[
                                                                        '1_years'
                                                                    ],
                                                                );
                                                                e.currentTarget.src =
                                                                    '/dogpic.jpg';
                                                            }}
                                                        />
                                                    ) : (
                                                        <div className="flex aspect-square w-full items-center justify-center rounded-2xl bg-gray-100 dark:bg-gray-800">
                                                            <Loader2 className="h-8 w-8 animate-spin text-gray-400" />
                                                        </div>
                                                    )}
                                                </div>
                                                <p className="mt-3 text-sm text-gray-700 dark:text-gray-300">
                                                    How your dog will look one
                                                    year from today
                                                </p>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </TabsContent>

                            {/* +3 Years Tab */}
                            <TabsContent value="second">
                                <Card>
                                    <CardContent className="p-4 sm:p-6">
                                        <div className="flex flex-col gap-6 lg:flex-row lg:gap-8">
                                            <div className="flex-1">
                                                <h3 className="mb-3 text-base font-semibold sm:text-lg dark:text-white">
                                                    Current Appearance
                                                </h3>
                                                <div className="mx-auto w-full max-w-md lg:max-w-none">
                                                    <img
                                                        src={getImageUrl(
                                                            currentOriginalImage,
                                                        )}
                                                        alt="Current appearance"
                                                        className="aspect-square w-full rounded-2xl object-cover shadow-lg"
                                                        onError={(e) => {
                                                            console.error(
                                                                'Failed to load original image:',
                                                                currentOriginalImage,
                                                            );
                                                            e.currentTarget.src =
                                                                '/dogpic.jpg';
                                                        }}
                                                    />
                                                </div>
                                                <p className="mt-3 text-sm text-gray-700 dark:text-gray-300">
                                                    How your dog looks today
                                                </p>
                                            </div>
                                            <div className="flex-1">
                                                <h3 className="mb-3 text-base font-semibold sm:text-lg dark:text-white">
                                                    In 3 Years
                                                </h3>
                                                <div className="mx-auto w-full max-w-md lg:max-w-none">
                                                    {simulations['3_years'] ? (
                                                        <img
                                                            src={getImageUrl(
                                                                simulations[
                                                                    '3_years'
                                                                ],
                                                            )}
                                                            alt="Appearance in 3 years"
                                                            className="aspect-square w-full rounded-2xl object-cover shadow-lg"
                                                            onError={(e) => {
                                                                console.error(
                                                                    'Failed to load 3-year simulation:',
                                                                    simulations[
                                                                        '3_years'
                                                                    ],
                                                                );
                                                                e.currentTarget.src =
                                                                    '/dogpic.jpg';
                                                            }}
                                                        />
                                                    ) : (
                                                        <div className="flex aspect-square w-full items-center justify-center rounded-2xl bg-gray-100 dark:bg-gray-800">
                                                            <Loader2 className="h-8 w-8 animate-spin text-gray-400" />
                                                        </div>
                                                    )}
                                                </div>
                                                <p className="mt-3 text-sm text-gray-700 dark:text-gray-300">
                                                    How your dog will look three
                                                    years from today
                                                </p>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </TabsContent>
                        </Tabs>
                    </div>
                )}
            </main>
        </div>
    );
};

export default ViewSimulation;
