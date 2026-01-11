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
import { Head, Link } from '@inertiajs/react';
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

export default function Dashboard() {
    const [value, setValue] = useState<number[]>([90]);

    const [open, setOpen] = useState(false);
    const [date, setDate] = useState<Date | undefined>(undefined);

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
                                <Select>
                                    <SelectTrigger className="dark:bg-neutral-800  text-black dark:text-white">
                                        <SelectValue placeholder="Theme" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="light">
                                            Light
                                        </SelectItem>
                                        <SelectItem value="dark">
                                            Dark
                                        </SelectItem>
                                        <SelectItem value="system">
                                            System
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
                                            className="justify-between font-normal dark:bg-neutral-800 "
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
                            Showing 10 of 30 scans
                        </p>
                        <p className="dark:text-blue-5 00 text-blue-600">
                            Clear Filters
                        </p>
                    </div>
                </Card>

                <Card className="flex-1 px-8 py-5 dark:bg-neutral-900">
                    <Table className="mt-[-10px]">
                        <TableCaption>
                            A list of your recent invoices.
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
                            <TableRow>
                                <TableCell>INV001</TableCell>
                                <TableCell>
                                    <img
                                        src="/dogPic.jpg"
                                        alt=""
                                        className="h-13 w-15 rounded-lg"
                                    />
                                </TableCell>
                                <TableCell className="text-gray-600 dark:text-white/70">
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
                                <TableCell className="text-gray-600 dark:text-white/70">
                                    2024-01-08 14:32
                                </TableCell>
                                <TableCell>
                                    <Badge className="bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-400">
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
