import AnalysisLoadingDialog from '@/components/AnalysisLoadingDialog';
import Header from '@/components/header';
import { Link, useForm, usePage } from '@inertiajs/react';
import {
    Activity, Camera, ChevronRight, CircleAlert, Eye,
    History, QrCode, Scan as ScanIcon, Shield,
    Smartphone, SwitchCamera, Target, TrendingUp, Upload,
    Wifi, X, XCircle, Zap, Download,
} from 'lucide-react';
import { ChangeEvent, useEffect, useRef, useState } from 'react';

interface PredictionResult { breed: string; confidence: number; }
interface SuccessFlash { breed: string; confidence: number; top_predictions: PredictionResult[]; message: string; }
interface ErrorFlash { message: string; not_a_dog?: boolean; }
interface PageProps {
    flash?: { success?: SuccessFlash; error?: ErrorFlash; };
    success?: SuccessFlash;
    error?: ErrorFlash;
    [key: string]: any;
}

const Scan = () => {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const videoRef = useRef<HTMLVideoElement>(null);
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const pageProps = usePage<PageProps>().props;
    const success = pageProps.flash?.success ?? pageProps.success ?? undefined;
    const error = pageProps.flash?.error ?? pageProps.error ?? undefined;

    const { data, setData, post, processing, errors, reset } = useForm({ image: null as File | null });
    const [preview, setPreview] = useState<string | null>(null);
    const [fileInfo, setFileInfo] = useState('');
    const [showLoading, setShowLoading] = useState(false);
    const [showCamera, setShowCamera] = useState(false);
    const [stream, setStream] = useState<MediaStream | null>(null);
    const [facingMode, setFacingMode] = useState<'user' | 'environment'>('environment');
    const [cameraError, setCameraError] = useState<string | null>(null);
    const [localError, setLocalError] = useState<ErrorFlash | null>(null);
    const [showLocalError, setShowLocalError] = useState(false);
    const [isDragging, setIsDragging] = useState(false);
    const [showQRModal, setShowQRModal] = useState(false);
    const [particleActive, setParticleActive] = useState(false);
    const [scanPhase, setScanPhase] = useState(0);

    const isCameraSupported = () =>
        !!(navigator.mediaDevices?.getUserMedia) &&
        /chrome|chromium|crios|edg|safari|firefox|fxios/.test(navigator.userAgent.toLowerCase());

    useEffect(() => {
        if (error?.message) {
            setShowLoading(false); setLocalError(error); setShowLocalError(true);
            const t = setTimeout(() => { setShowLocalError(false); setTimeout(() => setLocalError(null), 500); }, 7000);
            return () => clearTimeout(t);
        }
    }, [error]);
    useEffect(() => { if (success) setShowLoading(false); }, [success]);
    useEffect(() => { if (cameraError) { const t = setTimeout(() => setCameraError(null), 5000); return () => clearTimeout(t); } }, [cameraError]);
    useEffect(() => { return () => { if (stream) stream.getTracks().forEach(t => t.stop()); }; }, [stream]);
    useEffect(() => { const t = setInterval(() => setScanPhase(p => (p + 1) % 4), 2200); return () => clearInterval(t); }, []);

    const processImageFile = (file: File) => {
        if (file.size > 10 * 1024 * 1024) { alert('Max 10MB'); return; }
        const url = URL.createObjectURL(file); setPreview(url);
        const img = new Image();
        img.onload = () => { setFileInfo(`${file.name} · ${(file.size / 1024).toFixed(1)}KB · ${img.width}×${img.height}`); URL.revokeObjectURL(url); };
        img.onerror = () => { setFileInfo(`${file.name} · ${(file.size / 1024).toFixed(1)}KB`); URL.revokeObjectURL(url); };
        img.src = url;
        setData('image', file); stopCamera(); setParticleActive(true); setTimeout(() => setParticleActive(false), 900);
    };

    const startCamera = async () => {
        if (!isCameraSupported()) { alert('Camera available on Chrome, Edge, Safari, Firefox.'); return; }
        try {
            setCameraError(null);
            if (stream) { stream.getTracks().forEach(t => t.stop()); setStream(null); }
            const ms = await navigator.mediaDevices.getUserMedia({ video: { facingMode, width: { ideal: 1920 }, height: { ideal: 1080 } }, audio: false });
            setStream(ms);
            setTimeout(() => { if (videoRef.current && ms.active) { videoRef.current.srcObject = ms; videoRef.current.play().catch(console.error); } }, 100);
            setShowCamera(true);
        } catch (e: any) {
            const msgs: Record<string, string> = { NotAllowedError: 'Allow camera permissions.', NotFoundError: 'No camera found.', NotReadableError: 'Camera in use by another app.' };
            setCameraError(`Unable to access camera. ${msgs[e.name] || 'Try file upload instead.'}`); setShowCamera(false);
        }
    };

    const stopCamera = () => {
        if (stream) { stream.getTracks().forEach(t => t.stop()); setStream(null); }
        if (videoRef.current) videoRef.current.srcObject = null;
        setShowCamera(false); setCameraError(null);
    };

    const switchCamera = async () => {
        const nm = facingMode === 'user' ? 'environment' : 'user'; setFacingMode(nm);
        if (stream) { stream.getTracks().forEach(t => t.stop()); setStream(null); }
        setTimeout(async () => {
            try {
                const ms = await navigator.mediaDevices.getUserMedia({ video: { facingMode: nm, width: { ideal: 1920 }, height: { ideal: 1080 } }, audio: false });
                setStream(ms);
                if (videoRef.current && ms.active) { videoRef.current.srcObject = ms; videoRef.current.play().catch(console.error); }
            } catch { setCameraError('Failed to switch camera.'); setFacingMode(facingMode === 'user' ? 'environment' : 'user'); }
        }, 200);
    };

    const capturePhoto = () => {
        if (!videoRef.current || !canvasRef.current) return;
        const v = videoRef.current;
        if (v.readyState !== v.HAVE_ENOUGH_DATA) { alert('Camera still loading.'); return; }
        const c = canvasRef.current; c.width = v.videoWidth; c.height = v.videoHeight;
        c.getContext('2d')?.drawImage(v, 0, 0);
        c.toBlob(blob => { if (blob) processImageFile(new File([blob], `capture-${Date.now()}.jpg`, { type: 'image/jpeg' })); }, 'image/jpeg', 0.95);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault(); if (!data.image) { alert('Select an image first'); return; }
        setShowLoading(true);
        post('/analyze', { forceFormData: true, preserveScroll: false, onError: () => setShowLoading(false) });
    };

    const handleReset = () => {
        if (preview) URL.revokeObjectURL(preview);
        reset(); setPreview(null); setFileInfo(''); setShowLoading(false);
        setCameraError(null); setLocalError(null); setShowLocalError(false); stopCamera();
    };

    useEffect(() => { return () => { if (preview) URL.revokeObjectURL(preview); }; }, [preview]);

    const hudLabels = ['INITIALIZING', 'SCANNING', 'PROCESSING', 'READY'];
    const topBreeds = ['Golden Retriever', 'Labrador', 'German Shepherd', 'Bulldog', 'Poodle'];

    return (
        <>
            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap');

                /* ── Keyframes ── */
                @keyframes scan-beam   { from { top: -4px } to { top: 100% } }
                @keyframes ring-pulse  { 0%{transform:scale(.88);opacity:.7} 70%{transform:scale(1.12);opacity:0} 100%{transform:scale(1.12);opacity:0} }
                @keyframes sweep       { 0%{top:-100%} 100%{top:100%} }
                @keyframes dot-beat    { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(1.4);opacity:.6} }
                @keyframes ticker      { from{opacity:.3} to{opacity:1} }
                @keyframes cam-sweep   { from{top:-4px} to{top:100%} }
                @keyframes cam-pulse   { 0%,100%{transform:translate(-50%,-50%) scale(1)} 50%{transform:translate(-50%,-50%) scale(1.7);opacity:.4} }
                @keyframes bar-grow    { from{width:0} }
                @keyframes slide-in    { from{transform:translateY(-10px);opacity:0} to{transform:translateY(0);opacity:1} }
                @keyframes fade-up     { from{opacity:0;transform:translateY(18px)} to{opacity:1;transform:translateY(0)} }
                @keyframes modal-in    { from{transform:translateY(20px) scale(.97);opacity:0} to{transform:translateY(0) scale(1);opacity:1} }
                @keyframes fab-ring    { 0%{transform:scale(1);opacity:.8} 100%{transform:scale(1.3);opacity:0} }
                @keyframes hud-blink   { 0%,90%,100%{opacity:1} 95%{opacity:.15} }
                @keyframes particle-out{ from{transform:translate(-50%,-50%) scale(1);opacity:1} to{transform:translate(var(--dx),var(--dy)) scale(0);opacity:0} }
                @keyframes grid-fade   { from{opacity:0} to{opacity:1} }

                /* ── Base ── */
                .scan-root { font-family:'Plus Jakarta Sans',sans-serif; }
                .scan-root * { box-sizing:border-box; }
                .mono { font-family:'JetBrains Mono',monospace !important; }

                /* ── Background grid ── */
                .bg-grid {
                    position:fixed;inset:0;pointer-events:none;z-index:0;
                    background-image:
                        linear-gradient(rgba(0,0,0,.04) 1px,transparent 1px),
                        linear-gradient(90deg,rgba(0,0,0,.04) 1px,transparent 1px);
                    background-size:48px 48px;
                    -webkit-mask-image:radial-gradient(ellipse 100% 55% at 50% 0%,black 20%,transparent 100%);
                    mask-image:radial-gradient(ellipse 100% 55% at 50% 0%,black 20%,transparent 100%);
                }
                .dark .bg-grid {
                    background-image:
                        linear-gradient(rgba(255,255,255,.03) 1px,transparent 1px),
                        linear-gradient(90deg,rgba(255,255,255,.03) 1px,transparent 1px);
                }

                /* ── Drop zone scanning animation ── */
                .drop-zone { position:relative; overflow:hidden; }
                .dz-beam {
                    position:absolute;left:0;top:-4px;width:100%;height:3px;
                    background:linear-gradient(90deg,transparent,#10b981,transparent);
                    filter:blur(1px);opacity:0;transition:opacity .3s;pointer-events:none;z-index:2;
                }
                .drop-zone:hover .dz-beam,
                .drop-zone.dragging .dz-beam { opacity:1; animation:scan-beam 1.8s linear infinite; }
                .dz-side {
                    position:absolute;width:2px;top:0;height:100%;
                    background:linear-gradient(180deg,transparent,#10b981,transparent);
                    opacity:0;transition:opacity .4s;pointer-events:none;z-index:2;
                }
                .drop-zone:hover .dz-side,
                .drop-zone.dragging .dz-side { opacity:.35; }

                /* ── Rings on upload icon ── */
                .ring1 { position:absolute;inset:-10px;border-radius:50%;border:1px solid rgba(16,185,129,.18);animation:ring-pulse 2.5s ease-out infinite; }
                .ring2 { position:absolute;inset:-22px;border-radius:50%;border:1px solid rgba(16,185,129,.09);animation:ring-pulse 2.5s ease-out infinite .65s; }

                /* ── Preview scan sweep ── */
                .preview-sweep {
                    position:absolute;left:0;top:-100%;width:100%;height:100%;pointer-events:none;z-index:3;
                    background:linear-gradient(180deg,transparent 0%,rgba(16,185,129,.05) 47%,rgba(16,185,129,.18) 50%,rgba(16,185,129,.05) 53%,transparent 100%);
                    animation:sweep 3s ease-in-out infinite;
                }
                .preview-scanlines {
                    position:absolute;inset:0;pointer-events:none;z-index:2;
                    background:repeating-linear-gradient(0deg,transparent,transparent 2px,rgba(0,0,0,.025) 2px,rgba(0,0,0,.025) 4px);
                }

                /* ── HUD corner brackets ── */
                .hud-corner { position:absolute;width:18px;height:18px;border-color:#10b981;border-style:solid;pointer-events:none;z-index:4; }
                .hud-tl { top:9px;left:9px;border-width:2px 0 0 2px; }
                .hud-tr { top:9px;right:9px;border-width:2px 2px 0 0; }
                .hud-bl { bottom:9px;left:9px;border-width:0 0 2px 2px; }
                .hud-br { bottom:9px;right:9px;border-width:0 2px 2px 0; }

                /* ── Camera overlay ── */
                .cam-sweep {
                    position:absolute;left:0;top:-4px;width:100%;height:4px;
                    background:linear-gradient(90deg,transparent,#10b981,transparent);
                    filter:blur(2px);animation:cam-sweep 2s linear infinite;pointer-events:none;z-index:3;
                }
                .cam-crosshair-h { position:absolute;left:0;right:0;top:50%;height:1px;background:rgba(16,185,129,.4);transform:translateY(-50%); }
                .cam-crosshair-v { position:absolute;top:0;bottom:0;left:50%;width:1px;background:rgba(16,185,129,.4);transform:translateX(-50%); }
                .cam-dot {
                    position:absolute;top:50%;left:50%;width:8px;height:8px;border-radius:50%;
                    background:#10b981;box-shadow:0 0 12px #10b981;
                    animation:cam-pulse 1.5s ease-in-out infinite;
                }

                /* ── Particle burst ── */
                .particle {
                    position:absolute;width:3px;height:3px;border-radius:50%;
                    background:#10b981;box-shadow:0 0 5px #10b981;
                    top:50%;left:50%;animation:particle-out .75s ease-out forwards;
                }

                /* ── Animations ── */
                .animate-fade-up  { animation:fade-up .5s cubic-bezier(.16,1,.3,1) both; }
                .animate-slide-in { animation:slide-in .3s ease both; }
                .anim-delay-1 { animation-delay:.07s; }
                .anim-delay-2 { animation-delay:.14s; }
                .bar-grow { animation:bar-grow 1.6s ease-out forwards; }

                /* ── Button shimmer ── */
                .btn-shimmer { position:relative; overflow:hidden; }
                .btn-shimmer::before {
                    content:'';position:absolute;top:0;left:-100%;width:55%;height:100%;
                    background:linear-gradient(90deg,transparent,rgba(255,255,255,.25),transparent);
                    transform:skewX(-18deg);transition:left .5s;
                }
                .btn-shimmer:hover::before { left:160%; }

                /* ── QR FAB ring ── */
                .fab-ring::before {
                    content:'';position:absolute;inset:-3px;border-radius:14px;
                    border:1.5px solid rgba(16,185,129,.3);animation:fab-ring 2s ease-out infinite;
                }

                /* ── HUD blink ── */
                .hud-blink { animation:hud-blink 3s ease-in-out infinite; }

                /* ── Ticker ── */
                .ticker { animation:ticker 1.2s ease-in-out infinite alternate; }

                /* ── Card top accent ── */
                .card-accent::before {
                    content:'';position:absolute;top:0;left:0;right:0;height:2px;
                    background:linear-gradient(90deg,transparent,#10b981,transparent);
                    opacity:.4;
                }

                /* ── Modal ── */
                .modal-in { animation:modal-in .3s cubic-bezier(.16,1,.3,1) both; }
            `}</style>

            <div className="scan-root min-h-screen bg-slate-50 dark:bg-[#07090C] transition-colors duration-300">
                {/* Background grid */}
                <div className="bg-grid" />

                {/* Ambient glows */}
                <div className="fixed top-[-160px] left-[-80px] w-[500px] h-[300px] rounded-full bg-emerald-500/[.06] blur-[90px] pointer-events-none z-0" />
                <div className="fixed top-[-110px] right-[-60px] w-[380px] h-[240px] rounded-full bg-cyan-500/[.04] blur-[90px] pointer-events-none z-0" />

                <Header />
                <div className="mx-6"><AnalysisLoadingDialog isOpen={showLoading} /></div>

                {/* ── HUD Status Bar ── */}
                <div className="relative z-10 flex items-center gap-0 bg-white/80 dark:bg-[#0D1014]/80 backdrop-blur-sm border-b border-slate-200/60 dark:border-white/[.05] px-5 overflow-x-auto scrollbar-none">
                    {[
                        { l: 'System',   v: 'ONLINE',                    c: '#10b981' },
                        { l: 'Model',    v: 'v3.2.1',                    c: '#06b6d4' },
                        { l: 'Accuracy', v: '98.4%',                     c: '#10b981' },
                        { l: 'Breeds',   v: '120+',                      c: '#06b6d4' },
                        { l: 'Status',   v: hudLabels[scanPhase],        c: '#10b981' },
                    ].map((x, i) => (
                        <div key={i} className="mono flex items-center gap-1.5 px-3 py-2.5 text-[10px] font-semibold tracking-widest uppercase text-slate-400 dark:text-slate-500 border-r border-slate-200/60 dark:border-white/[.05] whitespace-nowrap last:border-none">
                            <span className="w-1.5 h-1.5 rounded-full flex-shrink-0" style={{ background: x.c, boxShadow: `0 0 6px ${x.c}` }} />
                            <span>{x.l}</span>
                            <span style={{ color: x.c }} className="font-semibold">{x.v}</span>
                        </div>
                    ))}
                    <div className="mono ml-auto pl-3 text-[10px] tracking-widest text-slate-300 dark:text-slate-600 whitespace-nowrap ticker">▶ DOGLENS AI</div>
                </div>

                {/* ── QR Modal ── */}
                {showQRModal && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-xl" onClick={() => setShowQRModal(false)}>
                        <div className="modal-in relative bg-white dark:bg-[#131720] border border-slate-200 dark:border-white/[.07] rounded-2xl p-7 w-full max-w-sm shadow-2xl card-accent" onClick={e => e.stopPropagation()}>
                            <button onClick={() => setShowQRModal(false)} className="absolute top-3 right-3 w-7 h-7 flex items-center justify-center rounded-lg bg-slate-100 dark:bg-white/[.05] hover:bg-slate-200 dark:hover:bg-white/10 text-slate-500 dark:text-slate-400 transition-colors">
                                <X size={13} />
                            </button>
                            <div className="text-center mb-5">
                                <div className="w-11 h-11 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center mx-auto mb-3">
                                    <Smartphone size={20} className="text-emerald-500" />
                                </div>
                                <h2 className="text-slate-900 dark:text-slate-100 font-bold text-lg">Install Mobile App</h2>
                                <p className="text-slate-500 dark:text-slate-400 text-xs mt-1">Scan to download the Android app</p>
                            </div>
                            <div className="flex justify-center mb-5">
                                <div className="bg-white rounded-xl p-2 shadow-lg shadow-emerald-500/10">
                                    <img src="/doglens_apk_qr.jpeg" alt="QR" className="w-32 h-32 block" />
                                </div>
                            </div>
                            <div className="rounded-xl border border-slate-200 dark:border-white/[.07] overflow-hidden mb-4 bg-slate-50 dark:bg-white/[.03]">
                                {[{ icon: <Download size={12} />, text: 'Fast & Easy Installation' }, { icon: <Smartphone size={12} />, text: 'Available on Android' }, { icon: <Camera size={12} />, text: 'All Features On-The-Go' }].map((f, i) => (
                                    <div key={i} className="flex items-center gap-2.5 px-3.5 py-2.5 border-b border-slate-200 dark:border-white/[.05] last:border-none text-slate-600 dark:text-slate-400 text-sm">
                                        <div className="w-6 h-6 rounded-md bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-500 flex-shrink-0">{f.icon}</div>
                                        {f.text}
                                    </div>
                                ))}
                            </div>
                            <button onClick={() => setShowQRModal(false)} className="w-full py-3 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-black font-bold text-sm transition-colors">Close</button>
                        </div>
                    </div>
                )}

                {/* ── FAB ── */}
                <button onClick={() => setShowQRModal(true)} className="fab-ring fixed bottom-6 right-6 z-40 w-12 h-12 rounded-[13px] bg-emerald-500 hover:bg-emerald-400 text-black flex items-center justify-center shadow-lg shadow-emerald-500/30 transition-all hover:scale-105 hover:-translate-y-0.5">
                    <QrCode size={18} />
                </button>

                {/* ── Main Layout ── */}
                <div className="relative z-10 max-w-6xl mx-auto px-4 py-6 pb-20 grid grid-cols-1 lg:grid-cols-[220px_1fr_220px] gap-5">

                    {/* ─── LEFT SIDEBAR ─── */}
                    <div className="hidden lg:flex flex-col gap-4 animate-fade-up">

                        {/* Navigation */}
                        <div className="card-accent relative bg-white dark:bg-[#131720] border border-slate-200 dark:border-white/[.07] rounded-2xl overflow-hidden shadow-sm dark:shadow-none">
                            <div className="flex items-center gap-2 px-3.5 py-2.5 bg-slate-50 dark:bg-white/[.03] border-b border-slate-200 dark:border-white/[.06]">
                                <div className="w-6 h-6 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-500 flex-shrink-0">
                                    <ScanIcon size={12} />
                                </div>
                                <span className="mono text-[10px] font-bold tracking-widest uppercase text-slate-400 dark:text-slate-500">Navigation</span>
                            </div>
                            <div className="p-3 flex flex-col gap-1.5">
                                {[
                                    { icon: <ScanIcon size={13} />, label: 'New Scan', href: '/scan', active: true },
                                    { icon: <History size={13} />, label: 'Scan History', href: '/scanhistory', active: false },
                                ].map((n, i) => (
                                    <Link key={i} href={n.href} className={`flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all no-underline
                                        ${n.active
                                            ? 'bg-emerald-500/10 dark:bg-emerald-500/10 border border-emerald-500/25 text-emerald-600 dark:text-emerald-400'
                                            : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-white/[.05] hover:text-slate-700 dark:hover:text-slate-200 border border-transparent'
                                        }`}>
                                        {n.icon}
                                        <span>{n.label}</span>
                                        {n.active && <ChevronRight size={12} className="ml-auto opacity-40" />}
                                    </Link>
                                ))}
                            </div>
                        </div>

                        {/* Top Breeds */}
                        <div className="card-accent relative bg-white dark:bg-[#131720] border border-slate-200 dark:border-white/[.07] rounded-2xl overflow-hidden shadow-sm dark:shadow-none">
                            <div className="flex items-center gap-2 px-3.5 py-2.5 bg-slate-50 dark:bg-white/[.03] border-b border-slate-200 dark:border-white/[.06]">
                                <div className="w-6 h-6 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-500 flex-shrink-0">
                                    <TrendingUp size={12} />
                                </div>
                                <span className="mono text-[10px] font-bold tracking-widest uppercase text-slate-400 dark:text-slate-500">Top Breeds</span>
                            </div>
                            <div className="p-3 flex flex-col gap-0.5">
                                {topBreeds.map((b, i) => (
                                    <div key={i} className="flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-slate-50 dark:hover:bg-white/[.03] transition-colors cursor-default">
                                        <span className="mono text-[9px] text-slate-300 dark:text-slate-600 w-4 flex-shrink-0">#{i + 1}</span>
                                        <span className="text-xs text-slate-600 dark:text-slate-400 font-medium flex-1">{b}</span>
                                        <div className="w-10 h-1 bg-slate-100 dark:bg-white/[.06] rounded-full overflow-hidden flex-shrink-0">
                                            <div className="h-full bg-emerald-500/50 rounded-full bar-grow" style={{ width: `${100 - i * 15}%`, animationDelay: `${i * .1}s` }} />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Global Stats */}
                        <div className="card-accent relative bg-white dark:bg-[#131720] border border-slate-200 dark:border-white/[.07] rounded-2xl overflow-hidden shadow-sm dark:shadow-none">
                            <div className="flex items-center gap-2 px-3.5 py-2.5 bg-slate-50 dark:bg-white/[.03] border-b border-slate-200 dark:border-white/[.06]">
                                <div className="w-6 h-6 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-500 flex-shrink-0">
                                    <Activity size={12} />
                                </div>
                                <span className="mono text-[10px] font-bold tracking-widest uppercase text-slate-400 dark:text-slate-500">Global Stats</span>
                            </div>
                            <div className="p-3 flex flex-col gap-1.5">
                                {[
                                    { l: 'Total Scans', v: '12,841', icon: <Target size={11} /> },
                                    { l: 'Verified',    v: '10,290', icon: <Shield size={11} /> },
                                    { l: 'Avg Score',   v: '94.2%',  icon: <Activity size={11} /> },
                                    { l: 'Uptime',      v: '99.9%',  icon: <Wifi size={11} /> },
                                ].map((s, i) => (
                                    <div key={i} className="flex items-center justify-between px-2.5 py-2 rounded-lg bg-slate-50 dark:bg-white/[.03] border border-slate-100 dark:border-white/[.04] hover:border-emerald-500/20 hover:bg-emerald-500/[.03] transition-all cursor-default">
                                        <div className="flex items-center gap-2">
                                            <span className="text-slate-300 dark:text-slate-600">{s.icon}</span>
                                            <span className="mono text-[9px] font-semibold tracking-wider uppercase text-slate-400 dark:text-slate-500">{s.l}</span>
                                        </div>
                                        <span className="mono text-xs font-bold text-slate-700 dark:text-slate-300">{s.v}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* ─── CENTER ─── */}
                    <div className="flex flex-col gap-5 animate-fade-up anim-delay-1">

                        {/* Page Header */}
                        <div className="flex items-start justify-between gap-3 flex-wrap">
                            <div>
                                <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 mb-2">
                                    <span className="w-2 h-2 rounded-full bg-emerald-500 shadow-[0_0_8px_#10b981]" style={{ animation: 'dot-beat 2s ease-in-out infinite' }} />
                                    <span className="mono text-[10px] font-bold tracking-widest uppercase text-emerald-600 dark:text-emerald-400">AI Breed Detection</span>
                                </div>
                                <h1 className="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-slate-50 tracking-tight leading-tight">Scan Your Dog</h1>
                                <p className="text-slate-500 dark:text-slate-400 text-sm mt-1.5 leading-relaxed">Upload a photo or use your camera to identify your dog's breed.</p>
                            </div>
                            <Link href="/scanhistory" className="flex-shrink-0 inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-white dark:bg-[#131720] border border-slate-200 dark:border-white/[.07] text-slate-500 dark:text-slate-400 text-sm font-semibold hover:border-emerald-500/30 hover:text-emerald-600 dark:hover:text-emerald-400 hover:bg-emerald-500/[.03] transition-all no-underline">
                                <History size={14} />
                                Scan History
                                <ChevronRight size={12} className="opacity-40" />
                            </Link>
                        </div>

                        {/* Alerts */}
                        {localError && showLocalError && (
                            <div className="animate-slide-in bg-red-50 dark:bg-red-500/[.07] border border-red-200 dark:border-red-500/20 rounded-2xl p-4">
                                <div className="flex gap-3 items-start">
                                    <XCircle size={16} className="text-red-500 flex-shrink-0 mt-0.5" />
                                    <div className="flex-1">
                                        <p className="text-red-600 dark:text-red-400 font-bold text-sm">{localError.not_a_dog ? 'Not a Dog Detected' : 'Analysis Error'}</p>
                                        <p className="text-red-500/80 text-xs mt-1 leading-relaxed">{localError.message}</p>
                                        {localError.not_a_dog && (
                                            <button onClick={handleReset} className="mt-3 inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-red-500 hover:bg-red-400 text-white font-semibold text-xs rounded-lg transition-colors">
                                                Try Another Image
                                            </button>
                                        )}
                                    </div>
                                </div>
                            </div>
                        )}
                        {cameraError && (
                            <div className="bg-amber-50 dark:bg-amber-500/[.06] border border-amber-200 dark:border-amber-500/20 rounded-2xl p-4">
                                <div className="flex gap-3 items-start">
                                    <CircleAlert size={16} className="text-amber-500 flex-shrink-0 mt-0.5" />
                                    <div>
                                        <p className="text-amber-600 dark:text-amber-400 font-bold text-sm">Camera Error</p>
                                        <p className="text-amber-500/80 text-xs mt-1">{cameraError}</p>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Main Scan Card */}
                        <div className="card-accent relative bg-white dark:bg-[#131720] border border-slate-200 dark:border-white/[.07] rounded-2xl overflow-hidden shadow-sm dark:shadow-none">
                            {/* Terminal bar */}
                            <div className="flex items-center gap-3 px-4 py-3 bg-slate-50 dark:bg-[#0D1014] border-b border-slate-200 dark:border-white/[.06]">
                                <div className="flex gap-1.5">
                                    <div className="w-2.5 h-2.5 rounded-full bg-[#FF5F57]" />
                                    <div className="w-2.5 h-2.5 rounded-full bg-[#FEBC2E]" />
                                    <div className="w-2.5 h-2.5 rounded-full bg-emerald-500 shadow-[0_0_6px_#10b981] hud-blink" />
                                </div>
                                <span className="mono text-[11px] text-slate-400 dark:text-slate-500 ml-1">doglens://scan</span>
                                <div className="ml-auto mono flex items-center gap-1.5 text-[10px] text-emerald-500 dark:text-emerald-400">
                                    <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 shadow-[0_0_5px_#10b981]" style={{ animation: 'dot-beat 2s infinite' }} />
                                    <span>{processing ? 'PROCESSING' : preview ? 'IMAGE LOADED' : showCamera ? 'CAMERA ACTIVE' : 'AWAITING INPUT'}</span>
                                </div>
                            </div>

                            <form onSubmit={handleSubmit} className="p-5">
                                {!preview && !showCamera ? (
                                    <>
                                        {/* Drop zone */}
                                        <div
                                            className={`drop-zone cursor-pointer border-2 border-dashed rounded-2xl min-h-[220px] flex flex-col items-center justify-center gap-5 transition-all px-5 py-11 ${isDragging ? 'dragging border-emerald-500 bg-emerald-500/[.04]' : 'border-slate-200 dark:border-white/10 hover:border-emerald-400 dark:hover:border-emerald-500/50 hover:bg-emerald-500/[.02]'}`}
                                            onClick={() => fileInputRef.current?.click()}
                                            onDragOver={e => { e.preventDefault(); setIsDragging(true); }}
                                            onDragLeave={() => setIsDragging(false)}
                                            onDrop={e => { e.preventDefault(); setIsDragging(false); const f = e.dataTransfer.files?.[0]; if (f) processImageFile(f); }}
                                        >
                                            {/* Animated beams */}
                                            <div className="dz-beam" />
                                            <div className="dz-side" style={{ left: 0 }} />
                                            <div className="dz-side" style={{ right: 0 }} />

                                            <input ref={fileInputRef} type="file" accept="image/*" className="hidden" onChange={(e: ChangeEvent<HTMLInputElement>) => { const f = e.target.files?.[0]; if (f) processImageFile(f); }} />

                                            {/* Upload icon with rings */}
                                            <div className="relative w-20 h-20 flex items-center justify-center">
                                                <div className="ring1" />
                                                <div className="ring2" />
                                                <div className="relative w-full h-full rounded-full bg-emerald-500/10 border-2 border-emerald-500/25 flex items-center justify-center">
                                                    <Upload size={26} className="text-emerald-500" />
                                                </div>
                                            </div>

                                            <div className="text-center">
                                                <p className="text-slate-800 dark:text-slate-200 font-bold text-base">Drop your dog image here</p>
                                                <p className="text-slate-400 dark:text-slate-500 text-sm mt-1">or <span className="text-emerald-600 dark:text-emerald-400 font-bold">click to browse</span></p>
                                            </div>
                                            <p className="mono text-[10px] tracking-widest text-slate-300 dark:text-slate-600">ALL FORMATS · MAX 10MB</p>
                                        </div>

                                        {/* Divider */}
                                        <div className="flex items-center gap-3 my-4">
                                            <div className="flex-1 h-px bg-slate-200 dark:bg-white/[.06]" />
                                            <span className="mono text-[10px] tracking-widest text-slate-300 dark:text-slate-600 uppercase font-bold">or use camera</span>
                                            <div className="flex-1 h-px bg-slate-200 dark:bg-white/[.06]" />
                                        </div>

                                        <button type="button" onClick={startCamera}
                                            className="w-full flex items-center justify-center gap-2.5 px-5 py-3 rounded-xl bg-slate-50 dark:bg-white/[.04] border border-slate-200 dark:border-emerald-500/20 text-emerald-600 dark:text-emerald-400 font-bold text-sm hover:bg-emerald-500/[.05] hover:border-emerald-500/40 transition-all">
                                            <Camera size={16} /> Activate Camera
                                        </button>
                                        <p className="mono text-center text-[9px] tracking-widest text-slate-300 dark:text-slate-600 mt-2">CHROME · EDGE · SAFARI · FIREFOX</p>

                                        {errors.image && <p className="text-red-500 text-xs mt-3 text-center">{errors.image}</p>}

                                        {/* Tips */}
                                        <div className="mt-5 rounded-xl bg-emerald-500/[.03] border border-emerald-500/15 p-4">
                                            <div className="flex items-center gap-2 mb-3">
                                                <Zap size={13} className="text-emerald-500" />
                                                <span className="mono text-[10px] font-bold tracking-widest uppercase text-emerald-600 dark:text-emerald-400">Capture Tips</span>
                                                <Shield size={11} className="text-slate-300 dark:text-slate-600 ml-auto" />
                                            </div>
                                            {[
                                                'Ensure your dog is clearly visible',
                                                'Use good lighting, no harsh shadows',
                                                'Center your dog, avoid clutter',
                                                'Front or side angles work best',
                                                'Only dog images are accepted',
                                            ].map((tip, i) => (
                                                <div key={i} className={`flex items-start gap-2.5 py-1.5 ${i < 4 ? 'border-b border-slate-100 dark:border-white/[.04]' : ''}`}>
                                                    <span className="mono text-[9px] text-emerald-500/60 flex-shrink-0 mt-0.5 w-4">0{i + 1}</span>
                                                    <span className={`text-xs leading-relaxed ${i === 4 ? 'text-slate-700 dark:text-slate-200 font-semibold' : 'text-slate-500 dark:text-slate-400'}`}>{tip}</span>
                                                </div>
                                            ))}
                                        </div>
                                    </>
                                ) : showCamera ? (
                                    <div>
                                        {/* Camera view */}
                                        <div className="relative rounded-2xl overflow-hidden border border-emerald-500/25 shadow-lg shadow-emerald-500/5">
                                            <video ref={videoRef} autoPlay playsInline muted className="block w-full" style={{ maxHeight: '60vh', objectFit: 'cover', background: '#000' }} />
                                            <canvas ref={canvasRef} className="hidden" />
                                            <div className="absolute inset-0 pointer-events-none z-[3]">
                                                <div className="cam-sweep" />
                                                {/* HUD corners */}
                                                {['tl','tr','bl','br'].map(p => <div key={p} className={`hud-corner hud-${p}`} />)}
                                                {/* Crosshair */}
                                                <div className="absolute top-1/2 left-1/2 w-16 h-16 -translate-x-1/2 -translate-y-1/2">
                                                    <div className="cam-crosshair-h" />
                                                    <div className="cam-crosshair-v" />
                                                    <div className="cam-dot" />
                                                </div>
                                                <div className="mono absolute bottom-3 left-3 text-[9px] font-semibold text-emerald-400 tracking-widest bg-black/60 backdrop-blur-sm border border-emerald-500/20 rounded px-2 py-0.5 uppercase" style={{ animation: 'ticker 1.5s ease-in-out infinite alternate' }}>
                                                    ● REC · {facingMode === 'environment' ? 'REAR' : 'FRONT'} CAM
                                                </div>
                                            </div>
                                            <button type="button" onClick={switchCamera} className="absolute top-3 right-3 z-10 w-10 h-10 rounded-xl bg-black/60 backdrop-blur-sm border border-white/10 text-white hover:bg-emerald-500/20 hover:border-emerald-500/40 flex items-center justify-center transition-all">
                                                <SwitchCamera size={16} />
                                            </button>
                                        </div>
                                        <div className="flex gap-3 mt-4">
                                            <button type="button" onClick={capturePhoto}
                                                className="btn-shimmer flex-1 flex items-center justify-center gap-2 py-3 rounded-xl bg-gradient-to-r from-emerald-500 to-cyan-500 text-black font-bold text-sm shadow-lg shadow-emerald-500/20 hover:shadow-emerald-500/30 hover:-translate-y-0.5 transition-all">
                                                <ScanIcon size={16} /> Capture & Scan
                                            </button>
                                            <button type="button" onClick={stopCamera} className="flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-slate-100 dark:bg-white/[.05] border border-slate-200 dark:border-white/[.08] text-slate-500 dark:text-slate-400 font-semibold text-sm hover:bg-slate-200 dark:hover:bg-white/10 transition-all">
                                                <X size={14} /> Cancel
                                            </button>
                                        </div>
                                    </div>
                                ) : (
                                    <div>
                                        {/* Image preview */}
                                        <div className="relative rounded-2xl overflow-hidden border border-emerald-500/25 shadow-lg shadow-emerald-500/[.06]">
                                            {/* Particle burst */}
                                            {particleActive && (
                                                <div className="absolute inset-0 pointer-events-none overflow-hidden z-10">
                                                    {[...Array(12)].map((_, i) => {
                                                        const a = (i / 12) * 360; const d = 65 + Math.random() * 55;
                                                        return <div key={i} className="particle" style={{ '--dx': `${Math.cos(a * Math.PI / 180) * d}px`, '--dy': `${Math.sin(a * Math.PI / 180) * d}px` } as any} />;
                                                    })}
                                                </div>
                                            )}
                                            <img src={preview || ''} alt="Preview" className="block w-full object-contain bg-slate-100 dark:bg-[#0D1014]" style={{ maxHeight: 380 }} />
                                            <div className="absolute inset-0 pointer-events-none">
                                                <div className="preview-scanlines" />
                                                <div className="preview-sweep" />
                                                {['tl','tr','bl','br'].map(p => <div key={p} className={`hud-corner hud-${p}`} style={{ opacity: 1 }} />)}
                                                <div className="absolute bottom-2.5 left-2.5 right-2.5 flex items-center justify-between z-[5]">
                                                    <span className="mono text-[9px] font-semibold text-emerald-400 tracking-wider bg-black/70 backdrop-blur-sm border border-emerald-500/20 rounded px-2 py-0.5">IMAGE LOADED</span>
                                                    {fileInfo && <span className="mono text-[8px] text-emerald-400 bg-black/70 backdrop-blur-sm border border-emerald-500/15 rounded px-2 py-0.5">{fileInfo.split('·').slice(1).join('·').trim()}</span>}
                                                </div>
                                            </div>
                                        </div>
                                        {fileInfo && <p className="mono text-center text-[10px] text-slate-300 dark:text-slate-600 mt-2 tracking-wide">{fileInfo}</p>}
                                        <div className="flex gap-3 mt-4">
                                            <button type="submit" disabled={processing}
                                                className="btn-shimmer flex-1 flex items-center justify-center gap-2 py-3 rounded-xl bg-emerald-500 hover:bg-emerald-400 disabled:opacity-40 disabled:cursor-not-allowed text-black font-bold text-sm shadow-lg shadow-emerald-500/20 hover:shadow-emerald-500/30 hover:-translate-y-0.5 transition-all">
                                                <ScanIcon size={16} /> {processing ? 'Analyzing...' : 'Analyze Image'}
                                            </button>
                                            <button type="button" onClick={handleReset} disabled={processing}
                                                className="flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-slate-100 dark:bg-white/[.05] border border-slate-200 dark:border-white/[.08] text-slate-500 dark:text-slate-400 font-semibold text-sm hover:bg-slate-200 dark:hover:bg-white/10 disabled:opacity-40 transition-all">
                                                <X size={14} /> Reset
                                            </button>
                                        </div>
                                    </div>
                                )}
                            </form>
                        </div>
                    </div>

                    {/* ─── RIGHT SIDEBAR ─── */}
                    <div className="hidden lg:flex flex-col gap-4 animate-fade-up anim-delay-2">

                        {/* How It Works */}
                        <div className="card-accent relative bg-white dark:bg-[#131720] border border-slate-200 dark:border-white/[.07] rounded-2xl overflow-hidden shadow-sm dark:shadow-none">
                            <div className="flex items-center gap-2 px-3.5 py-2.5 bg-slate-50 dark:bg-white/[.03] border-b border-slate-200 dark:border-white/[.06]">
                                <div className="w-6 h-6 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-500 flex-shrink-0">
                                    <Eye size={12} />
                                </div>
                                <span className="mono text-[10px] font-bold tracking-widest uppercase text-slate-400 dark:text-slate-500">How It Works</span>
                            </div>
                            <div className="p-3.5 flex flex-col">
                                {[
                                    { n: '01', t: 'Upload or capture a photo' },
                                    { n: '02', t: 'AI analyzes breed features in ~1.2s' },
                                    { n: '03', t: 'Results ranked by confidence' },
                                    { n: '04', t: 'Vet verification adds accuracy' },
                                ].map((s, i) => (
                                    <div key={i} className={`flex items-start gap-2.5 py-2.5 ${i < 3 ? 'border-b border-slate-100 dark:border-white/[.04]' : ''}`}>
                                        <span className="mono text-[10px] text-emerald-500/70 flex-shrink-0 mt-0.5 w-5 font-semibold">{s.n}</span>
                                        <span className="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">{s.t}</span>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Live Status */}
                        <div className="card-accent relative bg-white dark:bg-[#131720] border border-slate-200 dark:border-white/[.07] rounded-2xl overflow-hidden shadow-sm dark:shadow-none">
                            <div className="flex items-center gap-2 px-3.5 py-2.5 bg-slate-50 dark:bg-white/[.03] border-b border-slate-200 dark:border-white/[.06]">
                                <div className="w-6 h-6 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-500 flex-shrink-0">
                                    <Activity size={12} />
                                </div>
                                <span className="mono text-[10px] font-bold tracking-widest uppercase text-slate-400 dark:text-slate-500">System Status</span>
                            </div>
                            <div className="p-3.5 flex flex-col gap-3">
                                <div className="flex items-center gap-2 px-3 py-2 rounded-xl bg-emerald-500/[.06] border border-emerald-500/20">
                                    <span className="w-2 h-2 rounded-full bg-emerald-500 shadow-[0_0_8px_#10b981] flex-shrink-0" style={{ animation: 'dot-beat 2s infinite' }} />
                                    <span className="mono text-[10px] font-bold tracking-widest uppercase text-emerald-500 dark:text-emerald-400">Engine Online</span>
                                </div>
                                {[
                                    { l: 'Accuracy', v: '98.4%', b: 98 },
                                    { l: 'Latency',  v: '~1.2s', b: 72 },
                                    { l: 'Model',    v: 'v3.2.1', b: 100 },
                                ].map((m, i) => (
                                    <div key={i}>
                                        <div className="flex justify-between mb-1.5">
                                            <span className="mono text-[9px] font-semibold tracking-wider uppercase text-slate-400 dark:text-slate-500">{m.l}</span>
                                            <span className="mono text-[11px] font-bold text-emerald-500 dark:text-emerald-400">{m.v}</span>
                                        </div>
                                        <div className="h-1 bg-slate-100 dark:bg-white/[.06] rounded-full overflow-hidden">
                                            <div className="h-full bg-gradient-to-r from-emerald-500 to-cyan-500 rounded-full shadow-[0_0_5px_#10b981] bar-grow" style={{ width: `${m.b}%`, animationDelay: `${i * .15}s` }} />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Quick tips summary */}
                        <div className="card-accent relative bg-gradient-to-br from-emerald-500/[.04] to-cyan-500/[.03] dark:from-emerald-500/[.06] dark:to-cyan-500/[.03] border border-emerald-500/15 dark:border-emerald-500/10 rounded-2xl overflow-hidden shadow-sm dark:shadow-none p-4">
                            <div className="flex items-center gap-2 mb-3">
                                <Shield size={13} className="text-emerald-500" />
                                <span className="text-xs font-bold text-emerald-700 dark:text-emerald-400">Best Results</span>
                            </div>
                            <p className="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">For the most accurate breed identification, use a clear front or side photo of your dog in good lighting.</p>
                            <div className="mt-3 flex items-center gap-1.5">
                                <div className="w-2 h-2 rounded-full bg-emerald-500/50" />
                                <span className="mono text-[9px] tracking-wider text-emerald-600/60 dark:text-emerald-500/40 uppercase font-semibold">Only dogs accepted</span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </>
    );
};

export default Scan;