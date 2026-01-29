import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
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
import { CircleCheckBig, ListTodo, Trash2 } from 'lucide-react';

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

interface Correction {
    id: number;
    scan_id: string;
    image_path: string;
    original_breed: string;
    corrected_breed: string;
    confidence: number;
    created_at: string;
    status: string;
}

interface DashboardProps {
    corrections: Correction[];
    stats: {
        pending: number; // Total uncorrected results
        added: number; // Total corrected items
    };
}

export default function Dashboard({ corrections, stats }: DashboardProps) {
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

                {/* Statistics Cards */}
                <div className="flex gap-4">
                    {/* TOTAL CORRECTIONS CARD */}
                    <Card className="flex w-[25%] flex-row justify-between px-4 py-4 dark:bg-neutral-900">
                        <div className="">
                            <h1 className="text-sm text-gray-600 dark:text-white/80">
                                Total Corrections
                            </h1>
                            <p className="mt-[-5px] text-lg font-bold">
                                {stats?.added || 0}
                            </p>
                        </div>
                        <div className="flex w-[50px] items-center justify-center rounded-md bg-blue-600 p-2">
                            <CircleCheckBig className="text-white" />
                        </div>
                    </Card>

                    {/* PENDING CARD (Updated) */}
                    <Card className="flex w-[25%] flex-row justify-between px-4 py-4 dark:bg-neutral-900">
                        <div className="">
                            <h1 className="text-sm text-gray-600 dark:text-white/80">
                                Pending Review
                            </h1>
                            <p className="mt-[-5px] text-lg font-bold">
                                {stats?.pending || 0}
                            </p>
                        </div>
                        <div className="flex w-[50px] items-center justify-center rounded-md bg-amber-500 p-2">
                            <ListTodo className="text-white" />
                        </div>
                    </Card>
                </div>

                <div className="flex flex-1 gap-4">
                    <Card className="flex-1 px-8 py-5 dark:bg-neutral-900">
                        <h1 className="font-medium">Correction History</h1>
                        <Table className="mt-[-10px]">
                            <TableCaption>
                                {corrections.length === 0
                                    ? 'No corrections submitted yet.'
                                    : 'Recent correction history'}
                            </TableCaption>
                            <TableHeader>
                                <TableRow className="">
                                    <TableHead>Scan ID</TableHead>
                                    <TableHead>Image</TableHead>
                                    <TableHead>Original Prediction</TableHead>
                                    <TableHead>Correction</TableHead>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">
                                        Action
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {corrections.map((item) => (
                                    <TableRow key={item.id}>
                                        <TableCell>{item.scan_id}</TableCell>
                                        <TableCell>
                                            <div className="h-12 w-12 overflow-hidden rounded-md border border-gray-200 dark:border-gray-700">
                                                <img
                                                    src={`/storage/${item.image_path}`}
                                                    alt="Scan"
                                                    className="h-full w-full object-cover"
                                                />
                                            </div>
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {item.original_breed}
                                        </TableCell>
                                        <TableCell className="font-bold text-green-600">
                                            {item.corrected_breed}
                                        </TableCell>
                                        <TableCell className="text-gray-600 dark:text-white/80">
                                            {new Date(
                                                item.created_at,
                                            ).toLocaleDateString()}
                                        </TableCell>
                                        <TableCell>
                                            <Badge className="bg-blue-100 text-blue-700 dark:bg-slate-800 dark:text-blue-400">
                                                {item.status}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Link
                                                href={`/model-correction/${item.id}`} // Ensure this matches your route name/URL
                                                method="delete"
                                                as="button"
                                                className="p-2 text-gray-500 transition-colors hover:text-red-600"
                                                title="Delete Record"
                                            >
                                                <Trash2 size={18} />
                                            </Link>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
