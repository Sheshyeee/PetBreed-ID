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
        <div className="min-h-screen bg-[#FDFDFC] dark:bg-[#0a0a0a]">
            <Header />
            <main className="mx-auto mt-[-5px] w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-20 xl:px-32">
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

                {(status === 'pending' || status === 'generating') &&
                    !hasSimulations && (
                        <Card className="mt-6 p-8">
                            <div className="flex flex-col items-center justify-center gap-4">
                                <Loader2 className="h-12 w-12 animate-spin text-blue-500" />
                                <div className="text-center">
                                    <p className="font-semibold text-gray-800 dark:text-gray-200">
                                        {status === 'pending'
                                            ? 'Analyzing...'
                                            : 'Generating predictions...'}
                                    </p>
                                    <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                        Creating age progression images. This
                                        takes 40-60 seconds.
                                    </p>
                                    <p className="mt-1 text-xs text-gray-500">
                                        Check {pollingAttempts}/
                                        {MAX_POLLING_ATTEMPTS}
                                    </p>
                                </div>
                            </div>
                        </Card>
                    )}

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

                        <Tabs
                            defaultValue="first"
                            className="w-full"
                            key={lastUpdate}
                        >
                            <TabsList className="grid w-full grid-cols-2">
                                <TabsTrigger value="first">
                                    In 1 Year
                                </TabsTrigger>
                                <TabsTrigger value="second">
                                    In 3 Years
                                </TabsTrigger>
                            </TabsList>

                            <TabsContent value="first">
                                <Card>
                                    <CardContent className="p-4 sm:p-6">
                                        <div className="flex flex-col gap-6 lg:flex-row lg:gap-8">
                                            <div className="flex-1">
                                                <h3 className="mb-3 text-base font-semibold dark:text-white">
                                                    Current Appearance
                                                </h3>
                                                <div className="mx-auto w-full max-w-md lg:max-w-none">
                                                    <img
                                                        src={getImageUrl(
                                                            currentOriginalImage,
                                                        )}
                                                        alt="Current appearance"
                                                        className="w-full rounded-2xl bg-gray-50 object-contain shadow-lg dark:bg-gray-900"
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
                                                <p className="mt-3 text-sm text-gray-700 dark:text-gray-300">
                                                    How your dog looks today
                                                </p>
                                            </div>
                                            <div className="flex-1">
                                                <h3 className="mb-3 text-base font-semibold dark:text-white">
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
                                                            className="w-full rounded-2xl bg-gray-50 object-contain shadow-lg dark:bg-gray-900"
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
                                                                <Loader2 className="mx-auto h-8 w-8 animate-spin text-gray-400" />
                                                                <p className="mt-2 text-xs text-gray-500">
                                                                    Generating...
                                                                </p>
                                                            </div>
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

                            <TabsContent value="second">
                                <Card>
                                    <CardContent className="p-4 sm:p-6">
                                        <div className="flex flex-col gap-6 lg:flex-row lg:gap-8">
                                            <div className="flex-1">
                                                <h3 className="mb-3 text-base font-semibold dark:text-white">
                                                    Current Appearance
                                                </h3>
                                                <div className="mx-auto w-full max-w-md lg:max-w-none">
                                                    <img
                                                        src={getImageUrl(
                                                            currentOriginalImage,
                                                        )}
                                                        alt="Current appearance"
                                                        className="w-full rounded-2xl bg-gray-50 object-contain shadow-lg dark:bg-gray-900"
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
                                                <p className="mt-3 text-sm text-gray-700 dark:text-gray-300">
                                                    How your dog looks today
                                                </p>
                                            </div>
                                            <div className="flex-1">
                                                <h3 className="mb-3 text-base font-semibold dark:text-white">
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
                                                            className="w-full rounded-2xl bg-gray-50 object-contain shadow-lg dark:bg-gray-900"
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
                                                                <Loader2 className="mx-auto h-8 w-8 animate-spin text-gray-400" />
                                                                <p className="mt-2 text-xs text-gray-500">
                                                                    Generating...
                                                                </p>
                                                            </div>
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
