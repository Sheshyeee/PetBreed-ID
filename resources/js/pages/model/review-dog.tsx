import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

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

export default function Dashboard() {
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
                    <Card className="h-[630px] flex-1 px-6">
                        <h1 className="font-medium">Image Preview</h1>

                        <img
                            src="/dogPic.jpg"
                            alt=""
                            className="mx-auto h-[72%] w-[72%] rounded-lg"
                        />
                        <div className="px-10">
                            <div className="flex justify-between">
                                <p>Scan ID:</p>
                                <p className="font-medium">1234</p>
                            </div>
                            <div className="flex justify-between">
                                <p>Upload Date:</p>
                                <p className="font-medium">2024-01-08 14:32</p>
                            </div>
                        </div>
                    </Card>
                    <div className="flex-1">
                        <Card className="px-6">
                            <h1 className="font-medium">Model Prediction</h1>
                            <div className="space-y-2 px-5">
                                <div className="flex justify-between">
                                    <p className="text-gray-600">
                                        Predicted Breed
                                    </p>
                                    <p className="font-medium text-green-700">
                                        94% confidence
                                    </p>
                                </div>

                                <h1 className="text-xl font-medium">
                                    Golden Retriever
                                </h1>
                                <h1 className="text-gray-600">
                                    Top 3 Predictions
                                </h1>
                                <div>
                                    <div className="flex justify-between">
                                        <p className="text-gray-600">
                                            Golden Retriever
                                        </p>
                                        <p className="font-medium">94%</p>
                                    </div>
                                    <Progress
                                        value={94}
                                        className="[&>div]:bg-green-600"
                                    />
                                </div>
                                <div>
                                    <div className="flex justify-between">
                                        <p className="text-gray-600">
                                            Predicted Breed
                                        </p>
                                        <p className="font-medium">94%</p>
                                    </div>
                                    <Progress
                                        value={10}
                                        className="[&>div]:bg-green-600"
                                    />
                                </div>
                                <div>
                                    <div className="flex justify-between">
                                        <p className="text-gray-600">
                                            Predicted Breed
                                        </p>
                                        <p className="font-medium">94%</p>
                                    </div>
                                    <Progress
                                        value={5}
                                        className="[&>div]:bg-green-600"
                                    />
                                </div>
                            </div>
                        </Card>
                        <Card className="mt-4 px-6">
                            <h1 className="font-medium">
                                Veterinarian Correction
                            </h1>
                            <div className="px-5">
                                <p>Correct Breed (if different)</p>
                                <input type="text" className="w-full outline" />
                                <Card className="mt-4 bg-blue-50 pl-8 outline outline-blue-300">
                                    <h1 className="text-sm">
                                        <span className="font-bold text-blue-900">
                                            Note:{' '}
                                        </span>{' '}
                                        <span className="text-blue-800">
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
