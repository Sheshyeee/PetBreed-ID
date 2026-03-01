import {
    Activity,
    Brain,
    CheckCircle2,
    Globe,
    Loader2,
    PawPrint,
    Sparkles,
    Upload,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    AlertDialog,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogHeader,
    AlertDialogTitle,
} from './ui/alert-dialog';

interface AnalysisStage {
    id: string;
    label: string;
    sublabel: string;
    icon: React.ReactNode;
    duration: number;
    color: string;
}

interface AnalysisLoadingDialogProps {
    isOpen: boolean;
}

const AnalysisLoadingDialog: React.FC<AnalysisLoadingDialogProps> = ({
    isOpen,
}) => {
    const [currentStageIndex, setCurrentStageIndex] = useState(0);
    const [progress, setProgress] = useState(0);

    const stages: AnalysisStage[] = [
        {
            id: 'upload',
            label: 'Uploading image',
            sublabel: 'Preparing your photo for analysis',
            icon: <Upload className="h-4 w-4" />,
            duration: 800,
            color: 'from-cyan-400 to-blue-500',
        },
        {
            id: 'identify',
            label: 'Identifying breed',
            sublabel: 'Running neural network inference',
            icon: <Brain className="h-4 w-4" />,
            duration: 7500,
            color: 'from-emerald-400 to-cyan-500',
        },
        {
            id: 'features',
            label: 'Extracting features',
            sublabel: 'Analyzing coat, structure & markings',
            icon: <Activity className="h-4 w-4" />,
            duration: 3500,
            color: 'from-violet-400 to-purple-500',
        },
        {
            id: 'origin',
            label: 'Generating origin data',
            sublabel: 'Tracing breed history & geography',
            icon: <Globe className="h-4 w-4" />,
            duration: 3500,
            color: 'from-amber-400 to-orange-500',
        },
        {
            id: 'health',
            label: 'Creating health analysis',
            sublabel: 'Mapping breed-specific risk factors',
            icon: <Sparkles className="h-4 w-4" />,
            duration: 3500,
            color: 'from-pink-400 to-rose-500',
        },
        {
            id: 'finalize',
            label: 'Finalizing analysis',
            sublabel: 'Compiling your full report',
            icon: <Sparkles className="h-4 w-4" />,
            duration: 1500,
            color: 'from-emerald-400 to-teal-500',
        },
    ];

    const totalDuration = stages.reduce((sum, s) => sum + s.duration, 0);

    useEffect(() => {
        if (!isOpen) {
            setCurrentStageIndex(0);
            setProgress(0);
            return;
        }

        let cumulativeTime = 0;
        let currentIndex = 0;

        const interval = setInterval(() => {
            cumulativeTime += 50;
            const newProgress = Math.min(
                (cumulativeTime / totalDuration) * 100,
                100,
            );
            setProgress(newProgress);

            let timeSum = 0;
            for (let i = 0; i < stages.length; i++) {
                timeSum += stages[i].duration;
                if (cumulativeTime < timeSum) {
                    currentIndex = i;
                    break;
                }
            }
            setCurrentStageIndex(currentIndex);

            if (cumulativeTime >= totalDuration) clearInterval(interval);
        }, 50);

        return () => clearInterval(interval);
    }, [isOpen, totalDuration]);

    const currentStage = stages[currentStageIndex];

    return (
        <>
            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap');

                @keyframes ald-pulse  { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(1.6);opacity:.3} }
                @keyframes ald-ring   { 0%{transform:scale(.82);opacity:.7} 70%,100%{transform:scale(1.22);opacity:0} }
                @keyframes ald-spin   { to{transform:rotate(360deg)} }
                @keyframes ald-sweep  { 0%{transform:translateY(-100%)} 100%{transform:translateY(200%)} }
                @keyframes ald-fadein { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }
                @keyframes ald-pop    { 0%{transform:scale(.85);opacity:0} 100%{transform:scale(1);opacity:1} }
                @keyframes ald-bar    { from{width:0} }
                                @keyframes ald-float  { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-4px)} }
                @keyframes ald-orbit  { from{transform:rotate(0deg) translateX(28px) rotate(0deg)} to{transform:rotate(360deg) translateX(28px) rotate(-360deg)} }
                @keyframes ald-paw    { 0%,100%{transform:scale(1) rotate(0deg)} 25%{transform:scale(1.08) rotate(-5deg)} 75%{transform:scale(1.08) rotate(5deg)} }

                .ald-root { font-family:'Plus Jakarta Sans',sans-serif; }
                .ald-mono { font-family:'JetBrains Mono',monospace !important; }

                .ald-float { animation:ald-float 3s ease-in-out infinite; }
                .ald-paw { animation:ald-paw 2s ease-in-out infinite; }
                .ald-fadein { animation:ald-fadein .4s cubic-bezier(.16,1,.3,1) both; }
                .ald-pop { animation:ald-pop .3s cubic-bezier(.34,1.56,.64,1) both; }

                .ald-progress-track {
                    position:relative; height:6px; border-radius:9999px;
                    background:rgba(255,255,255,.08); overflow:hidden;
                }
                .ald-progress-track::after {
                    content:''; position:absolute; top:0; left:-100%; width:40%; height:100%;
                    background:linear-gradient(90deg,transparent,rgba(255,255,255,.25),transparent);
                    animation:ald-sweep 2s linear infinite;
                }

                .ald-stage-item { transition:all .3s cubic-bezier(.16,1,.3,1); }

                .ald-orb { position:absolute; width:5px; height:5px; border-radius:50%; background:#10b981; box-shadow:0 0 8px #10b981; animation:ald-orbit 3s linear infinite; }
                .ald-orb2 { animation-delay:-1.5s; background:#06b6d4; box-shadow:0 0 8px #06b6d4; }
            `}</style>

            <AlertDialog open={isOpen}>
                <AlertDialogContent className="ald-root max-w-sm border-0 bg-transparent p-0 shadow-none sm:mx-0">
                    <div className="relative overflow-hidden rounded-2xl border border-white/[.08] bg-[#0D1117] shadow-2xl shadow-black/60">
                        {/* Top accent line */}
                        <div className="absolute top-0 right-0 left-0 h-[1.5px] bg-gradient-to-r from-transparent via-emerald-500 to-transparent opacity-60" />

                        {/* Ambient glow blobs */}
                        <div className="pointer-events-none absolute -top-10 -left-10 h-40 w-40 rounded-full bg-emerald-500/[.06] blur-3xl" />
                        <div className="pointer-events-none absolute -top-6 -right-6 h-32 w-32 rounded-full bg-cyan-500/[.04] blur-2xl" />

                        {/* ── HEADER ── */}
                        <div className="relative px-6 pt-6 pb-5">
                            <AlertDialogHeader>
                                <AlertDialogTitle className="flex items-center gap-4">
                                    {/* Icon */}
                                    <div className="flex-shrink-0">
                                        <div className="flex h-14 w-14 items-center justify-center rounded-full border border-emerald-500/25 bg-gradient-to-br from-emerald-500/20 to-cyan-500/10">
                                            <PawPrint className="h-6 w-6 text-emerald-400" />
                                        </div>
                                    </div>

                                    <div className="min-w-0 flex-1">
                                        <h2 className="text-lg font-extrabold tracking-tight text-white">
                                            Analyzing Your Pet
                                        </h2>
                                        <div className="mt-1 flex items-center gap-2">
                                            <span className="h-1.5 w-1.5 flex-shrink-0 rounded-full bg-emerald-400" />
                                            <span className="ald-mono text-[10px] font-semibold tracking-[.1em] text-emerald-400/80 uppercase">
                                                Breed Identification
                                            </span>
                                        </div>
                                    </div>
                                </AlertDialogTitle>
                            </AlertDialogHeader>
                        </div>

                        {/* ── CONTENT ── */}
                        <div className="px-6 pb-6">
                            <AlertDialogDescription className="space-y-4">
                                {/* Current stage spotlight */}
                                <div className="ald-fadein relative overflow-hidden rounded-xl border border-emerald-500/20 bg-gradient-to-br from-emerald-500/[.07] to-cyan-500/[.04] p-4">
                                    {/* Sweep animation */}
                                    <div className="pointer-events-none absolute inset-0 overflow-hidden rounded-xl">
                                        <div
                                            className="absolute right-0 left-0 h-[2px] bg-gradient-to-r from-transparent via-emerald-400/40 to-transparent"
                                            style={{
                                                animation:
                                                    'ald-sweep 2.2s linear infinite',
                                            }}
                                        />
                                    </div>

                                    <div className="relative flex items-center gap-3">
                                        <div className="relative flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl border border-emerald-500/25 bg-emerald-500/15">
                                            <Loader2
                                                className="h-5 w-5 text-emerald-400"
                                                style={{
                                                    animation:
                                                        'ald-spin 1s linear infinite',
                                                }}
                                            />
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-bold text-white">
                                                {currentStage?.label}…
                                            </p>
                                            <p className="mt-0.5 text-xs text-slate-400">
                                                {currentStage?.sublabel}
                                            </p>
                                        </div>
                                        <div className="ald-mono flex-shrink-0 text-right">
                                            <span className="text-[11px] font-bold text-emerald-400">
                                                {Math.round(progress)}%
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                {/* Progress bar */}
                                <div className="space-y-1.5">
                                    <div className="ald-progress-track">
                                        <div
                                            className={`h-full rounded-full bg-gradient-to-r ${currentStage?.color ?? 'from-emerald-500 to-cyan-500'} transition-all duration-300 ease-out`}
                                            style={{ width: `${progress}%` }}
                                        />
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="ald-mono text-[9px] tracking-[.1em] text-slate-600 uppercase">
                                            Step {currentStageIndex + 1} /{' '}
                                            {stages.length}
                                        </span>
                                        <span className="ald-mono text-[9px] text-slate-600">
                                            {Math.round(progress)}% complete
                                        </span>
                                    </div>
                                </div>

                                {/* Stage list */}
                                <div className="overflow-hidden rounded-xl border border-white/[.06] bg-white/[.02]">
                                    <div className="border-b border-white/[.05] px-3.5 py-2">
                                        <span className="ald-mono text-[9px] font-bold tracking-[.14em] text-slate-500 uppercase">
                                            Analysis Pipeline
                                        </span>
                                    </div>
                                    <div className="flex flex-col divide-y divide-white/[.04]">
                                        {stages.map((stage, index) => {
                                            const isCompleted =
                                                index < currentStageIndex;
                                            const isCurrent =
                                                index === currentStageIndex;
                                            const isPending =
                                                index > currentStageIndex;

                                            return (
                                                <div
                                                    key={stage.id}
                                                    className={`ald-stage-item flex items-center gap-3 px-3.5 py-2.5 ${
                                                        isCurrent
                                                            ? 'bg-emerald-500/[.06]'
                                                            : isPending
                                                              ? 'opacity-35'
                                                              : ''
                                                    }`}
                                                >
                                                    {/* Status icon */}
                                                    {isCompleted ? (
                                                        <div className="ald-pop flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,.4)]">
                                                            <CheckCircle2
                                                                size={12}
                                                                className="text-black"
                                                            />
                                                        </div>
                                                    ) : isCurrent ? (
                                                        <div className="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full border border-emerald-500/40 bg-emerald-500/20">
                                                            <Loader2
                                                                size={10}
                                                                className="text-emerald-400"
                                                                style={{
                                                                    animation:
                                                                        'ald-spin 1s linear infinite',
                                                                }}
                                                            />
                                                        </div>
                                                    ) : (
                                                        <div className="h-5 w-5 flex-shrink-0 rounded-full border border-white/[.1]" />
                                                    )}

                                                    {/* Stage icon */}
                                                    <div
                                                        className={`flex-shrink-0 ${isCompleted ? 'text-emerald-500/60' : isCurrent ? 'text-emerald-400' : 'text-slate-600'}`}
                                                    >
                                                        {stage.icon}
                                                    </div>

                                                    {/* Label */}
                                                    <span
                                                        className={`flex-1 text-[12px] font-medium ${
                                                            isCompleted
                                                                ? 'text-emerald-400/70 line-through decoration-emerald-500/40'
                                                                : isCurrent
                                                                  ? 'font-bold text-white'
                                                                  : 'text-slate-600'
                                                        }`}
                                                    >
                                                        {stage.label}
                                                    </span>

                                                    {/* Completed badge */}
                                                    {isCompleted && (
                                                        <span className="ald-mono flex-shrink-0 text-[8px] font-semibold tracking-[.1em] text-emerald-500/60 uppercase">
                                                            Done
                                                        </span>
                                                    )}
                                                    {isCurrent && (
                                                        <span className="ald-mono flex-shrink-0 rounded border border-emerald-500/20 bg-emerald-500/[.08] px-1.5 py-0.5 text-[8px] font-semibold tracking-[.1em] text-emerald-400 uppercase">
                                                            Active
                                                        </span>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>

                                {/* Bottom hint */}
                                <p className="ald-mono text-center text-[9px] tracking-[.1em] text-slate-600 uppercase">
                                    This may take a few seconds · please wait
                                </p>
                            </AlertDialogDescription>
                        </div>

                        {/* Bottom accent line */}
                        <div className="absolute right-0 bottom-0 left-0 h-[1px] bg-gradient-to-r from-transparent via-emerald-500/20 to-transparent" />
                    </div>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
};

export default AnalysisLoadingDialog;
