import {
    Activity,
    Brain,
    CheckCircle2,
    Globe,
    Loader2,
    Sparkles,
    Upload,
    Zap,
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

const AnalysisLoadingDialog: React.FC<AnalysisLoadingDialogProps> = ({ isOpen }) => {
    const [currentStageIndex, setCurrentStageIndex] = useState(0);
    const [progress, setProgress] = useState(0);
    const [tick, setTick] = useState(0);

    const stages: AnalysisStage[] = [
        {
            id: 'upload',
            label: 'Uploading image',
            sublabel: 'Transmitting to analysis server',
            icon: <Upload className="h-4 w-4" />,
            duration: 800,
            color: 'emerald',
        },
        {
            id: 'identify',
            label: 'Identifying breed',
            sublabel: 'Running neural network inference',
            icon: <Brain className="h-4 w-4" />,
            duration: 7500,
            color: 'cyan',
        },
        {
            id: 'features',
            label: 'Extracting features',
            sublabel: 'Mapping morphological signatures',
            icon: <Activity className="h-4 w-4" />,
            duration: 3500,
            color: 'emerald',
        },
        {
            id: 'origin',
            label: 'Generating origin data',
            sublabel: 'Cross-referencing breed registry',
            icon: <Globe className="h-4 w-4" />,
            duration: 3500,
            color: 'cyan',
        },
        {
            id: 'health',
            label: 'Creating health analysis',
            sublabel: 'Compiling risk profile',
            icon: <Sparkles className="h-4 w-4" />,
            duration: 3500,
            color: 'emerald',
        },
        {
            id: 'finalize',
            label: 'Finalizing analysis',
            sublabel: 'Packaging results',
            icon: <Zap className="h-4 w-4" />,
            duration: 1500,
            color: 'cyan',
        },
    ];

    const totalDuration = stages.reduce((sum, s) => sum + s.duration, 0);

    useEffect(() => {
        if (!isOpen) {
            setCurrentStageIndex(0);
            setProgress(0);
            setTick(0);
            return;
        }

        let cumulativeTime = 0;

        const interval = setInterval(() => {
            cumulativeTime += 50;
            setProgress(Math.min((cumulativeTime / totalDuration) * 100, 100));
            setTick((t) => t + 1);

            let timeSum = 0;
            for (let i = 0; i < stages.length; i++) {
                timeSum += stages[i].duration;
                if (cumulativeTime < timeSum) {
                    setCurrentStageIndex(i);
                    break;
                }
            }

            if (cumulativeTime >= totalDuration) clearInterval(interval);
        }, 50);

        return () => clearInterval(interval);
    }, [isOpen, totalDuration]);

    const currentStage = stages[currentStageIndex];
    const isEmerald = currentStage?.color === 'emerald';

    // Fake live metrics for visual flair
    const fakeMetrics = [
        { label: 'CONF', value: progress > 5 ? `${(55 + progress * 0.43).toFixed(1)}%` : '—' },
        { label: 'LATENCY', value: progress > 10 ? `${(820 - progress * 3).toFixed(0)}ms` : '—' },
        { label: 'NODES', value: progress > 15 ? `${Math.floor(progress * 1.28)}` : '—' },
    ];

    return (
        <>
            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

                @keyframes ald-beam   { from{top:-3px} to{top:calc(100%+3px)} }
                @keyframes ald-ring   { 0%{transform:scale(.86);opacity:.6} 70%,100%{transform:scale(1.4);opacity:0} }
                @keyframes ald-pulse  { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(1.5);opacity:.4} }
                @keyframes ald-sweep  { 0%{top:-100%} 100%{top:100%} }
                @keyframes ald-huddim { 0%,88%,100%{opacity:1} 94%{opacity:.12} }
                @keyframes ald-ticker { from{opacity:.3} to{opacity:1} }
                @keyframes ald-fadein { from{opacity:0;transform:translateY(6px)} to{opacity:1;transform:translateY(0)} }
                @keyframes ald-spin   { to{transform:rotate(360deg)} }
                @keyframes ald-scanx  { 0%{left:-100%} 100%{left:100%} }
                @keyframes ald-barshine { 0%{left:-100%} 100%{left:200%} }

                .ald-root { font-family:'Plus Jakarta Sans',sans-serif; }
                .ald-mono { font-family:'JetBrains Mono',monospace !important; }

                .ald-panel::before {
                    content:''; position:absolute; top:0; left:0; right:0; height:1.5px;
                    background:linear-gradient(90deg,transparent,#10b981 45%,#06b6d4 55%,transparent); opacity:.4;
                    border-radius:inherit;
                }

                .ald-beam { position:absolute; left:0; top:-3px; width:100%; height:2px; background:linear-gradient(90deg,transparent,#10b981,transparent); filter:blur(1px); animation:ald-beam 1.8s linear infinite; pointer-events:none; z-index:3; }

                .ald-sweep { position:absolute; left:0; top:-100%; width:100%; height:100%; background:linear-gradient(180deg,transparent 0%,rgba(16,185,129,.05) 48%,rgba(16,185,129,.13) 50%,rgba(16,185,129,.05) 52%,transparent 100%); animation:ald-sweep 3s ease-in-out infinite; pointer-events:none; z-index:2; }

                .ald-scanline { position:absolute; inset:0; pointer-events:none; background:repeating-linear-gradient(0deg,transparent,transparent 2px,rgba(0,0,0,.018) 2px,rgba(0,0,0,.018) 4px); }

                .ald-hudblink { animation:ald-huddim 3s ease-in-out infinite; }
                .ald-ticker   { animation:ald-ticker 1.2s ease-in-out infinite alternate; }
                .ald-fadein   { animation:ald-fadein .35s cubic-bezier(.16,1,.3,1) both; }

                .ald-progress-bar { position:relative; overflow:hidden; }
                .ald-progress-bar::after { content:''; position:absolute; top:0; left:-100%; width:60%; height:100%; background:linear-gradient(90deg,transparent,rgba(255,255,255,.25),transparent); animation:ald-barshine 1.8s ease-in-out infinite; }

                .ald-ring1 { position:absolute; inset:-8px; border-radius:50%; border:1px solid rgba(16,185,129,.2); animation:ald-ring 2.4s ease-out infinite; }
                .ald-ring2 { position:absolute; inset:-18px; border-radius:50%; border:1px solid rgba(16,185,129,.08); animation:ald-ring 2.4s ease-out infinite .65s; }

                .ald-hc  { position:absolute; width:10px; height:10px; border-color:#10b981; border-style:solid; z-index:4; pointer-events:none; }
                .ald-htl { top:5px; left:5px; border-width:1.5px 0 0 1.5px; }
                .ald-htr { top:5px; right:5px; border-width:1.5px 1.5px 0 0; }
                .ald-hbl { bottom:5px; left:5px; border-width:0 0 1.5px 1.5px; }
                .ald-hbr { bottom:5px; right:5px; border-width:0 1.5px 1.5px 0; }

                .ald-stage-item { transition:all .3s cubic-bezier(.16,1,.3,1); }
                .ald-nsb::-webkit-scrollbar { display:none; }
                .ald-nsb { scrollbar-width:none; }
            `}</style>

            <AlertDialog open={isOpen}>
                <AlertDialogContent className="ald-root max-w-[420px] border-0 bg-transparent p-0 shadow-none sm:mx-0">
                    <div className="ald-panel relative overflow-hidden rounded-2xl border border-white/[.08] bg-[#131720] shadow-2xl shadow-black/60">
                        {/* Ambient glow */}
                        <div className="pointer-events-none absolute top-[-60px] left-1/2 z-0 h-[160px] w-[320px] -translate-x-1/2 rounded-full bg-emerald-500/[.06] blur-[60px]" />
                        <div className="pointer-events-none absolute bottom-[-40px] right-[-20px] z-0 h-[120px] w-[220px] rounded-full bg-cyan-500/[.04] blur-[50px]" />

                        {/* Scan beam */}
                        <div className="ald-beam" />

                        {/* Terminal bar */}
                        <div className="relative z-10 flex flex-shrink-0 items-center gap-3 border-b border-white/[.06] bg-[#0D1117] px-4 py-2.5">
                            <div className="flex gap-1.5">
                                <div className="h-2.5 w-2.5 rounded-full bg-[#FF5F57]" />
                                <div className="h-2.5 w-2.5 rounded-full bg-[#FEBC2E]" />
                                <div className="ald-hudblink h-2.5 w-2.5 rounded-full bg-emerald-500 shadow-[0_0_5px_#10b981]" />
                            </div>
                            <span className="ald-mono ml-1 text-[10px] text-slate-500 select-none">doglens://analyze</span>
                            <div className="ald-mono ml-auto flex items-center gap-1.5 text-[10px] text-emerald-400 select-none">
                                <span className="ald-ticker h-1.5 w-1.5 rounded-full bg-emerald-500 shadow-[0_0_5px_#10b981]" />
                                PROCESSING
                            </div>
                        </div>

                        <AlertDialogHeader className="relative z-10 px-5 pt-5 pb-0">
                            <AlertDialogTitle asChild>
                                <div className="flex items-center gap-3">
                                    {/* Animated icon orb */}
                                    <div className="relative flex h-14 w-14 flex-shrink-0 items-center justify-center">
                                        <div className="ald-ring1" />
                                        <div className="ald-ring2" />
                                        <div className="relative flex h-14 w-14 items-center justify-center rounded-full border border-emerald-500/20 bg-emerald-500/[.1] shadow-lg shadow-emerald-500/10">
                                            <Brain className="h-6 w-6 text-emerald-400" style={{ animation: 'ald-pulse 2s ease-in-out infinite' }} />
                                        </div>
                                    </div>
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <h2 className="text-lg font-extrabold tracking-tight text-white">Analyzing Your Dog</h2>
                                        </div>
                                        <div className="mt-1 flex items-center gap-2">
                                            <span className="ald-mono text-[10px] tracking-[.1em] text-emerald-500/70 uppercase">Breed Identification</span>
                                            <span className="ald-mono text-[10px] text-slate-600">·</span>
                                            <span className="ald-mono text-[10px] font-semibold text-emerald-400">{Math.round(progress)}%</span>
                                        </div>
                                    </div>
                                </div>
                            </AlertDialogTitle>
                        </AlertDialogHeader>

                        <AlertDialogDescription asChild>
                            <div className="relative z-10 flex flex-col gap-4 px-5 pb-5 pt-4">

                                {/* Live metrics row */}
                                <div className="grid grid-cols-3 gap-2">
                                    {fakeMetrics.map((m, i) => (
                                        <div key={i} className="flex flex-col items-center justify-center rounded-xl border border-white/[.05] bg-white/[.02] py-2 px-1">
                                            <span className="ald-mono text-[11px] font-bold text-white">{m.value}</span>
                                            <span className="ald-mono text-[8px] tracking-[.1em] text-slate-600 uppercase">{m.label}</span>
                                        </div>
                                    ))}
                                </div>

                                {/* Current stage box */}
                                <div className="ald-fadein relative overflow-hidden rounded-xl border border-emerald-500/20 bg-emerald-500/[.04]">
                                    <div className="ald-sweep" />
                                    <div className="ald-scanline" />
                                    {['ald-htl','ald-htr','ald-hbl','ald-hbr'].map((c) => <div key={c} className={`ald-hc ${c}`} />)}
                                    <div className="relative z-10 flex items-center gap-3.5 p-3.5">
                                        <div className="relative flex h-11 w-11 flex-shrink-0 items-center justify-center">
                                            <div className="absolute inset-0 rounded-full bg-emerald-500/10 animate-ping opacity-30" />
                                            <div className="relative flex h-11 w-11 items-center justify-center rounded-full border border-emerald-500/25 bg-[#0D1117]">
                                                <Loader2 className="h-5 w-5 text-emerald-400" style={{ animation: 'ald-spin 1s linear infinite' }} />
                                            </div>
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-bold text-white truncate">{currentStage?.label}…</p>
                                            <p className="text-[11px] text-slate-500 truncate">{currentStage?.sublabel}</p>
                                            <div className="ald-mono mt-1 flex items-center gap-1.5 text-[9px] text-emerald-500/70">
                                                Step {currentStageIndex + 1} of {stages.length}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {/* Progress bar */}
                                <div>
                                    <div className="mb-1.5 flex items-center justify-between">
                                        <span className="ald-mono text-[9px] tracking-[.1em] text-slate-600 uppercase">Overall Progress</span>
                                        <span className="ald-mono text-[10px] font-semibold text-emerald-400">{Math.round(progress)}%</span>
                                    </div>
                                    <div className="relative h-2 overflow-hidden rounded-full bg-white/[.05]">
                                        <div
                                            className="ald-progress-bar h-full rounded-full bg-gradient-to-r from-emerald-500 to-cyan-500 transition-all duration-300 ease-out"
                                            style={{ width: `${progress}%` }}
                                        />
                                    </div>
                                </div>

                                {/* Steps list */}
                                <div className="ald-nsb max-h-[200px] overflow-y-auto rounded-xl border border-white/[.05] bg-white/[.02] p-3">
                                    <p className="ald-mono mb-2.5 text-[9px] tracking-[.12em] text-slate-600 uppercase">Progress Steps</p>
                                    <div className="flex flex-col gap-1.5">
                                        {stages.map((stage, index) => {
                                            const isCompleted = index < currentStageIndex;
                                            const isCurrent = index === currentStageIndex;
                                            const isPending = index > currentStageIndex;

                                            return (
                                                <div
                                                    key={stage.id}
                                                    className={`ald-stage-item flex items-center gap-2.5 rounded-lg px-2.5 py-2 ${
                                                        isCurrent ? 'border border-emerald-500/20 bg-emerald-500/[.05]' :
                                                        isCompleted ? 'border border-transparent' :
                                                        'border border-transparent opacity-35'
                                                    }`}
                                                >
                                                    {isCompleted ? (
                                                        <div className="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full bg-emerald-500/20 border border-emerald-500/30">
                                                            <CheckCircle2 className="h-3 w-3 text-emerald-400" />
                                                        </div>
                                                    ) : isCurrent ? (
                                                        <div className="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-full border border-emerald-500/40 bg-emerald-500/10">
                                                            <Loader2 className="h-3 w-3 text-emerald-400" style={{ animation: 'ald-spin 1s linear infinite' }} />
                                                        </div>
                                                    ) : (
                                                        <div className="h-5 w-5 flex-shrink-0 rounded-full border border-white/[.08] bg-white/[.02]" />
                                                    )}

                                                    <div className="flex flex-1 items-center gap-2 min-w-0">
                                                        <span className={`ald-mono text-[9px] flex-shrink-0 ${isCompleted ? 'text-emerald-500/60' : isCurrent ? 'text-emerald-400' : 'text-slate-700'}`}>
                                                            0{index + 1}
                                                        </span>
                                                        <span className={`text-xs truncate ${
                                                            isCompleted ? 'text-emerald-400/70' :
                                                            isCurrent ? 'font-semibold text-white' :
                                                            'text-slate-600'
                                                        }`}>
                                                            {stage.label}
                                                        </span>
                                                    </div>

                                                    {isCompleted && (
                                                        <span className="ald-mono flex-shrink-0 text-[8px] text-emerald-500/50">DONE</span>
                                                    )}
                                                    {isCurrent && (
                                                        <span className="ald-ticker ald-mono flex-shrink-0 text-[8px] text-emerald-400">ACTIVE</span>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>

                                {/* Footer note */}
                                <div className="flex items-center justify-center gap-2">
                                    <div className="ald-ticker h-1.5 w-1.5 flex-shrink-0 rounded-full bg-emerald-500 shadow-[0_0_4px_#10b981]" />
                                    <span className="ald-mono text-center text-[9px] tracking-[.1em] text-slate-600 uppercase">
                                        Do not close this window
                                    </span>
                                    <div className="ald-ticker h-1.5 w-1.5 flex-shrink-0 rounded-full bg-emerald-500 shadow-[0_0_4px_#10b981]" style={{ animationDelay: '.6s' }} />
                                </div>

                            </div>
                        </AlertDialogDescription>
                    </div>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
};

export default AnalysisLoadingDialog;