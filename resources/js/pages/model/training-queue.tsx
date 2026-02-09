import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    CircleCheckBig,
    ListTodo,
    Trash2,
} from 'lucide-react';

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

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedCorrections {
    data: Correction[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: PaginationLink[];
}

interface DashboardProps {
    corrections: PaginatedCorrections;
    stats: {
        pending: number;
        added: number;
    };
}

export default function Dashboard({ corrections, stats }: DashboardProps) {
    const handlePageChange = (page: number) => {
        router.visit(`/model/training-queue?page=${page}`, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="flex h-full flex-col gap-6 p-4 md:p-8">
                <div>
                    <h1 className="text-xl font-bold dark:text-white">
                        Model Feedback & Training Queue
                    </h1>
                    <p className="text-sm text-gray-600 dark:text-white/70">
                        Track corrections and monitor model retraining progress
                    </p>
                </div>

                {/* Statistics Cards Grid */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {/* TOTAL CORRECTIONS CARD */}
                    <Card className="flex flex-row justify-between px-4 py-4 shadow-sm dark:bg-neutral-900">
                        <div>
                            <p className="text-sm font-medium text-gray-600 dark:text-white/80">
                                Total Corrections
                            </p>
                            <p className="mt-1 text-2xl font-bold">
                                {stats?.added || 0}
                            </p>
                        </div>
                        <div className="flex h-10 w-10 items-center justify-center rounded-md bg-blue-600">
                            <CircleCheckBig className="h-5 w-5 text-white" />
                        </div>
                    </Card>

                    {/* PENDING CARD */}
                    <Card className="flex flex-row justify-between px-4 py-4 shadow-sm dark:bg-neutral-900">
                        <div>
                            <p className="text-sm font-medium text-gray-600 dark:text-white/80">
                                Pending Review
                            </p>
                            <p className="mt-1 text-2xl font-bold">
                                {stats?.pending || 0}
                            </p>
                        </div>
                        <div className="flex h-10 w-10 items-center justify-center rounded-md bg-amber-500">
                            <ListTodo className="h-5 w-5 text-white" />
                        </div>
                    </Card>
                </div>

                {/* Correction History Table */}
                <div className="flex flex-1 flex-col gap-4">
                    <Card className="flex-1 overflow-hidden px-0 py-5 shadow-sm dark:bg-neutral-900">
                        <div className="mb-4 px-6">
                            <h1 className="text-lg font-medium">
                                Correction History
                            </h1>
                        </div>

                        <div className="overflow-x-auto">
                            <Table className="min-w-[800px]">
                                <TableCaption>
                                    {corrections.data.length === 0
                                        ? 'No corrections submitted yet.'
                                        : 'Recent correction history'}
                                </TableCaption>
                                <TableHeader>
                                    <TableRow className="hover:bg-transparent">
                                        <TableHead className="w-[150px] pl-6">
                                            Scan ID
                                        </TableHead>
                                        <TableHead>Image</TableHead>
                                        <TableHead>
                                            Original Prediction
                                        </TableHead>
                                        <TableHead>Correction</TableHead>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="pr-6 text-right">
                                            Action
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {corrections.data.map((item) => (
                                        <TableRow key={item.id}>
                                            <TableCell className="pl-6 font-mono text-xs">
                                                {item.scan_id}
                                            </TableCell>
                                            <TableCell>
                                                <div className="h-12 w-12 shrink-0 overflow-hidden rounded-md border border-gray-200 dark:border-gray-700">
                                                    <img
                                                        src={item.image_path}
                                                        alt="Scan"
                                                        className="h-full w-full object-cover"
                                                    />
                                                </div>
                                            </TableCell>
                                            <TableCell className="font-medium text-gray-600 dark:text-gray-300">
                                                {item.original_breed}
                                            </TableCell>
                                            <TableCell className="font-bold text-green-600 dark:text-green-500">
                                                {item.corrected_breed}
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap text-gray-600 dark:text-white/80">
                                                {new Date(
                                                    item.created_at,
                                                ).toLocaleDateString()}
                                            </TableCell>
                                            <TableCell>
                                                <Badge className="bg-blue-100 text-blue-700 hover:bg-blue-200 dark:bg-blue-900/30 dark:text-blue-300">
                                                    {item.status}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="pr-6 text-right">
                                                <Link
                                                    href={`/model-correction/${item.id}`}
                                                    method="delete"
                                                    as="button"
                                                    className="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20"
                                                    title="Delete Record"
                                                >
                                                    <Trash2
                                                        size={16}
                                                        className="text-red-600"
                                                    />
                                                </Link>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </Card>

                    {/* Pagination Controls */}
                    {corrections.last_page > 1 && (
                        <Card className="p-4 dark:bg-neutral-900">
                            <div className="flex flex-col items-center justify-between gap-4 sm:flex-row">
                                {/* Pagination Info */}
                                <div className="text-sm text-gray-600 dark:text-gray-400">
                                    Showing {corrections.from} to{' '}
                                    {corrections.to} of {corrections.total}{' '}
                                    results
                                </div>

                                {/* Pagination Buttons */}
                                <div className="flex items-center gap-2">
                                    {/* Previous Button */}
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            handlePageChange(
                                                corrections.current_page - 1,
                                            )
                                        }
                                        disabled={
                                            corrections.current_page === 1
                                        }
                                        className="dark:bg-neutral-800"
                                    >
                                        <ChevronLeft className="mr-1 h-4 w-4" />
                                        Previous
                                    </Button>

                                    {/* Page Numbers */}
                                    <div className="hidden items-center gap-1 sm:flex">
                                        {(() => {
                                            const currentPage =
                                                corrections.current_page;
                                            const lastPage =
                                                corrections.last_page;
                                            const pagesToShow: number[] = [];

                                            if (lastPage <= 3) {
                                                // If 3 or fewer pages, show all
                                                for (
                                                    let i = 1;
                                                    i <= lastPage;
                                                    i++
                                                ) {
                                                    pagesToShow.push(i);
                                                }
                                            } else {
                                                // Show current page and one on each side
                                                if (currentPage === 1) {
                                                    // At start: show 1, 2, 3
                                                    pagesToShow.push(1, 2, 3);
                                                } else if (
                                                    currentPage === lastPage
                                                ) {
                                                    // At end: show last-2, last-1, last
                                                    pagesToShow.push(
                                                        lastPage - 2,
                                                        lastPage - 1,
                                                        lastPage,
                                                    );
                                                } else {
                                                    // In middle: show prev, current, next
                                                    pagesToShow.push(
                                                        currentPage - 1,
                                                        currentPage,
                                                        currentPage + 1,
                                                    );
                                                }
                                            }

                                            return pagesToShow.map((page) => (
                                                <button
                                                    key={page}
                                                    onClick={() =>
                                                        handlePageChange(page)
                                                    }
                                                    className={`h-9 min-w-[2.5rem] rounded-md px-3 text-sm font-medium transition-colors ${
                                                        corrections.current_page ===
                                                        page
                                                            ? 'bg-blue-600 text-white hover:bg-blue-700 dark:bg-blue-600'
                                                            : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-gray-300 dark:hover:bg-neutral-700'
                                                    }`}
                                                >
                                                    {page}
                                                </button>
                                            ));
                                        })()}
                                    </div>

                                    {/* Current Page Indicator (Mobile) */}
                                    <div className="text-sm text-gray-600 sm:hidden dark:text-gray-400">
                                        Page {corrections.current_page} of{' '}
                                        {corrections.last_page}
                                    </div>

                                    {/* Next Button */}
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            handlePageChange(
                                                corrections.current_page + 1,
                                            )
                                        }
                                        disabled={
                                            corrections.current_page ===
                                            corrections.last_page
                                        }
                                        className="dark:bg-neutral-800"
                                    >
                                        Next
                                        <ChevronRight className="ml-1 h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
