import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Clock,
    MessageSquare,
    Stethoscope,
    Trash2,
    User,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';

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

type AppointmentOwner = {
    id: number;
    name: string;
    email: string;
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
    initiated_by: 'clinic' | 'user';
    result?: AppointmentResult;
    owner?: AppointmentOwner;
    created_at: string;
};

type PaginatedAppointments = {
    data: Appointment[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
};

type Stats = {
    total: number;
    pending: number;
    accepted: number;
    rejected: number;
    user_requests: number;
    clinic_scheduled: number;
};

type PageProps = {
    clinicAppointments: PaginatedAppointments;
    userAppointments: PaginatedAppointments;
    stats: Stats;
};

// ── status badge ──────────────────────────────────────────────────────────────
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
                <XCircle size={11} className="mr-1" /> Declined
            </Badge>
        );
    return (
        <Badge className="bg-yellow-100 text-yellow-700 hover:bg-yellow-100 dark:bg-yellow-900/30 dark:text-yellow-400">
            <Clock size={11} className="mr-1" /> Pending
        </Badge>
    );
};

// ── Respond modal for user requests ──────────────────────────────────────────
function RespondModal({
    appt,
    onClose,
}: {
    appt: Appointment;
    onClose: () => void;
}) {
    const { data, setData, post, processing, errors } = useForm({
        status: '' as 'accepted' | 'rejected',
        vet_name: '',
        rejection_reason: '',
    });
    const [step, setStep] = useState<'choose' | 'accept' | 'reject'>('choose');

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/appointments/${appt.id}/status`, { onSuccess: () => onClose() });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
            <div className="w-full max-w-md rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-white/10 dark:bg-neutral-900">
                <div className="flex items-start justify-between border-b border-gray-100 px-6 py-4 dark:border-white/10">
                    <div>
                        <h3 className="font-bold text-gray-900 dark:text-white">
                            Respond to Request
                        </h3>
                        <p className="text-xs text-gray-500 dark:text-gray-400">
                            From:{' '}
                            <span className="font-semibold">
                                {appt.owner?.name ?? 'Unknown'}
                            </span>{' '}
                            · {appt.reason}
                        </p>
                    </div>
                    <button
                        onClick={onClose}
                        className="ml-4 flex h-7 w-7 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 dark:hover:bg-white/10"
                    >
                        ✕
                    </button>
                </div>

                <div className="px-6 py-5">
                    {/* Request summary */}
                    <div className="mb-4 grid grid-cols-2 gap-2 rounded-xl border border-gray-100 bg-gray-50 p-3 dark:border-white/[.06] dark:bg-white/[.03]">
                        <div>
                            <p className="text-[10px] font-semibold text-gray-400 uppercase">
                                Preferred Date
                            </p>
                            <p className="text-sm font-semibold text-gray-800 dark:text-white">
                                {new Date(
                                    appt.appointment_date,
                                ).toLocaleDateString('en-US', {
                                    month: 'short',
                                    day: 'numeric',
                                    year: 'numeric',
                                })}
                            </p>
                        </div>
                        <div>
                            <p className="text-[10px] font-semibold text-gray-400 uppercase">
                                Preferred Time
                            </p>
                            <p className="text-sm font-semibold text-gray-800 dark:text-white">
                                {appt.appointment_time}
                            </p>
                        </div>
                        {appt.notes && (
                            <div className="col-span-2">
                                <p className="text-[10px] font-semibold text-gray-400 uppercase">
                                    Notes
                                </p>
                                <p className="text-xs text-gray-600 dark:text-gray-400">
                                    {appt.notes}
                                </p>
                            </div>
                        )}
                    </div>

                    {step === 'choose' && (
                        <div className="flex gap-3">
                            <button
                                onClick={() => {
                                    setStep('accept');
                                    setData('status', 'accepted');
                                }}
                                className="flex flex-1 items-center justify-center gap-2 rounded-xl bg-green-600 py-2.5 text-sm font-bold text-white hover:bg-green-700"
                            >
                                <CheckCircle2 size={14} /> Accept Request
                            </button>
                            <button
                                onClick={() => {
                                    setStep('reject');
                                    setData('status', 'rejected');
                                }}
                                className="flex flex-1 items-center justify-center gap-2 rounded-xl border border-red-200 bg-red-50 py-2.5 text-sm font-bold text-red-600 hover:bg-red-100 dark:border-red-800/40 dark:bg-red-900/10 dark:text-red-400"
                            >
                                <XCircle size={14} /> Decline
                            </button>
                        </div>
                    )}

                    {step === 'accept' && (
                        <form onSubmit={submit} className="space-y-3">
                            <div className="space-y-1.5">
                                <label className="flex items-center gap-1.5 text-xs font-semibold text-gray-600 dark:text-gray-400">
                                    <User size={11} /> Assign Vet
                                </label>
                                <input
                                    type="text"
                                    value={data.vet_name}
                                    onChange={(e) =>
                                        setData('vet_name', e.target.value)
                                    }
                                    placeholder="Dr. Name"
                                    required
                                    className="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 outline-none focus:ring-2 focus:ring-green-400/40 dark:border-white/[.08] dark:bg-white/[.04] dark:text-white"
                                />
                                {errors.vet_name && (
                                    <p className="text-xs text-red-500">
                                        {errors.vet_name}
                                    </p>
                                )}
                            </div>
                            <div className="flex gap-3">
                                <button
                                    type="button"
                                    onClick={() => setStep('choose')}
                                    className="flex flex-1 items-center justify-center rounded-xl border border-gray-200 bg-gray-50 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-100 dark:border-white/10 dark:bg-white/[.03] dark:text-gray-300"
                                >
                                    Back
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="flex flex-1 items-center justify-center gap-2 rounded-xl bg-green-600 py-2.5 text-sm font-bold text-white hover:bg-green-700 disabled:opacity-50"
                                >
                                    {processing ? (
                                        'Confirming…'
                                    ) : (
                                        <>
                                            <CheckCircle2 size={14} /> Confirm
                                            Approval
                                        </>
                                    )}
                                </button>
                            </div>
                        </form>
                    )}

                    {step === 'reject' && (
                        <form onSubmit={submit} className="space-y-3">
                            <div className="space-y-1.5">
                                <label className="text-xs font-semibold text-gray-600 dark:text-gray-400">
                                    Reason for declining
                                </label>
                                <textarea
                                    value={data.rejection_reason}
                                    onChange={(e) =>
                                        setData(
                                            'rejection_reason',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="e.g. Fully booked on that date, please choose another time…"
                                    rows={2}
                                    required
                                    className="w-full resize-none rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 outline-none focus:ring-2 focus:ring-red-400/40 dark:border-white/[.08] dark:bg-white/[.04] dark:text-white dark:placeholder-white/20"
                                />
                                {errors.rejection_reason && (
                                    <p className="text-xs text-red-500">
                                        {errors.rejection_reason}
                                    </p>
                                )}
                            </div>
                            <div className="flex gap-3">
                                <button
                                    type="button"
                                    onClick={() => setStep('choose')}
                                    className="flex flex-1 items-center justify-center rounded-xl border border-gray-200 bg-gray-50 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-100 dark:border-white/10 dark:bg-white/[.03] dark:text-gray-300"
                                >
                                    Back
                                </button>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="flex flex-1 items-center justify-center gap-2 rounded-xl bg-red-500 py-2.5 text-sm font-bold text-white hover:bg-red-600 disabled:opacity-50"
                                >
                                    {processing
                                        ? 'Sending…'
                                        : 'Confirm Decline'}
                                </button>
                            </div>
                        </form>
                    )}
                </div>
            </div>
        </div>
    );
}

// ── Main page ─────────────────────────────────────────────────────────────────
export default function AdminAppointments() {
    const { clinicAppointments, userAppointments, stats } =
        usePage<PageProps>().props;
    const [respondingTo, setRespondingTo] = useState<Appointment | null>(null);

    const handlePageChange = (type: 'clinic' | 'user', page: number) => {
        router.visit(`/model/appointments?${type}_page=${page}`, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const deleteAppointment = (id: number) => {
        if (!confirm('Delete this appointment?')) return;
        router.delete(`/appointments/${id}`, { preserveScroll: true });
    };
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Appointments" />
            {respondingTo && (
                <RespondModal
                    appt={respondingTo}
                    onClose={() => setRespondingTo(null)}
                />
            )}

            <div className="flex h-full w-full flex-col gap-6 p-4 md:p-8">
                {/* Header */}
                <div>
                    <h1 className="text-xl font-bold dark:text-white">
                        Appointments
                    </h1>
                    <p className="text-sm text-gray-600 dark:text-white/70">
                        Manage all clinic-scheduled consultations and owner
                        appointment requests.
                    </p>
                </div>

                {/* Stat Cards */}
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                    {[
                        {
                            label: 'Total',
                            value: stats.total,
                            color: 'bg-blue-600',
                            Icon: CalendarDays,
                        },
                        {
                            label: 'Pending',
                            value: stats.pending,
                            color: 'bg-yellow-500',
                            Icon: Clock,
                        },
                        {
                            label: 'Accepted',
                            value: stats.accepted,
                            color: 'bg-green-600',
                            Icon: CheckCircle2,
                        },
                        {
                            label: 'Declined',
                            value: stats.rejected,
                            color: 'bg-red-500',
                            Icon: XCircle,
                        },
                        {
                            label: 'User Requests',
                            value: stats.user_requests,
                            color: 'bg-purple-600',
                            Icon: MessageSquare,
                        },
                        {
                            label: 'Clinic Scheduled',
                            value: stats.clinic_scheduled,
                            color: 'bg-indigo-600',
                            Icon: Stethoscope,
                        },
                    ].map((s) => (
                        <Card
                            key={s.label}
                            className="flex flex-row items-center justify-between px-4 py-4 shadow-sm dark:bg-neutral-900"
                        >
                            <div>
                                <p className="text-xs font-medium text-gray-600 dark:text-white/80">
                                    {s.label}
                                </p>
                                <p className="mt-1 text-2xl font-bold dark:text-white">
                                    {s.value}
                                </p>
                            </div>
                            <div
                                className={`flex h-10 w-10 items-center justify-center rounded-md ${s.color}`}
                            >
                                <s.Icon className="h-5 w-5 text-white" />
                            </div>
                        </Card>
                    ))}
                </div>

                {/* ── SECTION 1: User Appointment Requests ── */}
                <div>
                    <div className="mb-3 flex items-center gap-3">
                        <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-900/30">
                            <MessageSquare
                                size={14}
                                className="text-purple-600 dark:text-purple-400"
                            />
                        </div>
                        <div>
                            <h2 className="font-bold text-gray-900 dark:text-white">
                                Owner Appointment Requests
                            </h2>
                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                Requests submitted by dog owners. Accept to
                                confirm or decline with a reason.
                            </p>
                        </div>
                        {userAppointments.data.filter(
                            (a) => a.status === 'pending',
                        ).length > 0 && (
                            <span className="ml-auto inline-flex items-center gap-1.5 rounded-full border border-amber-400/30 bg-amber-400/[.07] px-3 py-1 font-mono text-[10px] font-bold text-amber-600 dark:text-amber-300">
                                <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-400" />
                                {
                                    userAppointments.data.filter(
                                        (a) => a.status === 'pending',
                                    ).length
                                }{' '}
                                Awaiting Response
                            </span>
                        )}
                    </div>

                    <Card className="overflow-hidden p-0 dark:bg-neutral-900">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[800px] text-sm">
                                <thead>
                                    <tr className="border-b border-gray-100 dark:border-white/[.06]">
                                        <th className="px-6 py-3 text-left text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                            Owner
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                            Preferred Date & Time
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                            Reason
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                            Notes
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                            Status
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-white/[.04]">
                                    {userAppointments.data.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={6}
                                                className="px-6 py-12 text-center text-sm text-gray-400 dark:text-gray-600"
                                            >
                                                No owner requests yet.
                                            </td>
                                        </tr>
                                    ) : (
                                        userAppointments.data.map((appt) => (
                                            <tr
                                                key={appt.id}
                                                className="transition-colors hover:bg-gray-50 dark:hover:bg-white/[.025]"
                                            >
                                                <td className="px-6 py-3">
                                                    <p className="font-semibold text-gray-900 dark:text-white">
                                                        {appt.owner?.name ??
                                                            '—'}
                                                    </p>
                                                    <p className="text-xs text-gray-400 dark:text-gray-500">
                                                        {appt.owner?.email}
                                                    </p>
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap text-gray-700 dark:text-gray-300">
                                                    <p className="font-medium">
                                                        {new Date(
                                                            appt.appointment_date,
                                                        ).toLocaleDateString(
                                                            'en-US',
                                                            {
                                                                month: 'short',
                                                                day: 'numeric',
                                                                year: 'numeric',
                                                            },
                                                        )}
                                                    </p>
                                                    <p className="text-xs text-gray-500">
                                                        {appt.appointment_time}
                                                    </p>
                                                </td>
                                                <td className="max-w-[200px] px-4 py-3">
                                                    <p
                                                        className="truncate text-gray-600 dark:text-gray-400"
                                                        title={appt.reason}
                                                    >
                                                        {appt.reason}
                                                    </p>
                                                </td>
                                                <td className="max-w-[160px] px-4 py-3">
                                                    {appt.notes ? (
                                                        <p
                                                            className="truncate text-xs text-gray-500 dark:text-gray-400"
                                                            title={appt.notes}
                                                        >
                                                            {appt.notes}
                                                        </p>
                                                    ) : (
                                                        <span className="text-xs text-gray-300 dark:text-gray-600">
                                                            —
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap">
                                                    {statusBadge(appt.status)}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {appt.status ===
                                                    'pending' ? (
                                                        <>
                                                            <Button
                                                                size="sm"
                                                                onClick={() =>
                                                                    setRespondingTo(
                                                                        appt,
                                                                    )
                                                                }
                                                                className="bg-purple-600 text-white hover:bg-purple-700"
                                                            >
                                                                Respond
                                                            </Button>
                                                            <button
                                                                onClick={() =>
                                                                    deleteAppointment(
                                                                        appt.id,
                                                                    )
                                                                }
                                                                className="flex h-8 w-8 items-center justify-center rounded-md text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400"
                                                                title="Delete"
                                                            >
                                                                <Trash2
                                                                    size={15}
                                                                />
                                                            </button>
                                                        </>
                                                    ) : (
                                                        <>
                                                            <span className="text-xs text-gray-400 italic dark:text-gray-600">
                                                                {appt.status ===
                                                                    'rejected' &&
                                                                appt.rejection_reason
                                                                    ? `"${appt.rejection_reason.substring(0, 30)}…"`
                                                                    : appt.status ===
                                                                        'accepted'
                                                                      ? `Vet: ${appt.vet_name}`
                                                                      : '—'}
                                                            </span>
                                                            <button
                                                                onClick={() =>
                                                                    deleteAppointment(
                                                                        appt.id,
                                                                    )
                                                                }
                                                                className="ml-6px flex h-8 w-8 items-center justify-center rounded-md text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400"
                                                                title="Delete"
                                                            >
                                                                <Trash2
                                                                    size={15}
                                                                />
                                                            </button>
                                                        </>
                                                    )}
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* User requests pagination */}
                        {userAppointments.last_page > 1 && (
                            <div className="flex items-center justify-between border-t border-gray-100 px-6 py-3 dark:border-white/[.06]">
                                <p className="text-xs text-gray-500">
                                    Showing {userAppointments.from}–
                                    {userAppointments.to} of{' '}
                                    {userAppointments.total}
                                </p>
                                <div className="flex items-center gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            handlePageChange(
                                                'user',
                                                userAppointments.current_page -
                                                    1,
                                            )
                                        }
                                        disabled={
                                            userAppointments.current_page === 1
                                        }
                                        className="dark:bg-neutral-800"
                                    >
                                        <ChevronLeft className="h-4 w-4" />
                                    </Button>
                                    <span className="text-xs text-gray-500">
                                        {userAppointments.current_page} /{' '}
                                        {userAppointments.last_page}
                                    </span>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            handlePageChange(
                                                'user',
                                                userAppointments.current_page +
                                                    1,
                                            )
                                        }
                                        disabled={
                                            userAppointments.current_page ===
                                            userAppointments.last_page
                                        }
                                        className="dark:bg-neutral-800"
                                    >
                                        <ChevronRight className="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        )}
                    </Card>
                </div>

                {/* ── SECTION 2: Clinic Scheduled Appointments ── */}
                <div>
                    <div className="mb-3 flex items-center gap-3">
                        <div className="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                            <Stethoscope
                                size={14}
                                className="text-blue-600 dark:text-blue-400"
                            />
                        </div>
                        <div>
                            <h2 className="font-bold text-gray-900 dark:text-white">
                                Clinic-Scheduled Appointments
                            </h2>
                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                Appointments the clinic scheduled for dog
                                owners. Waiting for owner confirmation.
                            </p>
                        </div>
                    </div>

                    <Card className="overflow-hidden p-0 dark:bg-neutral-900">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[900px] text-sm">
                                <thead>
                                    <tr className="border-b border-gray-100 dark:border-white/[.06]">
                                        <th className="px-6 py-3 text-left text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                            Dog
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                            Breed
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                            Date & Time
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                            Vet
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                            Reason
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                            Owner Response
                                        </th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-white/[.04]">
                                    {clinicAppointments.data.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={7}
                                                className="px-6 py-12 text-center"
                                            >
                                                <div className="flex flex-col items-center gap-2">
                                                    <CalendarDays
                                                        size={32}
                                                        className="text-gray-300 dark:text-gray-600"
                                                    />
                                                    <p className="text-sm text-gray-400 dark:text-gray-600">
                                                        No appointments
                                                        scheduled yet.
                                                    </p>
                                                    <p className="text-xs text-gray-300 dark:text-gray-700">
                                                        Schedule from the Scan
                                                        Results review page.
                                                    </p>
                                                </div>
                                            </td>
                                        </tr>
                                    ) : (
                                        clinicAppointments.data.map((appt) => (
                                            <tr
                                                key={appt.id}
                                                className="transition-colors hover:bg-gray-50 dark:hover:bg-white/[.025]"
                                            >
                                                <td className="px-6 py-3">
                                                    {appt.result?.image ? (
                                                        <div className="h-12 w-14 overflow-hidden rounded-md border border-gray-200 dark:border-gray-700">
                                                            <img
                                                                src={
                                                                    appt.result
                                                                        .image
                                                                }
                                                                alt={
                                                                    appt.result
                                                                        ?.breed
                                                                }
                                                                className="h-full w-full object-cover"
                                                            />
                                                        </div>
                                                    ) : (
                                                        <div className="flex h-12 w-14 items-center justify-center rounded-md border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-white/[.03]">
                                                            <Stethoscope
                                                                size={16}
                                                                className="text-gray-400"
                                                            />
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <p className="font-medium whitespace-nowrap text-gray-900 dark:text-white">
                                                        {appt.result?.breed ??
                                                            '—'}
                                                    </p>
                                                    <p className="font-mono text-[10px] text-gray-400">
                                                        #{appt.scan_id}
                                                    </p>
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap text-gray-700 dark:text-gray-300">
                                                    <p className="font-medium">
                                                        {new Date(
                                                            appt.appointment_date,
                                                        ).toLocaleDateString(
                                                            'en-US',
                                                            {
                                                                month: 'short',
                                                                day: 'numeric',
                                                                year: 'numeric',
                                                            },
                                                        )}
                                                    </p>
                                                    <p className="text-xs text-gray-500">
                                                        {appt.appointment_time}
                                                    </p>
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap text-gray-700 dark:text-gray-300">
                                                    {appt.vet_name}
                                                </td>
                                                <td className="max-w-[180px] px-4 py-3">
                                                    <p
                                                        className="truncate text-gray-600 dark:text-gray-400"
                                                        title={appt.reason}
                                                    >
                                                        {appt.reason}
                                                    </p>
                                                </td>
                                                <td className="px-4 py-3 whitespace-nowrap">
                                                    {statusBadge(appt.status)}
                                                    {appt.status ===
                                                        'rejected' &&
                                                        appt.rejection_reason && (
                                                            <p
                                                                className="mt-1 max-w-[140px] truncate text-[10px] text-red-500 italic"
                                                                title={
                                                                    appt.rejection_reason
                                                                }
                                                            >
                                                                "
                                                                {
                                                                    appt.rejection_reason
                                                                }
                                                                "
                                                            </p>
                                                        )}
                                                </td>
                                                <td className="flex gap-1 px-4 py-3">
                                                    {appt.status !==
                                                    'pending' ? (
                                                        <button
                                                            onClick={() =>
                                                                deleteAppointment(
                                                                    appt.id,
                                                                )
                                                            }
                                                            className="flex h-8 w-8 items-center justify-center rounded-md text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400"
                                                            title="Delete"
                                                        >
                                                            <Trash2 size={15} />
                                                        </button>
                                                    ) : (
                                                        <button
                                                            disabled
                                                            title="Wait for owner response before deleting"
                                                            className="flex h-8 w-8 cursor-not-allowed items-center justify-center rounded-md text-gray-200 dark:text-gray-700"
                                                        >
                                                            <Trash2 size={15} />
                                                        </button>
                                                    )}
                                                    <Button
                                                        asChild
                                                        size="sm"
                                                        variant="secondary"
                                                    >
                                                        <Link
                                                            href={`/model/review-dog/${appt.result?.id}`}
                                                        >
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

                        {/* Clinic appointments pagination */}
                        {clinicAppointments.last_page > 1 && (
                            <div className="flex items-center justify-between border-t border-gray-100 px-6 py-3 dark:border-white/[.06]">
                                <p className="text-xs text-gray-500">
                                    Showing {clinicAppointments.from}–
                                    {clinicAppointments.to} of{' '}
                                    {clinicAppointments.total}
                                </p>
                                <div className="flex items-center gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            handlePageChange(
                                                'clinic',
                                                clinicAppointments.current_page -
                                                    1,
                                            )
                                        }
                                        disabled={
                                            clinicAppointments.current_page ===
                                            1
                                        }
                                        className="dark:bg-neutral-800"
                                    >
                                        <ChevronLeft className="h-4 w-4" />
                                    </Button>
                                    <span className="text-xs text-gray-500">
                                        {clinicAppointments.current_page} /{' '}
                                        {clinicAppointments.last_page}
                                    </span>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            handlePageChange(
                                                'clinic',
                                                clinicAppointments.current_page +
                                                    1,
                                            )
                                        }
                                        disabled={
                                            clinicAppointments.current_page ===
                                            clinicAppointments.last_page
                                        }
                                        className="dark:bg-neutral-800"
                                    >
                                        <ChevronRight className="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        )}
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
