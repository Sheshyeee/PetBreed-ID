import AnalysisLoadingDialog from '@/components/AnalysisLoadingDialog';
import Header from '@/components/header';
import { Link, useForm, usePage } from '@inertiajs/react';
import {
    Activity,
    Camera,
    ChevronRight,
    CircleAlert,
    Download,
    Eye,
    History,
    QrCode,
    Scan as ScanIcon,
    Shield,
    Smartphone,
    SwitchCamera,
    Target,
    TrendingUp,
    Upload,
    Wifi,
    X,
    XCircle,
    Zap,
} from 'lucide-react';
import { ChangeEvent, useEffect, useRef, useState } from 'react';

interface PredictionResult {
    breed: string;
    confidence: number;
}
interface SuccessFlash {
    breed: string;
    confidence: number;
    top_predictions: PredictionResult[];
    message: string;
}
interface ErrorFlash {
    message: string;
    not_a_dog?: boolean;
}
interface TopBreed {
    breed: string;
    scan_count: number;
    avg_confidence: number;
    bar_width: number;
}
interface GlobalStats {
    total_scans: string;
    verified: string;
    avg_score: string;
    uptime: string;
}
interface PageProps {
    flash?: { success?: SuccessFlash; error?: ErrorFlash };
    success?: SuccessFlash;
    error?: ErrorFlash;
    topBreeds?: TopBreed[];
    globalStats?: GlobalStats;
    [key: string]: any;
}

export default function Scan() {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const videoRef = useRef<HTMLVideoElement>(null);
    const canvasRef = useRef<HTMLCanvasElement>(null);

    const pageProps = usePage<PageProps>().props;
    const success = pageProps.flash?.success ?? pageProps.success;
    const error = pageProps.flash?.error ?? pageProps.error;
    const topBreeds = pageProps.topBreeds ?? [];
    const globalStats: GlobalStats = pageProps.globalStats ?? {
        total_scans: '—',
        verified: '—',
        avg_score: '—',
        uptime: '99.9%',
    };

    const { data, setData, post, processing, errors, reset } = useForm({
        image: null as File | null,
    });
    const [preview, setPreview] = useState<string | null>(null);
    const [fileInfo, setFileInfo] = useState('');
    const [showLoading, setShowLoading] = useState(false);
    const [showCamera, setShowCamera] = useState(false);
    const [stream, setStream] = useState<MediaStream | null>(null);
    const [facingMode, setFacingMode] = useState<'user' | 'environment'>(
        'environment',
    );
    const [cameraError, setCameraError] = useState<string | null>(null);
    const [localError, setLocalError] = useState<ErrorFlash | null>(null);
    const [showLocalError, setShowLocalError] = useState(false);
    const [isDragging, setIsDragging] = useState(false);
    const [showQRModal, setShowQRModal] = useState(false);
    const [particleActive, setParticleActive] = useState(false);

    const isCameraOk = () =>
        !!navigator.mediaDevices?.getUserMedia &&
        /chrome|chromium|crios|edg|safari|firefox|fxios/.test(
            navigator.userAgent.toLowerCase(),
        );

    useEffect(() => {
        if (error?.message) {
            setShowLoading(false);
            setLocalError(error);
            setShowLocalError(true);
            const t = setTimeout(() => {
                setShowLocalError(false);
                setTimeout(() => setLocalError(null), 500);
            }, 7000);
            return () => clearTimeout(t);
        }
    }, [error]);

    useEffect(() => {
        if (success) setShowLoading(false);
    }, [success]);
    useEffect(() => {
        if (cameraError) {
            const t = setTimeout(() => setCameraError(null), 5000);
            return () => clearTimeout(t);
        }
    }, [cameraError]);
    useEffect(() => {
        return () => {
            if (stream) stream.getTracks().forEach((t) => t.stop());
        };
    }, [stream]);
    useEffect(() => {
        return () => {
            if (preview) URL.revokeObjectURL(preview);
        };
    }, [preview]);

    const processFile = (file: File) => {
        if (file.size > 10 * 1024 * 1024) {
            alert('Max 10MB');
            return;
        }
        const url = URL.createObjectURL(file);
        setPreview(url);
        const img = new Image();
        img.onload = () => {
            setFileInfo(
                `${file.name} · ${(file.size / 1024).toFixed(1)}KB · ${img.width}×${img.height}`,
            );
            URL.revokeObjectURL(url);
        };
        img.onerror = () => {
            setFileInfo(`${file.name} · ${(file.size / 1024).toFixed(1)}KB`);
            URL.revokeObjectURL(url);
        };
        img.src = url;
        setData('image', file);
        stopCamera();
        setParticleActive(true);
        setTimeout(() => setParticleActive(false), 900);
    };

    const startCamera = async () => {
        if (!isCameraOk()) {
            alert('Camera available on Chrome, Edge, Safari, Firefox.');
            return;
        }
        try {
            setCameraError(null);
            if (stream) {
                stream.getTracks().forEach((t) => t.stop());
                setStream(null);
            }
            const ms = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode,
                    width: { ideal: 1920 },
                    height: { ideal: 1080 },
                },
                audio: false,
            });
            setStream(ms);
            setTimeout(() => {
                if (videoRef.current && ms.active) {
                    videoRef.current.srcObject = ms;
                    videoRef.current.play().catch(console.error);
                }
            }, 100);
            setShowCamera(true);
        } catch (e: any) {
            const msgs: Record<string, string> = {
                NotAllowedError: 'Allow camera permissions.',
                NotFoundError: 'No camera found.',
                NotReadableError: 'Camera in use.',
            };
            setCameraError(
                `Unable to access camera. ${msgs[e.name] || 'Try file upload instead.'}`,
            );
            setShowCamera(false);
        }
    };

    const stopCamera = () => {
        if (stream) {
            stream.getTracks().forEach((t) => t.stop());
            setStream(null);
        }
        if (videoRef.current) videoRef.current.srcObject = null;
        setShowCamera(false);
        setCameraError(null);
    };

    const switchCamera = async () => {
        const nm = facingMode === 'user' ? 'environment' : 'user';
        setFacingMode(nm);
        if (stream) {
            stream.getTracks().forEach((t) => t.stop());
            setStream(null);
        }
        setTimeout(async () => {
            try {
                const ms = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: nm,
                        width: { ideal: 1920 },
                        height: { ideal: 1080 },
                    },
                    audio: false,
                });
                setStream(ms);
                if (videoRef.current && ms.active) {
                    videoRef.current.srcObject = ms;
                    videoRef.current.play().catch(console.error);
                }
            } catch {
                setCameraError('Failed to switch camera.');
                setFacingMode((f) => (f === 'user' ? 'environment' : 'user'));
            }
        }, 200);
    };

    const capturePhoto = () => {
        if (!videoRef.current || !canvasRef.current) return;
        const v = videoRef.current;
        if (v.readyState !== v.HAVE_ENOUGH_DATA) {
            alert('Camera still loading.');
            return;
        }
        const c = canvasRef.current;
        c.width = v.videoWidth;
        c.height = v.videoHeight;
        c.getContext('2d')?.drawImage(v, 0, 0);
        c.toBlob(
            (b) => {
                if (b)
                    processFile(
                        new File([b], `capture-${Date.now()}.jpg`, {
                            type: 'image/jpeg',
                        }),
                    );
            },
            'image/jpeg',
            0.95,
        );
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!data.image) {
            alert('Select an image first');
            return;
        }
        setShowLoading(true);
        post('/analyze', {
            forceFormData: true,
            preserveScroll: false,
            onError: () => setShowLoading(false),
        });
    };

    const handleReset = () => {
        if (preview) URL.revokeObjectURL(preview);
        reset();
        setPreview(null);
        setFileInfo('');
        setShowLoading(false);
        setCameraError(null);
        setLocalError(null);
        setShowLocalError(false);
        stopCamera();
    };

    const captureTips = [
        { text: 'Dog should be clearly visible and centred' },
        { text: 'Good lighting, avoid harsh shadows' },
        { text: 'Front or side angles work best' },
        { text: 'Plain or simple backgrounds preferred' },
        { text: 'Only dog images are accepted' },
    ];

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
                {/* FIXED: was text-slate-400 → text-slate-600 */}
                <span className="font-mono text-[10px] font-bold tracking-[.12em] text-slate-600 uppercase dark:text-slate-400">
                    {title}
                </span>
            </div>
            {children}
        </div>
    );

    const TopBreedsContent = () => (
        <div className="flex flex-col gap-0.5 p-2.5">
            {topBreeds.length === 0 ? (
                /* FIXED: was text-slate-300 → text-slate-400 */
                <p className="py-3 text-center font-mono text-[10px] text-slate-400 dark:text-slate-600">
                    No scan data yet
                </p>
            ) : (
                topBreeds.map((b, i) => (
                    <div
                        key={i}
                        className="group flex cursor-default items-center gap-2.5 rounded-lg px-2 py-1.5 transition-colors hover:bg-slate-50 dark:hover:bg-white/[.03]"
                    >
                        {/* FIXED: was text-slate-300 → text-slate-500 */}
                        <span className="w-4 flex-shrink-0 font-mono text-[9px] text-slate-500 group-hover:text-emerald-500/70 dark:text-slate-600">
                            #{i + 1}
                        </span>
                        {/* FIXED: was text-slate-600 → text-slate-700 */}
                        <span
                            className="flex-1 truncate text-[12px] font-medium text-slate-700 dark:text-slate-300"
                            title={b.breed}
                        >
                            {b.breed}
                        </span>
                        <div className="h-[3px] w-10 flex-shrink-0 overflow-hidden rounded-full bg-slate-200 dark:bg-white/[.07]">
                            <div
                                className="h-full rounded-full bg-emerald-500/60"
                                style={{ width: `${b.bar_width}%` }}
                            />
                        </div>
                    </div>
                ))
            )}
        </div>
    );

    const GlobalStatsContent = () => (
        <div className="flex flex-col gap-1.5 p-2.5">
            {[
                {
                    l: 'Total Scans',
                    v: globalStats.total_scans,
                    icon: <Target size={10} />,
                },
                {
                    l: 'Verified',
                    v: globalStats.verified,
                    icon: <Shield size={10} />,
                },
                {
                    l: 'Avg Score',
                    v: globalStats.avg_score,
                    icon: <Activity size={10} />,
                },
                {
                    l: 'Uptime',
                    v: globalStats.uptime,
                    icon: <Wifi size={10} />,
                },
            ].map((s, i) => (
                <div
                    key={i}
                    className="flex cursor-default items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-2.5 py-2 transition-all hover:border-emerald-500/25 hover:bg-emerald-500/[.025] dark:border-white/[.04] dark:bg-white/[.03]"
                >
                    <div className="flex items-center gap-2">
                        {/* FIXED: was text-slate-300 → text-slate-500 */}
                        <span className="text-slate-500 dark:text-slate-600">
                            {s.icon}
                        </span>
                        {/* FIXED: was text-slate-400 → text-slate-600 */}
                        <span className="font-mono text-[9px] font-medium tracking-[.1em] text-slate-600 uppercase dark:text-slate-500">
                            {s.l}
                        </span>
                    </div>
                    {/* FIXED: was text-slate-700 → text-slate-800 */}
                    <span className="font-mono text-[12px] font-bold text-slate-800 dark:text-slate-200">
                        {s.v}
                    </span>
                </div>
            ))}
        </div>
    );

    return (
        <>
            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap');

                @keyframes sc-beam    { from{top:-3px} to{top:calc(100%+3px)} }
                @keyframes sc-ring    { 0%{transform:scale(.86);opacity:.6} 70%,100%{transform:scale(1.16);opacity:0} }
                @keyframes sc-sweep   { 0%{top:-100%} 100%{top:100%} }
                @keyframes sc-dpulse  { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(1.5);opacity:.5} }
                @keyframes sc-camline { from{top:-3px} to{top:calc(100%+3px)} }
                @keyframes sc-camdot  { 0%,100%{transform:translate(-50%,-50%) scale(1)} 50%{transform:translate(-50%,-50%) scale(1.8);opacity:.3} }
                @keyframes sc-barfill { from{width:0} }
                @keyframes sc-slidein { from{transform:translateY(-8px);opacity:0} to{transform:translateY(0);opacity:1} }
                @keyframes sc-faderise{ from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
                @keyframes sc-modalrise{from{transform:translateY(14px) scale(.97);opacity:0} to{transform:translateY(0) scale(1);opacity:1}}
                @keyframes sc-fabring { 0%{transform:scale(1);opacity:.7} 100%{transform:scale(1.34);opacity:0} }
                @keyframes sc-huddim  { 0%,88%,100%{opacity:1} 94%{opacity:.12} }
                @keyframes sc-particle{ from{transform:translate(-50%,-50%) scale(1);opacity:1} to{transform:translate(var(--dx),var(--dy)) scale(0);opacity:0} }
                @keyframes sc-ticker  { from{opacity:.26} to{opacity:1} }

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

                .sc-panel-line::before, .sc-maincard::before {
                    content:''; position:absolute; top:0; left:0; right:0; height:1.5px;
                    background:linear-gradient(90deg,transparent,#10b981 45%,#06b6d4 55%,transparent); opacity:.32;
                }
                .sc-dz { position:relative; overflow:hidden; }
                .sc-dzbeam { position:absolute; left:0; top:-3px; width:100%; height:2px; background:linear-gradient(90deg,transparent,#10b981,transparent); filter:blur(1px); opacity:0; pointer-events:none; z-index:2; transition:opacity .25s; }
                .sc-dz:hover .sc-dzbeam,.sc-dz.sc-dz-on .sc-dzbeam { opacity:1; animation:sc-beam 1.9s linear infinite; }
                .sc-dzedge { position:absolute; width:1.5px; top:0; height:100%; background:linear-gradient(180deg,transparent,#10b981,transparent); opacity:0; pointer-events:none; z-index:2; transition:opacity .3s; }
                .sc-dz:hover .sc-dzedge,.sc-dz.sc-dz-on .sc-dzedge { opacity:.22; }
                .sc-ring1 { position:absolute; inset:-10px; border-radius:50%; border:1px solid rgba(16,185,129,.16); animation:sc-ring 2.6s ease-out infinite; }
                .sc-ring2 { position:absolute; inset:-22px; border-radius:50%; border:1px solid rgba(16,185,129,.07); animation:sc-ring 2.6s ease-out infinite .7s; }
                .sc-imgsweep { position:absolute; left:0; top:-100%; width:100%; height:100%; background:linear-gradient(180deg,transparent 0%,rgba(16,185,129,.04) 46%,rgba(16,185,129,.13) 50%,rgba(16,185,129,.04) 54%,transparent 100%); animation:sc-sweep 3s ease-in-out infinite; pointer-events:none; z-index:3; }
                .sc-imglines { position:absolute; inset:0; pointer-events:none; z-index:2; background:repeating-linear-gradient(0deg,transparent,transparent 2px,rgba(0,0,0,.015) 2px,rgba(0,0,0,.015) 4px); }
                .sc-hc  { position:absolute; width:14px; height:14px; border-color:#10b981; border-style:solid; z-index:4; pointer-events:none; }
                .sc-htl { top:7px; left:7px; border-width:2px 0 0 2px; }
                .sc-htr { top:7px; right:7px; border-width:2px 2px 0 0; }
                .sc-hbl { bottom:7px; left:7px; border-width:0 0 2px 2px; }
                .sc-hbr { bottom:7px; right:7px; border-width:0 2px 2px 0; }
                .sc-cambeam { position:absolute; left:0; top:-3px; width:100%; height:3px; background:linear-gradient(90deg,transparent,#10b981,transparent); filter:blur(2px); animation:sc-camline 2s linear infinite; pointer-events:none; z-index:3; }
                .sc-camh { position:absolute; left:0; right:0; top:50%; height:1px; background:rgba(16,185,129,.35); transform:translateY(-50%); }
                .sc-camv { position:absolute; top:0; bottom:0; left:50%; width:1px; background:rgba(16,185,129,.35); transform:translateX(-50%); }
                .sc-camdot { position:absolute; top:50%; left:50%; width:7px; height:7px; border-radius:50%; background:#10b981; box-shadow:0 0 10px #10b981; animation:sc-camdot 1.6s ease-in-out infinite; }
                .sc-pt { position:absolute; width:3px; height:3px; border-radius:50%; background:#10b981; box-shadow:0 0 4px #10b981; top:50%; left:50%; animation:sc-particle .75s ease-out forwards; }
                .sc-shim { position:relative; overflow:hidden; }
                .sc-shim::before { content:''; position:absolute; top:0; left:-100%; width:50%; height:100%; background:linear-gradient(90deg,transparent,rgba(255,255,255,.18),transparent); transform:skewX(-18deg); transition:left .5s; }
                .sc-shim:hover::before { left:160%; }
                .sc-fab::before { content:''; position:absolute; inset:-4px; border-radius:15px; border:1.5px solid rgba(16,185,129,.27); animation:sc-fabring 2.1s ease-out infinite; }
                .sc-hudblink { animation:sc-huddim 3s ease-in-out infinite; }
                .sc-ticker    { animation:sc-ticker 1.3s ease-in-out infinite alternate; }
                .sc-fu        { animation:sc-faderise .48s cubic-bezier(.16,1,.3,1) both; }
                .sc-barfill   { animation:sc-barfill 1.5s ease-out forwards; }
                .sc-modalup   { animation:sc-modalrise .28s cubic-bezier(.16,1,.3,1) both; }
                .sc-alertin   { animation:sc-slidein .28s ease both; }
                .sc-nsb::-webkit-scrollbar { display:none; }
                .sc-nsb { scrollbar-width:none; }
            `}</style>

            <div className="sc-root flex h-screen flex-col overflow-hidden bg-slate-50 transition-colors duration-300 dark:bg-[#080B0F]">
                <div className="pointer-events-none fixed top-[-140px] left-[-70px] z-0 h-[260px] w-[460px] rounded-full bg-emerald-400/[.042] blur-[85px]" />
                <div className="pointer-events-none fixed top-[-90px] right-[-40px] z-0 h-[210px] w-[340px] rounded-full bg-cyan-400/[.028] blur-[85px]" />
                <div className="sc-dotgrid" />

                <div className="relative z-20 flex-shrink-0">
                    <Header />
                </div>
                <div className="relative z-30">
                    <AnalysisLoadingDialog isOpen={showLoading} />
                </div>

                {/* QR Modal */}
                {showQRModal && (
                    <div
                        className="fixed inset-0 z-50 flex items-center justify-center bg-black/65 p-4 backdrop-blur-xl"
                        onClick={() => setShowQRModal(false)}
                    >
                        <div
                            className="sc-modalup sc-panel-line relative w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-7 shadow-2xl dark:border-white/[.08] dark:bg-[#131720]"
                            onClick={(e) => e.stopPropagation()}
                        >
                            <button
                                onClick={() => setShowQRModal(false)}
                                className="absolute top-3 right-3 flex h-7 w-7 items-center justify-center rounded-lg bg-slate-100 text-slate-500 hover:bg-slate-200 dark:bg-white/[.06] dark:hover:bg-white/10"
                            >
                                <X size={13} />
                            </button>
                            <div className="mb-5 text-center">
                                <div className="mx-auto mb-3 flex h-11 w-11 items-center justify-center rounded-xl border border-emerald-500/20 bg-emerald-500/10">
                                    <Smartphone
                                        size={20}
                                        className="text-emerald-500"
                                    />
                                </div>
                                <h2 className="text-lg font-bold text-slate-900 dark:text-white">
                                    Install Mobile App
                                </h2>
                                {/* FIXED: was text-slate-400 → text-slate-500 */}
                                <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    Scan QR to download the Android app
                                </p>
                            </div>
                            <div className="mb-5 flex justify-center">
                                <div className="rounded-xl bg-white p-2.5 shadow-lg">
                                    <img
                                        src="/qr-DogLens.png"
                                        alt="QR"
                                        className="block h-32 w-32"
                                    />
                                </div>
                            </div>
                            <div className="mb-4 overflow-hidden rounded-xl border border-slate-200 dark:border-white/[.07]">
                                {[
                                    {
                                        icon: <Download size={12} />,
                                        t: 'Fast & Easy Installation',
                                    },
                                    {
                                        icon: <Smartphone size={12} />,
                                        t: 'Available on Android',
                                    },
                                    {
                                        icon: <Camera size={12} />,
                                        t: 'All Features On-The-Go',
                                    },
                                ].map((f, i) => (
                                    <div
                                        key={i}
                                        className="flex items-center gap-2.5 border-b border-slate-100 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-700 last:border-none dark:border-white/[.05] dark:bg-white/[.02] dark:text-slate-400"
                                    >
                                        <div className="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded-md bg-emerald-500/10 text-emerald-500">
                                            {f.icon}
                                        </div>
                                        {f.t}
                                    </div>
                                ))}
                            </div>
                            <button
                                onClick={() => setShowQRModal(false)}
                                className="w-full rounded-xl bg-emerald-500 py-3 text-sm font-bold text-black hover:bg-emerald-400"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                )}

                <button
                    onClick={() => setShowQRModal(true)}
                    className="sc-fab fixed right-5 bottom-5 z-40 flex h-11 w-11 items-center justify-center rounded-[13px] bg-emerald-500 text-black shadow-xl shadow-emerald-500/25 transition-all hover:scale-105 hover:bg-emerald-400 active:scale-95"
                >
                    <QrCode size={17} />
                </button>

                {/* ── MAIN CONTENT ── */}
                <div className="relative z-10 mt-[-20px] min-h-0 flex-1 overflow-hidden p-3">
                    <div className="sc-nsb mx-auto h-full max-w-[1360px] overflow-x-hidden overflow-y-auto lg:overflow-hidden">
                        <div className="flex flex-col gap-3 p-3 pb-24 lg:grid lg:h-full lg:grid-cols-[210px_1fr_220px] lg:grid-rows-[1fr] lg:gap-4 lg:overflow-hidden lg:p-4 lg:pb-4 xl:grid-cols-[224px_1fr_232px]">
                            {/* ── LEFT SIDEBAR — desktop only ── */}
                            <div className="hidden lg:flex lg:min-h-0 lg:flex-col lg:justify-end lg:gap-3 lg:overflow-x-hidden lg:overflow-y-auto">
                                <Panel
                                    icon={<ScanIcon size={11} />}
                                    title="Navigation"
                                >
                                    <div className="flex flex-col gap-1 p-2.5">
                                        <Link
                                            href="/scan"
                                            className="flex items-center gap-2.5 rounded-xl border border-emerald-500/25 bg-emerald-500/[.09] px-3 py-2.5 text-[13px] font-semibold text-emerald-700 no-underline dark:bg-emerald-500/[.11] dark:text-emerald-400"
                                        >
                                            <ScanIcon size={13} />
                                            <span>New Scan</span>
                                            <ChevronRight
                                                size={11}
                                                className="ml-auto opacity-40"
                                            />
                                        </Link>
                                        {/* FIXED: was text-slate-500 → text-slate-600 */}
                                        <Link
                                            href="/scanhistory"
                                            className="flex items-center gap-2.5 rounded-xl border border-transparent px-3 py-2.5 text-[13px] font-semibold text-slate-600 no-underline transition-all hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-white/[.05] dark:hover:text-slate-200"
                                        >
                                            <History size={13} />
                                            <span>Scan History</span>
                                        </Link>
                                    </div>
                                </Panel>
                                <Panel
                                    icon={<TrendingUp size={11} />}
                                    title="Top Breeds"
                                >
                                    <TopBreedsContent />
                                </Panel>
                                <Panel
                                    icon={<Activity size={11} />}
                                    title="Global Stats"
                                >
                                    <GlobalStatsContent />
                                </Panel>
                            </div>

                            {/* ── CENTER ── */}
                            <div className="sc-fu flex min-h-0 flex-col gap-3">
                                {/* PAGE TITLE */}
                                <div className="flex flex-shrink-0 flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <div className="mb-1.5 inline-flex items-center gap-2 rounded-full border border-emerald-500/20 bg-emerald-500/[.08] px-2.5 py-1">
                                            <span
                                                className="h-1.5 w-1.5 rounded-full bg-emerald-500 shadow-[0_0_6px_#10b981]"
                                                style={{
                                                    animation:
                                                        'sc-dpulse 2s ease-in-out infinite',
                                                }}
                                            />
                                            <span className="sc-mono text-[10px] font-semibold tracking-[.12em] text-emerald-700 uppercase dark:text-emerald-400">
                                                Breed Detection
                                            </span>
                                        </div>
                                        <h1 className="text-lg leading-none font-extrabold tracking-tight text-slate-900 sm:text-xl dark:text-white">
                                            Scan Your Dog
                                        </h1>
                                        {/* FIXED: was text-slate-500 → text-slate-600 */}
                                        <p className="mt-1 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                                            Upload a photo or use your camera to
                                            identify your dog's breed.
                                        </p>
                                    </div>
                                    {/* FIXED: was text-slate-500 → text-slate-600 */}
                                    <Link
                                        href="/scanhistory"
                                        className="inline-flex flex-shrink-0 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-[13px] font-semibold text-slate-600 no-underline transition-all hover:border-emerald-500/30 hover:bg-emerald-500/[.03] hover:text-emerald-700 dark:border-white/[.07] dark:bg-[#131720] dark:text-slate-400 dark:hover:text-emerald-400"
                                    >
                                        <History size={14} />
                                        Scan History
                                        <ChevronRight
                                            size={12}
                                            className="opacity-35"
                                        />
                                    </Link>
                                </div>

                                {/* Alerts */}
                                {localError && showLocalError && (
                                    <div className="sc-alertin flex-shrink-0 rounded-2xl border border-red-200 bg-red-50 p-3.5 dark:border-red-500/20 dark:bg-red-500/[.07]">
                                        <div className="flex items-start gap-3">
                                            <XCircle
                                                size={15}
                                                className="mt-0.5 flex-shrink-0 text-red-500"
                                            />
                                            <div className="min-w-0 flex-1">
                                                <p className="text-[13px] font-bold text-red-600 dark:text-red-400">
                                                    {localError.not_a_dog
                                                        ? 'Not a Dog '
                                                        : 'Analysis Error'}
                                                </p>
                                                <p className="mt-0.5 text-xs text-red-600/80 dark:text-red-500/75">
                                                    {localError.message}
                                                </p>
                                                {localError.not_a_dog && (
                                                    <button
                                                        onClick={handleReset}
                                                        className="mt-2 inline-flex gap-1.5 rounded-lg bg-red-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-400"
                                                    >
                                                        Try Another Image
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                )}
                                {cameraError && (
                                    <div className="flex-shrink-0 rounded-2xl border border-amber-200 bg-amber-50 p-3.5 dark:border-amber-500/20 dark:bg-amber-500/[.06]">
                                        <div className="flex items-start gap-2.5">
                                            <CircleAlert
                                                size={15}
                                                className="mt-0.5 flex-shrink-0 text-amber-500"
                                            />
                                            <div>
                                                <p className="text-[13px] font-bold text-amber-700 dark:text-amber-400">
                                                    Camera Error
                                                </p>
                                                <p className="mt-0.5 text-xs text-amber-700/80 dark:text-amber-500/75">
                                                    {cameraError}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {/* SCAN CARD */}
                                <div className="sc-maincard relative flex flex-shrink-0 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:min-h-0 lg:flex-1 dark:border-white/[.07] dark:bg-[#131720]">
                                    {/* Terminal bar */}
                                    <div className="flex flex-shrink-0 items-center gap-3 border-b border-slate-200 bg-slate-50 px-4 py-2.5 dark:border-white/[.06] dark:bg-[#0D1117]">
                                        {/* FIXED: was text-slate-400 → text-slate-500 */}
                                        <span className="sc-mono ml-1 text-[10px] text-slate-500 select-none dark:text-slate-500">
                                            doglens://scan
                                        </span>
                                        <div className="sc-mono ml-auto flex items-center gap-1.5 text-[10px] text-emerald-600 select-none dark:text-emerald-400">
                                            <span
                                                className="h-1.5 w-1.5 rounded-full bg-emerald-500 shadow-[0_0_5px_#10b981]"
                                                style={{
                                                    animation:
                                                        'sc-dpulse 2s infinite',
                                                }}
                                            />
                                            {processing
                                                ? 'PROCESSING'
                                                : preview
                                                  ? 'IMAGE LOADED'
                                                  : showCamera
                                                    ? 'CAMERA ACTIVE'
                                                    : 'AWAITING INPUT'}
                                        </div>
                                    </div>

                                    <form
                                        onSubmit={handleSubmit}
                                        className="flex flex-col gap-4 overflow-y-auto p-4 lg:h-full lg:p-5"
                                    >
                                        {/* STATE A: Drop zone */}
                                        {!preview && !showCamera && (
                                            <div className="flex flex-col gap-3">
                                                <div
                                                    className={`sc-dz flex min-h-[250px] cursor-pointer flex-col items-center justify-center gap-4 rounded-2xl border-2 border-dashed px-6 py-8 transition-all lg:min-h-[310px] ${isDragging ? 'sc-dz-on border-emerald-500 bg-emerald-500/[.04] dark:bg-emerald-500/[.06]' : 'border-slate-300 hover:border-emerald-400 hover:bg-emerald-500/[.02] dark:border-white/[.09] dark:hover:border-emerald-500/50'}`}
                                                    onClick={() =>
                                                        fileInputRef.current?.click()
                                                    }
                                                    onDragOver={(e) => {
                                                        e.preventDefault();
                                                        setIsDragging(true);
                                                    }}
                                                    onDragLeave={() =>
                                                        setIsDragging(false)
                                                    }
                                                    onDrop={(e) => {
                                                        e.preventDefault();
                                                        setIsDragging(false);
                                                        const f =
                                                            e.dataTransfer
                                                                .files?.[0];
                                                        if (f) processFile(f);
                                                    }}
                                                >
                                                    <div className="sc-dzbeam" />
                                                    <div
                                                        className="sc-dzedge"
                                                        style={{ left: 0 }}
                                                    />
                                                    <div
                                                        className="sc-dzedge"
                                                        style={{ right: 0 }}
                                                    />
                                                    <input
                                                        ref={fileInputRef}
                                                        type="file"
                                                        accept="image/*"
                                                        className="hidden"
                                                        onChange={(
                                                            e: ChangeEvent<HTMLInputElement>,
                                                        ) => {
                                                            const f =
                                                                e.target
                                                                    .files?.[0];
                                                            if (f)
                                                                processFile(f);
                                                        }}
                                                    />
                                                    <div className="relative flex h-16 w-16 flex-shrink-0 items-center justify-center">
                                                        <div className="sc-ring1" />
                                                        <div className="sc-ring2" />
                                                        <div className="relative flex h-full w-full items-center justify-center rounded-full border-2 border-emerald-500/30 bg-emerald-500/[.09]">
                                                            <Upload
                                                                size={22}
                                                                className="text-emerald-500"
                                                            />
                                                        </div>
                                                    </div>
                                                    <div className="text-center select-none">
                                                        {/* FIXED: was text-slate-800 → already fine, kept */}
                                                        <p className="text-[15px] font-bold text-slate-800 dark:text-slate-100">
                                                            Drop your dog image
                                                            here
                                                        </p>
                                                        {/* FIXED: was text-slate-400 → text-slate-500 */}
                                                        <p className="mt-1 text-[13px] text-slate-500 dark:text-slate-500">
                                                            or{' '}
                                                            <span className="font-bold text-emerald-600 dark:text-emerald-400">
                                                                click to browse
                                                            </span>
                                                        </p>
                                                    </div>
                                                    {/* FIXED: was text-slate-300 → text-slate-500 */}
                                                    <p className="sc-mono text-[9px] tracking-[.14em] text-slate-500 select-none dark:text-slate-600">
                                                        ALL FORMATS · MAX 10MB
                                                    </p>
                                                </div>

                                                <div className="flex items-center gap-3">
                                                    <div className="h-px flex-1 bg-slate-200 dark:bg-white/[.06]" />
                                                    {/* FIXED: was text-slate-300 → text-slate-500 */}
                                                    <span className="sc-mono text-[9px] font-medium tracking-[.14em] text-slate-500 uppercase select-none dark:text-slate-600">
                                                        or use camera
                                                    </span>
                                                    <div className="h-px flex-1 bg-slate-200 dark:bg-white/[.06]" />
                                                </div>

                                                <button
                                                    type="button"
                                                    onClick={startCamera}
                                                    className="flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-[13px] font-bold text-emerald-700 transition-all hover:border-emerald-500/40 hover:bg-emerald-500/[.05] dark:border-emerald-500/25 dark:bg-white/[.03] dark:text-emerald-400"
                                                >
                                                    <Camera size={15} />{' '}
                                                    Activate Camera
                                                </button>
                                                {/* FIXED: was text-slate-300 → text-slate-500 */}
                                                <p className="sc-mono text-center text-[9px] tracking-[.12em] text-slate-500 select-none dark:text-slate-600">
                                                    CHROME · EDGE · SAFARI ·
                                                    FIREFOX
                                                </p>
                                                {errors.image && (
                                                    <p className="text-center text-xs text-red-500">
                                                        {errors.image}
                                                    </p>
                                                )}
                                            </div>
                                        )}

                                        {/* STATE B: Camera */}
                                        {showCamera && !preview && (
                                            <div className="flex flex-col gap-4">
                                                <div className="relative overflow-hidden rounded-2xl border border-emerald-500/25 shadow-md shadow-emerald-500/5">
                                                    <video
                                                        ref={videoRef}
                                                        autoPlay
                                                        playsInline
                                                        muted
                                                        className="block w-full bg-black object-cover"
                                                        style={{
                                                            maxHeight: '55vh',
                                                            minHeight: 240,
                                                        }}
                                                    />
                                                    <canvas
                                                        ref={canvasRef}
                                                        className="hidden"
                                                    />
                                                    <div className="pointer-events-none absolute inset-0 z-[3]">
                                                        <div className="sc-cambeam" />
                                                        {[
                                                            'tl',
                                                            'tr',
                                                            'bl',
                                                            'br',
                                                        ].map((p) => (
                                                            <div
                                                                key={p}
                                                                className={`sc-hc sc-h${p}`}
                                                            />
                                                        ))}

                                                        <div className="sc-mono sc-ticker absolute bottom-3 left-3 rounded border border-emerald-500/20 bg-black/60 px-2 py-0.5 text-[9px] font-semibold tracking-[.14em] text-emerald-400 uppercase backdrop-blur-sm">
                                                            ● REC ·{' '}
                                                            {facingMode ===
                                                            'environment'
                                                                ? 'REAR'
                                                                : 'FRONT'}{' '}
                                                            CAM
                                                        </div>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        onClick={switchCamera}
                                                        className="absolute top-3 right-3 z-10 flex h-9 w-9 items-center justify-center rounded-xl border border-white/10 bg-black/55 text-white backdrop-blur-sm transition-all hover:border-emerald-500/40 hover:bg-emerald-500/20"
                                                    >
                                                        <SwitchCamera
                                                            size={15}
                                                        />
                                                    </button>
                                                </div>
                                                <div className="flex gap-3">
                                                    <button
                                                        type="button"
                                                        onClick={capturePhoto}
                                                        className="sc-shim flex flex-1 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-emerald-500 to-cyan-500 py-3 text-[13px] font-bold text-black shadow-lg shadow-emerald-500/20 transition-all hover:-translate-y-0.5"
                                                    >
                                                        <ScanIcon size={15} />{' '}
                                                        Capture & Scan
                                                    </button>
                                                    {/* FIXED: was text-slate-500 → text-slate-600 */}
                                                    <button
                                                        type="button"
                                                        onClick={stopCamera}
                                                        className="flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-slate-100 px-4 py-3 text-[13px] font-semibold text-slate-600 transition-all hover:bg-slate-200 dark:border-white/[.08] dark:bg-white/[.05] dark:text-slate-400 dark:hover:bg-white/10"
                                                    >
                                                        <X size={13} /> Cancel
                                                    </button>
                                                </div>
                                            </div>
                                        )}

                                        {/* STATE C: Preview */}
                                        {preview && (
                                            <div className="flex flex-col gap-4">
                                                <div className="relative overflow-hidden rounded-2xl border border-emerald-500/25 shadow-md shadow-emerald-500/[.06]">
                                                    {particleActive && (
                                                        <div className="pointer-events-none absolute inset-0 z-10 overflow-hidden">
                                                            {[...Array(12)].map(
                                                                (_, i) => {
                                                                    const a =
                                                                            (i /
                                                                                12) *
                                                                            360,
                                                                        d =
                                                                            60 +
                                                                            Math.random() *
                                                                                55;
                                                                    return (
                                                                        <div
                                                                            key={
                                                                                i
                                                                            }
                                                                            className="sc-pt"
                                                                            style={
                                                                                {
                                                                                    '--dx': `${Math.cos((a * Math.PI) / 180) * d}px`,
                                                                                    '--dy': `${Math.sin((a * Math.PI) / 180) * d}px`,
                                                                                } as any
                                                                            }
                                                                        />
                                                                    );
                                                                },
                                                            )}
                                                        </div>
                                                    )}
                                                    <img
                                                        src={preview}
                                                        alt="Preview"
                                                        className="block w-full bg-slate-100 object-contain dark:bg-[#0D1117]"
                                                        style={{
                                                            maxHeight: 360,
                                                        }}
                                                    />
                                                    <div className="pointer-events-none absolute inset-0">
                                                        <div className="sc-imglines" />
                                                        <div className="sc-imgsweep" />
                                                        {[
                                                            'tl',
                                                            'tr',
                                                            'bl',
                                                            'br',
                                                        ].map((p) => (
                                                            <div
                                                                key={p}
                                                                className={`sc-hc sc-h${p}`}
                                                                style={{
                                                                    opacity: 0.9,
                                                                }}
                                                            />
                                                        ))}
                                                        <div className="absolute right-2.5 bottom-2.5 left-2.5 z-[5] flex items-center justify-between">
                                                            <span className="sc-mono rounded border border-emerald-500/20 bg-black/65 px-2 py-0.5 text-[9px] font-medium tracking-[.1em] text-emerald-400 backdrop-blur-sm">
                                                                IMAGE LOADED
                                                            </span>
                                                            {fileInfo && (
                                                                <span className="sc-mono max-w-[180px] truncate rounded border border-emerald-500/15 bg-black/65 px-2 py-0.5 text-[8px] text-emerald-400 backdrop-blur-sm">
                                                                    {fileInfo
                                                                        .split(
                                                                            '·',
                                                                        )
                                                                        .slice(
                                                                            1,
                                                                        )
                                                                        .join(
                                                                            '·',
                                                                        )
                                                                        .trim()}
                                                                </span>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                                {/* FIXED: was text-slate-300 → text-slate-500 */}
                                                {fileInfo && (
                                                    <p className="sc-mono truncate text-center text-[9px] tracking-wide text-slate-500 dark:text-slate-600">
                                                        {fileInfo}
                                                    </p>
                                                )}
                                                <div className="flex gap-3">
                                                    <button
                                                        type="submit"
                                                        disabled={processing}
                                                        className="sc-shim flex flex-1 items-center justify-center gap-2 rounded-xl bg-emerald-500 py-3 text-[13px] font-bold text-black shadow-lg shadow-emerald-500/20 transition-all hover:-translate-y-0.5 hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-40"
                                                    >
                                                        <ScanIcon size={15} />{' '}
                                                        {processing
                                                            ? 'Analyzing…'
                                                            : 'Analyze Image'}
                                                    </button>
                                                    {/* FIXED: was text-slate-500 → text-slate-600 */}
                                                    <button
                                                        type="button"
                                                        onClick={handleReset}
                                                        disabled={processing}
                                                        className="flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-slate-100 px-4 py-3 text-[13px] font-semibold text-slate-600 transition-all hover:bg-slate-200 disabled:opacity-40 dark:border-white/[.08] dark:bg-white/[.05] dark:text-slate-400 dark:hover:bg-white/10"
                                                    >
                                                        <X size={13} /> Reset
                                                    </button>
                                                </div>
                                            </div>
                                        )}
                                    </form>
                                </div>

                                {/* MOBILE-ONLY panels */}
                                <div className="flex flex-col gap-3 lg:hidden">
                                    <Panel
                                        icon={<TrendingUp size={11} />}
                                        title="Top Breeds"
                                    >
                                        <TopBreedsContent />
                                    </Panel>
                                    <Panel
                                        icon={<Activity size={11} />}
                                        title="Global Stats"
                                    >
                                        <GlobalStatsContent />
                                    </Panel>
                                </div>
                            </div>

                            {/* ── RIGHT SIDEBAR ── */}
                            <div className="sc-fu flex flex-col gap-3 lg:min-h-0 lg:justify-end lg:overflow-x-hidden lg:overflow-y-auto">
                                <Panel
                                    icon={<Eye size={11} />}
                                    title="How It Works"
                                >
                                    <div className="flex flex-col p-3">
                                        {[
                                            {
                                                n: '01',
                                                t: 'Upload or capture a clear photo of your dog',
                                            },
                                            {
                                                n: '02',
                                                t: 'System analyzes visual breed features',
                                            },
                                            {
                                                n: '03',
                                                t: 'Results ranked by confidence score',
                                            },
                                            {
                                                n: '04',
                                                t: 'Optional vet verification for extra accuracy',
                                            },
                                        ].map((s, i) => (
                                            <div
                                                key={i}
                                                className={`flex items-start gap-2.5 py-2.5 ${i < 3 ? 'border-b border-slate-200 dark:border-white/[.05]' : ''}`}
                                            >
                                                <span className="sc-mono mt-[2px] w-5 flex-shrink-0 text-[9px] font-semibold text-emerald-600/80 dark:text-emerald-500/65">
                                                    {s.n}
                                                </span>
                                                {/* FIXED: was text-slate-500 → text-slate-600 */}
                                                <p className="text-[12px] leading-relaxed text-slate-600 dark:text-slate-400">
                                                    {s.t}
                                                </p>
                                            </div>
                                        ))}
                                    </div>
                                </Panel>
                                <Panel
                                    icon={<Zap size={11} />}
                                    title="Capture Tips"
                                >
                                    <div className="flex flex-col p-3">
                                        {captureTips.map((tip, i) => (
                                            <div
                                                key={i}
                                                className={`flex items-start gap-2.5 py-2 ${i < captureTips.length - 1 ? 'border-b border-slate-200 dark:border-white/[.05]' : ''}`}
                                            >
                                                <span className="sc-mono mt-[2px] w-5 flex-shrink-0 text-[9px] font-semibold text-emerald-600/70 dark:text-emerald-500/55">
                                                    0{i + 1}
                                                </span>
                                                {/* FIXED: regular text was text-slate-500 → text-slate-600 */}
                                                <p
                                                    className={`text-[12px] leading-relaxed text-slate-600 dark:text-slate-400`}
                                                >
                                                    {tip.text}
                                                </p>
                                            </div>
                                        ))}
                                    </div>
                                </Panel>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
