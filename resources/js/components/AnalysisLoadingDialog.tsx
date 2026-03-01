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
}

interface AnalysisLoadingDialogProps {
    isOpen: boolean;
}

const AnalysisLoadingDialog: React.FC<AnalysisLoadingDialogProps> = ({ isOpen }) => {
    const [currentStageIndex, setCurrentStageIndex] = useState(0);
    const [progress, setProgress] = useState(0);

    const stages: AnalysisStage[] = [
        { id:'upload',   label:'Uploading image',        sublabel:'Sending to analysis server',    icon:<Upload size={14}/>,   duration:800  },
        { id:'identify', label:'Identifying breed',      sublabel:'Running neural network',         icon:<Brain size={14}/>,    duration:7500 },
        { id:'features', label:'Extracting features',    sublabel:'Mapping visual characteristics', icon:<Activity size={14}/>, duration:3500 },
        { id:'origin',   label:'Generating origin data', sublabel:'Cross-referencing breed registry',icon:<Globe size={14}/>,    duration:3500 },
        { id:'health',   label:'Creating health analysis',sublabel:'Compiling breed risk profile',  icon:<Sparkles size={14}/>, duration:3500 },
        { id:'finalize', label:'Finalizing results',     sublabel:'Packaging your analysis',        icon:<Zap size={14}/>,      duration:1500 },
    ];

    const totalDuration = stages.reduce((sum, s) => sum + s.duration, 0);

    useEffect(() => {
        if (!isOpen) { setCurrentStageIndex(0); setProgress(0); return; }
        let elapsed = 0;
        const interval = setInterval(() => {
            elapsed += 50;
            setProgress(Math.min((elapsed / totalDuration) * 100, 100));
            let sum = 0;
            for (let i = 0; i < stages.length; i++) {
                sum += stages[i].duration;
                if (elapsed < sum) { setCurrentStageIndex(i); break; }
            }
            if (elapsed >= totalDuration) clearInterval(interval);
        }, 50);
        return () => clearInterval(interval);
    }, [isOpen, totalDuration]);

    const currentStage = stages[currentStageIndex];

    return (
        <>
            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

                .ald-root { font-family:'Plus Jakarta Sans',sans-serif; }

                @keyframes ald-fadein  { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }
                @keyframes ald-spin    { to{transform:rotate(360deg)} }
                @keyframes ald-pulse   { 0%,100%{transform:scale(1);opacity:.7} 50%{transform:scale(1.3);opacity:1} }
                @keyframes ald-shimmer { 0%{background-position:200% center} 100%{background-position:-200% center} }
                @keyframes ald-ping    { 0%{transform:scale(1);opacity:.6} 100%{transform:scale(1.6);opacity:0} }
                @keyframes ald-barshine{ 0%{left:-80%} 100%{left:120%} }

                .ald-root .ald-fadein { animation:ald-fadein .4s cubic-bezier(.16,1,.3,1) both; }

                .ald-dialog-card {
                    background:linear-gradient(155deg,#1D267D 0%,#0C134F 100%);
                    border:1px solid rgba(255,255,255,.1);
                    border-radius:20px;
                    overflow:hidden;
                    box-shadow:0 28px 70px rgba(0,0,0,.55);
                    position:relative;
                }
                .ald-dialog-card::before {
                    content:''; position:absolute; top:-60px; right:-40px;
                    width:200px; height:200px; border-radius:50%;
                    background:radial-gradient(circle,rgba(92,70,156,.35) 0%,transparent 70%);
                    pointer-events:none;
                }
                .ald-dialog-card::after {
                    content:''; position:absolute; bottom:-50px; left:-20px;
                    width:160px; height:160px; border-radius:50%;
                    background:radial-gradient(circle,rgba(12,19,79,.8) 0%,transparent 70%);
                    pointer-events:none;
                }

                .ald-header {
                    padding:24px 24px 20px;
                    border-bottom:1px solid rgba(255,255,255,.08);
                    position:relative; z-index:1;
                }

                .ald-icon-orb {
                    width:56px; height:56px; border-radius:16px; flex-shrink:0;
                    background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.16);
                    display:flex; align-items:center; justify-content:center;
                    position:relative;
                }
                .ald-icon-orb-ping {
                    position:absolute; inset:0; border-radius:16px;
                    border:2px solid rgba(255,255,255,.2);
                    animation:ald-ping 2s ease-out infinite;
                }

                .ald-body {
                    padding:20px 24px 24px;
                    position:relative; z-index:1;
                    display:flex; flex-direction:column; gap:18px;
                }

                .ald-progress-wrap {}
                .ald-progress-label {
                    display:flex; align-items:center; justify-content:space-between;
                    margin-bottom:8px;
                }
                .ald-progress-track {
                    height:8px; border-radius:999px;
                    background:rgba(255,255,255,.1); overflow:hidden; position:relative;
                }
                .ald-progress-fill {
                    height:100%; border-radius:999px;
                    background:linear-gradient(90deg,#5C469C,#7c5cbf,#a78bfa);
                    background-size:200% auto;
                    animation:ald-shimmer 2s linear infinite;
                    transition:width .3s ease-out;
                    position:relative; overflow:hidden;
                }
                .ald-progress-fill::after {
                    content:''; position:absolute; top:0; left:-80%; width:60%; height:100%;
                    background:linear-gradient(90deg,transparent,rgba(255,255,255,.3),transparent);
                    animation:ald-barshine 1.6s ease-in-out infinite;
                }

                .ald-current-stage {
                    background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1);
                    border-radius:14px; padding:14px 16px;
                    display:flex; align-items:center; gap:14px;
                }
                .ald-stage-icon {
                    width:42px; height:42px; flex-shrink:0; border-radius:12px;
                    background:rgba(92,70,156,.35); border:1px solid rgba(167,139,250,.25);
                    display:flex; align-items:center; justify-content:center;
                    color:#c4b5fd;
                }

                .ald-steps { display:flex; flex-direction:column; gap:0; }
                .ald-step {
                    display:flex; align-items:center; gap:12px;
                    padding:10px 14px; border-radius:10px;
                    transition:all .25s;
                }
                .ald-step-active { background:rgba(255,255,255,.06); }
                .ald-step-done   { opacity:.65; }
                .ald-step-pending{ opacity:.3; }

                .ald-step-dot {
                    width:22px; height:22px; flex-shrink:0; border-radius:50%;
                    display:flex; align-items:center; justify-content:center;
                }
                .ald-dot-done    { background:rgba(74,222,128,.18); border:1px solid rgba(74,222,128,.3); }
                .ald-dot-active  { background:rgba(92,70,156,.4);   border:1px solid rgba(167,139,250,.35); }
                .ald-dot-pending { background:transparent; border:1.5px solid rgba(255,255,255,.15); }

                .ald-nsb::-webkit-scrollbar { display:none; }
                .ald-nsb { scrollbar-width:none; }
            `}</style>

            <AlertDialog open={isOpen}>
                <AlertDialogContent className="ald-root max-w-[400px] border-0 bg-transparent p-0 shadow-none">
                    <div className="ald-dialog-card ald-fadein">

                        {/* Header */}
                        <AlertDialogHeader className="ald-header">
                            <AlertDialogTitle asChild>
                                <div style={{ display:'flex', alignItems:'center', gap:16 }}>
                                    <div className="ald-icon-orb">
                                        <div className="ald-icon-orb-ping" />
                                        <Brain size={24} style={{ color:'white' }} />
                                    </div>
                                    <div>
                                        <h2 style={{ fontSize:18, fontWeight:800, color:'white', margin:'0 0 3px', fontFamily:"'Plus Jakarta Sans',sans-serif" }}>
                                            Analyzing Your Dog
                                        </h2>
                                        <p style={{ fontSize:12, color:'rgba(255,255,255,.5)', margin:0, fontFamily:"'Plus Jakarta Sans',sans-serif" }}>
                                            Breed identification in progress
                                        </p>
                                    </div>
                                </div>
                            </AlertDialogTitle>
                        </AlertDialogHeader>

                        {/* Body */}
                        <AlertDialogDescription asChild>
                            <div className="ald-body">

                                {/* Progress */}
                                <div className="ald-progress-wrap">
                                    <div className="ald-progress-label">
                                        <span style={{ fontSize:12, fontWeight:600, color:'rgba(255,255,255,.6)' }}>Overall Progress</span>
                                        <span style={{ fontSize:13, fontWeight:700, color:'white' }}>{Math.round(progress)}%</span>
                                    </div>
                                    <div className="ald-progress-track">
                                        <div className="ald-progress-fill" style={{ width:`${progress}%` }} />
                                    </div>
                                </div>

                                {/* Current stage */}
                                <div className="ald-current-stage">
                                    <div className="ald-stage-icon">
                                        <Loader2 size={18} style={{ animation:'ald-spin 1s linear infinite' }} />
                                    </div>
                                    <div style={{ flex:1, minWidth:0 }}>
                                        <p style={{ fontSize:14, fontWeight:700, color:'white', margin:'0 0 2px', fontFamily:"'Plus Jakarta Sans',sans-serif" }}>
                                            {currentStage?.label}…
                                        </p>
                                        <p style={{ fontSize:12, color:'rgba(255,255,255,.45)', margin:0, fontFamily:"'Plus Jakarta Sans',sans-serif" }}>
                                            {currentStage?.sublabel}
                                        </p>
                                    </div>
                                    <span style={{ fontSize:12, fontWeight:600, color:'#a78bfa', flexShrink:0, fontFamily:"'Plus Jakarta Sans',sans-serif" }}>
                                        {currentStageIndex + 1}/{stages.length}
                                    </span>
                                </div>

                                {/* Steps list */}
                                <div className="ald-nsb" style={{ background:'rgba(255,255,255,.04)', border:'1px solid rgba(255,255,255,.08)', borderRadius:14, padding:'8px 6px', maxHeight:220, overflowY:'auto' }}>
                                    <p style={{ fontSize:11, fontWeight:600, color:'rgba(255,255,255,.35)', textTransform:'uppercase', letterSpacing:'.08em', margin:'4px 8px 8px', fontFamily:"'Plus Jakarta Sans',sans-serif" }}>Steps</p>
                                    <div className="ald-steps">
                                        {stages.map((stage, index) => {
                                            const done    = index < currentStageIndex;
                                            const current = index === currentStageIndex;
                                            const pending = index > currentStageIndex;
                                            return (
                                                <div key={stage.id} className={`ald-step ${current ? 'ald-step-active' : done ? 'ald-step-done' : 'ald-step-pending'}`}>
                                                    <div className={`ald-step-dot ${done ? 'ald-dot-done' : current ? 'ald-dot-active' : 'ald-dot-pending'}`}>
                                                        {done
                                                            ? <CheckCircle2 size={13} style={{ color:'#4ade80' }} />
                                                            : current
                                                                ? <Loader2 size={11} style={{ color:'#a78bfa', animation:'ald-spin 1s linear infinite' }} />
                                                                : null
                                                        }
                                                    </div>
                                                    <div style={{ flex:1, display:'flex', alignItems:'center', gap:8 }}>
                                                        <span style={{ color: done ? '#4ade80' : current ? '#a78bfa' : 'rgba(255,255,255,.4)', flexShrink:0 }}>{stage.icon}</span>
                                                        <span style={{ fontSize:13, fontWeight: current ? 600 : 400, color: done ? 'rgba(255,255,255,.65)' : current ? 'white' : 'rgba(255,255,255,.3)', fontFamily:"'Plus Jakarta Sans',sans-serif" }}>
                                                            {stage.label}
                                                        </span>
                                                    </div>
                                                    {done && (
                                                        <span style={{ fontSize:11, color:'rgba(74,222,128,.6)', fontWeight:600, fontFamily:"'Plus Jakarta Sans',sans-serif" }}>Done</span>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>

                                {/* Footer note */}
                                <p style={{ textAlign:'center', fontSize:12, color:'rgba(255,255,255,.3)', margin:0, fontFamily:"'Plus Jakarta Sans',sans-serif" }}>
                                    Please keep this window open while we analyze your image
                                </p>

                            </div>
                        </AlertDialogDescription>
                    </div>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
};

export default AnalysisLoadingDialog;