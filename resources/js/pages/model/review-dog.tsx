import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/react';

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
    scan_id: string; // your unique ID
    image: string; // path to the stored image
    breed: string; // primary predicted breed
    confidence: number; // confidence for the primary breed
    top_predictions: PredictionResult[]; // top 5 predictions
    created_at?: string; // optional timestamp from DB
    updated_at?: string; // optional timestamp from DB
};

type PageProps = {
    result?: Result;
};

export default function Dashboard() {
    const { result } = usePage<PageProps>().props;
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
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
                    <Card className="h-[630px] flex-1 px-6 dark:bg-neutral-900">
                        <h1 className="font-medium">Image Preview</h1>

                        <img
                            src={`/storage/${result?.image}`}
                            alt=""
                            className="mx-auto h-[72%] w-[72%] rounded-lg"
                        />
                        <div className="px-10">
                            <div className="flex justify-between">
                                <p>Scan ID:</p>
                                <p className="font-medium">{result?.scan_id}</p>
                            </div>
                            <div className="flex justify-between">
                                <p>Upload Date:</p>
                                <p className="font-medium">
                                    {result?.created_at
                                        ?.replace('T', ' ')
                                        .substring(0, 16)}
                                </p>
                            </div>
                        </div>
                    </Card>
                    <div className="flex-1">
                        <Card className="px-6 dark:bg-neutral-900">
                            <h1 className="font-medium">Model Prediction</h1>
                            <div className="space-y-2 px-5">
                                <div className="flex justify-between">
                                    <p className="text-gray-600 dark:text-white/70">
                                        Predicted Breed
                                    </p>
                                    <p className="font-medium">
                                        {result?.confidence}% confidence
                                    </p>
                                </div>

                                <h1 className="text-xl font-medium">
                                    {result?.breed}
                                </h1>
                                <h1 className="text-gray-600 dark:text-white/70">
                                    Top Predictions
                                </h1>
                                {result?.top_predictions
                                    ?.slice(0, 3)
                                    .map((prediction, index) => (
                                        <div key={index}>
                                            <div className="flex justify-between">
                                                <p className="text-gray-600 dark:text-white/70">
                                                    {prediction.breed}
                                                </p>
                                                <p className="font-medium">
                                                    {prediction.confidence}%
                                                </p>
                                            </div>
                                            <Progress
                                                value={prediction.confidence}
                                                className={` ${
                                                    prediction.confidence >= 80
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
                        </Card>
                        <Card className="mt-4 px-6 dark:bg-neutral-900">
                            <h1 className="font-medium">
                                Veterinarian Correction
                            </h1>
                            <div className="px-5">
                                <p>Correct Breed (if different)</p>
                                <Input className="mt-2 w-full focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-0 dark:border-white/20 dark:bg-white/10" />

                                <Card className="mt-4 bg-blue-50 pl-8 outline outline-blue-300 dark:bg-slate-900 dark:outline-blue-800">
                                    <h1 className="text-sm">
                                        <span className="font-bold text-blue-900 dark:text-blue-300">
                                            Note:{' '}
                                        </span>{' '}
                                        <span className="text-blue-800 dark:text-blue-400">
                                            Your correction will be added to the
                                            training dataset and used to improve
                                            future model accuracy.
                                        </span>
                                    </h1>
                                </Card>
                                <Button className="mt-4 w-full">
                                    Submit Correction
                                </Button>
                            </div>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
