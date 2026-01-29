import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Scan Results',
        href: '/model/scan-results',
    },
    {
        title: 'Review',
        href: '/model/review-dog',
    },
];

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
    created_at?: string;
};

type PageProps = {
    result?: Result;
};

export default function ReviewDog() {
    const { result } = usePage<PageProps>().props;

    // Form handling
    const { data, setData, post, processing, errors, reset } = useForm({
        scan_id: result?.scan_id || '',
        correct_breed: '',
    });

    const submitCorrection: FormEventHandler = (e) => {
        e.preventDefault();
        post('/model/correct', {
            onSuccess: () => reset('correct_breed'),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Review Scan" />
            <div className="flex h-full flex-col gap-4 overflow-x-auto rounded-xl p-4 px-10">
                <div>
                    <h1 className="text-lg font-bold dark:text-white">
                        Scan Review & Correction
                    </h1>
                    <h1 className="text-sm text-gray-600 dark:text-white/70">
                        Validate system prediction and provide correction if
                        necessary
                    </h1>
                </div>

                <div className="flex gap-4">
                    {/* LEFT: Image Preview */}
                    <Card className="h-[630px] flex-1 px-6 dark:bg-neutral-900">
                        <h1 className="mt-4 font-medium">Image Preview</h1>

                        <img
                            src={`/storage/${result?.image}`}
                            alt="Scanned Dog"
                            className="mx-auto mt-6 h-[72%] w-[72%] rounded-lg object-cover shadow-sm"
                        />
                        <div className="mt-6 px-10">
                            <div className="flex justify-between">
                                <p className="text-gray-600 dark:text-white/70">
                                    Scan ID:
                                </p>
                                <p className="font-medium">{result?.scan_id}</p>
                            </div>
                            <div className="mt-2 flex justify-between">
                                <p className="text-gray-600 dark:text-white/70">
                                    Upload Date:
                                </p>
                                <p className="font-medium">
                                    {result?.created_at
                                        ?.replace('T', ' ')
                                        .substring(0, 16)}
                                </p>
                            </div>
                        </div>
                    </Card>

                    {/* RIGHT: Predictions & Form */}
                    <div className="flex flex-1 flex-col gap-4">
                        {/* Model Prediction Card */}
                        <Card className="px-6 py-4 dark:bg-neutral-900">
                            <div className="mb-4 flex items-center justify-between">
                                <h1 className="font-medium">
                                    Model Prediction
                                </h1>
                                <Badge
                                    variant={
                                        result?.confidence &&
                                        result.confidence > 80
                                            ? 'default'
                                            : 'secondary'
                                    }
                                >
                                    {result?.confidence}% Confidence
                                </Badge>
                            </div>

                            <div className="space-y-4 px-5">
                                <h1 className="py-2 text-center text-2xl font-bold">
                                    {result?.breed}
                                </h1>

                                <h1 className="mt-4 mb-2 text-sm text-gray-600 dark:text-white/70">
                                    Top Predictions
                                </h1>
                                <div className="space-y-3">
                                    {result?.top_predictions
                                        ?.slice(0, 3)
                                        .map((prediction, index) => (
                                            <div
                                                key={index}
                                                className="space-y-1"
                                            >
                                                <div className="flex justify-between text-sm">
                                                    <span className="text-gray-700 dark:text-white/80">
                                                        {prediction.breed}
                                                    </span>
                                                    <span className="font-medium">
                                                        {prediction.confidence}%
                                                    </span>
                                                </div>
                                                <Progress
                                                    value={
                                                        prediction.confidence
                                                    }
                                                    className={`h-2 ${
                                                        prediction.confidence >=
                                                        80
                                                            ? '[&>div]:bg-green-600'
                                                            : prediction.confidence >=
                                                                60
                                                              ? '[&>div]:bg-yellow-500'
                                                              : prediction.confidence >=
                                                                  40
                                                                ? '[&>div]:bg-orange-500'
                                                                : '[&>div]:bg-red-500'
                                                    }`}
                                                />
                                            </div>
                                        ))}
                                </div>
                            </div>
                        </Card>

                        {/* Correction Form Card */}
                        <Card className="flex-1 px-6 py-6 dark:bg-neutral-900">
                            <h1 className="mb-4 font-medium">
                                Veterinarian Correction
                            </h1>

                            <form onSubmit={submitCorrection} className="px-5">
                                <p className="mb-2 text-sm text-gray-600 dark:text-white/70">
                                    Correct Breed (if different)
                                </p>

                                <Input
                                    value={data.correct_breed}
                                    onChange={(e) =>
                                        setData('correct_breed', e.target.value)
                                    }
                                    placeholder="Type correct breed here..."
                                    className="w-full focus-visible:ring-2 focus-visible:ring-blue-500 dark:border-white/20 dark:bg-white/10"
                                />
                                {errors.correct_breed && (
                                    <span className="mt-1 text-sm text-red-500">
                                        {errors.correct_breed}
                                    </span>
                                )}

                                <Card className="mt-4 border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-slate-900">
                                    <h1 className="text-xs leading-relaxed">
                                        <span className="font-bold text-blue-900 dark:text-blue-300">
                                            Note:{' '}
                                        </span>
                                        <span className="text-blue-800 dark:text-blue-400">
                                            Submitting this will instantly
                                            update the system's memory. The AI
                                            will recognize this specific dog as
                                            "{data.correct_breed || '...'}" in
                                            future scans.
                                        </span>
                                    </h1>
                                </Card>

                                <Button
                                    className="mt-4 w-full bg-blue-600 text-white hover:bg-blue-700"
                                    disabled={processing}
                                >
                                    {processing
                                        ? 'Learning...'
                                        : 'Submit Correction'}
                                </Button>
                            </form>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
