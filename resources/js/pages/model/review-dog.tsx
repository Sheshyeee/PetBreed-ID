import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    CalendarDays,
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    Clock,
    Heart,
    MapPin,
    Ruler,
    Scale,
    Shield,
    Stethoscope,
    User,
    X,
    XCircle,
} from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Scan Results', href: '/model/scan-results' },
    { title: 'Review', href: '/model/review-dog' },
];

type PredictionResult = { breed: string; confidence: number };

type HealthConcern = {
    name: string;
    risk_level: string;
    description: string;
    prevention: string;
};

type HealthScreening = { name: string; description: string };

type HealthRisks = {
    concerns?: HealthConcern[];
    screenings?: HealthScreening[];
    lifespan?: string;
    care_tips?: string[];
    weight?: { male?: string; female?: string } | string;
    height?: { male?: string; female?: string } | string;
};

type OriginHistory = {
    country?: string;
    country_code?: string;
    region?: string;
};

type Appointment = {
    id: number;
    appointment_date: string;
    appointment_time: string;
    vet_name: string;
    reason: string;
    notes?: string;
    status: 'pending' | 'accepted' | 'rejected';
    rejection_reason?: string;
};

type Result = {
    id: number;
    scan_id: string;
    image: string;
    breed: string;
    confidence: number;
    top_predictions: PredictionResult[];
    health_risks?: HealthRisks | string;
    origin_history?: OriginHistory | string;
    user_id?: number;
    created_at?: string;
};

type PageProps = {
    result?: Result;
    appointment?: Appointment | null;
    already_corrected?: boolean;
};

const riskColor = (level: string) => {
    if (level?.toLowerCase().includes('high'))
        return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
    if (level?.toLowerCase().includes('moderate'))
        return 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400';
    return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
};

const riskBar = (level: string) => {
    if (level?.toLowerCase().includes('high')) return '[&>div]:bg-red-500';
    if (level?.toLowerCase().includes('moderate')) return '[&>div]:bg-yellow-500';
    return '[&>div]:bg-green-500';
};

const riskValue = (level: string) => {
    if (level?.toLowerCase().includes('high')) return 85;
    if (level?.toLowerCase().includes('moderate')) return 55;
    return 25;
};

function parseField<T>(field: T | string | undefined): T | undefined {
    if (!field) return undefined;
    if (typeof field === 'string') {
        try { return JSON.parse(field) as T; } catch { return undefined; }
    }
    return field as T;
}

export default function ReviewDog() {
    const { result, appointment, already_corrected } = usePage<PageProps>().props;

    // ── Correction form ──────────────────────────────────────────────────────
    const { data, setData, post, processing, errors, reset } = useForm({
        scan_id: result?.scan_id || '',
        correct_breed: '',
    });

    // ── Appointment form ─────────────────────────────────────────────────────
    const apptForm = useForm({
        scan_id: result?.scan_id || '',
        result_id: result?.id || '',
        appointment_date: '',
        appointment_time: '',
        vet_name: '',
        reason: '',
        notes: '',
    });

    // ── UI state ─────────────────────────────────────────────────────────────
    const [summaryOpen, setSummaryOpen]               = useState(true);
    const [apptOpen, setApptOpen]                     = useState(true);
    const [showApptModal, setShowApptModal]           = useState(false);    
    const [showSuccessAlert, setShowSuccessAlert]     = useState(false);
    const [correctedBreedName, setCorrectedBreedName] = useState('');
    const [corrected, setCorrected] = useState(already_corrected ?? false);

    // ── Correction submit — stay on page, show prompt ────────────────────────
    const submitCorrection: FormEventHandler = (e) => {
        e.preventDefault();
        const breedBeforeReset = data.correct_breed;
        post('/model/correct', {
            onSuccess: () => {
                setCorrectedBreedName(breedBeforeReset);
                reset('correct_breed');
                setCorrected(true);
                setShowApptModal(true);
            },
        });
    };

    // ── Appointment submit — show success alert ──────────────────────────────
    const submitAppointment: FormEventHandler = (e) => {
        e.preventDefault();
        apptForm.post('/model/appointments', {
            onSuccess: () => {
                setApptOpen(false);
                setShowSuccessAlert(true);
            },
        });
    };

    // ── Parse stored JSON ────────────────────────────────────────────────────
    const healthRisks = parseField<HealthRisks>(result?.health_risks);
    const origin      = parseField<OriginHistory>(result?.origin_history);
    const concerns    = healthRisks?.concerns   ?? [];
    const screenings  = healthRisks?.screenings ?? [];
    const careTips    = healthRisks?.care_tips   ?? [];

    const weightStr = (() => {
        const w = healthRisks?.weight;
        if (!w) return null;
        if (typeof w === 'string') return w;
        const parts: string[] = [];
        if (w.male)   parts.push(`Male: ${w.male}`);
        if (w.female) parts.push(`Female: ${w.female}`);
        return parts.join(' · ') || null;
    })();

    const heightStr = (() => {
        const h = healthRisks?.height;
        if (!h) return null;
        if (typeof h === 'string') return h;
        const parts: string[] = [];
        if (h.male)   parts.push(`Male: ${h.male}`);
        if (h.female) parts.push(`Female: ${h.female}`);
        return parts.join(' · ') || null;
    })();

    const apptStatusBadge = () => {
        if (!appointment) return null;
        if (appointment.status === 'accepted')
            return (
                <span className="inline-flex items-center gap-1.5 rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700 dark:bg-green-900/30 dark:text-green-400">
                    <CheckCircle2 size={12} /> Owner Accepted
                </span>
            );
        if (appointment.status === 'rejected')
            return (
                <span className="inline-flex items-center gap-1.5 rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700 dark:bg-red-900/30 dark:text-red-400">
                    <XCircle size={12} /> Owner Declined
                </span>
            );
        return (
            <span className="inline-flex items-center gap-1.5 rounded-full bg-yellow-100 px-3 py-1 text-xs font-semibold text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400">
                <Clock size={12} /> Awaiting Owner Response
            </span>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Review Scan" />

            {/* ── Teaching overlay ─────────────────────────────────────────── */}
            {processing && (
                <div className="fixed inset-0 z-50 flex flex-col items-center justify-center bg-black/60 backdrop-blur-sm">
                    <div className="flex flex-col items-center gap-5 rounded-2xl border border-white/10 bg-white px-10 py-8 shadow-2xl dark:bg-neutral-900">
                        <div className="relative flex h-16 w-16 items-center justify-center">
                            <div className="absolute inset-0 animate-spin rounded-full border-4 border-blue-100 border-t-blue-600 dark:border-blue-900 dark:border-t-blue-400" />
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="h-6 w-6 text-blue-600 dark:text-blue-400">
                                <path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm1 14.93V15a1 1 0 0 0-2 0v1.93A8 8 0 0 1 4.07 13H6a1 1 0 0 0 0-2H4.07A8 8 0 0 1 11 4.07V6a1 1 0 0 0 2 0V4.07A8 8 0 0 1 19.93 11H18a1 1 0 0 0 0 2h1.93A8 8 0 0 1 13 16.93Z" />
                            </svg>
                        </div>
                        <div className="text-center">
                            <p className="text-base font-bold text-gray-900 dark:text-white">Teaching the System…</p>
                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Updating memory with{' '}
                                <span className="font-semibold text-blue-600 dark:text-blue-400">"{data.correct_breed}"</span>
                            </p>
                        </div>
                        <div className="h-1.5 w-56 overflow-hidden rounded-full bg-blue-100 dark:bg-blue-900/40">
                            <div className="h-full w-1/2 animate-[slide_1.2s_ease-in-out_infinite] rounded-full bg-blue-600 dark:bg-blue-400" />
                        </div>
                        <p className="text-xs text-gray-400 dark:text-gray-500">This may take a few seconds…</p>
                    </div>
                    <style>{`@keyframes slide{0%{transform:translateX(-100%)}100%{transform:translateX(300%)}}`}</style>
                </div>
            )}

            {/* ══════════════════════════════════════════════════════════════ */}
            {/* MODAL 1 — Post-correction: schedule appointment?               */}
            {/* ══════════════════════════════════════════════════════════════ */}
            {showApptModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
                    <div className="w-full max-w-md rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-white/10 dark:bg-neutral-900">
                        {/* Header */}
                        <div className="flex items-start justify-between border-b border-gray-100 px-6 py-4 dark:border-white/10">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/30">
                                    <CalendarDays size={20} className="text-indigo-600 dark:text-indigo-400" />
                                </div>
                                <div>
                                    <h3 className="font-bold text-gray-900 dark:text-white">
                                        Schedule a Consultation?
                                    </h3>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                        Breed correction saved successfully.
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => setShowApptModal(false)}
                                className="ml-4 flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/10"
                            >
                                <X size={15} />
                            </button>
                        </div>
                        {/* Body */}
                        <div className="px-6 py-5">
                            <div className="mb-4 rounded-lg border border-green-200 bg-green-50 p-3 dark:border-green-900/30 dark:bg-green-900/10">
                                <p className="text-sm text-green-700 dark:text-green-400">
                                    ✓ The breed has been corrected to{' '}
                                    <span className="font-bold">"{correctedBreedName || result?.breed}"</span>{' '}
                                    and the system has been updated.
                                </p>
                            </div>
                            <p className="text-sm text-gray-600 dark:text-gray-300">
                                Would you like to schedule a clinic consultation for this dog based on the corrected breed?
                            </p>
                        </div>
                        {/* Actions */}
                        <div className="flex gap-3 border-t border-gray-100 px-6 py-4 dark:border-white/10">
                            <button
                                type="button"
                                onClick={() => {
                                    setShowApptModal(false);
                                    setApptOpen(true);
                                    setTimeout(() => {
                                        document
                                            .getElementById('appointment-card')
                                            ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                                    }, 150);
                                }}
                                className="flex flex-1 items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-indigo-700"
                            >
                                <CalendarDays size={15} /> Yes, Schedule
                            </button>
                            <button
                                type="button"
                                onClick={() => {
                                    setShowApptModal(false);
                                    window.location.href = '/model/scan-results';
                                }}
                                className="flex flex-1 items-center justify-center rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-100 dark:border-white/10 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10"
                            >
                                No, Go Back
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* ══════════════════════════════════════════════════════════════ */}
            {/* MODAL 2 — Appointment created success                          */}
            {/* ══════════════════════════════════════════════════════════════ */}
            {showSuccessAlert && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
                    <div className="w-full max-w-sm rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-white/10 dark:bg-neutral-900">
                        {/* Icon + title */}
                        <div className="flex flex-col items-center px-6 pt-8 pb-4 text-center">
                            <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                                <CheckCircle2 size={32} className="text-green-600 dark:text-green-400" />
                            </div>
                            <h3 className="text-lg font-bold text-gray-900 dark:text-white">
                                Appointment Scheduled!
                            </h3>
                            <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                The consultation has been created and the dog owner has been notified. They can accept or decline from their portal.
                            </p>
                        </div>
                        {/* Summary */}
                        <div className="mx-6 mb-5 rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-white/[.06] dark:bg-white/[.03]">
                            <div className="space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-gray-500 dark:text-gray-400">Date</span>
                                    <span className="font-medium text-gray-800 dark:text-white">{apptForm.data.appointment_date}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-gray-500 dark:text-gray-400">Time</span>
                                    <span className="font-medium text-gray-800 dark:text-white">{apptForm.data.appointment_time}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-gray-500 dark:text-gray-400">Vet</span>
                                    <span className="font-medium text-gray-800 dark:text-white">{apptForm.data.vet_name}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-gray-500 dark:text-gray-400">Status</span>
                                    <span className="rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-semibold text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400">
                                        Awaiting Owner Response
                                    </span>
                                </div>
                            </div>
                        </div>
                        {/* Actions */}
                        <div className="flex gap-3 border-t border-gray-100 px-6 py-4 dark:border-white/10">
                            <button
                                type="button"
                                onClick={() => setShowSuccessAlert(false)}
                                className="flex flex-1 items-center justify-center rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-100 dark:border-white/10 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10"
                            >
                                Stay on Page
                            </button>
                            <button
                                type="button"
                                onClick={() => { window.location.href = '/model/scan-results'; }}
                                className="flex flex-1 items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-blue-700"
                            >
                                Go to Scan Results
                            </button>
                        </div>
                    </div>
                </div>
            )}

            <div className="flex h-full w-full flex-col gap-6 p-4 md:p-8">
                {/* Page header */}
                <div>
                    <h1 className="text-xl font-bold dark:text-white">Scan Review & Correction</h1>
                    <p className="text-sm text-gray-600 dark:text-white/70">
                        Validate system prediction, provide correction if necessary, and schedule a consultation.
                    </p>
                </div>

                {/* Main two-column layout */}
                <div className="flex flex-col gap-6 lg:flex-row lg:items-start">
                    {/* LEFT: Image Preview */}
                    <Card className="flex w-full flex-col p-6 lg:w-1/2 xl:w-[45%] dark:bg-neutral-900">
                        <h2 className="text-lg font-medium">Image Preview</h2>
                        <div className="mt-6 flex flex-1 items-center justify-center rounded-lg bg-gray-50 py-8 dark:bg-black/20">
                            <img
                                src={result?.image}
                                alt="Scanned Dog"
                                className="max-h-[300px] w-auto rounded-lg object-contain shadow-md lg:max-h-[400px]"
                            />
                        </div>
                        <div className="mt-6 space-y-3 px-2 md:px-4">
                            <div className="flex items-center justify-between border-b border-gray-100 pb-2 dark:border-gray-800">
                                <p className="text-sm text-gray-600 dark:text-white/70">Scan ID</p>
                                <p className="font-mono text-sm font-medium">{result?.scan_id}</p>
                            </div>
                            <div className="flex items-center justify-between border-b border-gray-100 pb-2 dark:border-gray-800">
                                <p className="text-sm text-gray-600 dark:text-white/70">Upload Date</p>
                                <p className="text-sm font-medium">{result?.created_at?.replace('T', ' ').substring(0, 16)}</p>
                            </div>
                            <div className="flex items-center justify-between">
                                <p className="text-sm text-gray-600 dark:text-white/70">File Name</p>
                                <p className="max-w-[150px] truncate text-sm font-medium">{result?.image.split('/').pop()}</p>
                            </div>
                        </div>
                    </Card>

                    {/* RIGHT: Prediction + Correction */}
                    <div className="flex w-full flex-col gap-6 lg:flex-1">
                        {/* Model Prediction */}
                        <Card className="px-6 py-6 dark:bg-neutral-900">
                            <div className="mb-6 flex items-center justify-between">
                                <h2 className="font-medium">Model Prediction</h2>
                                <Badge className="px-3 py-1" variant={result?.confidence && result.confidence > 80 ? 'default' : 'secondary'}>
                                    {result?.confidence}% Confidence
                                </Badge>
                            </div>
                            <div className="space-y-6 px-0 md:px-2">
                                <div className="text-center">
                                    <h3 className="text-3xl font-bold text-gray-900 dark:text-white">{result?.breed}</h3>
                                    <p className="mt-1 text-xs tracking-wide text-gray-500 uppercase">Primary Prediction</p>
                                </div>
                                <div className="rounded-lg bg-gray-50 p-4 dark:bg-neutral-800/50">
                                    <p className="mb-3 text-sm font-medium text-gray-600 dark:text-white/70">Top Alternatives</p>
                                    <div className="space-y-4">
                                        {result?.top_predictions?.slice(0, 3).map((p, i) => (
                                            <div key={i} className="space-y-1.5">
                                                <div className="flex justify-between text-sm">
                                                    <span className="text-gray-700 dark:text-white/90">{p.breed}</span>
                                                    <span className="font-semibold text-gray-900 dark:text-white">{p.confidence}%</span>
                                                </div>
                                                <Progress
                                                    value={p.confidence}
                                                    className={`h-2 ${p.confidence >= 80 ? '[&>div]:bg-green-600' : p.confidence >= 60 ? '[&>div]:bg-yellow-500' : p.confidence >= 40 ? '[&>div]:bg-orange-500' : '[&>div]:bg-red-500'}`}
                                                />
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        </Card>

                        {/* Correction Form */}
                        <Card className="flex-1 px-6 py-6 dark:bg-neutral-900">
                            <h2 className="mb-4 font-medium">Veterinarian Correction</h2>
                            <form onSubmit={submitCorrection} className="md:px-2">
                                <div className="space-y-2">
                                    <label className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Correct Breed (if different)
                                    </label>
                                    <Input
                                        value={data.correct_breed}
                                        onChange={(e) => setData('correct_breed', e.target.value)}
                                        placeholder="Type correct breed here..."
                                        className="w-full focus-visible:ring-2 focus-visible:ring-blue-500 dark:border-white/20 dark:bg-white/5"
                                        disabled={processing || corrected}
                                    />
                                    {errors.correct_breed && (
                                        <span className="text-sm font-medium text-red-500">{errors.correct_breed}</span>
                                    )}
                                </div>
                                <Card className="mt-5 border border-blue-200 bg-blue-50/50 p-4 dark:border-blue-900/50 dark:bg-blue-900/20">
                                    <div className="flex gap-3">
                                        <div className="shrink-0 text-blue-600 dark:text-blue-400">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className="h-5 w-5">
                                                <path fillRule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12ZM12 8.25a.75.75 0 0 1 .75.75v3.75a.75.75 0 0 1-1.5 0V9a.75.75 0 0 1 .75-.75Zm0 8.25a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" clipRule="evenodd" />
                                            </svg>
                                        </div>
                                        <p className="text-xs leading-relaxed text-blue-900 dark:text-blue-100">
                                            <span className="mb-1 block font-bold">Impact on Model:</span>
                                            Submitting this will instantly update the system's memory. The system will recognize this specific dog as{' '}
                                            <span className="mx-1 font-bold">"{data.correct_breed || '...'}"</span> in future scans.
                                        </p>
                                    </div>
                                </Card>

                                {corrected && (
                                    <div className="mt-4 rounded-lg border border-green-200 bg-green-50 p-3 dark:border-green-900/30 dark:bg-green-900/10">
                                        <p className="flex items-center gap-2 text-sm font-semibold text-green-700 dark:text-green-400">
                                            <CheckCircle2 size={15} />
                                            Correction already submitted for this scan.
                                        </p>
                                    </div>
                                )}

                                <Button
                                    className="mt-6 h-11 w-full bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-70"
                                    disabled={processing || corrected}
                                >
                                    {processing ? (
                                        <span className="flex items-center justify-center gap-2">
                                            <svg className="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                            </svg>
                                            Teaching the system…
                                        </span>
                                    ) : (
                                        'Submit Correction'
                                    )}
                                </Button>
                            </form>
                        </Card>
                    </div>
                </div>

                {/* ══════════════════════════════════════════════════════════ */}
                {/* BREED SUMMARY CARD                                         */}
                {/* ══════════════════════════════════════════════════════════ */}
                <Card className="overflow-hidden dark:bg-neutral-900">
                    <button
                        type="button"
                        onClick={() => setSummaryOpen((o) => !o)}
                        className="flex w-full items-center justify-between px-6 py-4 text-left"
                    >
                        <div className="flex items-center gap-2">
                            <Stethoscope size={18} className="text-blue-600 dark:text-blue-400" />
                            <h2 className="font-semibold text-gray-900 dark:text-white">
                                Breed Summary Card —{' '}
                                <span className="text-blue-600 dark:text-blue-400">{result?.breed}</span>
                            </h2>
                        </div>
                        {summaryOpen
                            ? <ChevronUp size={18} className="text-gray-400" />
                            : <ChevronDown size={18} className="text-gray-400" />}
                    </button>

                    {summaryOpen && (
                        <div className="border-t border-gray-100 px-6 pb-6 dark:border-gray-800">
                            <div className="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                {origin?.country && (
                                    <div className="flex items-start gap-2 rounded-lg bg-gray-50 p-3 dark:bg-neutral-800/50">
                                        <MapPin size={15} className="mt-0.5 shrink-0 text-blue-500" />
                                        <div>
                                            <p className="text-xs text-gray-500 dark:text-gray-400">Origin</p>
                                            <p className="text-sm font-semibold text-gray-800 dark:text-white">{origin.country}</p>
                                            {origin.region && <p className="text-xs text-gray-500">{origin.region}</p>}
                                        </div>
                                    </div>
                                )}
                                {healthRisks?.lifespan && (
                                    <div className="flex items-start gap-2 rounded-lg bg-gray-50 p-3 dark:bg-neutral-800/50">
                                        <Heart size={15} className="mt-0.5 shrink-0 text-red-400" />
                                        <div>
                                            <p className="text-xs text-gray-500 dark:text-gray-400">Lifespan</p>
                                            <p className="text-sm font-semibold text-gray-800 dark:text-white">{healthRisks.lifespan} yrs</p>
                                        </div>
                                    </div>
                                )}
                                {weightStr && (
                                    <div className="flex items-start gap-2 rounded-lg bg-gray-50 p-3 dark:bg-neutral-800/50">
                                        <Scale size={15} className="mt-0.5 shrink-0 text-purple-500" />
                                        <div>
                                            <p className="text-xs text-gray-500 dark:text-gray-400">Typical Weight</p>
                                            <p className="text-sm font-semibold text-gray-800 dark:text-white">{weightStr}</p>
                                        </div>
                                    </div>
                                )}
                                {heightStr && (
                                    <div className="flex items-start gap-2 rounded-lg bg-gray-50 p-3 dark:bg-neutral-800/50">
                                        <Ruler size={15} className="mt-0.5 shrink-0 text-teal-500" />
                                        <div>
                                            <p className="text-xs text-gray-500 dark:text-gray-400">Typical Height</p>
                                            <p className="text-sm font-semibold text-gray-800 dark:text-white">{heightStr}</p>
                                        </div>
                                    </div>
                                )}
                            </div>

                            {concerns.length > 0 && (
                                <div className="mt-6">
                                    <div className="mb-3 flex items-center gap-2">
                                        <AlertCircle size={15} className="text-orange-500" />
                                        <h3 className="text-sm font-semibold text-gray-800 dark:text-white">Common Health Concerns</h3>
                                    </div>
                                    <div className="space-y-3">
                                        {concerns.map((c, i) => (
                                            <div key={i} className="rounded-lg border border-gray-100 bg-gray-50/50 p-4 dark:border-gray-700 dark:bg-neutral-800/30">
                                                <div className="flex flex-wrap items-center justify-between gap-2">
                                                    <span className="text-sm font-semibold text-gray-900 dark:text-white">{c.name}</span>
                                                    <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${riskColor(c.risk_level)}`}>
                                                        {c.risk_level}
                                                    </span>
                                                </div>
                                                <Progress value={riskValue(c.risk_level)} className={`mt-2 h-1.5 ${riskBar(c.risk_level)}`} />
                                                <p className="mt-2 text-xs leading-relaxed text-gray-600 dark:text-gray-400">{c.description}</p>
                                                {c.prevention && (
                                                    <p className="mt-1.5 text-xs text-blue-600 dark:text-blue-400">
                                                        <span className="font-semibold">Prevention: </span>{c.prevention}
                                                    </p>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {screenings.length > 0 && (
                                <div className="mt-6">
                                    <div className="mb-3 flex items-center gap-2">
                                        <Shield size={15} className="text-green-500" />
                                        <h3 className="text-sm font-semibold text-gray-800 dark:text-white">Recommended Screenings</h3>
                                    </div>
                                    <div className="grid gap-2 sm:grid-cols-2">
                                        {screenings.map((s, i) => (
                                            <div key={i} className="rounded-lg border border-green-100 bg-green-50/50 p-3 dark:border-green-900/30 dark:bg-green-900/10">
                                                <p className="text-sm font-semibold text-green-800 dark:text-green-300">{s.name}</p>
                                                <p className="mt-0.5 text-xs leading-relaxed text-gray-600 dark:text-gray-400">{s.description}</p>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {careTips.length > 0 && (
                                <div className="mt-6">
                                    <h3 className="mb-2 text-sm font-semibold text-gray-800 dark:text-white">Care Tips</h3>
                                    <div className="flex flex-wrap gap-2">
                                        {careTips.map((tip, i) => (
                                            <span key={i} className="rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs text-blue-800 dark:border-blue-800/40 dark:bg-blue-900/20 dark:text-blue-300">
                                                {tip}
                                            </span>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    )}
                </Card>

                {/* ══════════════════════════════════════════════════════════ */}
                {/* APPOINTMENT SCHEDULING                                      */}
                {/* ══════════════════════════════════════════════════════════ */}
                <Card id="appointment-card" className="overflow-hidden dark:bg-neutral-900">
                    <button
                        type="button"
                        onClick={() => setApptOpen((o) => !o)}
                        className="flex w-full items-center justify-between px-6 py-4 text-left"
                    >
                        <div className="flex items-center gap-2">
                            <CalendarDays size={18} className="text-indigo-500" />
                            <h2 className="font-semibold text-gray-900 dark:text-white">Schedule Consultation</h2>
                            {appointment && <div className="ml-2">{apptStatusBadge()}</div>}
                        </div>
                        {apptOpen
                            ? <ChevronUp size={18} className="text-gray-400" />
                            : <ChevronDown size={18} className="text-gray-400" />}
                    </button>

                    {apptOpen && (
                        <div className="border-t border-gray-100 px-6 pb-6 dark:border-gray-800">
                            {appointment ? (
                                <div className="mt-5 space-y-4">
                                    <div className="flex items-center justify-between">
                                        <h3 className="text-sm font-semibold text-gray-800 dark:text-white">Appointment Details</h3>
                                        {apptStatusBadge()}
                                    </div>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div className="rounded-lg bg-gray-50 p-3 dark:bg-neutral-800/50">
                                            <p className="text-xs text-gray-500 dark:text-gray-400">Date & Time</p>
                                            <p className="mt-0.5 text-sm font-semibold text-gray-800 dark:text-white">
                                                {appointment.appointment_date} at {appointment.appointment_time}
                                            </p>
                                        </div>
                                        <div className="rounded-lg bg-gray-50 p-3 dark:bg-neutral-800/50">
                                            <p className="text-xs text-gray-500 dark:text-gray-400">Attending Vet</p>
                                            <p className="mt-0.5 text-sm font-semibold text-gray-800 dark:text-white">{appointment.vet_name}</p>
                                        </div>
                                        <div className="rounded-lg bg-gray-50 p-3 dark:bg-neutral-800/50 sm:col-span-2">
                                            <p className="text-xs text-gray-500 dark:text-gray-400">Reason</p>
                                            <p className="mt-0.5 text-sm text-gray-800 dark:text-white">{appointment.reason}</p>
                                        </div>
                                        {appointment.notes && (
                                            <div className="rounded-lg bg-gray-50 p-3 dark:bg-neutral-800/50 sm:col-span-2">
                                                <p className="text-xs text-gray-500 dark:text-gray-400">Notes</p>
                                                <p className="mt-0.5 text-sm text-gray-800 dark:text-white">{appointment.notes}</p>
                                            </div>
                                        )}
                                    </div>
                                    {appointment.status === 'rejected' && appointment.rejection_reason && (
                                        <div className="rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-900/30 dark:bg-red-900/10">
                                            <p className="text-xs font-semibold text-red-700 dark:text-red-400">Owner's Reason for Declining:</p>
                                            <p className="mt-0.5 text-sm text-red-600 dark:text-red-300">{appointment.rejection_reason}</p>
                                        </div>
                                    )}
                                    {appointment.status === 'accepted' && (
                                        <div className="rounded-lg border border-green-200 bg-green-50 p-3 dark:border-green-900/30 dark:bg-green-900/10">
                                            <p className="text-sm font-semibold text-green-700 dark:text-green-400">
                                                ✓ The dog owner has confirmed this appointment.
                                            </p>
                                        </div>
                                    )}
                                    {appointment.status === 'pending' && (
                                        <div className="rounded-lg border border-yellow-200 bg-yellow-50 p-3 dark:border-yellow-900/30 dark:bg-yellow-900/10">
                                            <p className="text-sm text-yellow-700 dark:text-yellow-400">
                                                Waiting for the dog owner to confirm or decline this appointment.
                                            </p>
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <form onSubmit={submitAppointment} className="mt-5 space-y-4">
                                    <p className="text-sm text-gray-600 dark:text-gray-400">
                                        Schedule a consultation for this dog. The owner will be notified and can confirm or decline the appointment.
                                    </p>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="space-y-1.5">
                                            <label className="flex items-center gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                                                <CalendarDays size={13} /> Appointment Date
                                            </label>
                                            <Input
                                                type="date"
                                                value={apptForm.data.appointment_date}
                                                onChange={(e) => apptForm.setData('appointment_date', e.target.value)}
                                                min={new Date().toISOString().split('T')[0]}
                                                className="dark:border-white/20 dark:bg-white/5"
                                                required
                                            />
                                            {apptForm.errors.appointment_date && (
                                                <p className="text-xs text-red-500">{apptForm.errors.appointment_date}</p>
                                            )}
                                        </div>
                                        <div className="space-y-1.5">
                                            <label className="flex items-center gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                                                <Clock size={13} /> Appointment Time
                                            </label>
                                            <Input
                                                type="time"
                                                value={apptForm.data.appointment_time}
                                                onChange={(e) => apptForm.setData('appointment_time', e.target.value)}
                                                className="dark:border-white/20 dark:bg-white/5"
                                                required
                                            />
                                            {apptForm.errors.appointment_time && (
                                                <p className="text-xs text-red-500">{apptForm.errors.appointment_time}</p>
                                            )}
                                        </div>
                                        <div className="space-y-1.5">
                                            <label className="flex items-center gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                                                <User size={13} /> Attending Vet
                                            </label>
                                            <Input
                                                value={apptForm.data.vet_name}
                                                onChange={(e) => apptForm.setData('vet_name', e.target.value)}
                                                placeholder="Dr. Name"
                                                className="dark:border-white/20 dark:bg-white/5"
                                                required
                                            />
                                            {apptForm.errors.vet_name && (
                                                <p className="text-xs text-red-500">{apptForm.errors.vet_name}</p>
                                            )}
                                        </div>
                                        <div className="space-y-1.5">
                                            <label className="flex items-center gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                                                <Stethoscope size={13} /> Reason for Consultation
                                            </label>
                                            <Input
                                                value={apptForm.data.reason}
                                                onChange={(e) => apptForm.setData('reason', e.target.value)}
                                                placeholder="e.g. Routine health screening, Hip evaluation…"
                                                className="dark:border-white/20 dark:bg-white/5"
                                                required
                                            />
                                            {apptForm.errors.reason && (
                                                <p className="text-xs text-red-500">{apptForm.errors.reason}</p>
                                            )}
                                        </div>
                                    </div>
                                    <div className="space-y-1.5">
                                        <label className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Additional Notes <span className="text-gray-400">(optional)</span>
                                        </label>
                                        <textarea
                                            value={apptForm.data.notes}
                                            onChange={(e) => apptForm.setData('notes', e.target.value)}
                                            placeholder="Any special instructions or things to prepare…"
                                            rows={3}
                                            className="w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-500 dark:border-white/20 dark:bg-white/5 dark:text-white dark:placeholder-white/30"
                                        />
                                    </div>
                                    <div className="rounded-lg border border-indigo-200 bg-indigo-50/50 p-3 dark:border-indigo-900/40 dark:bg-indigo-900/10">
                                        <p className="text-xs leading-relaxed text-indigo-800 dark:text-indigo-300">
                                            <span className="font-bold">Note: </span>
                                            Once you submit, the dog owner will receive a notification in their portal. The appointment will be marked as{' '}
                                            <strong>Pending</strong> until the owner accepts or declines. You will be notified of their response.
                                        </p>
                                    </div>
                                    <Button
                                        type="submit"
                                        disabled={apptForm.processing}
                                        className="h-11 w-full bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-70"
                                    >
                                        {apptForm.processing ? (
                                            <span className="flex items-center justify-center gap-2">
                                                <svg className="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                                </svg>
                                                Scheduling…
                                            </span>
                                        ) : (
                                            <span className="flex items-center justify-center gap-2">
                                                <CalendarDays size={16} /> Schedule & Notify Owner
                                            </span>
                                        )}
                                    </Button>
                                </form>
                            )}
                        </div>
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}