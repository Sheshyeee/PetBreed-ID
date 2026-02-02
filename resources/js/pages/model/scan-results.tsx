import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Card } from '@/components/ui/card';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Progress } from '@/components/ui/progress';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Slider } from '@/components/ui/slider';
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
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ChevronDownIcon,
    ChevronLeft,
    ChevronRight,
    ListFilterPlus,
    Trash2,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

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

type PredictionResult = {
    breed: string;
    confidence: number;
};

type Result = {
    id: number;
    scan_id: string;
    image: string;
    breed: string;
    confidence: number;
    top_predictions: PredictionResult[];
    created_at?: string;
    updated_at?: string;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedResults = {
    data: Result[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: PaginationLink[];
};

type PageProps = {
    results: PaginatedResults;
    filters: {
        min_confidence: number;
        status: string;
        date: string | null;
    };
};

export default function Dashboard() {
    const { results, filters } = usePage<PageProps>().props;

    // Initialize states from server props
    const [minConfidence, setMinConfidence] = useState<number>(
        filters.min_confidence || 0,
    );
    const [open, setOpen] = useState(false);
    const [date, setDate] = useState<Date | undefined>(
        filters.date ? new Date(filters.date + 'T00:00:00') : undefined,
    );
    const [statusFilter, setStatusFilter] = useState<string>(
        filters.status || 'all',
    );

    // Track if we're on initial mount
    const isInitialMount = useRef(true);
    const debounceTimer = useRef<NodeJS.Timeout | null>(null);

    // Helper function to format date consistently
    const formatDateForURL = (dateObj: Date): string => {
        const year = dateObj.getFullYear();
        const month = String(dateObj.getMonth() + 1).padStart(2, '0');
        const day = String(dateObj.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    // Build query parameters
    const buildQueryParams = (
        confidence: number,
        status: string,
        filterDate: Date | undefined,
        page: number = 1,
    ): string => {
        const params = new URLSearchParams();

        if (page > 1) {
            params.append('page', page.toString());
        }

        // Always send min_confidence if it's greater than 0
        if (confidence > 0) {
            params.append('min_confidence', confidence.toString());
        }

        if (status !== 'all') {
            params.append('status', status);
        }

        if (filterDate) {
            params.append('date', formatDateForURL(filterDate));
        }

        return params.toString();
    };

    // Navigate to URL with filters
    const navigateWithFilters = (
        confidence: number,
        status: string,
        filterDate: Date | undefined,
        page: number = 1,
    ) => {
        const queryString = buildQueryParams(
            confidence,
            status,
            filterDate,
            page,
        );
        const url = queryString
            ? `/model/scan-results?${queryString}`
            : '/model/scan-results';

        console.log('Navigating with filters:', {
            confidence,
            status,
            date: filterDate ? formatDateForURL(filterDate) : null,
            page,
            url,
        });

        router.visit(url, {
            preserveState: true,
            preserveScroll: true,
            only: ['results', 'filters'],
        });
    };

    // Debounced slider effect
    useEffect(() => {
        // Skip initial mount
        if (isInitialMount.current) {
            isInitialMount.current = false;
            return;
        }

        // Clear existing timer
        if (debounceTimer.current) {
            clearTimeout(debounceTimer.current);
        }

        // Set new timer
        debounceTimer.current = setTimeout(() => {
            console.log('Slider debounce triggered:', minConfidence);
            navigateWithFilters(minConfidence, statusFilter, date, 1);
        }, 500);

        // Cleanup
        return () => {
            if (debounceTimer.current) {
                clearTimeout(debounceTimer.current);
            }
        };
    }, [minConfidence]); // Only depend on minConfidence

    // Sync state when URL changes (for browser back/forward)
    useEffect(() => {
        setMinConfidence(filters.min_confidence || 0);
        setStatusFilter(filters.status || 'all');
        setDate(
            filters.date ? new Date(filters.date + 'T00:00:00') : undefined,
        );
    }, [filters.min_confidence, filters.status, filters.date]);

    const handleSliderChange = (newValue: number[]) => {
        console.log('Slider changed to:', newValue[0]);
        setMinConfidence(newValue[0]);
    };

    const handleStatusChange = (newStatus: string) => {
        console.log('Status changed to:', newStatus);
        setStatusFilter(newStatus);
        navigateWithFilters(minConfidence, newStatus, date, 1);
    };

    const handleDateChange = (newDate: Date | undefined) => {
        console.log(
            'Date changed to:',
            newDate ? formatDateForURL(newDate) : null,
        );
        setDate(newDate);
        setOpen(false);
        navigateWithFilters(minConfidence, statusFilter, newDate, 1);
    };

    const handlePageChange = (page: number) => {
        console.log('Page changed to:', page);
        navigateWithFilters(minConfidence, statusFilter, date, page);
    };

    const clearFilters = () => {
        console.log('Clearing all filters');
        setMinConfidence(0);
        setStatusFilter('all');
        setDate(undefined);

        router.visit('/model/scan-results', {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const hasActiveFilters =
        minConfidence > 0 || statusFilter !== 'all' || date;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Scan Results" />

            <div className="flex h-full w-full flex-col gap-6 p-4 md:p-8">
                {/* Header */}
                <div>
                    <h1 className="text-xl font-bold dark:text-white">
                        Scan Results Management
                    </h1>
                    <p className="text-sm text-gray-600 dark:text-white/70">
                        Review and validate dog breed predictions from user
                        uploads.
                    </p>
                </div>

                {/* Filters Card */}
                <Card className="flex flex-col gap-6 p-6 dark:bg-neutral-900">
                    <div className="flex items-center gap-2 border-b border-gray-100 pb-2 dark:border-gray-800">
                        <ListFilterPlus className="h-5 w-5 text-gray-900 dark:text-white" />
                        <h2 className="font-semibold text-gray-900 dark:text-white">
                            Filters
                        </h2>
                    </div>

                    <div className="flex flex-col gap-6 md:flex-row">
                        {/* Slider Section */}
                        <div className="flex-1 space-y-3">
                            <div className="flex justify-between">
                                <label className="text-sm font-medium">
                                    Min Confidence
                                </label>
                                <span className="text-sm text-gray-500">
                                    {minConfidence}%
                                </span>
                            </div>
                            <Slider
                                onValueChange={handleSliderChange}
                                value={[minConfidence]}
                                max={100}
                                step={1}
                                className="py-2"
                            />
                            <p className="text-xs text-gray-500">
                                Showing results with confidence ≥{' '}
                                {minConfidence}%
                            </p>
                        </div>

                        {/* Status Select Section */}
                        <div className="flex-1 space-y-2">
                            <label className="text-sm font-medium">
                                Status
                            </label>
                            <Select
                                value={statusFilter}
                                onValueChange={handleStatusChange}
                            >
                                <SelectTrigger className="w-full text-black dark:bg-neutral-800 dark:text-white">
                                    <SelectValue placeholder="All Statuses" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All Statuses
                                    </SelectItem>
                                    <SelectItem value="High_Confidence">
                                        High Confidence (≥80%)
                                    </SelectItem>
                                    <SelectItem value="Medium_Confidence">
                                        Medium Confidence (60-79%)
                                    </SelectItem>
                                    <SelectItem value="Low_Confidence">
                                        Low Confidence (40-59%)
                                    </SelectItem>
                                    <SelectItem value="Very_Low_Confidence">
                                        Very Low Confidence (&lt;40%)
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Date Picker Section */}
                        <div className="flex-1 space-y-2">
                            <label className="text-sm font-medium">Date</label>
                            <Popover open={open} onOpenChange={setOpen}>
                                <PopoverTrigger asChild>
                                    <Button
                                        variant="outline"
                                        className="w-full justify-between font-normal dark:bg-neutral-800"
                                    >
                                        {date
                                            ? formatDateForURL(date)
                                            : 'Select date'}
                                        <ChevronDownIcon className="h-4 w-4 opacity-50" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent
                                    className="w-auto p-0"
                                    align="start"
                                >
                                    <Calendar
                                        mode="single"
                                        selected={date}
                                        onSelect={handleDateChange}
                                        initialFocus
                                    />
                                </PopoverContent>
                            </Popover>
                        </div>
                    </div>

                    {/* Footer Row: Count & Clear Button */}
                    <div className="flex flex-col items-start justify-between gap-4 pt-2 sm:flex-row sm:items-center">
                        <p className="text-sm text-gray-600 dark:text-white/80">
                            Showing{' '}
                            <span className="font-bold">{results.total}</span>{' '}
                            {hasActiveFilters ? 'filtered' : 'total'} scans
                        </p>

                        {hasActiveFilters && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={clearFilters}
                                className="h-auto px-2 text-blue-600 hover:text-blue-700 dark:text-blue-400"
                            >
                                <X className="mr-2 h-4 w-4" />
                                Clear Filters
                            </Button>
                        )}
                    </div>
                </Card>

                {/* Results Table */}
                <Card className="flex-1 overflow-hidden p-0 dark:bg-neutral-900">
                    <div className="overflow-x-auto">
                        <Table>
                            <TableCaption>
                                A list of your recent scans.
                            </TableCaption>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-[100px]">
                                        Scan ID
                                    </TableHead>
                                    <TableHead>Image</TableHead>
                                    <TableHead>Predicted Breed</TableHead>
                                    <TableHead className="min-w-[180px]">
                                        Confidence
                                    </TableHead>
                                    <TableHead>Scan Date</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="flex items-center justify-center">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {results.data.length > 0 ? (
                                    results.data.map((result) => (
                                        <TableRow key={result.scan_id}>
                                            <TableCell className="font-mono text-xs whitespace-nowrap">
                                                {result.scan_id}
                                            </TableCell>
                                            <TableCell>
                                                <div className="h-12 w-16 overflow-hidden rounded-md border border-gray-200 dark:border-gray-700">
                                                    <img
                                                        src={`/storage/${result.image}`}
                                                        alt={result.breed}
                                                        className="h-full w-full object-cover"
                                                    />
                                                </div>
                                            </TableCell>
                                            <TableCell className="font-medium whitespace-nowrap text-gray-900 dark:text-white">
                                                {result.breed}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-3">
                                                    <Progress
                                                        value={
                                                            result.confidence
                                                        }
                                                        className={`h-2 w-24 ${
                                                            result.confidence >=
                                                            80
                                                                ? '[&>div]:bg-green-600'
                                                                : result.confidence >=
                                                                    60
                                                                  ? '[&>div]:bg-yellow-500'
                                                                  : result.confidence >=
                                                                      40
                                                                    ? '[&>div]:bg-orange-500'
                                                                    : '[&>div]:bg-red-500'
                                                        }`}
                                                    />
                                                    <span className="w-[3ch] text-xs font-bold">
                                                        {result.confidence}%
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap text-gray-500 dark:text-gray-400">
                                                {result.created_at
                                                    ?.replace('T', ' ')
                                                    .substring(0, 10)}
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap">
                                                {result.confidence >= 80 ? (
                                                    <Badge className="bg-green-100 text-green-700 hover:bg-green-100 dark:bg-green-900/40 dark:text-green-400">
                                                        High Confidence
                                                    </Badge>
                                                ) : result.confidence >= 60 ? (
                                                    <Badge className="bg-yellow-100 text-yellow-700 hover:bg-yellow-100 dark:bg-yellow-900/40 dark:text-yellow-400">
                                                        Medium Confidence
                                                    </Badge>
                                                ) : result.confidence >= 40 ? (
                                                    <Badge className="bg-orange-100 text-orange-700 hover:bg-orange-100 dark:bg-orange-900/40 dark:text-orange-400">
                                                        Low Confidence
                                                    </Badge>
                                                ) : (
                                                    <Badge className="bg-red-100 text-red-700 hover:bg-red-100 dark:bg-red-900/40 dark:text-red-400">
                                                        Very Low Confidence
                                                    </Badge>
                                                )}
                                            </TableCell>
                                            <TableCell className="flex items-center justify-center gap-3 text-right">
                                                <Link
                                                    href={`/model/review-dog/${result.id}/delete`}
                                                >
                                                    <Trash2
                                                        size={16}
                                                        className="text-red-600"
                                                    />
                                                </Link>
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="secondary"
                                                >
                                                    <Link
                                                        href={`/model/review-dog/${result.id}`}
                                                    >
                                                        Review
                                                    </Link>
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                ) : (
                                    <TableRow>
                                        <TableCell
                                            colSpan={7}
                                            className="h-24 text-center text-gray-500"
                                        >
                                            No scans match your filters.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </Card>

                {/* Pagination Controls */}
                {results.last_page > 1 && (
                    <Card className="p-4 dark:bg-neutral-900">
                        <div className="flex flex-col items-center justify-between gap-4 sm:flex-row">
                            {/* Pagination Info */}
                            <div className="text-sm text-gray-600 dark:text-gray-400">
                                Showing {results.from} to {results.to} of{' '}
                                {results.total} results
                            </div>

                            {/* Pagination Buttons */}
                            <div className="flex items-center gap-2">
                                {/* Previous Button */}
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        handlePageChange(
                                            results.current_page - 1,
                                        )
                                    }
                                    disabled={results.current_page === 1}
                                    className="dark:bg-neutral-800"
                                >
                                    <ChevronLeft className="mr-1 h-4 w-4" />
                                    Previous
                                </Button>

                                {/* Page Numbers */}
                                <div className="hidden items-center gap-1 sm:flex">
                                    {(() => {
                                        const currentPage =
                                            results.current_page;
                                        const lastPage = results.last_page;
                                        const pagesToShow: number[] = [];

                                        if (lastPage <= 3) {
                                            for (
                                                let i = 1;
                                                i <= lastPage;
                                                i++
                                            ) {
                                                pagesToShow.push(i);
                                            }
                                        } else {
                                            if (currentPage === 1) {
                                                pagesToShow.push(1, 2, 3);
                                            } else if (
                                                currentPage === lastPage
                                            ) {
                                                pagesToShow.push(
                                                    lastPage - 2,
                                                    lastPage - 1,
                                                    lastPage,
                                                );
                                            } else {
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
                                                    results.current_page ===
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
                                    Page {results.current_page} of{' '}
                                    {results.last_page}
                                </div>

                                {/* Next Button */}
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        handlePageChange(
                                            results.current_page + 1,
                                        )
                                    }
                                    disabled={
                                        results.current_page ===
                                        results.last_page
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
        </AppLayout>
    );
}
