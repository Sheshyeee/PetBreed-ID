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
import { Head } from '@inertiajs/react';
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

export default function Dashboard() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex gap-4">
                    <Card className="flex flex-1 flex-row justify-between px-4 py-4">
                        <div className="">
                            <h1 className="text-sm text-gray-600">
                                Total Scans
                            </h1>
                            <div className="mt-[-5px] flex items-center justify-center space-x-1">
                                <p className="text-lg font-bold">1000</p>
                                <p className="font-bold text-green-600">
                                    +12.3%
                                </p>
                            </div>
                        </div>
                        <div className="flex w-[50px] items-center justify-center rounded-md bg-blue-600 p-2">
                            <ChartNoAxesCombined color="white" />
                        </div>
                    </Card>
                    <Card className="flex flex-1 flex-row justify-between px-4 py-4">
                        <div className="">
                            <h1 className="text-sm text-gray-600">
                                Low Confidence
                            </h1>
                            <div className="mt-[-5px] flex items-center justify-center space-x-1">
                                <p className="text-lg font-bold">1000</p>
                                <p className="font-bold text-green-600">
                                    +12.3%
                                </p>
                            </div>
                        </div>
                        <div className="flex w-[50px] items-center justify-center rounded-md bg-blue-600 p-2">
                            <TriangleAlert color="white" />
                        </div>
                    </Card>
                    <Card className="flex flex-1 flex-row justify-between px-4 py-4">
                        <div className="">
                            <h1 className="text-sm text-gray-600">Corrected</h1>
                            <div className="mt-[-5px] flex items-center justify-center space-x-1">
                                <p className="text-lg font-bold">1000</p>
                                <p className="font-bold text-green-600">
                                    +12.3%
                                </p>
                            </div>
                        </div>
                        <div className="flex w-[50px] items-center justify-center rounded-md bg-blue-600 p-2">
                            <ShieldCheck color="white" />
                        </div>
                    </Card>
                    <Card className="flex flex-1 flex-row justify-between px-4 py-4">
                        <div className="">
                            <h1 className="text-sm text-gray-600">
                                Model Accuracy
                            </h1>
                            <div className="mt-[-5px] flex items-center justify-center space-x-1">
                                <p className="text-lg font-bold">87.6%</p>
                                <p className="font-bold text-green-600">
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
                    <Card className="flex-1 px-8 py-5">
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
                                A list of your recent invoices.
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
                                <TableRow>
                                    <TableCell>INV001</TableCell>
                                    <TableCell>Paid</TableCell>
                                    <TableCell>Credit Card</TableCell>
                                    <TableCell>
                                        <Badge className="bg-green-100 text-green-700">
                                            High Confidence
                                        </Badge>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </Card>

                    <Card className="w-[28%] p-8">
                        <div className="space-y-7">
                            <h1 className="text-lg font-bold">Model Status</h1>
                            <div>
                                <h1 className="text-gray-600">
                                    Current Version
                                </h1>
                                <p className="font-medium">v3.2</p>
                            </div>
                            <div>
                                <h1 className="text-gray-600">
                                    Last Retrained
                                </h1>
                                <p className="font-medium">Jan 1, 2024</p>
                            </div>
                            <div>
                                <h1 className="text-gray-600">
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
                                    <p className="text-gray-700">Health</p>
                                    <p className="font-bold text-green-700">
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
                    <Card className="flex-1 px-6">
                        <h1 className="text-lg font-bold">
                            Confidence Distribution
                        </h1>
                    </Card>
                    <Card className="flex-1 px-6">
                        <h1 className="text-lg font-bold">
                            Confidence Distribution
                        </h1>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
