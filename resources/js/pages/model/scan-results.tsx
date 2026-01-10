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
import { Head, Link } from '@inertiajs/react';
import { ChartNoAxesCombined } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
    {
        title: 'Scan Results',
        href: '/model/scan-results',
    },
];

export default function Dashboard() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-col gap-4 overflow-x-auto rounded-xl p-4 px-10">
                <div>
                    <h1 className="text-lg font-bold dark:text-white">
                        Scan Results Management
                    </h1>
                    <h1 className="text-sm text-gray-600 dark:text-white/70">
                        Review and validate dog breed predictions from user
                        uploads
                    </h1>
                </div>

                <Card className="px-10">
                    <div className="flex gap-2">
                        <ChartNoAxesCombined color="black" />
                        <h1 className="font-medium">Filters</h1>
                    </div>
                    <div className="flex">
                        <div className="flex-1">
                            <h1>Min Confidence: 0%</h1>
                        </div>
                        <div className="flex-1">
                            <h1>Status</h1>
                        </div>
                        <div className="flex-1">
                            <h1>Date Range</h1>
                        </div>
                    </div>
                    <div className="flex justify-between">
                        <p className="text-gray-600">Showing 10 of 30 scans</p>
                        <p className="text-blue-600">Clear Filters</p>
                    </div>
                </Card>

                <Card className="flex-1 px-8 py-5">
                    <Table className="mt-[-10px]">
                        <TableCaption>
                            A list of your recent invoices.
                        </TableCaption>
                        <TableHeader>
                            <TableRow className="">
                                <TableHead>Scan ID</TableHead>
                                <TableHead>Image</TableHead>
                                <TableHead>Predicted Breed</TableHead>
                                <TableHead>Confidence</TableHead>
                                <TableHead>Scan Date</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow>
                                <TableCell>INV001</TableCell>
                                <TableCell>
                                    <img
                                        src="/dogPic.jpg"
                                        alt=""
                                        className="h-13 w-15 rounded-lg"
                                    />
                                </TableCell>
                                <TableCell className="text-gray-600">
                                    Golden Retriever
                                </TableCell>
                                <TableCell>
                                    <div className="flex items-center gap-2">
                                        <Progress
                                            value={80}
                                            className="w-[40%] [&>div]:bg-green-600"
                                        />
                                        <p className="font-medium">80%</p>
                                    </div>
                                </TableCell>
                                <TableCell className="text-gray-600">
                                    2024-01-08 14:32
                                </TableCell>
                                <TableCell>
                                    <Badge className="bg-green-100 text-green-700">
                                        High Confidence
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <Link href="/model/review-dog">
                                        <Button>Review</Button>
                                    </Link>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </Card>
            </div>
        </AppLayout>
    );
}
