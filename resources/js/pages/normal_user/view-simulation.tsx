import Header from '@/components/header';
import { Link } from '@inertiajs/react';
import axios from 'axios';
import { AlertCircle, ArrowLeft, ChevronRight, Clock, History, Loader2, Scan as ScanIcon, Sparkles, TriangleAlert } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

interface SimulationData { '1_years': string | null; '3_years': string | null; }
interface Props {
    breed: string; originalImage: string; simulations: SimulationData;
    simulation_status?: 'pending' | 'generating' | 'complete' | 'failed'; scan_id: string;
}

const ViewSimulation: React.FC<Props> = ({ breed, originalImage, simulations: initialSimulations, simulation_status: initialStatus = 'pending', scan_id }) => {
    const [simulations, setSimulations] = useState<SimulationData>(initialSimulations);
    const [status, setStatus] = useState<string>(initialStatus);
    const [currentOriginalImage, setCurrentOriginalImage] = useState<string>(originalImage);
    const [isPolling, setIsPolling] = useState(initialStatus !== 'complete' && initialStatus !== 'failed');
    const [pollingAttempts, setPollingAttempts] = useState(0);
    const [lastUpdate, setLastUpdate] = useState<number>(Date.now());
    const [activeTab, setActiveTab] = useState<'1yr' | '3yr'>('1yr');
    const MAX_POLLING_ATTEMPTS = 120;

    const getImageUrl = useCallback((url: string | null): string => {
        if (!url || url.trim() === '') return '/dogpic.jpg';
        return url;
    }, []);

    const hasSimulations = Boolean(simulations && (simulations['1_years'] || simulations['3_years']));

    useEffect(() => {
        if (!isPolling || pollingAttempts >= MAX_POLLING_ATTEMPTS) {
            if (pollingAttempts >= MAX_POLLING_ATTEMPTS) { setStatus('failed'); setIsPolling(false); }
            return;
        }
        const poll = async () => {
            try {
                const response = await axios.get(`/api/simulation-status?scan_id=${scan_id}&t=${Date.now()}`, {
                    headers: { 'Cache-Control': 'no-cache, no-store, must-revalidate', Pragma: 'no-cache', Expires: '0' }
                });
                const data = response.data;
                const dataChanged = data.status !== status || data.simulations['1_years'] !== simulations['1_years'] || data.simulations['3_years'] !== simulations['3_years'] || data.original_image !== currentOriginalImage;
                if (dataChanged) {
                    setStatus(data.status);
                    setSimulations({ '1_years': data.simulations['1_years'], '3_years': data.simulations['3_years'] });
                    if (data.original_image) setCurrentOriginalImage(data.original_image);
                    setLastUpdate(Date.now());
                }
                setPollingAttempts(prev => prev + 1);
                if (data.status === 'complete' || data.status === 'failed') setIsPolling(false);
            } catch (error) { setPollingAttempts(prev => prev + 1); }
        };
        poll();
        const interval = setInterval(poll, 3000);
        return () => clearInterval(interval);
    }, [isPolling, pollingAttempts, status, simulations, scan_id, currentOriginalImage]);

    const ImageCard = ({ src, label, sublabel, isLoading = false }: { src: string | null; label: string; sublabel: string; isLoading?: boolean }) => (
        <div className="vs-imgcard relative flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-white/[.07] dark:bg-[#131720]">
            <div className="vs-panel-line absolute top-0 left-0 right-0 h-[1.5px]"/>
            <div className="flex flex-shrink-0 items-center gap-2 border-b border-slate-200 bg-slate-50/80 px-4 py-2.5 dark:border-white/[.06] dark:bg-white/[.025]">
                <div className="flex h-5 w-5 items-center justify-center rounded-md border border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"><Clock size={11}/></div>
                <span className="vs-mono text-[10px] font-bold tracking-[.12em] text-slate-600 uppercase dark:text-slate-400">{label}</span>
            </div>
            <div className="relative aspect-square overflow-hidden bg-slate-100 dark:bg-[#0D1117]">
                {isLoading ? (
                    <div className="flex h-full flex-col items-center justify-center gap-4">
                        <div className="relative">
                            <Loader2 size={36} className="animate-spin text-violet-500"/>
                            <div className="absolute inset-[-8px] rounded-full border border-violet-500/20 animate-ping"/>
                        </div>
                        <div className="text-center">
                            <p className="text-[13px] font-bold text-slate-700 dark:text-slate-200">Generating…</p>
                            <p className="vs-mono text-[9px] tracking-[.1em] text-slate-500 uppercase dark:text-slate-500">AI processing</p>
                        </div>
                    </div>
                ) : src ? (
                    <>
                        <img src={getImageUrl(src)} alt={label} className="h-full w-full object-cover" key={`${label}-${lastUpdate}`} onError={e => { e.currentTarget.src = '/dogpic.jpg'; }}/>
                        {/* Scan overlay */}
                        <div className="vs-sweep pointer-events-none absolute inset-0"/>
                        <div className="pointer-events-none absolute inset-0" style={{background:'repeating-linear-gradient(0deg,transparent,transparent 2px,rgba(0,0,0,.012) 2px,rgba(0,0,0,.012) 4px)'}}/>
                        {/* HUD corners */}
                        {['vs-htl','vs-htr','vs-hbl','vs-hbr'].map(c=><div key={c} className={`vs-hc ${c}`}/>)}
                        <div className="absolute bottom-2 left-2 right-2 z-[5]">
                            <span className="vs-mono rounded border border-emerald-500/20 bg-black/65 px-2 py-0.5 text-[9px] font-medium tracking-[.1em] text-emerald-400 backdrop-blur-sm">AI GENERATED</span>
                        </div>
                    </>
                ) : (
                    <div className="flex h-full items-center justify-center">
                        <p className="text-sm text-slate-400 dark:text-slate-600">No image available</p>
                    </div>
                )}
            </div>
            <div className="px-4 py-3">
                <p className="text-[12px] leading-relaxed text-slate-600 dark:text-slate-400">{sublabel}</p>
            </div>
        </div>
    );

    return (
        <>
            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap');
                @keyframes vs-faderise { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
                @keyframes vs-sweep    { 0%{top:-100%} 100%{top:100%} }
                @keyframes vs-dpulse   { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(1.5);opacity:.45} }
                @keyframes vs-huddim   { 0%,88%,100%{opacity:1} 94%{opacity:.12} }
                @keyframes vs-spin     { from{transform:rotate(0deg)} to{transform:rotate(360deg)} }
                @keyframes vs-ping     { 0%{transform:scale(1);opacity:.6} 100%{transform:scale(1.5);opacity:0} }
                .vs-root { font-family:'Plus Jakarta Sans',sans-serif; }
                .vs-mono { font-family:'JetBrains Mono',monospace !important; }
                .vs-dotgrid { position:fixed;inset:0;pointer-events:none;z-index:0;background-image:radial-gradient(circle,rgba(16,185,129,.07) 1px,transparent 1px);background-size:28px 28px;-webkit-mask-image:radial-gradient(ellipse 80% 55% at 50% 0%,black 0%,transparent 100%);mask-image:radial-gradient(ellipse 80% 55% at 50% 0%,black 0%,transparent 100%); }
                .dark .vs-dotgrid { background-image:radial-gradient(circle,rgba(16,185,129,.055) 1px,transparent 1px); }
                .vs-panel-line { background:linear-gradient(90deg,transparent,#10b981 45%,#a855f7 55%,transparent);opacity:.3; }
                .vs-imgcard::before { content:'';position:absolute;top:0;left:0;right:0;height:1.5px;background:linear-gradient(90deg,transparent,#10b981 45%,#a855f7 55%,transparent);opacity:.3; }
                .vs-fu  { animation:vs-faderise .45s cubic-bezier(.16,1,.3,1) both; }
                .vs-fu1 { animation-delay:.07s } .vs-fu2 { animation-delay:.14s } .vs-fu3 { animation-delay:.21s } .vs-fu4 { animation-delay:.28s }
                .vs-sweep { position:absolute;left:0;top:-100%;width:100%;height:100%;background:linear-gradient(180deg,transparent 0%,rgba(168,85,247,.04) 46%,rgba(168,85,247,.11) 50%,rgba(168,85,247,.04) 54%,transparent 100%);animation:vs-sweep 4s ease-in-out infinite;pointer-events:none;z-index:3; }
                .vs-hc { position:absolute;width:12px;height:12px;border-color:rgba(168,85,247,.65);border-style:solid;z-index:4;pointer-events:none; }
                .vs-htl{top:6px;left:6px;border-width:2px 0 0 2px} .vs-htr{top:6px;right:6px;border-width:2px 2px 0 0} .vs-hbl{bottom:6px;left:6px;border-width:0 0 2px 2px} .vs-hbr{bottom:6px;right:6px;border-width:0 2px 2px 0}
                .vs-hudblink { animation:vs-huddim 3s ease-in-out infinite; }
                .vs-nsb::-webkit-scrollbar{display:none} .vs-nsb{scrollbar-width:none}
                .vs-tab { transition:all .18s; }
                .vs-tab-active { background:white;color:#1e293b;box-shadow:0 1px 3px rgba(0,0,0,.1); }
                .dark .vs-tab-active { background:#131720;color:white;box-shadow:0 1px 3px rgba(0,0,0,.4); }
            `}</style>

            <div className="vs-root flex min-h-screen flex-col bg-slate-50 dark:bg-[#080B0F]">
                <div className="pointer-events-none fixed top-[-120px] left-[-60px] z-0 h-[260px] w-[440px] rounded-full bg-violet-400/[.03] blur-[85px]" />
                <div className="pointer-events-none fixed bottom-0 right-0 z-0 h-[220px] w-[360px] rounded-full bg-emerald-400/[.025] blur-[90px]" />
                <div className="vs-dotgrid" />
                <div className="relative z-20 flex-shrink-0"><Header /></div>

                <div className="vs-nsb relative z-10 mt-[-10px] flex-1 overflow-y-auto">
                    <div className="mx-auto max-w-[1100px] px-4 py-6 sm:px-6 lg:px-4">

                        {/* ── PAGE HEADER ── */}
                        <div className="vs-fu mb-5 flex flex-wrap items-center justify-between gap-4">
                            <div className="flex items-center gap-3">
                                <Link href="/scan-results" className="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition-all hover:border-violet-500/30 hover:text-violet-600 dark:border-white/[.07] dark:bg-[#131720] dark:text-slate-400 dark:hover:text-violet-400">
                                    <ArrowLeft size={15} />
                                </Link>
                                <div>
                                    <div className="mb-1 inline-flex items-center gap-2 rounded-full border border-violet-500/20 bg-violet-500/[.07] px-2.5 py-0.5">
                                        <span className="h-1.5 w-1.5 rounded-full bg-violet-500 shadow-[0_0_6px_#a855f7]" style={{animation:'vs-dpulse 2s ease-in-out infinite'}} />
                                        <span className="vs-mono text-[9px] font-semibold tracking-[.12em] text-violet-700 uppercase dark:text-violet-400">Age Simulation</span>
                                    </div>
                                    <h1 className="text-xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                                        Future <span className="bg-gradient-to-r from-violet-500 to-purple-500 bg-clip-text text-transparent">Appearance</span>
                                    </h1>
                                    <p className="mt-0.5 text-sm text-slate-600 dark:text-slate-400">See how <strong className="font-semibold text-slate-800 dark:text-slate-200">{breed}</strong> will look in 1 &amp; 3 years</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <Link href="/scanhistory" className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-[13px] font-semibold text-slate-600 no-underline transition-all hover:border-emerald-500/30 hover:text-emerald-600 dark:border-white/[.07] dark:bg-[#131720] dark:text-slate-400 dark:hover:text-emerald-400">
                                    <History size={13} /> History
                                </Link>
                                <Link href="/scan" className="inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-3.5 py-2 text-[13px] font-bold text-black no-underline shadow-lg shadow-emerald-500/20 transition-all hover:-translate-y-0.5 hover:bg-emerald-400">
                                    <ScanIcon size={13} /> New Scan <ChevronRight size={11} className="opacity-60" />
                                </Link>
                            </div>
                        </div>

                        {/* ── NOTE ── */}
                        <div className="vs-fu vs-fu1 mb-5 flex items-start gap-3.5 rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/20 dark:bg-amber-500/[.06]">
                            <TriangleAlert size={15} className="mt-0.5 flex-shrink-0 text-amber-500"/>
                            <div>
                                <p className="text-[13px] font-bold text-amber-700 dark:text-amber-300">Predictive Simulation</p>
                                <p className="mt-0.5 text-xs leading-relaxed text-amber-700/80 dark:text-amber-400/80">This prediction shows your dog 1 and 3 years from today based on current age and breed patterns. Actual aging may vary depending on genetics, health, and environment.</p>
                            </div>
                        </div>

                        {/* ── LOADING STATE ── */}
                        {(status === 'pending' || status === 'generating') && !hasSimulations && (
                            <div className="vs-fu vs-fu2">
                                <div className="relative overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-white/[.07] dark:bg-[#131720]">
                                    <div className="vs-imgcard absolute top-0 left-0 right-0 h-[1.5px]"/>
                                    <div className="flex flex-col items-center justify-center gap-6 py-20 text-center">
                                        <div className="relative">
                                            <div className="absolute inset-[-16px] rounded-full border border-violet-500/15" style={{animation:'vs-ping 2s ease-out infinite'}}/>
                                            <div className="absolute inset-[-8px] rounded-full border border-violet-500/20" style={{animation:'vs-ping 2s ease-out infinite .4s'}}/>
                                            <div className="flex h-20 w-20 items-center justify-center rounded-full border border-violet-500/20 bg-violet-500/[.07]">
                                                <Loader2 size={32} className="text-violet-500" style={{animation:'vs-spin 1s linear infinite'}}/>
                                            </div>
                                        </div>
                                        <div>
                                            <p className="text-lg font-extrabold text-slate-900 dark:text-white">{status === 'pending' ? 'Preparing simulation…' : 'Generating predictions…'}</p>
                                            <p className="mt-1 text-sm text-slate-600 dark:text-slate-400">Creating AI age progression images. This takes 40–60 seconds.</p>
                                            <p className="vs-mono mt-2 text-[10px] tracking-[.1em] text-slate-500 dark:text-slate-500">CHECK {pollingAttempts}/{MAX_POLLING_ATTEMPTS}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* ── FAILED STATE ── */}
                        {status === 'failed' && !hasSimulations && (
                            <div className="vs-fu vs-fu2">
                                <div className="relative overflow-hidden rounded-2xl border border-red-200 bg-red-50 shadow-sm dark:border-red-500/20 dark:bg-red-500/[.06]">
                                    <div className="flex flex-col items-center justify-center gap-5 py-20 text-center">
                                        <div className="flex h-16 w-16 items-center justify-center rounded-full border border-red-200 bg-red-100 dark:border-red-500/25 dark:bg-red-500/[.1]">
                                            <AlertCircle size={28} className="text-red-500"/>
                                        </div>
                                        <div>
                                            <p className="text-lg font-extrabold text-red-800 dark:text-red-200">Simulation failed</p>
                                            <p className="mt-1 text-sm text-red-700/80 dark:text-red-400/80">We couldn't generate the age simulations. Please try again later.</p>
                                        </div>
                                        <Link href="/scan-results" className="inline-flex items-center gap-2 rounded-xl bg-red-500 px-5 py-2.5 text-[13px] font-bold text-white no-underline transition-all hover:bg-red-400">
                                            <ArrowLeft size={13}/> Back to Results
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* ── RESULTS ── */}
                        {hasSimulations && (
                            <div className="vs-fu vs-fu2">
                                {/* Still generating banner */}
                                {status === 'generating' && (
                                    <div className="mb-4 flex items-center gap-3 rounded-xl border border-blue-200 bg-blue-50 p-3.5 dark:border-blue-500/20 dark:bg-blue-500/[.06]">
                                        <Loader2 size={14} className="animate-spin text-blue-500"/>
                                        <p className="text-[13px] font-semibold text-blue-700 dark:text-blue-300">Still generating remaining images…</p>
                                    </div>
                                )}

                                {/* Tab selector */}
                                <div className="mb-5 flex items-center gap-2">
                                    <div className="inline-flex gap-1 rounded-xl border border-slate-200 bg-slate-100 p-1 dark:border-white/[.06] dark:bg-white/[.04]">
                                        {([['1yr', '1 Year View', Sparkles], ['3yr', '3 Year View', Clock]] as const).map(([key, label, Icon]) => (
                                            <button key={key} onClick={() => setActiveTab(key)} className={`vs-tab flex items-center gap-2 rounded-lg px-4 py-2 text-[13px] font-bold transition-all ${activeTab === key ? 'vs-tab-active' : 'text-slate-500 hover:text-slate-700 dark:text-slate-500 dark:hover:text-slate-300'}`}>
                                                <Icon size={13} className={activeTab === key ? 'text-violet-500' : ''}/>
                                                {label}
                                            </button>
                                        ))}
                                    </div>
                                    <span className="vs-mono ml-auto text-[9px] tracking-[.1em] text-slate-500 dark:text-slate-500">AI GENERATED · {breed?.toUpperCase()}</span>
                                </div>

                                {/* Image comparison */}
                                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                    {/* Current */}
                                    <div className="relative flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-white/[.07] dark:bg-[#131720]">
                                        <div className="vs-imgcard absolute top-0 left-0 right-0 h-[1.5px]"/>
                                        <div className="flex flex-shrink-0 items-center gap-2 border-b border-slate-200 bg-slate-50/80 px-4 py-2.5 dark:border-white/[.06] dark:bg-white/[.025]">
                                            <div className="flex h-5 w-5 items-center justify-center rounded-md border border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"><ScanIcon size={11}/></div>
                                            <span className="vs-mono text-[10px] font-bold tracking-[.12em] text-slate-600 uppercase dark:text-slate-400">Current Appearance</span>
                                        </div>
                                        <div className="relative overflow-hidden bg-slate-100 dark:bg-[#0D1117]" style={{aspectRatio:'1'}}>
                                            <img src={getImageUrl(currentOriginalImage)} alt="Current" className="h-full w-full object-cover" key={`orig-${lastUpdate}`} onError={e=>{e.currentTarget.src='/dogpic.jpg'}}/>
                                            <div className="vs-sweep pointer-events-none absolute inset-0" style={{background:'linear-gradient(180deg,transparent 0%,rgba(16,185,129,.04) 46%,rgba(16,185,129,.1) 50%,rgba(16,185,129,.04) 54%,transparent 100%)'}}/>
                                            {['vs-htl','vs-htr','vs-hbl','vs-hbr'].map(c=><div key={c} className={`vs-hc ${c}`} style={{borderColor:'rgba(16,185,129,.65)'}}/>)}
                                            <div className="absolute bottom-2 left-2 right-2 z-[5]">
                                                <span className="vs-mono rounded border border-emerald-500/20 bg-black/65 px-2 py-0.5 text-[9px] font-medium tracking-[.1em] text-emerald-400 backdrop-blur-sm">TODAY</span>
                                            </div>
                                        </div>
                                        <div className="px-4 py-3">
                                            <p className="text-[12px] text-slate-600 dark:text-slate-400">How your dog looks today</p>
                                        </div>
                                    </div>

                                    {/* Future */}
                                    <div key={`tab-${activeTab}-${lastUpdate}`}>
                                        <ImageCard
                                            src={activeTab === '1yr' ? simulations['1_years'] : simulations['3_years']}
                                            label={activeTab === '1yr' ? 'In 1 Year' : 'In 3 Years'}
                                            sublabel={activeTab === '1yr' ? 'How your dog will look one year from today' : 'How your dog will look three years from today'}
                                            isLoading={activeTab === '1yr' ? !simulations['1_years'] : !simulations['3_years']}
                                        />
                                    </div>
                                </div>

                                {/* Bottom info strip */}
                                <div className="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
                                    {[
                                        { label: 'Breed', value: breed },
                                        { label: 'Simulation', value: 'AI Powered' },
                                        { label: '1 Year', value: simulations['1_years'] ? 'Ready' : 'Generating' },
                                        { label: '3 Years', value: simulations['3_years'] ? 'Ready' : 'Generating' },
                                    ].map((stat, i) => (
                                        <div key={i} className="flex flex-col gap-0.5 rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-white/[.07] dark:bg-[#131720]">
                                            <span className="vs-mono text-[9px] tracking-[.1em] text-slate-500 uppercase dark:text-slate-500">{stat.label}</span>
                                            <span className={`text-[13px] font-bold ${stat.value === 'Ready' ? 'text-emerald-600 dark:text-emerald-400' : stat.value === 'Generating' ? 'text-amber-600 dark:text-amber-400' : 'text-slate-800 dark:text-slate-200'}`}>{stat.value}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                    </div>
                </div>
            </div>
        </>
    );
};

export default ViewSimulation;