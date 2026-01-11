import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { Head } from '@inertiajs/react';
import { CircleCheckBig, Clock, TriangleAlert, Zap } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Training Queue',
        href: '/model/training-queue',
    },
];

export default function Dashboard() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-col gap-4 overflow-x-auto rounded-xl p-4 px-10">
                <div>
                    <h1 className="text-lg font-bold dark:text-white">
                        Model Feedback & Training Queue
                    </h1>
                    <h1 className="text-sm text-gray-600 dark:text-white/70">
                        Track corrections and monitor model retraining progress
                    </h1>
                </div>
                <div className="flex gap-4">
                    <Card className="flex flex-1 flex-row justify-between px-4 py-4 dark:bg-neutral-900">
                        <div className="">
                            <h1 className="text-sm text-gray-600 dark:text-white/80">
                                Pending
                            </h1>
                            <p className="mt-[-5px] text-lg font-bold">1000</p>
                        </div>
                        <div className="flex w-[50px] items-center justify-center rounded-md bg-blue-600 p-2">
                            <Clock className="text-white" />
                        </div>
                    </Card>
                    <Card className="flex flex-1 flex-row justify-between px-4 py-4 dark:bg-neutral-900">
                        <div className="">
                            <h1 className="text-sm text-gray-600 dark:text-white/80">
                                Added to Dataset
                            </h1>
                            <p className="mt-[-5px] text-lg font-bold">1000</p>
                        </div>
                        <div className="flex w-[50px] items-center justify-center rounded-md bg-blue-600 p-2">
                            <CircleCheckBig className="text-white" />
                        </div>
                    </Card>
                    <Card className="flex flex-1 flex-row justify-between px-4 py-4 dark:bg-neutral-900">
                        <div className="">
                            <h1 className="text-sm text-gray-600 dark:text-white/80">
                                Used in Training
                            </h1>

                            <p className="mt-[-5px] text-lg font-bold">1000</p>
                        </div>
                        <div className="flex w-[50px] items-center justify-center rounded-md bg-blue-600 p-2">
                            <Zap className="text-white" />
                        </div>
                    </Card>
                </div>

                <Card className="px-6 dark:bg-neutral-900">
                    <h1 className="font-medium">Retraining Status</h1>
                    <Card className="bg-blue-50 px-8 pl-8 outline outline-blue-300 dark:bg-slate-900 dark:outline-blue-800">
                        <div className="flex gap-4">
                            <TriangleAlert className="text-blue dark:text-blue-400" />
                            <div className="flex w-full flex-col gap-2">
                                <span className="text-sm font-bold text-blue-900 dark:text-blue-300">
                                    Collecting Corrections
                                </span>
                                <span className="text-sm text-blue-800 dark:text-blue-400">
                                    Need 49 more corrections before retraining
                                    (minimum 50 required)
                                </span>
                                <Progress
                                    value={94}
                                    className="w-full [&>div]:bg-blue-600"
                                />
                            </div>
                        </div>
                    </Card>

                    <Button className="bg-blue-600 text-white dark:bg-blue-300 dark:text-slate-950">
                        Retraining Not Available
                    </Button>
                </Card>

                <div className="flex flex-1 gap-4">
                    <Card className="flex-1 px-8 py-5 dark:bg-neutral-900">
                        <h1 className="font-medium">Corrcetion History</h1>
                        <Table className="mt-[-10px]">
                            <TableCaption>Correction history</TableCaption>
                            <TableHeader>
                                <TableRow className="">
                                    <TableHead>Scan ID</TableHead>
                                    <TableHead>Original Prediction</TableHead>
                                    <TableHead>Corrected Breed</TableHead>
                                    <TableHead>Confidence</TableHead>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow>
                                    <TableCell>INV001</TableCell>
                                    <TableCell className="text-gray-600 dark:text-white/80">
                                        Pug
                                    </TableCell>
                                    <TableCell>Golden Retriever</TableCell>
                                    <TableCell>
                                        <p className="text-gray-600 dark:text-white/80">
                                            80%
                                        </p>
                                    </TableCell>
                                    <TableCell className="text-gray-600 dark:text-white/80">
                                        2024-01-08 14:32
                                    </TableCell>
                                    <TableCell>
                                        <Badge className="bg-blue-100 text-blue-700 dark:bg-slate-800 dark:text-blue-400">
                                            Added to Dataset
                                        </Badge>
                                    </TableCell>
                                    <TableCell></TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </Card>
                    <Card className="w-1/4 px-5 dark:bg-neutral-900">
                        <h1 className="font-medium">Retraining Histiory</h1>
                        <div className="px-4">
                            <div className="flex gap-4">
                                <p className="font-medium">Model v1.0</p>
                                <Badge className="bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-400">
                                    Current
                                </Badge>
                            </div>
                            <p>Accuracy: 87.49%</p>
                        </div>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
