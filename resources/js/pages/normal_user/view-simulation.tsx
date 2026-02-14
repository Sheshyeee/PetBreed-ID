import Header from '@/components/header';
import { Card, CardContent } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Link } from '@inertiajs/react';
import axios from 'axios';
import { AlertCircle, ArrowLeft, Loader2 } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

interface SimulationData {
    '1_years': string | null;
    '3_years': string | null;
}

interface ViewSimulationProps {
    breed: string;
    originalImage: string;
    simulations: SimulationData;
    simulation_status?: 'pending' | 'generating' | 'complete' | 'failed';
    scan_id: string;
}

const ViewSimulation: React.FC<ViewSimulationProps> = ({
    breed,
    originalImage,
    simulations: initialSimulations,
    simulation_status: initialStatus = 'pending',
    scan_id,
}) => {
    const [simulations, setSimulations] =
        useState<SimulationData>(initialSimulations);
    const [status, setStatus] = useState<string>(initialStatus);
    const [currentOriginalImage, setCurrentOriginalImage] =
        useState<string>(originalImage);
    const [isPolling, setIsPolling] = useState(
        initialStatus !== 'complete' && initialStatus !== 'failed',
    );
    const [pollingAttempts, setPollingAttempts] = useState(0);
    const [lastUpdate, setLastUpdate] = useState<number>(Date.now());
    const MAX_POLLING_ATTEMPTS = 120;

    // Log initial props for debugging
    useEffect(() => {
        console.log('🎬 COMPONENT INITIALIZED', {
            breed,
            originalImage_prop: originalImage,
            simulations_prop: initialSimulations,
            status_prop: initialStatus,
            scan_id,
        });
    }, []); // Only run once on mount

    // Simple image URL handler - trust the backend
    const getImageUrl = useCallback((url: string | null): string => {
        if (!url || url.trim() === '') {
            console.warn('⚠️ No URL provided, using fallback');
            return '/dogpic.jpg';
        }

        console.log('✅ Using image URL:', url);
        return url;
    }, []);

    const hasSimulations = Boolean(
        simulations && (simulations['1_years'] || simulations['3_years']),
    );

    // Comprehensive state logging
    useEffect(() => {
        console.log('📊 CURRENT STATE', {
            scan_id,
            status,
            breed,
            currentOriginalImage,
            simulations_1yr: simulations['1_years'],
            simulations_3yr: simulations['3_years'],
            hasSimulations,
            isPolling,
            pollingAttempts,
            lastUpdate,
        });
    }, [
        scan_id,
        status,
        breed,
        currentOriginalImage,
        simulations,
        hasSimulations,
        isPolling,
        pollingAttempts,
        lastUpdate,
    ]);

    useEffect(() => {
        if (!isPolling || pollingAttempts >= MAX_POLLING_ATTEMPTS) {
            if (pollingAttempts >= MAX_POLLING_ATTEMPTS) {
                console.error('❌ Max polling attempts reached');
                setStatus('failed');
                setIsPolling(false);
            }
            return;
        }

        console.log(
            `🔄 Poll #${pollingAttempts + 1}/${MAX_POLLING_ATTEMPTS} for scan_id: ${scan_id}`,
        );

        const poll = async () => {
            try {
                const timestamp = Date.now();
                const url = `/api/simulation-status?scan_id=${scan_id}&t=${timestamp}`;

                console.log('📡 Making API request:', url);

                const response = await axios.get(url, {
                    headers: {
                        'Cache-Control': 'no-cache, no-store, must-revalidate',
                        Pragma: 'no-cache',
                        Expires: '0',
                    },
                });

                const data = response.data;

                console.log('📥 API RESPONSE:', {
                    status: data.status,
                    original_image: data.original_image,
                    simulations_1yr: data.simulations['1_years'],
                    simulations_3yr: data.simulations['3_years'],
                    breed: data.breed,
                    timestamp: data.timestamp,
                    full_response: data,
                });

                // Check if data changed
                const dataChanged =
                    data.status !== status ||
                    data.simulations['1_years'] !== simulations['1_years'] ||
                    data.simulations['3_years'] !== simulations['3_years'] ||
                    data.original_image !== currentOriginalImage;

                if (dataChanged) {
                    console.log('✨ DATA CHANGED - Updating state', {
                        old_status: status,
                        new_status: data.status,
                        old_original: currentOriginalImage,
                        new_original: data.original_image,
                        old_1yr: simulations['1_years'],
                        new_1yr: data.simulations['1_years'],
                        old_3yr: simulations['3_years'],
                        new_3yr: data.simulations['3_years'],
                    });

                    setStatus(data.status);
                    setSimulations({
                        '1_years': data.simulations['1_years'],
                        '3_years': data.simulations['3_years'],
                    });

                    if (data.original_image) {
                        console.log(
                            '🖼️ Updating original image:',
                            data.original_image,
                        );
                        setCurrentOriginalImage(data.original_image);
                    }

                    setLastUpdate(Date.now());
                } else {
                    console.log('⏸️ No changes detected');
                }

                setPollingAttempts((prev) => prev + 1);

                if (data.status === 'complete' || data.status === 'failed') {
                    console.log(
                        `✅ ${data.status.toUpperCase()} - stopping poll`,
                    );
                    setIsPolling(false);
                }
            } catch (error: any) {
                console.error('❌ POLL ERROR:', {
                    message: error.message,
                    response: error.response?.data,
                    status: error.response?.status,
                    full_error: error,
                });
                setPollingAttempts((prev) => prev + 1);
            }
        };

        poll();
        const pollInterval = setInterval(poll, 3000);

        return () => {
            clearInterval(pollInterval);
        };
    }, [
        isPolling,
        pollingAttempts,
        status,
        simulations,
        scan_id,
        currentOriginalImage,
    ]);

    return (
        <div className="min-h-screen bg-gray-50 dark:bg-gray-950">
            <Header />
            <main className="mx-auto w-full max-w-7xl px-4 pt-4 pb-8 sm:px-10 lg:px-8">
                <div className="mb-6 flex items-start gap-4 sm:items-center sm:gap-6">
                    <Link href="/scan-results" className="mt-1 sm:mt-0">
                        <ArrowLeft className="h-5 w-5 text-gray-900 dark:text-white" />
                    </Link>
                    <div className="flex-1">
                        <h1 className="text-2xl font-bold text-gray-900 sm:text-3xl dark:text-white">
                            Future Appearance Simulation
                        </h1>
                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            See how your {breed} will look 1 and 3 years from
                            now
                        </p>
                    </div>
                </div>

                <Card className="mt-6 border-orange-200 bg-orange-50 p-6 sm:p-8 dark:border-orange-800 dark:bg-orange-950/40">
                    <p className="text-sm leading-relaxed sm:text-base">
                        <span className="font-bold text-orange-900 dark:text-orange-200">
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

                {(status === 'pending' || status === 'generating') &&
                    !hasSimulations && (
                        <Card className="mt-6 border-gray-200 bg-white p-10 sm:p-12 dark:border-gray-800 dark:bg-gray-900">
                            <div className="flex flex-col items-center justify-center gap-6">
                                <Loader2 className="h-16 w-16 animate-spin text-blue-500 dark:text-blue-400" />
                                <div className="text-center">
                                    <p className="text-lg font-semibold text-gray-900 dark:text-white">
                                        {status === 'pending'
                                            ? 'Analyzing...'
                                            : 'Generating predictions...'}
                                    </p>
                                    <p className="mt-3 text-base text-gray-600 dark:text-gray-400">
                                        Creating age progression images. This
                                        takes 40-60 seconds.
                                    </p>
                                    <p className="mt-2 text-sm text-gray-500 dark:text-gray-500">
                                        Check {pollingAttempts}/
                                        {MAX_POLLING_ATTEMPTS}
                                    </p>
                                </div>
                            </div>
                        </Card>
                    )}

                {status === 'failed' && !hasSimulations && (
                    <Card className="mt-6 border-red-200 bg-red-50 p-10 sm:p-12 dark:border-red-800 dark:bg-red-950/40">
                        <div className="flex flex-col items-center justify-center gap-6">
                            <AlertCircle className="h-16 w-16 text-red-600 dark:text-red-400" />
                            <div className="text-center">
                                <p className="text-lg font-semibold text-red-900 dark:text-red-200">
                                    Simulation generation failed
                                </p>
                                <p className="mt-3 text-base text-red-700 dark:text-red-300">
                                    We couldn't generate the age simulations.
                                    Please try again later.
                                </p>
                                <Link
                                    href="/scan-results"
                                    className="mt-6 inline-block rounded-lg bg-red-600 px-8 py-3 text-base font-medium text-white transition-colors hover:bg-red-700 dark:bg-red-700 dark:hover:bg-red-800"
                                >
                                    Back to Results
                                </Link>
                            </div>
                        </div>
                    </Card>
                )}

                {hasSimulations && (
                    <div className="mt-6 flex w-full flex-col gap-6">
                        {status === 'generating' && (
                            <div className="flex items-center gap-3 rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-950/40">
                                <Loader2 className="h-5 w-5 animate-spin text-blue-600 dark:text-blue-400" />
                                <p className="text-sm font-medium text-blue-900 dark:text-blue-200">
                                    Still generating remaining images...
                                </p>
                            </div>
                        )}

                        <Tabs
                            defaultValue="first"
                            className="w-full"
                            key={lastUpdate}
                        >
                            <TabsList className="grid w-full grid-cols-2 bg-gray-200 dark:bg-gray-800">
                                <TabsTrigger
                                    value="first"
                                    className="text-base font-medium data-[state=active]:bg-white dark:data-[state=active]:bg-gray-900"
                                >
                                    In 1 Year
                                </TabsTrigger>
                                <TabsTrigger
                                    value="second"
                                    className="text-base font-medium data-[state=active]:bg-white dark:data-[state=active]:bg-gray-900"
                                >
                                    In 3 Years
                                </TabsTrigger>
                            </TabsList>

                            <TabsContent value="first">
                                <Card className="border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                                    <CardContent className="p-6 sm:p-8 lg:p-10">
                                        <div className="flex flex-col gap-8 lg:flex-row lg:gap-10">
                                            <div className="flex-1">
                                                <h3 className="mb-4 text-lg font-bold text-gray-900 dark:text-white">
                                                    Current Appearance
                                                </h3>
                                                <div className="mx-auto w-full max-w-md lg:max-w-none">
                                                    <img
                                                        src={getImageUrl(
                                                            currentOriginalImage,
                                                        )}
                                                        alt="Current appearance"
                                                        className="w-full rounded-2xl bg-gray-50 object-contain shadow-xl ring-1 ring-black/5 dark:bg-gray-800 dark:ring-white/10"
                                                        key={`orig-${lastUpdate}`}
                                                        onError={(e) => {
                                                            console.error(
                                                                '❌ Image load failed:',
                                                                currentOriginalImage,
                                                            );
                                                            e.currentTarget.src =
                                                                '/dogpic.jpg';
                                                        }}
                                                        onLoad={() => {
                                                            console.log(
                                                                '✅ Original image loaded:',
                                                                currentOriginalImage,
                                                            );
                                                        }}
                                                    />
                                                </div>
                                                <p className="mt-4 text-sm text-gray-700 dark:text-gray-300">
                                                    How your dog looks today
                                                </p>
                                            </div>
                                            <div className="flex-1">
                                                <h3 className="mb-4 text-lg font-bold text-gray-900 dark:text-white">
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
                                                            className="w-full rounded-2xl bg-gray-50 object-contain shadow-xl ring-1 ring-black/5 dark:bg-gray-800 dark:ring-white/10"
                                                            key={`1yr-${lastUpdate}`}
                                                            onError={(e) => {
                                                                console.error(
                                                                    '❌ 1-year image load failed',
                                                                );
                                                                e.currentTarget.src =
                                                                    '/dogpic.jpg';
                                                            }}
                                                            onLoad={() => {
                                                                console.log(
                                                                    '✅ 1-year image loaded',
                                                                );
                                                            }}
                                                        />
                                                    ) : (
                                                        <div className="flex aspect-square w-full items-center justify-center rounded-2xl bg-gray-100 dark:bg-gray-800">
                                                            <div className="text-center">
                                                                <Loader2 className="mx-auto h-10 w-10 animate-spin text-gray-400" />
                                                                <p className="mt-3 text-sm text-gray-500">
                                                                    Generating...
                                                                </p>
                                                            </div>
                                                        </div>
                                                    )}
                                                </div>
                                                <p className="mt-4 text-sm text-gray-700 dark:text-gray-300">
                                                    How your dog will look one
                                                    year from today
                                                </p>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </TabsContent>

                            <TabsContent value="second">
                                <Card className="border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                                    <CardContent className="p-6 sm:p-8 lg:p-10">
                                        <div className="flex flex-col gap-8 lg:flex-row lg:gap-10">
                                            <div className="flex-1">
                                                <h3 className="mb-4 text-lg font-bold text-gray-900 dark:text-white">
                                                    Current Appearance
                                                </h3>
                                                <div className="mx-auto w-full max-w-md lg:max-w-none">
                                                    <img
                                                        src={getImageUrl(
                                                            currentOriginalImage,
                                                        )}
                                                        alt="Current appearance"
                                                        className="w-full rounded-2xl bg-gray-50 object-contain shadow-xl ring-1 ring-black/5 dark:bg-gray-800 dark:ring-white/10"
                                                        key={`orig2-${lastUpdate}`}
                                                        onError={(e) => {
                                                            console.error(
                                                                '❌ Image load failed:',
                                                                currentOriginalImage,
                                                            );
                                                            e.currentTarget.src =
                                                                '/dogpic.jpg';
                                                        }}
                                                    />
                                                </div>
                                                <p className="mt-4 text-sm text-gray-700 dark:text-gray-300">
                                                    How your dog looks today
                                                </p>
                                            </div>
                                            <div className="flex-1">
                                                <h3 className="mb-4 text-lg font-bold text-gray-900 dark:text-white">
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
                                                            className="w-full rounded-2xl bg-gray-50 object-contain shadow-xl ring-1 ring-black/5 dark:bg-gray-800 dark:ring-white/10"
                                                            key={`3yr-${lastUpdate}`}
                                                            onError={(e) => {
                                                                console.error(
                                                                    '❌ 3-year image load failed',
                                                                );
                                                                e.currentTarget.src =
                                                                    '/dogpic.jpg';
                                                            }}
                                                            onLoad={() => {
                                                                console.log(
                                                                    '✅ 3-year image loaded',
                                                                );
                                                            }}
                                                        />
                                                    ) : (
                                                        <div className="flex aspect-square w-full items-center justify-center rounded-2xl bg-gray-100 dark:bg-gray-800">
                                                            <div className="text-center">
                                                                <Loader2 className="mx-auto h-10 w-10 animate-spin text-gray-400" />
                                                                <p className="mt-3 text-sm text-gray-500">
                                                                    Generating...
                                                                </p>
                                                            </div>
                                                        </div>
                                                    )}
                                                </div>
                                                <p className="mt-4 text-sm text-gray-700 dark:text-gray-300">
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
