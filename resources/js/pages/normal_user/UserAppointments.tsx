import Header from '@/components/header';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    CheckCircle2,
    ChevronRight,
    Clock,
    FileText,
    History,
    Scan as ScanIcon,
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

type TopBreed = {
    breed: string;
    scan_count: number;
    avg_confidence: number;
    bar_width: number;
};

type GlobalStats = {
    total_scans: string;
    verified: string;
    avg_score: string;
    uptime: string;
};

type PageProps = {
    appointments: Appointment[];
    topBreeds?: TopBreed[];
    globalStats?: GlobalStats;
};

// ── Panel (matches scan page) ─────────────────────────────────────────────────
const Panel = ({
    icon,
    title,
    children,
}: {
    icon: React.ReactNode;
    title: string;
    children: React.ReactNode;
}) => (
    <div className="relative flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-white/[.07] dark:bg-[#131720]">
        <div className="flex flex-shrink-0 items-center gap-2 border-b border-slate-200 bg-slate-50/80 px-3 py-2.5 dark:border-white/[.06] dark:bg-white/[.025]">
            <div className="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-md border border-emerald-500/20 bg-emerald-500/10 text-emerald-500">
                {icon}
            </div>
            <span className="font-mono text-[10px] font-bold tracking-[.12em] text-slate-600 uppercase dark:text-slate-400">
                {title}
            </span>
        </div>
        {children}
    </div>
);

// ── Status config ─────────────────────────────────────────────────────────────
const statusConfig = {
    pending: {
        label: 'Awaiting Response',
        barColor: 'bg-amber-400',
        dot: 'bg-amber-400 shadow-[0_0_6px_#f59e0b]',
        badge: 'border border-amber-400/30 bg-amber-400/[.08] text-amber-600 dark:text-amber-300',
        Icon: Clock,
    },
    accepted: {
        label: 'Confirmed',
        barColor: 'bg-emerald-500',
        dot: 'bg-emerald-500 shadow-[0_0_6px_#10b981]',
        badge: 'border border-emerald-500/30 bg-emerald-500/[.08] text-emerald-700 dark:text-emerald-400',
        Icon: CheckCircle2,
    },
    rejected: {
        label: 'Declined',
        barColor: 'bg-red-500',
        dot: 'bg-red-500 shadow-[0_0_6px_#ef4444]',
        badge: 'border border-red-400/30 bg-red-500/[.07] text-red-600 dark:text-red-400',
        Icon: XCircle,
    },
};

// ── Appointment Card ──────────────────────────────────────────────────────────
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
        <div className="sc-appt-card relative overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-white/[.07] dark:bg-[#131720]">
            {/* Accent top bar */}

            {/* Card header */}
            <div className="flex flex-col gap-3 border-b border-slate-100 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between dark:border-white/[.05]">
                <div className="flex items-center gap-3">
                    {appt.result?.image ? (
                        <div className="h-11 w-13 flex-shrink-0 overflow-hidden rounded-xl border border-slate-200 dark:border-white/[.07]">
                            <img
                                src={appt.result.image}
                                alt={appt.result.breed}
                                className="h-full w-full object-cover"
                            />
                        </div>
                    ) : (
                        <div className="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 dark:border-white/[.07] dark:bg-white/[.03]">
                            <Stethoscope size={16} className="text-slate-400" />
                        </div>
                    )}
                    <div>
                        <p className="text-[13px] font-bold text-slate-900 dark:text-white">
                            {appt.result?.breed ?? 'Unknown Breed'}
                        </p>
                        <p className="font-mono text-[10px] text-slate-400 dark:text-slate-500">
                            #{appt.scan_id}
                        </p>
                    </div>
                </div>

                {/* Status badge */}
                <span
                    className={`inline-flex items-center gap-1.5 self-start rounded-full px-3 py-1 font-mono text-[10px] font-semibold tracking-[.08em] uppercase sm:self-auto ${cfg.badge}`}
                >
                    <span className={`h-1.5 w-1.5 rounded-full ${cfg.dot}`} />
                    {cfg.label}
                </span>
            </div>

            {/* Details grid */}
            <div className="grid grid-cols-2 gap-2 p-3.5 sm:grid-cols-4">
                {[
                    {
                        icon: <CalendarDays size={10} />,
                        label: 'Date',
                        value: new Date(
                            appt.appointment_date,
                        ).toLocaleDateString('en-US', {
                            month: 'short',
                            day: 'numeric',
                            year: 'numeric',
                        }),
                    },
                    {
                        icon: <Clock size={10} />,
                        label: 'Time',
                        value: appt.appointment_time,
                    },
                    {
                        icon: <User size={10} />,
                        label: 'Vet',
                        value: appt.vet_name,
                    },
                    {
                        icon: <Stethoscope size={10} />,
                        label: 'Reason',
                        value: appt.reason,
                    },
                ].map((item, i) => (
                    <div
                        key={i}
                        className="rounded-xl border border-slate-200 bg-slate-50 p-2.5 transition-all hover:border-emerald-500/25 hover:bg-emerald-500/[.025] dark:border-white/[.04] dark:bg-white/[.03]"
                    >
                        <p className="mb-0.5 flex items-center gap-1 font-mono text-[9px] font-medium tracking-[.08em] text-slate-400 uppercase dark:text-slate-500">
                            <span className="text-emerald-500/70">
                                {item.icon}
                            </span>
                            {item.label}
                        </p>
                        <p
                            className="truncate text-[12px] font-semibold text-slate-800 dark:text-slate-200"
                            title={item.value}
                        >
                            {item.value}
                        </p>
                    </div>
                ))}
            </div>

            {/* Clinic notes */}
            {appt.notes && (
                <div className="px-3.5 pb-3.5">
                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-white/[.04] dark:bg-white/[.02]">
                        <p className="mb-0.5 font-mono text-[9px] font-semibold tracking-[.1em] text-emerald-600 uppercase dark:text-emerald-500">
                            Clinic Notes
                        </p>
                        <p className="text-[12px] leading-relaxed text-slate-600 dark:text-slate-400">
                            {appt.notes}
                        </p>
                    </div>
                </div>
            )}

            {/* Rejection reason */}
            {appt.status === 'rejected' && appt.rejection_reason && (
                <div className="px-3.5 pb-3.5">
                    <div className="rounded-xl border border-red-200/60 bg-red-500/[.05] p-3 dark:border-red-500/20 dark:bg-red-500/[.07]">
                        <p className="mb-0.5 font-mono text-[9px] font-semibold tracking-[.1em] text-red-500 uppercase dark:text-red-400">
                            Your Reason
                        </p>
                        <p className="text-[12px] leading-relaxed text-red-700 dark:text-red-300">
                            {appt.rejection_reason}
                        </p>
                    </div>
                </div>
            )}

            {/* Action buttons */}
            {appt.status === 'pending' && (
                <div className="border-t border-slate-100 px-3.5 py-3 dark:border-white/[.05]">
                    {!showRejectForm ? (
                        <div className="flex gap-2.5">
                            <button
                                onClick={() => respond('accepted')}
                                disabled={processing}
                                className="flex flex-1 items-center justify-center gap-2 rounded-xl bg-emerald-500 py-2.5 text-[12px] font-bold text-black shadow-lg shadow-emerald-500/20 transition-all hover:-translate-y-0.5 hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <CheckCircle2 size={13} /> Accept
                            </button>
                            <button
                                onClick={() => respond('rejected')}
                                disabled={processing}
                                className="flex flex-1 items-center justify-center gap-2 rounded-xl border border-red-200/70 bg-red-500/[.06] py-2.5 text-[12px] font-bold text-red-600 transition-all hover:bg-red-500/[.12] disabled:opacity-50 dark:border-red-500/25 dark:text-red-400"
                            >
                                <XCircle size={13} /> Decline
                            </button>
                        </div>
                    ) : (
                        <div className="flex flex-col gap-2.5">
                            <p className="font-mono text-[10px] font-semibold tracking-[.1em] text-slate-500 uppercase dark:text-slate-500">
                                Reason for declining
                            </p>
                            <textarea
                                value={data.rejection_reason}
                                onChange={(e) =>
                                    setData('rejection_reason', e.target.value)
                                }
                                placeholder="e.g. Schedule conflict, will reschedule soon…"
                                rows={2}
                                className="w-full resize-none rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-[12px] text-slate-800 transition-all outline-none focus:border-red-400/60 focus:ring-2 focus:ring-red-400/20 dark:border-white/[.08] dark:bg-white/[.04] dark:text-white dark:placeholder-white/20"
                            />
                            {errors.rejection_reason && (
                                <p className="font-mono text-[10px] text-red-500">
                                    {errors.rejection_reason}
                                </p>
                            )}
                            <div className="flex gap-2.5">
                                <button
                                    onClick={() =>
                                        post(`/appointments/${appt.id}/status`)
                                    }
                                    disabled={processing}
                                    className="flex flex-1 items-center justify-center gap-2 rounded-xl bg-red-500 py-2.5 text-[12px] font-bold text-white transition-all hover:bg-red-400 disabled:opacity-50"
                                >
                                    {processing
                                        ? 'Sending…'
                                        : 'Confirm Decline'}
                                </button>
                                <button
                                    onClick={() => setShowRejectForm(false)}
                                    disabled={processing}
                                    className="flex flex-1 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-slate-100 py-2.5 text-[12px] font-semibold text-slate-600 transition-all hover:bg-slate-200 dark:border-white/[.08] dark:bg-white/[.05] dark:text-slate-400"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

// ── Main Page ─────────────────────────────────────────────────────────────────
export default function UserAppointments() {
    const {
        appointments,
        topBreeds = [],
        globalStats,
    } = usePage<PageProps>().props;

    const stats: GlobalStats = globalStats ?? {
        total_scans: '—',
        verified: '—',
        avg_score: '—',
        uptime: '99.9%',
    };

    const pending = appointments.filter((a) => a.status === 'pending');
    const responded = appointments.filter((a) => a.status !== 'pending');

    return (
        <>
            <Head title="My Appointments" />

            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap');

                @keyframes sc-dpulse  { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(1.5);opacity:.5} }
                @keyframes sc-faderise{ from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }

                .sc-root { font-family:'Plus Jakarta Sans',sans-serif; }
                .sc-root * { box-sizing:border-box; }
                .sc-mono { font-family:'JetBrains Mono',monospace !important; }

                .sc-dotgrid {
                    position:fixed; inset:0; pointer-events:none; z-index:0;
                    background-image:radial-gradient(circle,rgba(16,185,129,.08) 1px,transparent 1px);
                    background-size:28px 28px;
                    -webkit-mask-image:radial-gradient(ellipse 80% 55% at 50% 0%,black 0%,transparent 100%);
                    mask-image:radial-gradient(ellipse 80% 55% at 50% 0%,black 0%,transparent 100%);
                }
                .dark .sc-dotgrid { background-image:radial-gradient(circle,rgba(16,185,129,.06) 1px,transparent 1px); }

                .sc-panel-line::before {
                    content:''; position:absolute; top:0; left:0; right:0; height:1.5px;
                    background:linear-gradient(90deg,transparent,#10b981 45%,#06b6d4 55%,transparent); opacity:.32;
                }
                .sc-fu        { animation:sc-faderise .48s cubic-bezier(.16,1,.3,1) both; }
                .sc-nsb::-webkit-scrollbar { display:none; }
                .sc-nsb { scrollbar-width:none; }
                .sc-appt-card { transition: box-shadow .2s, border-color .2s; }
                .sc-appt-card:hover { border-color: rgba(16,185,129,.18); }
            `}</style>

            <div className="sc-root flex h-screen flex-col overflow-hidden bg-slate-50 transition-colors duration-300 dark:bg-[#080B0F]">
                {/* Ambient glows */}
                <div className="pointer-events-none fixed top-[-140px] left-[-70px] z-0 h-[260px] w-[460px] rounded-full bg-emerald-400/[.042] blur-[85px]" />
                <div className="pointer-events-none fixed top-[-90px] right-[-40px] z-0 h-[210px] w-[340px] rounded-full bg-cyan-400/[.028] blur-[85px]" />
                <div className="sc-dotgrid" />

                {/* Header */}
                <div className="relative z-20 flex-shrink-0">
                    <Header />
                </div>

                {/* Main content */}
                <div className="relative z-10 mt-[-20px] min-h-0 flex-1 overflow-hidden p-3 px-4">
                    <div className="sc-nsb mx-auto h-full max-w-[1360px] overflow-x-hidden overflow-y-auto lg:overflow-hidden">
                        <div className="flex flex-col gap-3 p-3 pb-24 lg:grid lg:h-full lg:grid-cols-[210px_1fr_220px] lg:grid-rows-[1fr] lg:gap-4 lg:overflow-hidden lg:p-4 lg:pb-4 xl:grid-cols-[224px_1fr_232px]">
                            {/* ── LEFT SIDEBAR (desktop only) ── */}
                            <div className="hidden lg:flex lg:min-h-0 lg:flex-col lg:justify-start lg:gap-3 lg:overflow-x-hidden lg:overflow-y-auto">
                                <Panel
                                    icon={<ScanIcon size={11} />}
                                    title="Navigation"
                                >
                                    <div className="flex flex-col gap-1 p-2.5">
                                        <Link
                                            href="/scan"
                                            className="flex items-center gap-2 rounded-xl border border-transparent px-3 py-2 text-[11px] font-semibold text-slate-600 no-underline transition-all hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-white/[.05] dark:hover:text-slate-200"
                                        >
                                            <ScanIcon size={13} />
                                            <span>New Scan</span>
                                        </Link>
                                        <Link
                                            href="/scanhistory"
                                            className="flex items-center gap-2 rounded-xl border border-transparent px-3 py-2 text-[11px] font-semibold text-slate-600 no-underline transition-all hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-white/[.05] dark:hover:text-slate-200"
                                        >
                                            <History size={13} />
                                            <span>Scan History</span>
                                        </Link>
                                        <Link
                                            href="/appointments"
                                            className="flex items-center gap-2 rounded-xl border border-emerald-500/25 bg-emerald-500/[.09] px-3 py-2 text-[11px] font-semibold text-emerald-700 no-underline dark:bg-emerald-500/[.11] dark:text-emerald-400"
                                        >
                                            <CalendarDays size={13} />
                                            <span>Appointments</span>
                                            <ChevronRight
                                                size={11}
                                                className="ml-auto opacity-40"
                                            />
                                        </Link>
                                    </div>
                                </Panel>

                                <Panel
                                    icon={<FileText size={11} />}
                                    title="What to Expect"
                                >
                                    <div className="flex flex-col p-3">
                                        {[
                                            {
                                                n: '01',
                                                t: "Clinic reviews your dog's breed scan",
                                            },
                                            {
                                                n: '02',
                                                t: 'They schedule a consultation and notify you',
                                            },
                                            {
                                                n: '03',
                                                t: 'Accept or decline with an optional reason',
                                            },
                                            {
                                                n: '04',
                                                t: 'Attend your appointment for expert advice',
                                            },
                                        ].map((s, i) => (
                                            <div
                                                key={i}
                                                className={`flex items-start gap-2.5 py-2.5 ${i < 3 ? 'border-b border-slate-200 dark:border-white/[.05]' : ''}`}
                                            >
                                                <span className="sc-mono mt-[2px] w-5 flex-shrink-0 text-[9px] font-semibold text-emerald-600/80 dark:text-emerald-500/65">
                                                    {s.n}
                                                </span>
                                                <p className="text-[12px] leading-relaxed text-slate-600 dark:text-slate-400">
                                                    {s.t}
                                                </p>
                                            </div>
                                        ))}
                                    </div>
                                </Panel>
                            </div>

                            {/* ── CENTER ── */}
                            <div className="sc-fu flex min-h-0 flex-1 flex-col gap-3">
                                {/* Page title */}
                                <div className="flex flex-shrink-0 flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <h1 className="text-lg leading-none font-extrabold tracking-tight text-slate-900 sm:text-xl dark:text-white">
                                            My Appointments
                                        </h1>
                                        <p className="mt-1 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                                            Appointments scheduled by your vet
                                            clinic based on scan results.
                                        </p>
                                    </div>

                                    {/* Pending count badge */}
                                    {pending.length > 0 && (
                                        <div className="inline-flex items-center gap-2 rounded-xl border border-amber-400/30 bg-amber-400/[.07] px-3.5 py-2">
                                            <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-400 shadow-[0_0_6px_#f59e0b]" />
                                            <span className="sc-mono text-[10px] font-bold tracking-[.1em] text-amber-600 uppercase dark:text-amber-300">
                                                {pending.length} Pending
                                            </span>
                                        </div>
                                    )}
                                </div>

                                {/* Terminal bar card wrapping the list */}
                                <div className="sc-panel-line relative flex min-h-0 flex-1 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-white/[.07] dark:bg-[#131720]">
                                    {/* Scrollable content */}
                                    <div className="sc-nsb flex-1 overflow-y-auto p-4">
                                        {appointments.length === 0 ? (
                                            /* Empty state */
                                            <div className="flex flex-col items-center justify-center gap-4 py-20">
                                                <div className="relative flex h-16 w-16 items-center justify-center">
                                                    <div
                                                        className="absolute inset-0 rounded-full border border-emerald-500/20 opacity-60"
                                                        style={{
                                                            animation:
                                                                'sc-dpulse 2.6s ease-out infinite',
                                                        }}
                                                    />
                                                    <div className="flex h-full w-full items-center justify-center rounded-full border border-emerald-500/20 bg-emerald-500/[.08]">
                                                        <CalendarDays
                                                            size={22}
                                                            className="text-emerald-500/70"
                                                        />
                                                    </div>
                                                </div>
                                                <div className="text-center">
                                                    <p className="text-[14px] font-bold text-slate-700 dark:text-slate-300">
                                                        No appointments yet
                                                    </p>
                                                    <p className="sc-mono mt-1 text-[10px] tracking-[.1em] text-slate-400 dark:text-slate-600">
                                                        THE CLINIC WILL NOTIFY
                                                        YOU HERE
                                                    </p>
                                                </div>
                                            </div>
                                        ) : (
                                            <div className="flex flex-col gap-6">
                                                {/* Pending section */}
                                                {pending.length > 0 && (
                                                    <section>
                                                        <div className="mb-3 flex items-center gap-2.5">
                                                            <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-400 shadow-[0_0_5px_#f59e0b]" />
                                                            <span className="sc-mono text-[10px] font-bold tracking-[.12em] text-amber-600 uppercase dark:text-amber-400">
                                                                Action Required
                                                                —{' '}
                                                                {pending.length}
                                                            </span>
                                                            <div className="h-px flex-1 bg-amber-400/20" />
                                                        </div>
                                                        <div className="flex flex-col gap-3">
                                                            {pending.map(
                                                                (a) => (
                                                                    <AppointmentCard
                                                                        key={
                                                                            a.id
                                                                        }
                                                                        appt={a}
                                                                    />
                                                                ),
                                                            )}
                                                        </div>
                                                    </section>
                                                )}

                                                {/* Responded section */}
                                                {responded.length > 0 && (
                                                    <section>
                                                        <div className="mb-3 flex items-center gap-2.5">
                                                            <span className="h-1.5 w-1.5 rounded-full bg-slate-400 dark:bg-slate-600" />
                                                            <span className="sc-mono text-[10px] font-bold tracking-[.12em] text-slate-500 uppercase dark:text-slate-500">
                                                                Responded —{' '}
                                                                {
                                                                    responded.length
                                                                }
                                                            </span>
                                                            <div className="h-px flex-1 bg-slate-200 dark:bg-white/[.05]" />
                                                        </div>
                                                        <div className="flex flex-col gap-3">
                                                            {responded.map(
                                                                (a) => (
                                                                    <AppointmentCard
                                                                        key={
                                                                            a.id
                                                                        }
                                                                        appt={a}
                                                                    />
                                                                ),
                                                            )}
                                                        </div>
                                                    </section>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
