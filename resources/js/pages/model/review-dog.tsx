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

            {/* CHANGED: Adjusted padding (p-4 for mobile, md:p-8 for desktop) */}
            <div className="flex h-full w-full flex-col gap-6 p-4 md:p-8">
                {/* Header Section */}
                <div>
                    <h1 className="text-xl font-bold dark:text-white">
                        Scan Review & Correction
                    </h1>
                    <p className="text-sm text-gray-600 dark:text-white/70">
                        Validate system prediction and provide correction if
                        necessary
                    </p>
                </div>

                {/* Main Content Layout: Stack on mobile, Side-by-side on Large screens */}
                <div className="flex flex-col gap-6 lg:flex-row lg:items-start">
                    {/* LEFT: Image Preview */}
                    {/* CHANGED: Removed fixed height, added width logic */}
                    <Card className="flex w-full flex-col p-6 lg:w-1/2 xl:w-[45%] dark:bg-neutral-900">
                        <h1 className="text-lg font-medium">Image Preview</h1>

                        {/* Image Container - Responsive sizing */}
                        <div className="mt-6 flex flex-1 items-center justify-center rounded-lg bg-gray-50 py-8 dark:bg-black/20">
                            <img
                                src={result?.image}
                                alt="Scanned Dog"
                                className="max-h-[300px] w-auto rounded-lg object-contain shadow-md lg:max-h-[400px]"
                            />
                        </div>

                        {/* Metadata Section */}
                        <div className="mt-6 space-y-3 px-2 md:px-4">
                            <div className="flex items-center justify-between border-b border-gray-100 pb-2 dark:border-gray-800">
                                <p className="text-sm text-gray-600 dark:text-white/70">
                                    Scan ID
                                </p>
                                <p className="font-mono text-sm font-medium">
                                    {result?.scan_id}
                                </p>
                            </div>
                            <div className="flex items-center justify-between border-b border-gray-100 pb-2 dark:border-gray-800">
                                <p className="text-sm text-gray-600 dark:text-white/70">
                                    Upload Date
                                </p>
                                <p className="text-sm font-medium">
                                    {result?.created_at
                                        ?.replace('T', ' ')
                                        .substring(0, 16)}
                                </p>
                            </div>
                            <div className="flex items-center justify-between">
                                <p className="text-sm text-gray-600 dark:text-white/70">
                                    File Name
                                </p>
                                <p className="max-w-[150px] truncate text-sm font-medium">
                                    {result?.image.split('/').pop()}
                                </p>
                            </div>
                        </div>
                    </Card>

                    {/* RIGHT: Predictions & Form */}
                    <div className="flex w-full flex-col gap-6 lg:flex-1">
                        {/* Model Prediction Card */}
                        <Card className="px-6 py-6 dark:bg-neutral-900">
                            <div className="mb-6 flex items-center justify-between">
                                <h1 className="font-medium">
                                    Model Prediction
                                </h1>
                                <Badge
                                    className="px-3 py-1"
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

                            <div className="space-y-6 px-0 md:px-2">
                                <div className="text-center">
                                    <h1 className="text-3xl font-bold text-gray-900 dark:text-white">
                                        {result?.breed}
                                    </h1>
                                    <p className="mt-1 text-xs tracking-wide text-gray-500 uppercase">
                                        Primary Prediction
                                    </p>
                                </div>

                                <div className="rounded-lg bg-gray-50 p-4 dark:bg-neutral-800/50">
                                    <h1 className="mb-3 text-sm font-medium text-gray-600 dark:text-white/70">
                                        Top Alternatives
                                    </h1>
                                    <div className="space-y-4">
                                        {result?.top_predictions
                                            ?.slice(0, 3)
                                            .map((prediction, index) => (
                                                <div
                                                    key={index}
                                                    className="space-y-1.5"
                                                >
                                                    <div className="flex justify-between text-sm">
                                                        <span className="text-gray-700 dark:text-white/90">
                                                            {prediction.breed}
                                                        </span>
                                                        <span className="font-semibold text-gray-900 dark:text-white">
                                                            {
                                                                prediction.confidence
                                                            }
                                                            %
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
                            </div>
                        </Card>

                        {/* Correction Form Card */}
                        <Card className="flex-1 px-6 py-6 dark:bg-neutral-900">
                            <h1 className="mb-4 flex items-center gap-2 font-medium">
                                Veterinarian Correction
                            </h1>

                            <form
                                onSubmit={submitCorrection}
                                className="md:px-2"
                            >
                                <div className="space-y-2">
                                    <label className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Correct Breed (if different)
                                    </label>

                                    <Input
                                        value={data.correct_breed}
                                        onChange={(e) =>
                                            setData(
                                                'correct_breed',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Type correct breed here..."
                                        className="w-full focus-visible:ring-2 focus-visible:ring-blue-500 dark:border-white/20 dark:bg-white/5"
                                    />
                                    {errors.correct_breed && (
                                        <span className="text-sm font-medium text-red-500">
                                            {errors.correct_breed}
                                        </span>
                                    )}
                                </div>

                                <Card className="mt-5 border border-blue-200 bg-blue-50/50 p-4 dark:border-blue-900/50 dark:bg-blue-900/20">
                                    <div className="flex gap-3">
                                        <div className="shrink-0 text-blue-600 dark:text-blue-400">
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 24 24"
                                                fill="currentColor"
                                                className="h-5 w-5"
                                            >
                                                <path
                                                    fillRule="evenodd"
                                                    d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12ZM12 8.25a.75.75 0 0 1 .75.75v3.75a.75.75 0 0 1-1.5 0V9a.75.75 0 0 1 .75-.75Zm0 8.25a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z"
                                                    clipRule="evenodd"
                                                />
                                            </svg>
                                        </div>
                                        <p className="text-xs leading-relaxed text-blue-900 dark:text-blue-100">
                                            <span className="mb-1 block font-bold">
                                                Impact on Model:
                                            </span>
                                            Submitting this will instantly
                                            update the system's memory. The AI
                                            will recognize this specific dog as
                                            <span className="mx-1 font-bold">
                                                "{data.correct_breed || '...'}"
                                            </span>
                                            in future scans.
                                        </p>
                                    </div>
                                </Card>

                                <Button
                                    className="mt-6 h-11 w-full bg-blue-600 text-white hover:bg-blue-700"
                                    disabled={processing}
                                >
                                    {processing
                                        ? 'Learning from correction...'
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
