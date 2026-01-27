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
import { Head, Link, usePage } from '@inertiajs/react';
import { ChevronDownIcon, ListFilterPlus } from 'lucide-react';
import { useState } from 'react';

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

type PageProps = {
    results?: Result[];
};

export default function Dashboard() {
    const { results } = usePage<PageProps>().props;
    const [value, setValue] = useState<number[]>([0]);
    const [open, setOpen] = useState(false);
    const [date, setDate] = useState<Date | undefined>(undefined);
    const [statusFilter, setStatusFilter] = useState<string>('all');

    const getConfidenceLevel = (confidence: number): string => {
        if (confidence >= 80) return 'High_Confidence';
        if (confidence >= 60) return 'Medium_Confidence';
        if (confidence >= 40) return 'Low_Confidence';
        return 'Very_Low_Confidence';
    };

    const filteredResults = results?.filter((result) => {
        // Min confidence filter
        const meetsMinConfidence = result.confidence >= value[0];

        // Status filter
        const meetsStatus =
            statusFilter === 'all' ||
            getConfidenceLevel(result.confidence) === statusFilter;

        // Date filter
        const meetsDate =
            !date ||
            (result.created_at &&
                new Date(result.created_at).toDateString() ===
                    date.toDateString());

        return meetsMinConfidence && meetsStatus && meetsDate;
    });

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

                <Card className="px-10 dark:bg-neutral-900">
                    <div className="flex gap-2">
                        <ListFilterPlus className="text-black dark:text-white" />
                        <h1 className="font-medium">Filters</h1>
                    </div>
                    <div className="flex space-x-8">
                        <div className="flex flex-1 flex-col">
                            <h1>Min Confidence: {value[0]}%</h1>
                            <Slider
                                onValueChange={setValue}
                                value={value}
                                max={100}
                                step={1}
                                className="mt-4"
                            />
                        </div>
                        <div className="flex-1">
                            <h1>Status</h1>
                            <div className="">
                                <Select
                                    value={statusFilter}
                                    onValueChange={setStatusFilter}
                                >
                                    <SelectTrigger className="text-black dark:bg-neutral-800 dark:text-white">
                                        <SelectValue placeholder="All Statuses" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All Statuses
                                        </SelectItem>
                                        <SelectItem value="High_Confidence">
                                            High Confidence
                                        </SelectItem>
                                        <SelectItem value="Medium_Confidence">
                                            Medium Confidence
                                        </SelectItem>
                                        <SelectItem value="Low_Confidence">
                                            Low Confidence
                                        </SelectItem>
                                        <SelectItem value="Very_Low_Confidence">
                                            Very Low Confidence
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div className="flex-1">
                            <h1>Date Range</h1>
                            <div className="flex w-full flex-col gap-3">
                                <Popover open={open} onOpenChange={setOpen}>
                                    <PopoverTrigger asChild>
                                        <Button
                                            variant="outline"
                                            id="date"
                                            className="justify-between font-normal dark:bg-neutral-800"
                                        >
                                            {date
                                                ? date.toLocaleDateString()
                                                : 'Select date'}
                                            <ChevronDownIcon />
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent
                                        className="w-auto overflow-hidden p-0"
                                        align="start"
                                    >
                                        <Calendar
                                            mode="single"
                                            selected={date}
                                            captionLayout="dropdown"
                                            onSelect={(date) => {
                                                setDate(date);
                                                setOpen(false);
                                            }}
                                        />
                                    </PopoverContent>
                                </Popover>
                            </div>
                        </div>
                    </div>
                    <div className="flex justify-between">
                        <p className="text-gray-600 dark:text-white/80">
                            Showing {filteredResults?.length || 0} of{' '}
                            {results?.length || 0} scans
                        </p>
                        <button
                            onClick={() => {
                                setValue([0]);
                                setStatusFilter('all');
                                setDate(undefined);
                            }}
                            className="cursor-pointer text-blue-600 dark:text-blue-500"
                        >
                            Clear Filters
                        </button>
                    </div>
                </Card>

                <Card className="flex-1 px-8 py-5 dark:bg-neutral-900">
                    <Table className="mt-[-10px]">
                        <TableCaption>
                            A list of your recent scans.
                        </TableCaption>
                        <TableHeader>
                            <TableRow>
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
                            {filteredResults?.map((result) => (
                                <TableRow key={result.scan_id}>
                                    <TableCell>{result.scan_id}</TableCell>
                                    <TableCell>
                                        <img
                                            src={`/storage/${result.image}`}
                                            alt=""
                                            className="h-13 w-15 rounded-lg"
                                        />
                                    </TableCell>
                                    <TableCell className="text-gray-600 dark:text-white/70">
                                        {result.breed}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex items-center gap-2">
                                            <Progress
                                                value={result.confidence}
                                                className={`w-[40%] ${
                                                    result.confidence >= 80
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
                                            <p className="font-medium">
                                                {result.confidence}%
                                            </p>
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-gray-600 dark:text-white/70">
                                        {result.created_at
                                            ?.replace('T', ' ')
                                            .substring(0, 16)}
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
                                    <TableCell>
                                        <Link href={`/model/review-dog/${result.id}`}>
                                            <Button>Review</Button>
                                        </Link>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>
            </div>
        </AppLayout>
    );
}
