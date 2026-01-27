import { Badge } from '@/components/ui/badge';
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
import { Head, usePage } from '@inertiajs/react';
import {
    ChartNoAxesCombined,
    ShieldCheck,
    TrendingUp,
    TriangleAlert,
} from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

type Result = {
    scan_id: string;
    breed: string;
    confidence: number;
};

type PageProps = {
    results?: Result[];
};

export default function Dashboard() {
    const { results } = usePage<PageProps>().props;
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex gap-4">
                    <Card className="flex flex-1 flex-row justify-between px-4 py-4 dark:bg-neutral-900">
                        <div className="">
                            <h1 className="text-sm text-gray-600 dark:text-white/80">
                                Total Scans
                            </h1>
                            <div className="mt-[-5px] flex items-center justify-center space-x-1">
                                <p className="text-lg font-bold">1000</p>
                                <p className="font-bold text-green-600 dark:text-green-400">
                                    +12.3%
                                </p>
                            </div>
                        </div>
                        <div className="flex w-[50px] items-center justify-center rounded-md bg-blue-600 p-2">
                            <ChartNoAxesCombined color="white" />
                        </div>
                    </Card>
                    <Card className="flex flex-1 flex-row justify-between px-4 py-4 dark:bg-neutral-900">
                        <div className="">
                            <h1 className="text-sm text-gray-600 dark:text-white/80">
                                Low Confidence
                            </h1>
                            <div className="mt-[-5px] flex items-center justify-center space-x-1">
                                <p className="text-lg font-bold">1000</p>
                                <p className="font-bold text-green-600 dark:text-green-400">
                                    +12.3%
                                </p>
                            </div>
                        </div>
                        <div className="flex w-[50px] items-center justify-center rounded-md bg-blue-600 p-2">
                            <TriangleAlert color="white" />
                        </div>
                    </Card>
                    <Card className="flex flex-1 flex-row justify-between px-4 py-4 dark:bg-neutral-900">
                        <div className="">
                            <h1 className="text-sm text-gray-600 dark:text-white/70">
                                Corrected
                            </h1>
                            <div className="mt-[-5px] flex items-center justify-center space-x-1">
                                <p className="text-lg font-bold">1000</p>
                                <p className="font-bold text-green-600 dark:text-green-400">
                                    +12.3%
                                </p>
                            </div>
                        </div>
                        <div className="flex w-[50px] items-center justify-center rounded-md bg-blue-600 p-2">
                            <ShieldCheck color="white" />
                        </div>
                    </Card>
                    <Card className="flex flex-1 flex-row justify-between px-4 py-4 dark:bg-neutral-900">
                        <div className="">
                            <h1 className="text-sm text-gray-600 dark:text-white/80">
                                Model Accuracy
                            </h1>
                            <div className="mt-[-5px] flex items-center justify-center space-x-1">
                                <p className="text-lg font-bold">87.6%</p>
                                <p className="font-bold text-green-600 dark:text-green-400">
                                    +12.3%
                                </p>
                            </div>
                        </div>
                        <div className="flex w-[50px] items-center justify-center rounded-md bg-blue-600 p-2">
                            <TrendingUp color="white" />
                        </div>
                    </Card>
                </div>

                <div className="flex space-x-4">
                    <Card className="flex-1 px-8 py-5 dark:bg-neutral-900">
                        <div className="flex items-center justify-between">
                            <div className="">
                                <h1 className="text-lg font-bold dark:text-white">
                                    Recent Scans
                                </h1>
                                <h1 className="text-sm text-gray-600 dark:text-white/70">
                                    Latest scans processed by the system.
                                </h1>
                            </div>
                        </div>
                        <Table className="mt-[-10px]">
                            <TableCaption>
                                A list of your recent scans.
                            </TableCaption>
                            <TableHeader>
                                <TableRow className="">
                                    <TableHead>Scan ID</TableHead>
                                    <TableHead>Breed</TableHead>
                                    <TableHead>Confidence</TableHead>
                                    <TableHead>Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {results?.map((result) => (
                                    <TableRow>
                                        <TableCell>{result.scan_id}</TableCell>
                                        <TableCell>{result.breed}</TableCell>
                                        <TableCell>
                                            {result.confidence}%
                                        </TableCell>
                                        <TableCell>
                                            {result.confidence >= 80 ? (
                                                <Badge className="bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-400">
                                                    High Confidence
                                                </Badge>
                                            ) : result.confidence >= 60 ? (
                                                <Badge className="bg-yellow-100 text-yellow-700 dark:bg-yellow-950 dark:text-yellow-400">
                                                    Medium Confidence
                                                </Badge>
                                            ) : result.confidence >= 40 ? (
                                                <Badge className="bg-orange-100 text-orange-700 dark:bg-orange-950 dark:text-orange-400">
                                                    Low Confidence
                                                </Badge>
                                            ) : (
                                                <Badge className="bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-400">
                                                    Very Low Confidence
                                                </Badge>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </Card>

                    <Card className="w-[28%] p-8 dark:bg-neutral-900">
                        <div className="space-y-7">
                            <h1 className="text-lg font-bold">Model Status</h1>
                            <div>
                                <h1 className="text-gray-600 dark:text-white/80">
                                    Current Version
                                </h1>
                                <p className="font-medium">v3.2</p>
                            </div>
                            <div>
                                <h1 className="text-gray-600 dark:text-white/80">
                                    Last Retrained
                                </h1>
                                <p className="font-medium">Jan 1, 2024</p>
                            </div>
                            <div>
                                <h1 className="text-gray-600 dark:text-white/80">
                                    Accuracy Trend
                                </h1>
                                <div className="flex">
                                    <TrendingUp color="green" />{' '}
                                    <p className="font-bold text-green-600">
                                        +2.2%
                                    </p>
                                </div>
                            </div>

                            <hr className="my-4 border-gray-300 dark:border-gray-700" />

                            <div>
                                <div className="flex justify-between">
                                    <p className="text-gray-700 dark:text-white/80">
                                        Health
                                    </p>
                                    <p className="font-bold text-green-700 dark:text-green-500">
                                        Optimal
                                    </p>
                                </div>
                                <Progress
                                    value={80}
                                    className="mt-2 [&>div]:bg-green-600"
                                />
                            </div>
                        </div>
                    </Card>
                </div>

                <div className="flex gap-4">
                    <Card className="flex-1 px-6 dark:bg-neutral-900">
                        <h1 className="text-lg font-bold">
                            Confidence Distribution
                        </h1>
                    </Card>
                    <Card className="flex-1 px-6 dark:bg-neutral-900">
                        <h1 className="text-lg font-bold">
                            Confidence Distribution
                        </h1>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
