import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Clock,
    Stethoscope,
    XCircle,
} from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Appointments', href: '/model/appointments' },
];

type AppointmentResult = {
    id: number;
    scan_id: string;
    breed: string;
    image?: string;
};

type Appointment = {
    id: number;
    scan_id: string;
    appointment_date: string;
    appointment_time: string;
    vet_name: string;
    reason: string;
    notes?: string;
    status: 'pending' | 'accepted' | 'rejected';
    rejection_reason?: string;
    result?: AppointmentResult;
    created_at: string;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedAppointments = {
    data: Appointment[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: PaginationLink[];
};

type Stats = {
    total: number;
    pending: number;
    accepted: number;
    rejected: number;
};

type PageProps = {
    appointments: PaginatedAppointments;
    stats: Stats;
};

// ── status helpers ────────────────────────────────────────────────────────────
const statusBadge = (status: Appointment['status']) => {
    if (status === 'accepted')
        return (
            <Badge className="bg-green-100 text-green-700 hover:bg-green-100 dark:bg-green-900/30 dark:text-green-400">
                <CheckCircle2 size={11} className="mr-1" /> Accepted
            </Badge>
        );
    if (status === 'rejected')
        return (
            <Badge className="bg-red-100 text-red-700 hover:bg-red-100 dark:bg-red-900/30 dark:text-red-400">
                <XCircle size={11} className="mr-1" /> Rejected
            </Badge>
        );
    return (
        <Badge className="bg-yellow-100 text-yellow-700 hover:bg-yellow-100 dark:bg-yellow-900/30 dark:text-yellow-400">
            <Clock size={11} className="mr-1" /> Pending
        </Badge>
    );
};

export default function Appointments() {
    const { appointments, stats } = usePage<PageProps>().props;

    const handlePageChange = (page: number) => {
        router.visit(`/model/appointments?page=${page}`, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Appointments" />

            <div className="flex h-full w-full flex-col gap-6 p-4 md:p-8">
                {/* Header */}
                <div>
                    <h1 className="text-xl font-bold dark:text-white">
                        Appointments
                    </h1>
                    <p className="text-sm text-gray-600 dark:text-white/70">
                        All consultations scheduled by the clinic and their owner response status.
                    </p>
                </div>

                {/* Stat Cards */}
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <Card className="flex flex-row items-center justify-between px-4 py-4 shadow-sm dark:bg-neutral-900">
                        <div>
                            <p className="text-sm font-medium text-gray-600 dark:text-white/80">Total</p>
                            <p className="mt-1 text-2xl font-bold dark:text-white">{stats.total}</p>
                        </div>
                        <div className="flex h-10 w-10 items-center justify-center rounded-md bg-blue-600">
                            <CalendarDays className="h-5 w-5 text-white" />
                        </div>
                    </Card>

                    <Card className="flex flex-row items-center justify-between px-4 py-4 shadow-sm dark:bg-neutral-900">
                        <div>
                            <p className="text-sm font-medium text-gray-600 dark:text-white/80">Pending</p>
                            <p className="mt-1 text-2xl font-bold dark:text-white">{stats.pending}</p>
                        </div>
                        <div className="flex h-10 w-10 items-center justify-center rounded-md bg-yellow-500">
                            <Clock className="h-5 w-5 text-white" />
                        </div>
                    </Card>

                    <Card className="flex flex-row items-center justify-between px-4 py-4 shadow-sm dark:bg-neutral-900">
                        <div>
                            <p className="text-sm font-medium text-gray-600 dark:text-white/80">Accepted</p>
                            <p className="mt-1 text-2xl font-bold dark:text-white">{stats.accepted}</p>
                        </div>
                        <div className="flex h-10 w-10 items-center justify-center rounded-md bg-green-600">
                            <CheckCircle2 className="h-5 w-5 text-white" />
                        </div>
                    </Card>

                    <Card className="flex flex-row items-center justify-between px-4 py-4 shadow-sm dark:bg-neutral-900">
                        <div>
                            <p className="text-sm font-medium text-gray-600 dark:text-white/80">Rejected</p>
                            <p className="mt-1 text-2xl font-bold dark:text-white">{stats.rejected}</p>
                        </div>
                        <div className="flex h-10 w-10 items-center justify-center rounded-md bg-red-500">
                            <XCircle className="h-5 w-5 text-white" />
                        </div>
                    </Card>
                </div>

                {/* Table Card */}
                <Card className="flex-1 overflow-hidden p-0 dark:bg-neutral-900">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[900px] text-sm">
                            <thead>
                                <tr className="border-b border-gray-100 dark:border-white/[.06]">
                                    <th className="px-6 py-3 text-left text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Dog</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Breed</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Date & Time</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Vet</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Reason</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Status</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Decline Reason</th>
                                    <th className="px-4 py-3 text-left text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-white/[.04]">
                                {appointments.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={8} className="px-6 py-16 text-center text-gray-500 dark:text-gray-400">
                                            <div className="flex flex-col items-center gap-2">
                                                <CalendarDays size={36} className="text-gray-300 dark:text-gray-600" />
                                                <p className="text-sm font-medium">No appointments scheduled yet.</p>
                                                <p className="text-xs text-gray-400 dark:text-gray-500">
                                                    Schedule consultations from the Scan Results review page.
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    appointments.data.map((appt) => (
                                        <tr key={appt.id} className="transition-colors hover:bg-gray-50 dark:hover:bg-white/[.025]">
                                            {/* Dog thumbnail */}
                                            <td className="px-6 py-3">
                                                {appt.result?.image ? (
                                                    <div className="h-12 w-14 overflow-hidden rounded-md border border-gray-200 dark:border-gray-700">
                                                        <img
                                                            src={appt.result.image}
                                                            alt={appt.result.breed}
                                                            className="h-full w-full object-cover"
                                                        />
                                                    </div>
                                                ) : (
                                                    <div className="flex h-12 w-14 items-center justify-center rounded-md border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-white/[.03]">
                                                        <Stethoscope size={16} className="text-gray-400" />
                                                    </div>
                                                )}
                                            </td>

                                            {/* Breed */}
                                            <td className="px-4 py-3">
                                                <p className="font-medium whitespace-nowrap text-gray-900 dark:text-white">
                                                    {appt.result?.breed ?? '—'}
                                                </p>
                                                <p className="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                                    #{appt.scan_id}
                                                </p>
                                            </td>

                                            {/* Date & Time */}
                                            <td className="px-4 py-3 whitespace-nowrap text-gray-700 dark:text-gray-300">
                                                <p className="font-medium">
                                                    {new Date(appt.appointment_date).toLocaleDateString('en-US', {
                                                        month: 'short',
                                                        day: 'numeric',
                                                        year: 'numeric',
                                                    })}
                                                </p>
                                                <p className="text-xs text-gray-500 dark:text-gray-400">{appt.appointment_time}</p>
                                            </td>

                                            {/* Vet */}
                                            <td className="px-4 py-3 whitespace-nowrap text-gray-700 dark:text-gray-300">
                                                {appt.vet_name}
                                            </td>

                                            {/* Reason */}
                                            <td className="max-w-[180px] px-4 py-3">
                                                <p className="truncate text-gray-600 dark:text-gray-400" title={appt.reason}>
                                                    {appt.reason}
                                                </p>
                                            </td>

                                            {/* Status */}
                                            <td className="px-4 py-3 whitespace-nowrap">
                                                {statusBadge(appt.status)}
                                            </td>

                                            {/* Decline reason */}
                                            <td className="max-w-[180px] px-4 py-3">
                                                {appt.rejection_reason ? (
                                                    <p className="truncate text-xs italic text-red-600 dark:text-red-400" title={appt.rejection_reason}>
                                                        "{appt.rejection_reason}"
                                                    </p>
                                                ) : (
                                                    <span className="text-xs text-gray-400 dark:text-gray-600">—</span>
                                                )}
                                            </td>

                                            {/* Actions */}
                                            <td className="px-4 py-3">
                                                <Button asChild size="sm" variant="secondary">
                                                    <Link href={`/model/review-dog/${appt.result?.id}`}>
                                                        Review
                                                    </Link>
                                                </Button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </Card>

                {/* Pagination */}
                {appointments.last_page > 1 && (
                    <Card className="p-4 dark:bg-neutral-900">
                        <div className="flex flex-col items-center justify-between gap-4 sm:flex-row">
                            <div className="text-sm text-gray-600 dark:text-gray-400">
                                Showing {appointments.from} to {appointments.to} of {appointments.total} results
                            </div>
                            <div className="flex items-center gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => handlePageChange(appointments.current_page - 1)}
                                    disabled={appointments.current_page === 1}
                                    className="dark:bg-neutral-800"
                                >
                                    <ChevronLeft className="mr-1 h-4 w-4" /> Previous
                                </Button>

                                <div className="hidden items-center gap-1 sm:flex">
                                    {(() => {
                                        const cur = appointments.current_page;
                                        const last = appointments.last_page;
                                        let pages: number[] = [];
                                        if (last <= 3) {
                                            for (let i = 1; i <= last; i++) pages.push(i);
                                        } else if (cur === 1) {
                                            pages = [1, 2, 3];
                                        } else if (cur === last) {
                                            pages = [last - 2, last - 1, last];
                                        } else {
                                            pages = [cur - 1, cur, cur + 1];
                                        }
                                        return pages.map((p) => (
                                            <button
                                                key={p}
                                                onClick={() => handlePageChange(p)}
                                                className={`h-9 min-w-[2.5rem] rounded-md px-3 text-sm font-medium transition-colors ${
                                                    appointments.current_page === p
                                                        ? 'bg-blue-600 text-white hover:bg-blue-700'
                                                        : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-gray-300 dark:hover:bg-neutral-700'
                                                }`}
                                            >
                                                {p}
                                            </button>
                                        ));
                                    })()}
                                </div>

                                <div className="text-sm text-gray-600 sm:hidden dark:text-gray-400">
                                    Page {appointments.current_page} of {appointments.last_page}
                                </div>

                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => handlePageChange(appointments.current_page + 1)}
                                    disabled={appointments.current_page === appointments.last_page}
                                    className="dark:bg-neutral-800"
                                >
                                    Next <ChevronRight className="ml-1 h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}