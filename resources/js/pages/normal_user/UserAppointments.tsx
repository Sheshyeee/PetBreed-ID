import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import Header from '@/components/header';
import { Head, useForm, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    CheckCircle2,
    Clock,
    Stethoscope,
    User,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';

type ResultInfo = {
    id: number;
    scan_id: string;
    breed: string;
    confidence: number;
    image: string;
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
    result?: ResultInfo;
    created_at: string;
};

type PageProps = {
    appointments: Appointment[];
};

// ── status helpers ────────────────────────────────────────────────────────────
const statusConfig = {
    pending:  { label: 'Awaiting Your Response', color: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300', Icon: Clock },
    accepted: { label: 'Confirmed',               color: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',   Icon: CheckCircle2 },
    rejected: { label: 'Declined',                color: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',           Icon: XCircle },
};

// ── Individual appointment card ───────────────────────────────────────────────
function AppointmentCard({ appt }: { appt: Appointment }) {
    const [showRejectForm, setShowRejectForm] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        status: '' as 'accepted' | 'rejected',
        rejection_reason: '',
    });

    const respond = (status: 'accepted' | 'rejected') => {
        if (status === 'rejected' && !showRejectForm) {
            setShowRejectForm(true);
            setData('status', 'rejected');
            return;
        }
        setData('status', status);
        post(`/appointments/${appt.id}/status`, {
            onSuccess: () => setShowRejectForm(false),
        });
    };

    const cfg = statusConfig[appt.status];
    const StatusIcon = cfg.Icon;

    return (
        <Card className="overflow-hidden dark:bg-neutral-900">
            {/* Card header row */}
            <div className="flex flex-col gap-3 border-b border-gray-100 p-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-3">
                    {/* Dog thumbnail */}
                    {appt.result?.image && (
                        <div className="h-12 w-14 flex-shrink-0 overflow-hidden rounded-md border border-gray-200 dark:border-gray-700">
                            <img src={appt.result.image} alt={appt.result.breed} className="h-full w-full object-cover" />
                        </div>
                    )}
                    <div>
                        <p className="text-sm font-bold text-gray-900 dark:text-white">{appt.result?.breed ?? 'Unknown Breed'}</p>
                        <p className="font-mono text-xs text-gray-500 dark:text-gray-400">#{appt.scan_id}</p>
                    </div>
                </div>
                {/* Status badge */}
                <span className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ${cfg.color}`}>
                    <StatusIcon size={12} /> {cfg.label}
                </span>
            </div>

            {/* Appointment details */}
            <div className="grid grid-cols-2 gap-3 p-4 sm:grid-cols-4">
                <div className="rounded-lg bg-gray-50 p-2.5 dark:bg-neutral-800/50">
                    <p className="mb-0.5 flex items-center gap-1 text-xs text-gray-400"><CalendarDays size={10} /> Date</p>
                    <p className="text-sm font-semibold text-gray-800 dark:text-white">
                        {new Date(appt.appointment_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                    </p>
                </div>
                <div className="rounded-lg bg-gray-50 p-2.5 dark:bg-neutral-800/50">
                    <p className="mb-0.5 flex items-center gap-1 text-xs text-gray-400"><Clock size={10} /> Time</p>
                    <p className="text-sm font-semibold text-gray-800 dark:text-white">{appt.appointment_time}</p>
                </div>
                <div className="rounded-lg bg-gray-50 p-2.5 dark:bg-neutral-800/50">
                    <p className="mb-0.5 flex items-center gap-1 text-xs text-gray-400"><User size={10} /> Vet</p>
                    <p className="text-sm font-semibold text-gray-800 dark:text-white">{appt.vet_name}</p>
                </div>
                <div className="rounded-lg bg-gray-50 p-2.5 dark:bg-neutral-800/50">
                    <p className="mb-0.5 flex items-center gap-1 text-xs text-gray-400"><Stethoscope size={10} /> Reason</p>
                    <p className="truncate text-sm font-semibold text-gray-800 dark:text-white" title={appt.reason}>{appt.reason}</p>
                </div>
            </div>

            {appt.notes && (
                <div className="px-4 pb-4">
                    <div className="rounded-lg bg-blue-50/60 p-3 dark:bg-blue-900/10">
                        <p className="text-xs font-semibold text-blue-700 dark:text-blue-400">Clinic Notes</p>
                        <p className="mt-0.5 text-sm text-gray-700 dark:text-gray-300">{appt.notes}</p>
                    </div>
                </div>
            )}

            {/* Rejection reason if already declined */}
            {appt.status === 'rejected' && appt.rejection_reason && (
                <div className="px-4 pb-4">
                    <div className="rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-900/30 dark:bg-red-900/10">
                        <p className="text-xs font-semibold text-red-600 dark:text-red-400">Your reason:</p>
                        <p className="mt-0.5 text-sm text-red-700 dark:text-red-300">{appt.rejection_reason}</p>
                    </div>
                </div>
            )}

            {/* Action buttons — only shown when pending */}
            {appt.status === 'pending' && (
                <div className="border-t border-gray-100 p-4 dark:border-gray-800">
                    {!showRejectForm ? (
                        <div className="flex gap-3">
                            <Button
                                onClick={() => respond('accepted')}
                                disabled={processing}
                                className="flex-1 bg-green-600 text-white hover:bg-green-700"
                            >
                                <CheckCircle2 size={15} className="mr-2" /> Accept Appointment
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() => respond('rejected')}
                                disabled={processing}
                                className="flex-1 border-red-200 text-red-600 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20"
                            >
                                <XCircle size={15} className="mr-2" /> Decline
                            </Button>
                        </div>
                    ) : (
                        <div className="space-y-3">
                            <p className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                Please tell the clinic why you are declining:
                            </p>
                            <textarea
                                value={data.rejection_reason}
                                onChange={(e) => setData('rejection_reason', e.target.value)}
                                placeholder="e.g. Schedule conflict, will reschedule soon…"
                                rows={2}
                                className="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-red-400 dark:border-white/20 dark:bg-white/5 dark:text-white dark:placeholder-white/30"
                            />
                            {errors.rejection_reason && (
                                <p className="text-xs text-red-500">{errors.rejection_reason}</p>
                            )}
                            <div className="flex gap-3">
                                <Button
                                    onClick={() => post(`/appointments/${appt.id}/status`)}
                                    disabled={processing}
                                    className="flex-1 bg-red-600 text-white hover:bg-red-700"
                                >
                                    {processing ? 'Sending…' : 'Confirm Decline'}
                                </Button>
                                <Button
                                    variant="outline"
                                    onClick={() => setShowRejectForm(false)}
                                    disabled={processing}
                                    className="flex-1"
                                >
                                    Cancel
                                </Button>
                            </div>
                        </div>
                    )}
                </div>
            )}
        </Card>
    );
}

// ── Main page ─────────────────────────────────────────────────────────────────
export default function UserAppointments() {
    const { appointments } = usePage<PageProps>().props;

    const pending  = appointments.filter((a) => a.status === 'pending');
    const past     = appointments.filter((a) => a.status !== 'pending');

    return (
        <>
            <Head title="My Appointments" />
            <div className="flex min-h-screen flex-col bg-white dark:bg-[#080B0F]">
                <Header />

                <div className="mx-auto w-full max-w-3xl flex-1 px-4 py-8">
                    {/* Page header */}
                    <div className="mb-6">
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">My Appointments</h1>
                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Appointments scheduled by your vet clinic based on your dog's breed scan results.
                        </p>
                    </div>

                    {appointments.length === 0 ? (
                        <Card className="flex flex-col items-center gap-3 py-16 text-center dark:bg-neutral-900">
                            <CalendarDays size={40} className="text-gray-300 dark:text-gray-600" />
                            <p className="text-gray-500 dark:text-gray-400">No appointments scheduled yet.</p>
                            <p className="text-xs text-gray-400 dark:text-gray-500">
                                The clinic will notify you here when they schedule a consultation for your dog.
                            </p>
                        </Card>
                    ) : (
                        <div className="space-y-8">
                            {/* Pending — action required */}
                            {pending.length > 0 && (
                                <section>
                                    <div className="mb-3 flex items-center gap-2">
                                        <span className="h-2 w-2 animate-pulse rounded-full bg-yellow-400" />
                                        <h2 className="text-sm font-semibold uppercase tracking-wide text-yellow-600 dark:text-yellow-400">
                                            Action Required ({pending.length})
                                        </h2>
                                    </div>
                                    <div className="space-y-4">
                                        {pending.map((a) => <AppointmentCard key={a.id} appt={a} />)}
                                    </div>
                                </section>
                            )}

                            {/* Past / Responded */}
                            {past.length > 0 && (
                                <section>
                                    <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                        Responded
                                    </h2>
                                    <div className="space-y-4">
                                        {past.map((a) => <AppointmentCard key={a.id} appt={a} />)}
                                    </div>
                                </section>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}