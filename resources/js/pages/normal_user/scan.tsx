import AnalysisLoadingDialog from '@/components/AnalysisLoadingDialog';
import Header from '@/components/header';
import { Link, useForm, usePage } from '@inertiajs/react';
import {
    Activity, BookOpen, Camera, ChevronRight, CircleAlert, Clock, Cpu,
    Download, Eye, History, Layers, QrCode, Scan as ScanIcon, Shield,
    Smartphone, SwitchCamera, Target, TrendingUp, Upload, Wifi, X, XCircle, Zap,
} from 'lucide-react';
import { ChangeEvent, useEffect, useRef, useState } from 'react';

interface PredictionResult { breed: string; confidence: number; }
interface SuccessFlash { breed: string; confidence: number; top_predictions: PredictionResult[]; message: string; }
interface ErrorFlash { message: string; not_a_dog?: boolean; }
interface PageProps { flash?: { success?: SuccessFlash; error?: ErrorFlash; }; success?: SuccessFlash; error?: ErrorFlash; [key: string]: any; }

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
    const [scanPhase, setScanPhase] = useState(0);
    const [particleActive, setParticleActive] = useState(false);
    const [timeStr, setTimeStr] = useState('');

    const isCameraSupported = () => !!(navigator.mediaDevices?.getUserMedia) && /chrome|chromium|crios|edg|safari|firefox|fxios/.test(navigator.userAgent.toLowerCase());

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
    useEffect(() => {
        const update = () => setTimeStr(new Date().toLocaleTimeString('en-US', { hour12: false }));
        update(); const t = setInterval(update, 1000); return () => clearInterval(t);
    }, []);

    const processImageFile = (file: File) => {
        if (file.size > 10 * 1024 * 1024) { alert('Max 10MB'); return; }
        const url = URL.createObjectURL(file); setPreview(url);
        const img = new Image();
        img.onload = () => { setFileInfo(`${file.name} (${(file.size / 1024).toFixed(1)}KB, ${img.width}×${img.height})`); URL.revokeObjectURL(url); };
        img.onerror = () => { setFileInfo(`${file.name} (${(file.size / 1024).toFixed(1)}KB)`); URL.revokeObjectURL(url); };
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

    const hudPhases = ['INITIALIZING', 'SCANNING', 'PROCESSING', 'READY'];
    const recentBreeds = ['Golden Retriever', 'Labrador', 'German Shepherd', 'Bulldog', 'Poodle'];

    return (
        <>
            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Mono:wght@300;400;500&display=swap');

                html.dark {
                    --neon:#00FFB2; --neon2:#00D4FF; --nd:rgba(0,255,178,0.09);
                    --ng:0 0 20px rgba(0,255,178,0.3),0 0 50px rgba(0,255,178,0.1);
                    --bg:#07090C; --s1:#0D1014; --s2:#131720; --s3:#1A1F2A;
                    --br:rgba(255,255,255,0.06); --bn:rgba(0,255,178,0.2);
                    --t1:#ECF0F5; --t2:#68788F; --t3:#38404F;
                    --red:#FF4757; --amber:#FFB830;
                    --sh:0 8px 40px rgba(0,0,0,0.55),0 1px 0 rgba(255,255,255,0.04) inset;
                }
                html.light {
                    --neon:#009E6A; --neon2:#007BAA; --nd:rgba(0,158,106,0.08);
                    --ng:0 0 16px rgba(0,158,106,0.2),0 0 40px rgba(0,158,106,0.07);
                    --bg:#EFF1F6; --s1:#F8F9FC; --s2:#FFFFFF; --s3:#F2F4F8;
                    --br:rgba(0,0,0,0.07); --bn:rgba(0,158,106,0.25);
                    --t1:#0D1117; --t2:#556070; --t3:#99A0AE;
                    --red:#E03040; --amber:#D4900A;
                    --sh:0 4px 24px rgba(0,0,0,0.07),0 1px 0 rgba(255,255,255,0.9) inset;
                }
                :root {
                    --neon:#00FFB2; --neon2:#00D4FF; --nd:rgba(0,255,178,0.09);
                    --ng:0 0 20px rgba(0,255,178,0.3),0 0 50px rgba(0,255,178,0.1);
                    --bg:#07090C; --s1:#0D1014; --s2:#131720; --s3:#1A1F2A;
                    --br:rgba(255,255,255,0.06); --bn:rgba(0,255,178,0.2);
                    --t1:#ECF0F5; --t2:#68788F; --t3:#38404F;
                    --red:#FF4757; --amber:#FFB830;
                    --sh:0 8px 40px rgba(0,0,0,0.55),0 1px 0 rgba(255,255,255,0.04) inset;
                }

                .sf *{font-family:'Syne',sans-serif!important;box-sizing:border-box;}
                .mono{font-family:'DM Mono',monospace!important;}
                .sf{background:var(--bg);min-height:100vh;position:relative;overflow-x:hidden;}

                .sf-grid{position:fixed;inset:0;pointer-events:none;z-index:0;
                    background-image:linear-gradient(var(--br) 1px,transparent 1px),linear-gradient(90deg,var(--br) 1px,transparent 1px);
                    background-size:52px 52px;
                    mask-image:radial-gradient(ellipse 100% 60% at 50% 0%,black 30%,transparent 100%);}
                .sf-g1{position:fixed;width:600px;height:350px;top:-180px;left:-80px;border-radius:50%;
                    background:radial-gradient(var(--neon),transparent 70%);filter:blur(90px);opacity:.09;
                    pointer-events:none;z-index:0;animation:gd 14s ease-in-out infinite alternate;}
                .sf-g2{position:fixed;width:450px;height:280px;top:-120px;right:-60px;border-radius:50%;
                    background:radial-gradient(var(--neon2),transparent 70%);filter:blur(90px);opacity:.06;
                    pointer-events:none;z-index:0;animation:gd 14s ease-in-out infinite alternate-reverse;}
                @keyframes gd{from{transform:translateX(0) scale(1);}to{transform:translateX(50px) scale(1.08);}}

                .sf-pt{position:fixed;border-radius:50%;background:var(--neon);box-shadow:0 0 5px var(--neon);
                    pointer-events:none;z-index:0;opacity:0;animation:ptf linear infinite;}
                @keyframes ptf{0%{transform:translateY(100vh);opacity:0;}8%{opacity:.5;}92%{opacity:.2;}100%{transform:translateY(-5vh);opacity:0;}}

                .sf-z{position:relative;z-index:1;}

                /* HUD */
                .hud{display:flex;align-items:center;background:var(--s1);border-bottom:1px solid var(--br);
                    padding:5px 20px;overflow-x:auto;scrollbar-width:none;}
                .hud::-webkit-scrollbar{display:none;}
                .hud-it{display:flex;align-items:center;gap:5px;padding:3px 12px;font-size:10px;font-weight:700;
                    letter-spacing:.1em;text-transform:uppercase;color:var(--t2);border-right:1px solid var(--br);white-space:nowrap;}
                .hud-it:last-child{border:none;}
                .hud-dot{width:5px;height:5px;border-radius:50%;flex-shrink:0;}
                .hud-val{color:var(--neon);font-size:10px;}
                .hud-tick{margin-left:auto;font-size:10px;color:var(--t3);padding-left:12px;white-space:nowrap;
                    animation:tf 1.2s ease-in-out infinite alternate;}
                @keyframes tf{from{opacity:.35;}to{opacity:1;}}

                /* Three-column grid */
                .mgrid{display:grid;grid-template-columns:230px 1fr 230px;gap:18px;
                    max-width:1260px;margin:0 auto;padding:22px 18px 72px;}
                @media(max-width:1080px){.mgrid{grid-template-columns:200px 1fr;}.col-r{display:none;}}
                @media(max-width:760px){.mgrid{grid-template-columns:1fr;}.col-l{display:none;}}

                /* Side panels */
                .sp{background:var(--s2);border:1px solid var(--br);border-radius:16px;overflow:hidden;
                    box-shadow:var(--sh);position:relative;}
                .sp::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;
                    background:linear-gradient(90deg,transparent,var(--neon),transparent);opacity:.35;}
                .sph{padding:11px 13px;border-bottom:1px solid var(--br);display:flex;align-items:center;gap:8px;background:var(--s1);}
                .sph-ic{width:25px;height:25px;border-radius:7px;background:var(--nd);border:1px solid var(--bn);
                    display:flex;align-items:center;justify-content:center;color:var(--neon);flex-shrink:0;}
                .sph-t{font-size:10px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--t2);}
                .spb{padding:13px;display:flex;flex-direction:column;gap:9px;}

                /* Metric cards */
                .mc{display:flex;align-items:center;justify-content:space-between;padding:7px 9px;
                    background:var(--s1);border:1px solid var(--br);border-radius:9px;transition:all .2s;cursor:default;}
                .mc:hover{border-color:var(--bn);background:var(--nd);}
                .mc-l{font-size:10px;color:var(--t2);font-weight:600;letter-spacing:.08em;text-transform:uppercase;}
                .mc-v{font-size:13px;font-weight:700;color:var(--t1);}
                .mc-v.g{color:var(--neon);}

                .mbar{height:3px;background:var(--br);border-radius:2px;margin-top:4px;overflow:hidden;}
                .mbar-f{height:100%;background:var(--neon);border-radius:2px;box-shadow:0 0 6px var(--neon);animation:bg 1.8s ease-out forwards;}
                @keyframes bg{from{width:0;}}

                /* Breed items */
                .bi{display:flex;align-items:center;gap:7px;padding:6px 9px;border-radius:8px;transition:all .2s;cursor:default;}
                .bi:hover{background:var(--nd);}
                .bi-n{font-size:9px;color:var(--t3);width:14px;flex-shrink:0;}
                .bi-name{font-size:12px;color:var(--t2);font-weight:500;}
                .bi-bar{flex:1;height:2px;background:var(--br);border-radius:2px;overflow:hidden;}
                .bi-bf{height:100%;background:var(--neon);opacity:.5;border-radius:2px;}

                /* Status */
                .slive{display:flex;align-items:center;gap:6px;padding:6px 10px;background:rgba(0,255,178,.05);
                    border:1px solid var(--bn);border-radius:8px;}
                .sdot{width:6px;height:6px;border-radius:50%;background:var(--neon);box-shadow:0 0 8px var(--neon);
                    animation:sdp 2s ease-in-out infinite;flex-shrink:0;}
                @keyframes sdp{0%,100%{transform:scale(1);box-shadow:0 0 8px var(--neon);}50%{transform:scale(1.3);box-shadow:0 0 16px var(--neon);}}
                .stxt{font-size:10px;font-weight:700;color:var(--neon);letter-spacing:.1em;text-transform:uppercase;}

                /* Log */
                .le{display:flex;align-items:flex-start;gap:8px;padding:5px 0;border-bottom:1px solid var(--br);}
                .le:last-child{border:none;}
                .le-t{font-size:9px;color:var(--t3);flex-shrink:0;margin-top:1px;}
                .le-m{font-size:11px;color:var(--t2);line-height:1.4;}
                .le-m.ok{color:var(--neon);}

                /* Scan card */
                .sc{background:var(--s2);border:1px solid var(--br);border-radius:20px;
                    position:relative;overflow:hidden;box-shadow:var(--sh);}
                .scc{position:absolute;width:18px;height:18px;border-color:var(--neon);border-style:solid;
                    opacity:.45;pointer-events:none;z-index:2;transition:opacity .3s;}
                .sc:hover .scc{opacity:.85;}
                .scc-tl{top:10px;left:10px;border-width:2px 0 0 2px;}
                .scc-tr{top:10px;right:10px;border-width:2px 2px 0 0;}
                .scc-bl{bottom:10px;left:10px;border-width:0 0 2px 2px;}
                .scc-br{bottom:10px;right:10px;border-width:0 2px 2px 0;}

                /* Terminal bar */
                .ctb{padding:10px 18px;border-bottom:1px solid var(--br);display:flex;align-items:center;gap:10px;background:var(--s1);}
                .ctbd{width:9px;height:9px;border-radius:50%;}
                .ctbd-r{background:#FF5F57;}.ctbd-y{background:#FEBC2E;}
                .ctbd-g{background:var(--neon);box-shadow:0 0 6px var(--neon);animation:tdblink 3s ease-in-out infinite;}
                @keyframes tdblink{0%,90%,100%{opacity:1;}95%{opacity:.2;}}
                .ctb-lbl{font-size:11px;color:var(--t2);margin-left:4px;}
                .ctb-st{margin-left:auto;font-size:10px;color:var(--neon);display:flex;align-items:center;gap:5px;}
                .ctb-sd{width:5px;height:5px;border-radius:50%;background:var(--neon);box-shadow:0 0 6px var(--neon);animation:sdp 2s infinite;}

                /* Drop zone */
                .dz{border:1.5px dashed var(--bn);border-radius:16px;min-height:220px;
                    display:flex;flex-direction:column;align-items:center;justify-content:center;gap:16px;
                    cursor:pointer;position:relative;overflow:hidden;transition:all .3s;padding:44px 20px;}
                .dz:hover,.dz.drag{border-color:var(--neon);background:var(--nd);
                    box-shadow:inset 0 0 50px rgba(0,255,178,.03),var(--ng);}
                .dz-beam{position:absolute;left:0;top:-4px;width:100%;height:3px;
                    background:linear-gradient(90deg,transparent,var(--neon),transparent);
                    filter:blur(1px);opacity:0;transition:opacity .3s;}
                .dz:hover .dz-beam,.dz.drag .dz-beam{opacity:1;animation:dzs 1.8s linear infinite;}
                @keyframes dzs{from{top:-4px;}to{top:100%;}}
                .dz-sl{position:absolute;width:2px;top:0;height:100%;
                    background:linear-gradient(180deg,transparent,var(--neon),transparent);opacity:0;transition:opacity .4s;}
                .dz-sl-l{left:0;}.dz-sl-r{right:0;}
                .dz:hover .dz-sl,.dz.drag .dz-sl{opacity:.4;}

                .dz-icon{position:relative;width:76px;height:76px;display:flex;align-items:center;justify-content:center;}
                .dz-ibg{position:absolute;inset:0;border-radius:50%;background:var(--nd);border:1.5px solid var(--bn);transition:all .3s;}
                .dz-r1{position:absolute;inset:-10px;border-radius:50%;border:1px solid rgba(0,255,178,.1);animation:rp 2.5s ease-out infinite;}
                .dz-r2{position:absolute;inset:-20px;border-radius:50%;border:1px solid rgba(0,255,178,.05);animation:rp 2.5s ease-out infinite .7s;}
                @keyframes rp{0%{transform:scale(.9);opacity:.7;}70%{transform:scale(1.1);opacity:0;}100%{transform:scale(1.1);opacity:0;}}
                .dz-inner{position:relative;z-index:1;color:var(--neon);transition:transform .3s;}
                .dz:hover .dz-inner{transform:scale(1.1) translateY(-2px);}
                .dz:hover .dz-ibg{background:rgba(0,255,178,.14);border-color:var(--neon);box-shadow:var(--ng);}

                /* Divider */
                .dvd{display:flex;align-items:center;gap:10px;margin:12px 0;}
                .dvd-l{flex:1;height:1px;background:var(--br);}
                .dvd-t{font-size:10px;font-weight:700;color:var(--t3);letter-spacing:.12em;text-transform:uppercase;}

                /* Buttons */
                .btn-n{display:inline-flex;align-items:center;justify-content:center;gap:8px;
                    background:var(--neon);color:#000;font-weight:700;font-size:14px;letter-spacing:.04em;
                    border:none;border-radius:11px;padding:13px 26px;cursor:pointer;
                    position:relative;overflow:hidden;transition:all .25s;text-decoration:none;}
                .btn-n::before{content:'';position:absolute;top:0;left:-100%;width:60%;height:100%;
                    background:linear-gradient(90deg,transparent,rgba(255,255,255,.28),transparent);
                    transform:skewX(-20deg);transition:left .5s;}
                .btn-n:hover{box-shadow:var(--ng);transform:translateY(-2px);}
                .btn-n:hover::before{left:160%;}
                .btn-n:disabled{opacity:.4;cursor:not-allowed;transform:none;box-shadow:none;}

                .btn-o{display:inline-flex;align-items:center;justify-content:center;gap:8px;
                    background:var(--s1);color:var(--t2);font-weight:600;font-size:14px;
                    border:1px solid var(--br);border-radius:11px;padding:13px 24px;cursor:pointer;transition:all .2s;}
                .btn-o:hover{border-color:var(--bn);color:var(--neon);background:var(--nd);}

                .btn-cam{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;
                    background:var(--s1);border:1.5px solid var(--bn);border-radius:11px;
                    color:var(--neon);font-weight:700;font-size:14px;padding:12px 20px;cursor:pointer;transition:all .25s;}
                .btn-cam:hover{border-color:var(--neon);background:var(--nd);box-shadow:var(--ng);}

                /* Tips */
                .tips{background:rgba(0,255,178,.025);border:1px solid var(--bn);border-radius:14px;padding:14px 16px;margin-top:18px;}
                .tip-r{display:flex;gap:9px;align-items:flex-start;padding:5px 0;border-bottom:1px solid var(--br);}
                .tip-r:last-child{border:none;}
                .tip-n{font-size:9px;color:var(--neon);opacity:.6;flex-shrink:0;margin-top:2px;width:16px;}
                .tip-t{font-size:12px;color:var(--t2);line-height:1.5;}
                .tip-t.b{color:var(--t1);font-weight:600;}

                /* Alerts */
                .al-e{background:rgba(255,71,87,.07);border:1px solid rgba(255,71,87,.2);border-radius:13px;
                    padding:14px 16px;margin-bottom:16px;animation:sld .3s ease;}
                .al-w{background:rgba(255,184,48,.06);border:1px solid rgba(255,184,48,.18);border-radius:13px;
                    padding:14px 16px;margin-bottom:16px;}
                @keyframes sld{from{transform:translateY(-8px);opacity:0;}to{transform:translateY(0);opacity:1;}}

                /* Preview */
                .pf{border-radius:14px;overflow:hidden;border:1px solid var(--bn);position:relative;
                    box-shadow:0 0 40px rgba(0,255,178,.08);}
                .p-sl{position:absolute;inset:0;background:repeating-linear-gradient(0deg,transparent,transparent 2px,rgba(0,0,0,.03) 2px,rgba(0,0,0,.03) 4px);pointer-events:none;z-index:2;}
                .p-sw{position:absolute;left:0;top:-100%;width:100%;height:100%;
                    background:linear-gradient(180deg,transparent 0%,rgba(0,255,178,.04) 48%,rgba(0,255,178,.13) 50%,rgba(0,255,178,.04) 52%,transparent 100%);
                    animation:psw 3s ease-in-out infinite;pointer-events:none;z-index:3;}
                @keyframes psw{0%{top:-100%;}100%{top:100%;}}
                .phc{position:absolute;width:22px;height:22px;border-color:var(--neon);border-style:solid;z-index:4;animation:hf 4s ease-in-out infinite;}
                @keyframes hf{0%,94%,100%{opacity:1;}97%{opacity:.2;}}
                .phc-tl{top:9px;left:9px;border-width:2px 0 0 2px;}
                .phc-tr{top:9px;right:9px;border-width:2px 2px 0 0;}
                .phc-bl{bottom:9px;left:9px;border-width:0 0 2px 2px;}
                .phc-br{bottom:9px;right:9px;border-width:0 2px 2px 0;}
                .pbadge{position:absolute;bottom:9px;display:flex;align-items:center;justify-content:space-between;left:9px;right:9px;z-index:5;}
                .pmb{font-size:9px;font-weight:500;color:var(--neon);letter-spacing:.08em;background:rgba(0,0,0,.7);backdrop-filter:blur(8px);border:1px solid rgba(0,255,178,.2);border-radius:5px;padding:2px 7px;}

                /* Particle burst */
                .pb-wrap{position:absolute;inset:0;pointer-events:none;overflow:hidden;z-index:10;}
                .pb-d{position:absolute;width:3px;height:3px;border-radius:50%;background:var(--neon);
                    box-shadow:0 0 6px var(--neon);top:50%;left:50%;animation:bo .75s ease-out forwards;}
                @keyframes bo{from{transform:translate(-50%,-50%) scale(1);opacity:1;}to{transform:translate(var(--dx),var(--dy)) scale(0);opacity:0;}}

                /* Camera */
                .cf{border-radius:14px;overflow:hidden;border:1px solid var(--bn);position:relative;box-shadow:0 0 40px rgba(0,255,178,.07);}
                .csw{position:absolute;left:0;top:-4px;width:100%;height:4px;
                    background:linear-gradient(90deg,transparent,var(--neon),transparent);filter:blur(2px);animation:cswm 2s linear infinite;}
                @keyframes cswm{from{top:-4px;}to{top:100%;}}
                .cxh{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:72px;height:72px;}
                .ch{position:absolute;left:0;right:0;top:50%;height:1px;background:rgba(0,255,178,.35);transform:translateY(-50%);}
                .cv{position:absolute;top:0;bottom:0;left:50%;width:1px;background:rgba(0,255,178,.35);transform:translateX(-50%);}
                .cc{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:8px;height:8px;border-radius:50%;
                    background:var(--neon);box-shadow:0 0 10px var(--neon);animation:cp 1.5s ease-in-out infinite;}
                @keyframes cp{0%,100%{transform:translate(-50%,-50%) scale(1);opacity:1;}50%{transform:translate(-50%,-50%) scale(1.6);opacity:.5;}}
                .chc-corner{position:absolute;width:22px;height:22px;border-color:var(--neon);border-style:solid;opacity:.7;animation:hf 3s ease-in-out infinite;}
                .chc-tl{top:12px;left:12px;border-width:2px 0 0 2px;}
                .chc-tr{top:12px;right:50px;border-width:2px 2px 0 0;}
                .chc-bl{bottom:12px;left:12px;border-width:0 0 2px 2px;}
                .chc-br{bottom:12px;right:12px;border-width:0 2px 2px 0;}
                .clbl{position:absolute;bottom:11px;left:11px;font-size:9px;font-weight:500;color:var(--neon);letter-spacing:.12em;
                    background:rgba(0,0,0,.65);backdrop-filter:blur(6px);border:1px solid rgba(0,255,178,.2);
                    border-radius:4px;padding:2px 7px;text-transform:uppercase;z-index:10;animation:tf 1.5s ease-in-out infinite;}
                .csb{position:absolute;top:13px;right:13px;width:40px;height:40px;border-radius:11px;
                    background:rgba(0,0,0,.6);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.1);
                    color:white;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s;z-index:10;}
                .csb:hover{background:rgba(0,255,178,.2);border-color:rgba(0,255,178,.5);box-shadow:0 0 14px rgba(0,255,178,.3);}
                .btn-cap{display:flex;align-items:center;justify-content:center;gap:8px;flex:1;
                    background:linear-gradient(135deg,var(--neon),var(--neon2));color:#000;font-weight:700;font-size:14px;
                    border:none;border-radius:11px;padding:13px 20px;cursor:pointer;transition:all .25s;
                    box-shadow:0 4px 18px rgba(0,255,178,.22);}
                .btn-cap:hover{transform:translateY(-2px);box-shadow:0 8px 30px rgba(0,255,178,.38);}

                /* QR FAB */
                .qfab{position:fixed;bottom:26px;right:26px;z-index:40;width:48px;height:48px;border-radius:13px;
                    background:var(--neon);color:#000;border:none;cursor:pointer;
                    display:flex;align-items:center;justify-content:center;
                    box-shadow:0 4px 22px rgba(0,255,178,.38);transition:all .25s;}
                .qfab::before{content:'';position:absolute;inset:-3px;border-radius:16px;
                    border:1.5px solid rgba(0,255,178,.28);animation:fr 2s ease-out infinite;}
                @keyframes fr{0%{transform:scale(1);opacity:.8;}100%{transform:scale(1.28);opacity:0;}}
                .qfab:hover{transform:scale(1.08) translateY(-3px);box-shadow:0 8px 34px rgba(0,255,178,.52);}

                /* QR Modal */
                .qrov{position:fixed;inset:0;background:rgba(0,0,0,.78);backdrop-filter:blur(16px);z-index:50;
                    display:flex;align-items:center;justify-content:center;padding:16px;animation:ovin .2s ease;}
                @keyframes ovin{from{opacity:0;}to{opacity:1;}}
                .qrmod{background:var(--s2);border:1px solid var(--br);border-radius:22px;padding:30px;
                    max-width:400px;width:100%;position:relative;animation:mu .3s cubic-bezier(.16,1,.3,1);box-shadow:var(--sh);}
                @keyframes mu{from{transform:translateY(18px) scale(.97);opacity:0;}to{transform:translateY(0) scale(1);opacity:1;}}
                .qrc2{position:absolute;width:15px;height:15px;border-color:var(--neon);border-style:solid;opacity:.45;}
                .qrc2-tl{top:9px;left:9px;border-width:2px 0 0 2px;}.qrc2-tr{top:9px;right:9px;border-width:2px 2px 0 0;}
                .qrc2-bl{bottom:9px;left:9px;border-width:0 0 2px 2px;}.qrc2-br{bottom:9px;right:9px;border-width:0 2px 2px 0;}
                .qrx{position:absolute;top:13px;right:13px;width:30px;height:30px;border-radius:7px;
                    background:var(--s1);border:1px solid var(--br);color:var(--t2);cursor:pointer;
                    display:flex;align-items:center;justify-content:center;transition:all .2s;}
                .qrx:hover{color:var(--t1);border-color:var(--bn);}
                .mf2{display:flex;align-items:center;gap:9px;padding:9px 0;border-bottom:1px solid var(--br);color:var(--t2);font-size:13px;}
                .mf2:last-child{border:none;}
                .mf2-ic{width:26px;height:26px;border-radius:6px;background:var(--nd);border:1px solid var(--bn);
                    display:flex;align-items:center;justify-content:center;color:var(--neon);flex-shrink:0;}

                /* Page eyebrow */
                .pg-ey{display:inline-flex;align-items:center;gap:7px;padding:3px 11px 3px 7px;
                    background:var(--nd);border:1px solid var(--bn);border-radius:100px;margin-bottom:9px;}
                .pg-ed{width:7px;height:7px;border-radius:50%;background:var(--neon);box-shadow:0 0 8px var(--neon);animation:sdp 2s infinite;}
                .pg-et{font-size:10px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--neon);}

                /* Hist btn */
                .hbtn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:var(--s2);
                    border:1px solid var(--br);border-radius:10px;color:var(--t2);font-size:13px;font-weight:600;
                    text-decoration:none;transition:all .2s;flex-shrink:0;white-space:nowrap;}
                .hbtn:hover{border-color:var(--bn);color:var(--neon);background:var(--nd);box-shadow:var(--ng);}

                /* Fade up */
                @keyframes fu{from{opacity:0;transform:translateY(16px);}to{opacity:1;transform:translateY(0);}}
                .fu{animation:fu .5s cubic-bezier(.16,1,.3,1) both;}
                .fu1{animation-delay:0s;}.fu2{animation-delay:.07s;}.fu3{animation-delay:.14s;}

                /* Response time mini bars */
                .rt-bar{display:flex;align-items:center;gap:8px;}
                .rt-lbl{font-size:9px;color:var(--t3);width:28px;}
                .rt-bg{flex:1;height:4px;background:var(--br);border-radius:3px;overflow:hidden;}
                .rt-fill{height:100%;background:linear-gradient(90deg,var(--neon),var(--neon2));border-radius:3px;box-shadow:0 0 5px var(--neon);}
                .rt-val{font-size:9px;color:var(--neon);width:28px;text-align:right;}
            `}</style>

            {/* Floating particles */}
            {[...Array(6)].map((_, i) => (
                <div key={i} className="sf-pt" style={{ left: `${8 + i * 16}%`, width: i % 2 === 0 ? '3px' : '2px', height: i % 2 === 0 ? '3px' : '2px', animationDuration: `${9 + i * 2}s`, animationDelay: `${i * 1.8}s` }} />
            ))}

            <div className="sf">
                <div className="sf-grid" />
                <div className="sf-g1" /><div className="sf-g2" />
                <Header />
                <div className="mx-6"><AnalysisLoadingDialog isOpen={showLoading} /></div>

                {/* HUD Strip */}
                <div className="hud sf-z">
                    {[
                        { l: 'System', v: 'ONLINE', c: '#00FFB2' }, { l: 'Model', v: 'v3.2.1', c: '#00D4FF' },
                        { l: 'Accuracy', v: '98.4%', c: '#00FFB2' }, { l: 'Breeds', v: '120+', c: '#00D4FF' },
                        { l: 'Status', v: hudPhases[scanPhase], c: '#00FFB2' },
                    ].map((x, i) => (
                        <div className="hud-it mono" key={i}>
                            <div className="hud-dot" style={{ background: x.c, boxShadow: `0 0 6px ${x.c}` }} />
                            <span>{x.l}</span>
                            <span className="hud-val mono">{x.v}</span>
                        </div>
                    ))}
                    <div className="hud-tick mono">▶ DOGLENS AI &nbsp; {timeStr}</div>
                </div>

                {/* QR Modal */}
                {showQRModal && (
                    <div className="qrov" onClick={() => setShowQRModal(false)}>
                        <div className="qrmod" onClick={e => e.stopPropagation()}>
                            <div className="qrc2 qrc2-tl" /><div className="qrc2 qrc2-tr" />
                            <div className="qrc2 qrc2-bl" /><div className="qrc2 qrc2-br" />
                            <button className="qrx" onClick={() => setShowQRModal(false)}><X size={13} /></button>
                            <div style={{ textAlign: 'center', marginBottom: 22 }}>
                                <div style={{ width: 48, height: 48, background: 'var(--nd)', border: '1.5px solid var(--bn)', borderRadius: 13, display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto 12px' }}>
                                    <Smartphone size={22} color="var(--neon)" />
                                </div>
                                <h2 style={{ color: 'var(--t1)', fontSize: 19, fontWeight: 800, margin: 0 }}>Install Mobile App</h2>
                                <p style={{ color: 'var(--t2)', fontSize: 12, marginTop: 5 }}>Scan to download the Android app</p>
                            </div>
                            <div style={{ display: 'flex', justifyContent: 'center', marginBottom: 20 }}>
                                <div style={{ background: '#fff', borderRadius: 12, padding: 9, boxShadow: '0 0 35px rgba(0,255,178,.18)' }}>
                                    <img src="/doglens_apk_qr.jpeg" alt="QR" style={{ width: 140, height: 140, display: 'block' }} />
                                </div>
                            </div>
                            <div style={{ background: 'var(--s1)', border: '1px solid var(--br)', borderRadius: 11, overflow: 'hidden', marginBottom: 16 }}>
                                {[{ icon: <Download size={12} />, text: 'Fast & Easy Installation' }, { icon: <Smartphone size={12} />, text: 'Available on Android' }, { icon: <Camera size={12} />, text: 'All Features On-The-Go' }].map((f, i) => (
                                    <div className="mf2" key={i} style={{ padding: '9px 13px' }}><div className="mf2-ic">{f.icon}</div>{f.text}</div>
                                ))}
                            </div>
                            <button className="btn-n" style={{ width: '100%' }} onClick={() => setShowQRModal(false)}>Close</button>
                        </div>
                    </div>
                )}

                {/* FAB */}
                <button className="qfab" onClick={() => setShowQRModal(true)} title="Install App"><QrCode size={19} /></button>

                {/* 3-col layout */}
                <div className="mgrid sf-z">

                    {/* ── LEFT SIDEBAR ── */}
                    <div className="col-l flex flex-col gap-4 fu fu1">

                        {/* System Status */}
                        <div className="sp">
                            <div className="sph"><div className="sph-ic"><Activity size={13} /></div><span className="sph-t mono">System Status</span></div>
                            <div className="spb">
                                <div className="slive"><div className="sdot" /><span className="stxt mono">Engine Online</span></div>
                                {[{ l: 'Model', v: 'v3.2.1', b: 100 }, { l: 'Accuracy', v: '98.4%', b: 98 }, { l: 'Latency', v: '~1.2s', b: 72 }].map((m, i) => (
                                    <div key={i}>
                                        <div className="mc"><span className="mc-l mono">{m.l}</span><span className="mc-v g mono">{m.v}</span></div>
                                        <div className="mbar"><div className="mbar-f" style={{ width: `${m.b}%`, animationDelay: `${i * .15}s` }} /></div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Activity Log */}
                        <div className="sp">
                            <div className="sph"><div className="sph-ic"><Cpu size={13} /></div><span className="sph-t mono">Activity Log</span></div>
                            <div className="spb">
                                {[{ t: '12:42', m: 'Model loaded', ok: true }, { t: '12:42', m: 'DB connected', ok: true }, { t: '12:41', m: 'Vet sync active', ok: true }, { t: '12:40', m: 'Cache cleared', ok: false }, { t: '12:38', m: 'Scan complete', ok: true }].map((l, i) => (
                                    <div className="le" key={i}><span className="le-t mono">{l.t}</span><span className={`le-m mono ${l.ok ? 'ok' : ''}`}>{l.m}</span></div>
                                ))}
                            </div>
                        </div>

                        {/* Navigation */}
                        <div className="sp">
                            <div className="sph"><div className="sph-ic"><Layers size={13} /></div><span className="sph-t mono">Navigation</span></div>
                            <div className="spb">
                                {[
                                    { icon: <ScanIcon size={13} />, label: 'New Scan', href: '/scan', active: true },
                                    { icon: <History size={13} />, label: 'Scan History', href: '/scanhistory', active: false },
                                    { icon: <BookOpen size={13} />, label: 'Breed Guide', href: '#', active: false },
                                    { icon: <Shield size={13} />, label: 'Vet Verify', href: '#', active: false },
                                ].map((n, i) => (
                                    <Link key={i} href={n.href}
                                        style={{ display: 'flex', alignItems: 'center', gap: 9, padding: '8px 10px', borderRadius: 9, textDecoration: 'none', background: n.active ? 'var(--nd)' : 'transparent', border: n.active ? '1px solid var(--bn)' : '1px solid transparent', color: n.active ? 'var(--neon)' : 'var(--t2)', fontSize: 13, fontWeight: 600, transition: 'all .2s' }}>
                                        {n.icon}{n.label}{n.active && <ChevronRight size={12} style={{ marginLeft: 'auto', opacity: .5 }} />}
                                    </Link>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* ── CENTER ── */}
                    <div className="col-c fu fu2">
                        {/* Header */}
                        <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', marginBottom: 20, gap: 14, flexWrap: 'wrap' }}>
                            <div>
                                <div className="pg-ey"><span className="pg-ed" /><span className="pg-et">AI Breed Detection</span></div>
                                <h1 style={{ color: 'var(--t1)', fontSize: 'clamp(22px,3.5vw,30px)', fontWeight: 800, margin: 0, letterSpacing: '-.025em', lineHeight: 1.1 }}>Scan Your Dog</h1>
                                <p style={{ color: 'var(--t2)', fontSize: 13, marginTop: 6, lineHeight: 1.6 }}>Upload a photo or use your camera to identify your dog's breed.</p>
                            </div>
                            <Link href="/scanhistory" className="hbtn"><History size={14} />Scan History<ChevronRight size={12} style={{ opacity: .4 }} /></Link>
                        </div>

                        {/* Alerts */}
                        {localError && showLocalError && (
                            <div className="al-e" style={{ opacity: showLocalError ? 1 : 0, transition: 'opacity .5s' }}>
                                <div style={{ display: 'flex', gap: 11, alignItems: 'flex-start' }}>
                                    <XCircle size={16} color="var(--red)" style={{ flexShrink: 0, marginTop: 2 }} />
                                    <div style={{ flex: 1 }}>
                                        <p style={{ color: '#FF8091', fontWeight: 700, margin: 0, fontSize: 13 }}>{localError.not_a_dog ? 'Not a Dog Detected' : 'Analysis Error'}</p>
                                        <p style={{ color: '#FF8091', fontSize: 12, margin: '3px 0 0', opacity: .8 }}>{localError.message}</p>
                                        {localError.not_a_dog && <button className="btn-n" onClick={handleReset} style={{ marginTop: 12, background: 'var(--red)', fontSize: 12, padding: '8px 18px' }}>Try Another Image</button>}
                                    </div>
                                </div>
                            </div>
                        )}
                        {cameraError && (
                            <div className="al-w">
                                <div style={{ display: 'flex', gap: 11, alignItems: 'flex-start' }}>
                                    <CircleAlert size={16} color="var(--amber)" style={{ flexShrink: 0, marginTop: 2 }} />
                                    <div><p style={{ color: '#FFD580', fontWeight: 700, margin: 0, fontSize: 13 }}>Camera Error</p><p style={{ color: '#FFD580', fontSize: 12, margin: '3px 0 0', opacity: .8 }}>{cameraError}</p></div>
                                </div>
                            </div>
                        )}

                        {/* Main card */}
                        <div className="sc">
                            <div className="scc scc-tl" /><div className="scc scc-tr" /><div className="scc scc-bl" /><div className="scc scc-br" />
                            <div className="ctb">
                                <div style={{ display: 'flex', gap: 6 }}><div className="ctbd ctbd-r" /><div className="ctbd ctbd-y" /><div className="ctbd ctbd-g" /></div>
                                <span className="ctb-lbl mono">doglens://scan</span>
                                <div className="ctb-st mono"><span className="ctb-sd" />{processing ? 'PROCESSING' : preview ? 'IMAGE LOADED' : showCamera ? 'CAMERA ACTIVE' : 'AWAITING INPUT'}</div>
                            </div>
                            <form onSubmit={handleSubmit} style={{ padding: '22px 22px 26px' }}>
                                {!preview && !showCamera ? (
                                    <>
                                        <div className={`dz ${isDragging ? 'drag' : ''}`} onClick={() => fileInputRef.current?.click()} onDragOver={e => { e.preventDefault(); setIsDragging(true); }} onDragLeave={() => setIsDragging(false)} onDrop={e => { e.preventDefault(); setIsDragging(false); const f = e.dataTransfer.files?.[0]; if (f) processImageFile(f); }}>
                                            <div className="dz-beam" /><div className="dz-sl dz-sl-l" /><div className="dz-sl dz-sl-r" />
                                            <input ref={fileInputRef} type="file" accept="image/*" style={{ display: 'none' }} onChange={(e: ChangeEvent<HTMLInputElement>) => { const f = e.target.files?.[0]; if (f) processImageFile(f); }} />
                                            <div className="dz-icon"><div className="dz-r1" /><div className="dz-r2" /><div className="dz-ibg" /><div className="dz-inner"><Upload size={26} /></div></div>
                                            <div style={{ textAlign: 'center' }}>
                                                <p style={{ color: 'var(--t1)', fontWeight: 700, fontSize: 15, margin: 0 }}>Drop your dog image here</p>
                                                <p style={{ color: 'var(--t2)', fontSize: 13, marginTop: 4 }}>or <span style={{ color: 'var(--neon)', fontWeight: 700 }}>click to browse</span></p>
                                            </div>
                                            <p className="mono" style={{ color: 'var(--t3)', fontSize: 10, margin: 0, letterSpacing: '.1em' }}>ALL FORMATS · MAX 10MB</p>
                                        </div>
                                        <div className="dvd"><div className="dvd-l" /><span className="dvd-t mono">or use camera</span><div className="dvd-l" /></div>
                                        <button type="button" onClick={startCamera} className="btn-cam"><Camera size={16} />Activate Camera</button>
                                        <p className="mono" style={{ textAlign: 'center', color: 'var(--t3)', fontSize: 9, marginTop: 7, letterSpacing: '.1em' }}>CHROME · EDGE · SAFARI · FIREFOX</p>
                                        {errors.image && <p style={{ color: 'var(--red)', fontSize: 12, marginTop: 10, textAlign: 'center' }}>{errors.image}</p>}
                                        <div className="tips">
                                            <div style={{ display: 'flex', alignItems: 'center', gap: 7, marginBottom: 10 }}>
                                                <Zap size={13} color="var(--neon)" />
                                                <span className="mono" style={{ fontSize: 10, fontWeight: 700, letterSpacing: '.12em', textTransform: 'uppercase', color: 'var(--neon)' }}>Capture Tips</span>
                                                <Shield size={11} color="var(--t3)" style={{ marginLeft: 'auto' }} />
                                            </div>
                                            {['Ensure your dog is clearly visible', 'Use good lighting, no harsh shadows', 'Center your dog, avoid clutter', 'Front or side angles work best', 'Only dog images are accepted'].map((t, i) => (
                                                <div className="tip-r" key={i}><span className="tip-n mono">0{i + 1}</span><span className={`tip-t ${i === 4 ? 'b' : ''}`}>{t}</span></div>
                                            ))}
                                        </div>
                                    </>
                                ) : showCamera ? (
                                    <div>
                                        <div className="cf">
                                            <video ref={videoRef} autoPlay playsInline muted style={{ display: 'block', width: '100%', maxHeight: '60vh', objectFit: 'cover', background: '#000' }} />
                                            <canvas ref={canvasRef} style={{ display: 'none' }} />
                                            <div style={{ position: 'absolute', inset: 0, pointerEvents: 'none', zIndex: 3 }}>
                                                <div className="csw" />
                                                <div className="chc-corner chc-tl" /><div className="chc-corner chc-tr" /><div className="chc-corner chc-bl" /><div className="chc-corner chc-br" />
                                                <div className="cxh"><div className="ch" /><div className="cv" /><div className="cc" /></div>
                                                <div className="clbl">● REC · {facingMode === 'environment' ? 'REAR' : 'FRONT'} CAM</div>
                                            </div>
                                            <button type="button" onClick={switchCamera} className="csb"><SwitchCamera size={17} /></button>
                                        </div>
                                        <div style={{ display: 'flex', gap: 10, marginTop: 16 }}>
                                            <button type="button" onClick={capturePhoto} className="btn-cap"><ScanIcon size={17} />Capture & Scan</button>
                                            <button type="button" onClick={stopCamera} className="btn-o"><X size={15} />Cancel</button>
                                        </div>
                                    </div>
                                ) : (
                                    <div>
                                        <div className="pf" style={{ position: 'relative' }}>
                                            {particleActive && (
                                                <div className="pb-wrap">
                                                    {[...Array(12)].map((_, i) => {
                                                        const a = (i / 12) * 360; const d = 70 + Math.random() * 55;
                                                        return <div className="pb-d" key={i} style={{ '--dx': `${Math.cos(a * Math.PI / 180) * d}px`, '--dy': `${Math.sin(a * Math.PI / 180) * d}px` } as any} />;
                                                    })}
                                                </div>
                                            )}
                                            <img src={preview || ''} alt="Preview" style={{ display: 'block', maxHeight: 380, width: '100%', objectFit: 'contain', background: 'var(--s1)' }} />
                                            <div style={{ position: 'absolute', inset: 0, pointerEvents: 'none' }}>
                                                <div className="p-sl" /><div className="p-sw" />
                                                <div className="phc phc-tl" /><div className="phc phc-tr" /><div className="phc phc-bl" /><div className="phc phc-br" />
                                                <div className="pbadge">
                                                    <span className="pmb mono">IMAGE LOADED</span>
                                                    {fileInfo && <span className="pmb mono" style={{ fontSize: 8 }}>{fileInfo.split('(')[1]?.replace(')', '') || ''}</span>}
                                                </div>
                                            </div>
                                        </div>
                                        {fileInfo && <p className="mono" style={{ textAlign: 'center', color: 'var(--t3)', fontSize: 10, marginTop: 7, letterSpacing: '.06em' }}>{fileInfo}</p>}
                                        <div style={{ display: 'flex', gap: 10, marginTop: 16 }}>
                                            <button type="submit" className="btn-n" disabled={processing} style={{ flex: 1, padding: '13px 20px' }}>
                                                <ScanIcon size={16} />{processing ? 'Analyzing...' : 'Analyze Image'}
                                            </button>
                                            <button type="button" onClick={handleReset} className="btn-o" disabled={processing}><X size={15} />Reset</button>
                                        </div>
                                    </div>
                                )}
                            </form>
                        </div>
                    </div>

                    {/* ── RIGHT SIDEBAR ── */}
                    <div className="col-r flex flex-col gap-4 fu fu3">

                        {/* Top Breeds */}
                        <div className="sp">
                            <div className="sph"><div className="sph-ic"><TrendingUp size={13} /></div><span className="sph-t mono">Top Breeds</span></div>
                            <div className="spb">
                                {recentBreeds.map((b, i) => (
                                    <div className="bi" key={i}>
                                        <span className="bi-n mono">#{i + 1}</span>
                                        <span className="bi-name">{b}</span>
                                        <div className="bi-bar"><div className="bi-bf" style={{ width: `${100 - i * 15}%` }} /></div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Scan Stats */}
                        <div className="sp">
                            <div className="sph"><div className="sph-ic"><Activity size={13} /></div><span className="sph-t mono">Global Stats</span></div>
                            <div className="spb">
                                {[{ l: 'Total Scans', v: '12,841', icon: <Target size={12} /> }, { l: 'Verified', v: '10,290', icon: <Shield size={12} /> }, { l: 'Avg Score', v: '94.2%', icon: <Activity size={12} /> }, { l: 'Uptime', v: '99.9%', icon: <Wifi size={12} /> }].map((s, i) => (
                                    <div className="mc" key={i}>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: 7 }}><div style={{ color: 'var(--t3)' }}>{s.icon}</div><span className="mc-l mono">{s.l}</span></div>
                                        <span className="mc-v mono">{s.v}</span>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* How it works */}
                        <div className="sp">
                            <div className="sph"><div className="sph-ic"><Eye size={13} /></div><span className="sph-t mono">How It Works</span></div>
                            <div className="spb">
                                {[{ n: '01', t: 'Upload or capture a photo' }, { n: '02', t: 'AI analyzes breed features in ~1.2s' }, { n: '03', t: 'Results ranked by confidence' }, { n: '04', t: 'Vet verification adds accuracy' }].map((s, i) => (
                                    <div key={i} style={{ display: 'flex', gap: 10, alignItems: 'flex-start', padding: '7px 0', borderBottom: i < 3 ? '1px solid var(--br)' : 'none' }}>
                                        <span className="mono" style={{ fontSize: 10, color: 'var(--neon)', opacity: .7, flexShrink: 0, marginTop: 2, width: 18 }}>{s.n}</span>
                                        <span style={{ fontSize: 12, color: 'var(--t2)', lineHeight: 1.5 }}>{s.t}</span>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Response time */}
                        <div className="sp">
                            <div className="sph"><div className="sph-ic"><Clock size={13} /></div><span className="sph-t mono">Response Time</span></div>
                            <div className="spb">
                                {[95, 78, 88, 92, 70, 85, 90].map((h, i) => (
                                    <div className="rt-bar" key={i}>
                                        <span className="rt-lbl mono">T-{6 - i}m</span>
                                        <div className="rt-bg"><div className="rt-fill" style={{ width: `${h}%`, animation: `bg 1.5s ease-out ${i * .08}s both` }} /></div>
                                        <span className="rt-val mono">{(h / 100 * 2).toFixed(1)}s</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </>
    );
};

export default Scan;