import AnalysisLoadingDialog from '@/components/AnalysisLoadingDialog';
import Header from '@/components/header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Link, useForm, usePage } from '@inertiajs/react';
import {
    Camera,
    CircleAlert,
    Download,
    QrCode,
    Smartphone,
    SwitchCamera,
    X,
    XCircle,
    Scan as ScanIcon,
    History,
    Upload,
    Zap,
    Activity,
    Cpu,
    Eye,
    Shield,
    ChevronRight,
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

interface PageProps {
    flash?: {
        success?: SuccessFlash;
        error?: ErrorFlash;
    };
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

    const { data, setData, post, processing, errors, reset } = useForm({
        image: null as File | null,
    });

    const [preview, setPreview] = useState<string | null>(null);
    const [fileInfo, setFileInfo] = useState<string>('');
    const [showLoading, setShowLoading] = useState(false);
    const [showCamera, setShowCamera] = useState(false);
    const [stream, setStream] = useState<MediaStream | null>(null);
    const [facingMode, setFacingMode] = useState<'user' | 'environment'>('environment');
    const [cameraError, setCameraError] = useState<string | null>(null);
    const [localError, setLocalError] = useState<ErrorFlash | null>(null);
    const [showLocalError, setShowLocalError] = useState(false);
    const [isDragging, setIsDragging] = useState(false);
    const [showQRModal, setShowQRModal] = useState(false);
    const [scanPhase, setScanPhase] = useState(0); // for multi-phase scan animation
    const [particleActive, setParticleActive] = useState(false);

    const isCameraSupported = () => {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) return false;
        const ua = navigator.userAgent.toLowerCase();
        return /chrome|chromium|crios|edg|safari|firefox|fxios/.test(ua);
    };

    useEffect(() => {
        if (error?.message) {
            setShowLoading(false);
            setLocalError(error);
            setShowLocalError(true);
            const timer = setTimeout(() => {
                setShowLocalError(false);
                setTimeout(() => setLocalError(null), 500);
            }, 7000);
            return () => clearTimeout(timer);
        }
    }, [error]);

    useEffect(() => { if (success) setShowLoading(false); }, [success]);

    useEffect(() => {
        if (cameraError) {
            const t = setTimeout(() => setCameraError(null), 5000);
            return () => clearTimeout(t);
        }
    }, [cameraError]);

    useEffect(() => {
        return () => { if (stream) stream.getTracks().forEach(t => t.stop()); };
    }, [stream]);

    // Cycle scan phase for animated hud elements
    useEffect(() => {
        const t = setInterval(() => setScanPhase(p => (p + 1) % 4), 2000);
        return () => clearInterval(t);
    }, []);

    const validateImageFile = (file: File): string | null => {
        if (file.size > 10 * 1024 * 1024) return 'File is too large. Maximum size is 10MB.';
        return null;
    };

    const handleFileChange = (e: ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) processImageFile(file);
    };

    const processImageFile = (file: File) => {
        const validationError = validateImageFile(file);
        if (validationError) { alert(validationError); return; }
        const objectUrl = URL.createObjectURL(file);
        setPreview(objectUrl);
        const img = new Image();
        img.onload = () => {
            setFileInfo(`${file.name} (${(file.size / 1024).toFixed(1)}KB, ${img.width}×${img.height})`);
            URL.revokeObjectURL(objectUrl);
        };
        img.onerror = () => {
            setFileInfo(`${file.name} (${(file.size / 1024).toFixed(1)}KB)`);
            URL.revokeObjectURL(objectUrl);
        };
        img.src = objectUrl;
        setData('image', file);
        stopCamera();
        setParticleActive(true);
        setTimeout(() => setParticleActive(false), 1200);
    };

    const triggerFileInput = () => fileInputRef.current?.click();

    const startCamera = async () => {
        if (!isCameraSupported()) {
            alert('Camera feature is only available on Chrome, Edge, Safari, and Firefox browsers.');
            return;
        }
        try {
            setCameraError(null);
            if (stream) { stream.getTracks().forEach(t => t.stop()); setStream(null); }
            const mediaStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode, width: { ideal: 1920 }, height: { ideal: 1080 } },
                audio: false,
            });
            setStream(mediaStream);
            setTimeout(() => {
                if (videoRef.current && mediaStream.active) {
                    videoRef.current.srcObject = mediaStream;
                    videoRef.current.play().catch(console.error);
                }
            }, 100);
            setShowCamera(true);
        } catch (err: any) {
            let msg = 'Unable to access camera. ';
            if (err.name === 'NotAllowedError') msg += 'Please allow camera permissions.';
            else if (err.name === 'NotFoundError') msg += 'No camera found on this device.';
            else if (err.name === 'NotReadableError') msg += 'Camera is in use by another application.';
            else msg += 'Please check permissions or use file upload instead.';
            setCameraError(msg);
            setShowCamera(false);
        }
    };

    const stopCamera = () => {
        if (stream) { stream.getTracks().forEach(t => t.stop()); setStream(null); }
        if (videoRef.current) videoRef.current.srcObject = null;
        setShowCamera(false);
        setCameraError(null);
    };

    const switchCamera = async () => {
        const newMode = facingMode === 'user' ? 'environment' : 'user';
        setFacingMode(newMode);
        if (stream) { stream.getTracks().forEach(t => t.stop()); setStream(null); }
        setTimeout(async () => {
            try {
                const mediaStream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: newMode, width: { ideal: 1920 }, height: { ideal: 1080 } },
                    audio: false,
                });
                setStream(mediaStream);
                if (videoRef.current && mediaStream.active) {
                    videoRef.current.srcObject = mediaStream;
                    videoRef.current.play().catch(console.error);
                }
            } catch {
                setCameraError('Failed to switch camera.');
                setFacingMode(facingMode === 'user' ? 'environment' : 'user');
            }
        }, 200);
    };

    const capturePhoto = () => {
        if (videoRef.current && canvasRef.current) {
            const video = videoRef.current;
            const canvas = canvasRef.current;
            if (video.readyState !== video.HAVE_ENOUGH_DATA) {
                alert('Camera is still loading. Please wait.');
                return;
            }
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const ctx = canvas.getContext('2d');
            if (ctx) {
                ctx.drawImage(video, 0, 0);
                canvas.toBlob((blob) => {
                    if (blob) {
                        const file = new File([blob], `capture-${Date.now()}.jpg`, { type: 'image/jpeg' });
                        processImageFile(file);
                    }
                }, 'image/jpeg', 0.95);
            }
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!data.image) { alert('Please select an image first'); return; }
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

    useEffect(() => {
        return () => { if (preview) URL.revokeObjectURL(preview); };
    }, [preview]);

    const handleDragOver = (e: React.DragEvent) => { e.preventDefault(); setIsDragging(true); };
    const handleDragLeave = () => setIsDragging(false);
    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        setIsDragging(false);
        const file = e.dataTransfer.files?.[0];
        if (file) processImageFile(file);
    };

    const hudLabels = ['INITIALIZING', 'SCANNING', 'PROCESSING', 'READY'];
    const hudColors = ['#FFB830', '#00FFB2', '#00D4FF', '#00FFB2'];

    return (
        <>
            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Mono:ital,wght@0,300;0,400;0,500;1,300&display=swap');

                /* ── Variables ─────────────────────────────────── */
                :root {
                    --neon: #00FFB2;
                    --neon2: #00D4FF;
                    --neon-dim: rgba(0,255,178,0.1);
                    --neon-glow: 0 0 20px rgba(0,255,178,0.35), 0 0 60px rgba(0,255,178,0.12);
                    --neon2-glow: 0 0 20px rgba(0,212,255,0.3);
                    --red: #FF4757;
                    --amber: #FFB830;

                    /* Dark mode surfaces */
                    --bg: #080A0D;
                    --s1: #0E1115;
                    --s2: #141720;
                    --s3: #1C2028;
                    --border: rgba(255,255,255,0.055);
                    --border-neon: rgba(0,255,178,0.22);
                    --t1: #EDF0F5;
                    --t2: #6B7585;
                    --t3: #3D4554;
                    --card-shadow: 0 8px 40px rgba(0,0,0,0.5), 0 1px 0 rgba(255,255,255,0.04) inset;
                }

                /* Light mode overrides */
                .light :root,
                :root.light,
                [data-theme="light"] {
                    --bg: #F0F2F7;
                    --s1: #FAFBFD;
                    --s2: #FFFFFF;
                    --s3: #F4F5F9;
                    --border: rgba(0,0,0,0.07);
                    --border-neon: rgba(0,180,120,0.3);
                    --t1: #0F1117;
                    --t2: #5A6275;
                    --t3: #9AA0B0;
                    --neon: #00B87A;
                    --neon2: #0099CC;
                    --neon-dim: rgba(0,184,122,0.08);
                    --neon-glow: 0 0 16px rgba(0,184,122,0.25), 0 0 40px rgba(0,184,122,0.08);
                    --card-shadow: 0 4px 24px rgba(0,0,0,0.08), 0 1px 0 rgba(255,255,255,0.8) inset;
                }

                /* Apply light vars when html has class="light" */
                html.light {
                    --bg: #F0F2F7;
                    --s1: #FAFBFD;
                    --s2: #FFFFFF;
                    --s3: #F4F5F9;
                    --border: rgba(0,0,0,0.07);
                    --border-neon: rgba(0,180,120,0.3);
                    --t1: #0F1117;
                    --t2: #5A6275;
                    --t3: #9AA0B0;
                    --neon: #00B87A;
                    --neon2: #0099CC;
                    --neon-dim: rgba(0,184,122,0.08);
                    --neon-glow: 0 0 16px rgba(0,184,122,0.25), 0 0 40px rgba(0,184,122,0.08);
                    --card-shadow: 0 4px 24px rgba(0,0,0,0.08), 0 1px 0 rgba(255,255,255,0.8) inset;
                }
                html.dark {
                    --bg: #080A0D;
                    --s1: #0E1115;
                    --s2: #141720;
                    --s3: #1C2028;
                    --border: rgba(255,255,255,0.055);
                    --border-neon: rgba(0,255,178,0.22);
                    --t1: #EDF0F5;
                    --t2: #6B7585;
                    --t3: #3D4554;
                    --neon: #00FFB2;
                    --neon2: #00D4FF;
                    --neon-dim: rgba(0,255,178,0.1);
                    --neon-glow: 0 0 20px rgba(0,255,178,0.35), 0 0 60px rgba(0,255,178,0.12);
                    --card-shadow: 0 8px 40px rgba(0,0,0,0.5), 0 1px 0 rgba(255,255,255,0.04) inset;
                }

                /* ── Base ─────────────────────────────────────── */
                .sp * { font-family: 'Syne', sans-serif !important; box-sizing: border-box; }
                .sp .mono { font-family: 'DM Mono', monospace !important; }
                .sp { background: var(--bg); min-height: 100vh; position: relative; overflow-x: hidden; transition: background 0.3s; }

                /* ── Animated Background ───────────────────────── */
                .sp-grid {
                    position: fixed; inset: 0; pointer-events: none; z-index: 0;
                    background-image:
                        linear-gradient(var(--border) 1px, transparent 1px),
                        linear-gradient(90deg, var(--border) 1px, transparent 1px);
                    background-size: 56px 56px;
                    mask-image: radial-gradient(ellipse 90% 70% at 50% 0%, black 20%, transparent 90%);
                }

                .sp-aurora {
                    position: fixed; pointer-events: none; z-index: 0;
                    border-radius: 50%; filter: blur(80px); opacity: 0.12;
                    animation: aurora-drift 12s ease-in-out infinite alternate;
                }
                .sp-aurora-1 {
                    width: 700px; height: 400px;
                    top: -150px; left: -100px;
                    background: radial-gradient(ellipse, var(--neon), transparent 70%);
                    animation-delay: 0s;
                }
                .sp-aurora-2 {
                    width: 500px; height: 300px;
                    top: -100px; right: -80px;
                    background: radial-gradient(ellipse, var(--neon2), transparent 70%);
                    animation-delay: -4s; opacity: 0.07;
                }
                .sp-aurora-3 {
                    width: 400px; height: 400px;
                    bottom: 10%; left: 20%;
                    background: radial-gradient(ellipse, var(--neon), transparent 70%);
                    animation-delay: -8s; opacity: 0.04;
                }
                @keyframes aurora-drift {
                    0% { transform: translateX(0) scale(1); }
                    100% { transform: translateX(60px) scale(1.1); }
                }

                /* Floating particles */
                .sp-particle {
                    position: fixed; pointer-events: none; z-index: 0;
                    width: 2px; height: 2px; border-radius: 50%;
                    background: var(--neon);
                    box-shadow: 0 0 6px var(--neon);
                    animation: float-particle linear infinite;
                    opacity: 0;
                }
                @keyframes float-particle {
                    0% { transform: translateY(100vh) translateX(0); opacity: 0; }
                    10% { opacity: 0.6; }
                    90% { opacity: 0.3; }
                    100% { transform: translateY(-10vh) translateX(30px); opacity: 0; }
                }

                /* ── Content ─────────────────────────────────── */
                .sp-content { position: relative; z-index: 1; }

                /* ── HUD Strip ───────────────────────────────── */
                .hud-strip {
                    display: flex; align-items: center; gap: 0;
                    background: var(--s1);
                    border-bottom: 1px solid var(--border);
                    padding: 6px 24px;
                    overflow-x: auto; scrollbar-width: none;
                    position: relative;
                }
                .hud-strip::-webkit-scrollbar { display: none; }
                .hud-item {
                    display: flex; align-items: center; gap: 6px;
                    padding: 4px 14px;
                    font-size: 10px; font-weight: 600;
                    letter-spacing: 0.1em; text-transform: uppercase;
                    color: var(--t2);
                    border-right: 1px solid var(--border);
                    white-space: nowrap;
                }
                .hud-item:last-child { border-right: none; }
                .hud-dot {
                    width: 5px; height: 5px; border-radius: 50%;
                    flex-shrink: 0;
                }
                .hud-dot.active { box-shadow: 0 0 8px currentColor; }
                .hud-value {
                    font-family: 'DM Mono', monospace !important;
                    font-size: 10px; color: var(--neon);
                    font-weight: 500;
                }
                .hud-ticker {
                    margin-left: auto;
                    display: flex; align-items: center; gap: 8px;
                    font-family: 'DM Mono', monospace !important;
                    font-size: 10px; color: var(--t3);
                    animation: ticker-fade 1s ease-in-out infinite alternate;
                    padding-left: 14px;
                    white-space: nowrap;
                }
                @keyframes ticker-fade {
                    from { opacity: 0.4; }
                    to { opacity: 1; }
                }

                /* ── Page Header ─────────────────────────────── */
                .pg-header {
                    display: flex; align-items: flex-start;
                    justify-content: space-between;
                    margin-bottom: 28px; gap: 16px;
                    flex-wrap: wrap;
                }
                .pg-eyebrow {
                    display: inline-flex; align-items: center; gap: 8px;
                    padding: 4px 12px 4px 8px;
                    background: var(--neon-dim);
                    border: 1px solid var(--border-neon);
                    border-radius: 100px;
                    margin-bottom: 10px;
                }
                .pg-eyebrow-dot {
                    width: 7px; height: 7px; border-radius: 50%;
                    background: var(--neon);
                    box-shadow: 0 0 8px var(--neon);
                    animation: eyebrow-pulse 2s ease-in-out infinite;
                }
                @keyframes eyebrow-pulse {
                    0%, 100% { transform: scale(1); box-shadow: 0 0 8px var(--neon); }
                    50% { transform: scale(1.3); box-shadow: 0 0 16px var(--neon), 0 0 30px var(--neon); }
                }
                .pg-eyebrow-text {
                    font-size: 10px; font-weight: 700;
                    letter-spacing: 0.12em; text-transform: uppercase;
                    color: var(--neon);
                }
                .pg-title {
                    font-size: clamp(24px, 4vw, 32px);
                    font-weight: 800; letter-spacing: -0.025em;
                    color: var(--t1); margin: 0; line-height: 1.1;
                }
                .pg-sub {
                    font-size: 14px; color: var(--t2);
                    margin: 6px 0 0; line-height: 1.6; font-weight: 400;
                }

                /* History link */
                .history-btn {
                    display: inline-flex; align-items: center; gap: 6px;
                    padding: 9px 16px;
                    background: var(--s2);
                    border: 1px solid var(--border);
                    border-radius: 10px;
                    color: var(--t2); font-size: 13px; font-weight: 600;
                    text-decoration: none; white-space: nowrap;
                    transition: all 0.2s; cursor: pointer;
                    flex-shrink: 0;
                }
                .history-btn:hover {
                    border-color: var(--border-neon);
                    color: var(--neon);
                    background: var(--neon-dim);
                    box-shadow: var(--neon-glow);
                }

                /* ── Stat Row ────────────────────────────────── */
                .stat-row {
                    display: grid; grid-template-columns: repeat(3, 1fr);
                    gap: 10px; margin-bottom: 20px;
                }
                .stat-card {
                    background: var(--s2);
                    border: 1px solid var(--border);
                    border-radius: 14px;
                    padding: 14px 16px;
                    position: relative; overflow: hidden;
                    transition: all 0.25s;
                }
                .stat-card::before {
                    content: '';
                    position: absolute; top: 0; left: 0; right: 0; height: 2px;
                    background: linear-gradient(90deg, transparent, var(--neon), transparent);
                    opacity: 0; transition: opacity 0.3s;
                }
                .stat-card:hover::before { opacity: 1; }
                .stat-card:hover { border-color: var(--border-neon); transform: translateY(-2px); box-shadow: var(--neon-glow); }
                .stat-icon {
                    width: 32px; height: 32px;
                    border-radius: 9px;
                    background: var(--neon-dim);
                    border: 1px solid var(--border-neon);
                    display: flex; align-items: center; justify-content: center;
                    margin-bottom: 10px; color: var(--neon);
                }
                .stat-label {
                    font-size: 10px; font-weight: 700;
                    letter-spacing: 0.1em; text-transform: uppercase;
                    color: var(--t2); margin-bottom: 3px;
                }
                .stat-value {
                    font-family: 'DM Mono', monospace !important;
                    font-size: 18px; font-weight: 500;
                    color: var(--t1);
                }
                .stat-sub { font-size: 10px; color: var(--t2); margin-top: 2px; }
                .stat-bar-bg {
                    position: absolute; bottom: 0; left: 0; right: 0; height: 2px;
                    background: var(--border);
                }
                .stat-bar-fill {
                    height: 100%; background: var(--neon);
                    border-radius: 2px;
                    box-shadow: 0 0 8px var(--neon);
                    animation: stat-grow 2s ease-out forwards;
                }
                @keyframes stat-grow {
                    from { width: 0%; }
                }

                /* ── Main Scan Card ──────────────────────────── */
                .scan-card {
                    background: var(--s2);
                    border: 1px solid var(--border);
                    border-radius: 20px;
                    position: relative; overflow: hidden;
                    box-shadow: var(--card-shadow);
                    transition: all 0.3s;
                    max-width: 860px;
                    margin: 0 auto;
                }
                .scan-card.camera-open { max-width: 1100px; }

                /* Corner brackets */
                .sc-corner {
                    position: absolute; width: 18px; height: 18px;
                    border-color: var(--neon); border-style: solid;
                    opacity: 0.5; pointer-events: none; z-index: 2;
                    transition: opacity 0.3s;
                }
                .scan-card:hover .sc-corner { opacity: 0.9; }
                .sc-tl { top: 10px; left: 10px; border-width: 2px 0 0 2px; }
                .sc-tr { top: 10px; right: 10px; border-width: 2px 2px 0 0; }
                .sc-bl { bottom: 10px; left: 10px; border-width: 0 0 2px 2px; }
                .sc-br { bottom: 10px; right: 10px; border-width: 0 2px 2px 0; }

                /* Card inner top bar */
                .card-topbar {
                    padding: 12px 20px;
                    border-bottom: 1px solid var(--border);
                    display: flex; align-items: center; gap: 10px;
                    background: var(--s1);
                }
                .topbar-dots { display: flex; gap: 6px; }
                .td { width: 9px; height: 9px; border-radius: 50%; }
                .td-r { background: #FF5F57; }
                .td-y { background: #FEBC2E; }
                .td-g { background: var(--neon); box-shadow: 0 0 6px var(--neon); animation: td-blink 3s ease-in-out infinite; }
                @keyframes td-blink {
                    0%, 90%, 100% { opacity: 1; }
                    95% { opacity: 0.3; }
                }
                .topbar-label {
                    font-family: 'DM Mono', monospace !important;
                    font-size: 11px; color: var(--t2);
                    margin-left: 4px;
                }
                .topbar-status {
                    margin-left: auto;
                    font-family: 'DM Mono', monospace !important;
                    font-size: 10px; color: var(--neon);
                    display: flex; align-items: center; gap: 6px;
                }
                .topbar-status-dot {
                    width: 5px; height: 5px; border-radius: 50%;
                    background: var(--neon); box-shadow: 0 0 6px var(--neon);
                    animation: eyebrow-pulse 2s infinite;
                }

                /* ── Drop Zone ───────────────────────────────── */
                .drop-zone {
                    border: 1.5px dashed var(--border-neon);
                    border-radius: 16px;
                    background: transparent;
                    min-height: 240px;
                    display: flex; flex-direction: column;
                    align-items: center; justify-content: center; gap: 18px;
                    cursor: pointer; position: relative; overflow: hidden;
                    transition: all 0.3s;
                    padding: 48px 20px;
                }
                .drop-zone:hover, .drop-zone.dz-drag {
                    border-color: var(--neon);
                    background: var(--neon-dim);
                    box-shadow: inset 0 0 60px rgba(0,255,178,0.04), var(--neon-glow);
                }
                .drop-zone .dz-scan-line {
                    position: absolute; left: 0; top: -4px; width: 100%; height: 3px;
                    background: linear-gradient(90deg, transparent 0%, var(--neon) 50%, transparent 100%);
                    filter: blur(1px);
                    opacity: 0;
                    transition: opacity 0.3s;
                }
                .drop-zone:hover .dz-scan-line,
                .drop-zone.dz-drag .dz-scan-line {
                    opacity: 1;
                    animation: dz-sweep 1.8s linear infinite;
                }
                @keyframes dz-sweep {
                    from { top: -4px; }
                    to { top: 100%; }
                }
                /* Side glow lines on hover */
                .drop-zone .dz-left-line,
                .drop-zone .dz-right-line {
                    position: absolute; width: 2px; top: 0; height: 100%;
                    background: linear-gradient(180deg, transparent, var(--neon), transparent);
                    opacity: 0; transition: opacity 0.4s;
                }
                .drop-zone .dz-left-line { left: 0; }
                .drop-zone .dz-right-line { right: 0; }
                .drop-zone:hover .dz-left-line,
                .drop-zone:hover .dz-right-line,
                .drop-zone.dz-drag .dz-left-line,
                .drop-zone.dz-drag .dz-right-line { opacity: 0.5; }

                /* ── Upload Icon ─────────────────────────────── */
                .dz-icon-wrap {
                    position: relative; width: 80px; height: 80px;
                    display: flex; align-items: center; justify-content: center;
                }
                .dz-icon-bg {
                    position: absolute; inset: 0; border-radius: 50%;
                    background: var(--neon-dim);
                    border: 1.5px solid var(--border-neon);
                    transition: all 0.3s;
                }
                .dz-icon-ring-1 {
                    position: absolute; inset: -10px; border-radius: 50%;
                    border: 1px solid rgba(0,255,178,0.12);
                    animation: ring-pulse 2.5s ease-out infinite;
                }
                .dz-icon-ring-2 {
                    position: absolute; inset: -22px; border-radius: 50%;
                    border: 1px solid rgba(0,255,178,0.06);
                    animation: ring-pulse 2.5s ease-out infinite 0.6s;
                }
                @keyframes ring-pulse {
                    0% { transform: scale(0.9); opacity: 0.7; }
                    70% { transform: scale(1.1); opacity: 0; }
                    100% { transform: scale(1.1); opacity: 0; }
                }
                .dz-icon-bg-inner {
                    position: relative; z-index: 1;
                    color: var(--neon);
                    transition: transform 0.3s;
                }
                .drop-zone:hover .dz-icon-bg-inner { transform: scale(1.1) translateY(-2px); }
                .drop-zone:hover .dz-icon-bg {
                    background: rgba(0,255,178,0.15);
                    border-color: var(--neon);
                    box-shadow: var(--neon-glow);
                }

                /* ── Divider ─────────────────────────────────── */
                .dz-divider {
                    display: flex; align-items: center; gap: 12px;
                    margin: 14px 0;
                }
                .dz-divider-line { flex: 1; height: 1px; background: var(--border); }
                .dz-divider-text {
                    font-size: 10px; font-weight: 700;
                    color: var(--t3); letter-spacing: 0.12em;
                    text-transform: uppercase;
                    font-family: 'DM Mono', monospace !important;
                }

                /* ── Buttons ─────────────────────────────────── */
                .btn-neon {
                    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
                    background: var(--neon);
                    color: #000;
                    font-weight: 700; font-size: 14px; letter-spacing: 0.04em;
                    border: none; border-radius: 11px;
                    padding: 13px 28px;
                    cursor: pointer;
                    position: relative; overflow: hidden;
                    transition: all 0.25s;
                    text-decoration: none;
                }
                .btn-neon::before {
                    content: '';
                    position: absolute; top: 0; left: -100%;
                    width: 60%; height: 100%;
                    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
                    transform: skewX(-20deg);
                    transition: left 0.5s;
                }
                .btn-neon:hover { box-shadow: var(--neon-glow); transform: translateY(-2px); }
                .btn-neon:hover::before { left: 160%; }
                .btn-neon:disabled { opacity: 0.45; cursor: not-allowed; transform: none; box-shadow: none; }

                .btn-outline {
                    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
                    background: var(--s1); color: var(--t2);
                    font-weight: 600; font-size: 14px;
                    border: 1px solid var(--border); border-radius: 11px;
                    padding: 13px 28px; cursor: pointer;
                    transition: all 0.2s;
                }
                .btn-outline:hover {
                    border-color: var(--border-neon);
                    color: var(--neon);
                    background: var(--neon-dim);
                }

                .btn-camera {
                    display: flex; align-items: center; justify-content: center; gap: 8px;
                    width: 100%;
                    background: var(--s1);
                    border: 1.5px solid var(--border-neon);
                    border-radius: 11px;
                    color: var(--neon); font-weight: 700; font-size: 14px;
                    padding: 13px 20px; cursor: pointer;
                    transition: all 0.25s; position: relative; overflow: hidden;
                }
                .btn-camera::after {
                    content: '';
                    position: absolute; inset: 0;
                    background: linear-gradient(135deg, var(--neon-dim), transparent);
                    opacity: 0; transition: opacity 0.3s;
                }
                .btn-camera:hover {
                    border-color: var(--neon);
                    background: var(--neon-dim);
                    box-shadow: var(--neon-glow);
                }
                .btn-camera:hover::after { opacity: 1; }

                /* ── Tips ────────────────────────────────────── */
                .tips-panel {
                    background: rgba(0,255,178,0.03);
                    border: 1px solid var(--border-neon);
                    border-radius: 14px;
                    padding: 16px 18px;
                    margin-top: 20px;
                }
                .tips-header {
                    display: flex; align-items: center; gap: 8px;
                    margin-bottom: 12px;
                }
                .tips-title {
                    font-size: 10px; font-weight: 700;
                    letter-spacing: 0.12em; text-transform: uppercase;
                    color: var(--neon);
                }
                .tip-row {
                    display: flex; align-items: flex-start; gap: 10px;
                    padding: 6px 0;
                    border-bottom: 1px solid var(--border);
                }
                .tip-row:last-child { border-bottom: none; }
                .tip-num {
                    font-family: 'DM Mono', monospace !important;
                    font-size: 10px; color: var(--neon); opacity: 0.7;
                    flex-shrink: 0; margin-top: 2px;
                    width: 16px;
                }
                .tip-text { font-size: 12.5px; color: var(--t2); line-height: 1.5; }
                .tip-text.bold { color: var(--t1); font-weight: 600; }

                /* ── Alerts ──────────────────────────────────── */
                .alert-error {
                    background: rgba(255,71,87,0.07);
                    border: 1px solid rgba(255,71,87,0.2);
                    border-radius: 14px; padding: 16px 18px;
                    margin-bottom: 18px;
                    transition: opacity 0.5s;
                    animation: slide-down 0.3s ease;
                }
                .alert-warn {
                    background: rgba(255,184,48,0.06);
                    border: 1px solid rgba(255,184,48,0.18);
                    border-radius: 14px; padding: 16px 18px;
                    margin-bottom: 18px;
                }
                @keyframes slide-down {
                    from { transform: translateY(-8px); opacity: 0; }
                    to { transform: translateY(0); opacity: 1; }
                }

                /* ── Preview ─────────────────────────────────── */
                .preview-frame {
                    border-radius: 14px; overflow: hidden;
                    border: 1px solid var(--border-neon);
                    position: relative;
                    box-shadow: 0 0 40px rgba(0,255,178,0.1);
                }
                .preview-overlay {
                    position: absolute; inset: 0; pointer-events: none;
                }
                /* Scan lines effect over preview */
                .preview-scanlines {
                    position: absolute; inset: 0;
                    background: repeating-linear-gradient(
                        0deg,
                        transparent,
                        transparent 2px,
                        rgba(0,0,0,0.04) 2px,
                        rgba(0,0,0,0.04) 4px
                    );
                    pointer-events: none; z-index: 2;
                }
                .preview-sweep {
                    position: absolute; left: 0; top: -100%; width: 100%; height: 100%;
                    background: linear-gradient(
                        180deg,
                        transparent 0%,
                        rgba(0,255,178,0.04) 48%,
                        rgba(0,255,178,0.15) 50%,
                        rgba(0,255,178,0.04) 52%,
                        transparent 100%
                    );
                    animation: preview-sweep 3s ease-in-out infinite;
                    pointer-events: none; z-index: 3;
                }
                @keyframes preview-sweep {
                    0% { top: -100%; }
                    100% { top: 100%; }
                }
                /* HUD corners on preview */
                .prev-hud-corner {
                    position: absolute; width: 24px; height: 24px;
                    border-color: var(--neon); border-style: solid;
                    z-index: 4;
                    animation: hud-flicker 4s ease-in-out infinite;
                }
                @keyframes hud-flicker {
                    0%, 95%, 100% { opacity: 1; }
                    97% { opacity: 0.3; }
                }
                .phc-tl { top: 10px; left: 10px; border-width: 2px 0 0 2px; }
                .phc-tr { top: 10px; right: 10px; border-width: 2px 2px 0 0; }
                .phc-bl { bottom: 10px; left: 10px; border-width: 0 0 2px 2px; }
                .phc-br { bottom: 10px; right: 10px; border-width: 0 2px 2px 0; }

                .preview-meta {
                    position: absolute; bottom: 10px; left: 10px;
                    right: 10px; z-index: 5;
                    display: flex; align-items: flex-end; justify-content: space-between;
                }
                .preview-meta-badge {
                    font-family: 'DM Mono', monospace !important;
                    font-size: 9px; font-weight: 500;
                    color: var(--neon); letter-spacing: 0.08em;
                    background: rgba(0,0,0,0.7);
                    backdrop-filter: blur(8px);
                    border: 1px solid rgba(0,255,178,0.2);
                    border-radius: 5px; padding: 3px 8px;
                }

                /* Burst particles on load */
                .particle-burst { position: absolute; inset: 0; pointer-events: none; overflow: hidden; z-index: 10; }
                .pb-dot {
                    position: absolute;
                    width: 3px; height: 3px; border-radius: 50%;
                    background: var(--neon);
                    box-shadow: 0 0 6px var(--neon);
                    top: 50%; left: 50%;
                    animation: burst-out 0.8s ease-out forwards;
                }
                @keyframes burst-out {
                    from { transform: translate(-50%, -50%) scale(1); opacity: 1; }
                    to { transform: translate(var(--dx), var(--dy)) scale(0); opacity: 0; }
                }

                /* ── Camera View ─────────────────────────────── */
                .camera-frame {
                    border-radius: 14px; overflow: hidden;
                    border: 1px solid var(--border-neon);
                    position: relative;
                    box-shadow: 0 0 40px rgba(0,255,178,0.08);
                }
                .camera-hud {
                    position: absolute; inset: 0;
                    pointer-events: none; z-index: 3;
                }
                /* Animated crosshair in camera */
                .cam-crosshair {
                    position: absolute; top: 50%; left: 50%;
                    transform: translate(-50%, -50%);
                    width: 80px; height: 80px;
                }
                .cam-ch-h, .cam-ch-v {
                    position: absolute;
                    background: rgba(0,255,178,0.4);
                }
                .cam-ch-h { left: 0; right: 0; top: 50%; height: 1px; transform: translateY(-50%); }
                .cam-ch-v { top: 0; bottom: 0; left: 50%; width: 1px; transform: translateX(-50%); }
                .cam-ch-center {
                    position: absolute; top: 50%; left: 50%;
                    transform: translate(-50%, -50%);
                    width: 8px; height: 8px; border-radius: 50%;
                    background: var(--neon); box-shadow: 0 0 10px var(--neon);
                    animation: ch-pulse 1.5s ease-in-out infinite;
                }
                @keyframes ch-pulse {
                    0%, 100% { transform: translate(-50%,-50%) scale(1); opacity: 1; }
                    50% { transform: translate(-50%,-50%) scale(1.6); opacity: 0.5; }
                }
                /* Camera scan sweep */
                .cam-sweep {
                    position: absolute; left: 0; top: -4px; width: 100%; height: 4px;
                    background: linear-gradient(90deg, transparent, var(--neon), transparent);
                    filter: blur(2px);
                    animation: cam-sweep 2s linear infinite;
                }
                @keyframes cam-sweep {
                    from { top: -4px; }
                    to { top: 100%; }
                }
                .cam-switch-btn {
                    position: absolute; top: 14px; right: 14px;
                    width: 42px; height: 42px; border-radius: 12px;
                    background: rgba(0,0,0,0.65);
                    backdrop-filter: blur(10px);
                    border: 1px solid rgba(255,255,255,0.1);
                    color: white; cursor: pointer;
                    display: flex; align-items: center; justify-content: center;
                    transition: all 0.2s; z-index: 10;
                }
                .cam-switch-btn:hover {
                    background: rgba(0,255,178,0.25);
                    border-color: rgba(0,255,178,0.5);
                    box-shadow: 0 0 16px rgba(0,255,178,0.3);
                }
                /* Camera corner HUD */
                .cam-hud-corner {
                    position: absolute; width: 22px; height: 22px;
                    border-color: var(--neon); border-style: solid; opacity: 0.7;
                    animation: hud-flicker 3s ease-in-out infinite;
                }
                .chc-tl { top: 12px; left: 12px; border-width: 2px 0 0 2px; }
                .chc-tr { top: 12px; right: 56px; border-width: 2px 2px 0 0; }
                .chc-bl { bottom: 12px; left: 12px; border-width: 0 0 2px 2px; }
                .chc-br { bottom: 12px; right: 12px; border-width: 0 2px 2px 0; }
                .cam-label {
                    position: absolute; bottom: 12px; left: 12px;
                    font-family: 'DM Mono', monospace !important;
                    font-size: 9px; font-weight: 500;
                    color: var(--neon); letter-spacing: 0.12em;
                    background: rgba(0,0,0,0.6);
                    backdrop-filter: blur(6px);
                    border: 1px solid rgba(0,255,178,0.2);
                    border-radius: 4px; padding: 3px 8px;
                    text-transform: uppercase;
                    z-index: 10;
                    animation: ticker-fade 1.5s ease-in-out infinite;
                }
                .btn-capture {
                    display: flex; align-items: center; justify-content: center; gap: 8px;
                    flex: 1;
                    background: linear-gradient(135deg, var(--neon) 0%, var(--neon2) 100%);
                    color: #000; font-weight: 700; font-size: 14px;
                    border: none; border-radius: 11px; padding: 14px 20px;
                    cursor: pointer; transition: all 0.25s;
                    box-shadow: 0 4px 20px rgba(0,255,178,0.25);
                    position: relative; overflow: hidden;
                }
                .btn-capture:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 8px 32px rgba(0,255,178,0.4);
                }

                /* ── Analyze Row ─────────────────────────────── */
                .analyze-row {
                    display: flex; gap: 12px; margin-top: 18px;
                    align-items: stretch;
                }

                /* ── QR FAB ──────────────────────────────────── */
                .qr-fab {
                    position: fixed; bottom: 28px; right: 28px; z-index: 40;
                    width: 50px; height: 50px; border-radius: 14px;
                    background: var(--neon); color: #000;
                    border: none; cursor: pointer;
                    display: flex; align-items: center; justify-content: center;
                    box-shadow: 0 4px 24px rgba(0,255,178,0.4);
                    transition: all 0.25s;
                }
                .qr-fab::before {
                    content: ''; position: absolute; inset: -3px;
                    border-radius: 17px;
                    border: 1.5px solid rgba(0,255,178,0.3);
                    animation: fab-ring 2s ease-out infinite;
                }
                @keyframes fab-ring {
                    0% { transform: scale(1); opacity: 0.8; }
                    100% { transform: scale(1.25); opacity: 0; }
                }
                .qr-fab:hover { transform: scale(1.1) translateY(-3px); box-shadow: 0 8px 36px rgba(0,255,178,0.55); }

                /* ── QR Modal ────────────────────────────────── */
                .qr-overlay {
                    position: fixed; inset: 0;
                    background: rgba(0,0,0,0.75);
                    backdrop-filter: blur(16px);
                    z-index: 50; display: flex;
                    align-items: center; justify-content: center; padding: 16px;
                    animation: overlay-in 0.2s ease;
                }
                @keyframes overlay-in {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }
                .qr-modal {
                    background: var(--s2);
                    border: 1px solid var(--border);
                    border-radius: 24px; padding: 32px;
                    max-width: 420px; width: 100%;
                    position: relative;
                    animation: modal-up 0.3s cubic-bezier(0.16,1,0.3,1);
                    box-shadow: var(--card-shadow);
                }
                @keyframes modal-up {
                    from { transform: translateY(20px) scale(0.97); opacity: 0; }
                    to { transform: translateY(0) scale(1); opacity: 1; }
                }
                .qr-corner { position: absolute; width: 16px; height: 16px; border-color: var(--neon); border-style: solid; opacity: 0.5; }
                .qrc-tl { top: 10px; left: 10px; border-width: 2px 0 0 2px; }
                .qrc-tr { top: 10px; right: 10px; border-width: 2px 2px 0 0; }
                .qrc-bl { bottom: 10px; left: 10px; border-width: 0 0 2px 2px; }
                .qrc-br { bottom: 10px; right: 10px; border-width: 0 2px 2px 0; }
                .qr-close {
                    position: absolute; top: 14px; right: 14px;
                    width: 32px; height: 32px; border-radius: 8px;
                    background: var(--s1); border: 1px solid var(--border);
                    color: var(--t2); cursor: pointer;
                    display: flex; align-items: center; justify-content: center;
                    transition: all 0.2s;
                }
                .qr-close:hover { color: var(--t1); border-color: var(--border-neon); }

                .modal-feat {
                    display: flex; align-items: center; gap: 10px;
                    padding: 10px 0;
                    border-bottom: 1px solid var(--border);
                    color: var(--t2); font-size: 13px;
                }
                .modal-feat:last-child { border-bottom: none; }
                .modal-feat-icon {
                    width: 28px; height: 28px; border-radius: 7px;
                    background: var(--neon-dim); border: 1px solid var(--border-neon);
                    display: flex; align-items: center; justify-content: center;
                    color: var(--neon); flex-shrink: 0;
                }

                /* ── Fade-up animations ──────────────────────── */
                @keyframes fadeUp {
                    from { opacity: 0; transform: translateY(18px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                .fu { animation: fadeUp 0.55s cubic-bezier(0.16,1,0.3,1) both; }
                .fu-1 { animation-delay: 0.0s; }
                .fu-2 { animation-delay: 0.08s; }
                .fu-3 { animation-delay: 0.16s; }
                .fu-4 { animation-delay: 0.24s; }
                .fu-5 { animation-delay: 0.32s; }
            `}</style>

            {/* Floating particles */}
            {[...Array(8)].map((_, i) => (
                <div key={i} className="sp-particle" style={{
                    left: `${10 + i * 12}%`,
                    animationDuration: `${8 + i * 2}s`,
                    animationDelay: `${i * 1.5}s`,
                    width: i % 3 === 0 ? '3px' : '2px',
                    height: i % 3 === 0 ? '3px' : '2px',
                }} />
            ))}

            <div className="sp">
                <div className="sp-grid" />
                <div className="sp-aurora sp-aurora-1" />
                <div className="sp-aurora sp-aurora-2" />
                <div className="sp-aurora sp-aurora-3" />

                <Header />

                <div className="mx-6">
                    <AnalysisLoadingDialog isOpen={showLoading} />
                </div>

                {/* HUD Strip */}
                <div className="hud-strip sp-content">
                    {[
                        { label: 'System', value: 'ONLINE', color: '#00FFB2' },
                        { label: 'Model', value: 'v3.2.1', color: '#00D4FF' },
                        { label: 'Accuracy', value: '98.4%', color: '#00FFB2' },
                        { label: 'Breeds', value: '120+', color: '#00D4FF' },
                    ].map((item, i) => (
                        <div className="hud-item mono" key={i}>
                            <div className="hud-dot active" style={{ background: item.color, color: item.color }} />
                            <span>{item.label}</span>
                            <span className="hud-value mono">{item.value}</span>
                        </div>
                    ))}
                    <div className="hud-ticker mono">
                        <span style={{ color: 'var(--neon)', opacity: 0.6 }}>▶</span>
                        DOGLENS AI ENGINE · {hudLabels[scanPhase]}
                    </div>
                </div>

                {/* QR Modal */}
                {showQRModal && (
                    <div className="qr-overlay" onClick={() => setShowQRModal(false)}>
                        <div className="qr-modal" onClick={e => e.stopPropagation()}>
                            <div className="qr-corner qrc-tl" /><div className="qr-corner qrc-tr" />
                            <div className="qr-corner qrc-bl" /><div className="qr-corner qrc-br" />
                            <button className="qr-close" onClick={() => setShowQRModal(false)}><X size={14} /></button>
                            <div style={{ textAlign: 'center', marginBottom: 24 }}>
                                <div style={{
                                    width: 52, height: 52,
                                    background: 'var(--neon-dim)', border: '1.5px solid var(--border-neon)',
                                    borderRadius: 15, display: 'flex', alignItems: 'center', justifyContent: 'center',
                                    margin: '0 auto 14px',
                                }}>
                                    <Smartphone size={24} color="var(--neon)" />
                                </div>
                                <h2 style={{ color: 'var(--t1)', fontSize: 20, fontWeight: 800, margin: 0, letterSpacing: '-0.02em' }}>Install Mobile App</h2>
                                <p style={{ color: 'var(--t2)', fontSize: 13, marginTop: 6 }}>Scan with your device to download the Android app</p>
                            </div>
                            <div style={{ display: 'flex', justifyContent: 'center', marginBottom: 22 }}>
                                <div style={{ background: '#fff', borderRadius: 14, padding: 10, boxShadow: '0 0 40px rgba(0,255,178,0.2)' }}>
                                    <img src="/doglens_apk_qr.jpeg" alt="QR Code" style={{ width: 148, height: 148, display: 'block' }} />
                                </div>
                            </div>
                            <div style={{ background: 'var(--s1)', border: '1px solid var(--border)', borderRadius: 12, overflow: 'hidden', marginBottom: 18 }}>
                                {[{ icon: <Download size={13} />, text: 'Fast & Easy Installation' }, { icon: <Smartphone size={13} />, text: 'Available on Android' }, { icon: <Camera size={13} />, text: 'All Features On-The-Go' }].map((f, i) => (
                                    <div className="modal-feat" key={i} style={{ padding: '10px 14px' }}>
                                        <div className="modal-feat-icon">{f.icon}</div>
                                        {f.text}
                                    </div>
                                ))}
                            </div>
                            <button className="btn-neon" style={{ width: '100%' }} onClick={() => setShowQRModal(false)}>Close</button>
                        </div>
                    </div>
                )}

                {/* FAB */}
                <button className="qr-fab" onClick={() => setShowQRModal(true)} title="Install Mobile App">
                    <QrCode size={20} />
                </button>

                {/* Main content */}
                <div className="sp-content" style={{ padding: '0 16px', paddingBottom: 72 }}>
                    <div style={{ maxWidth: 960, margin: '0 auto', paddingTop: '20px' }}>

                        {/* Page Header */}
                        <div className="pg-header fu fu-1">
                            <div>
                                <div className="pg-eyebrow">
                                    <span className="pg-eyebrow-dot" />
                                    <span className="pg-eyebrow-text">AI Breed Detection</span>
                                </div>
                                <h1 className="pg-title">Scan Your Dog</h1>
                                <p className="pg-sub">Upload a photo or use your camera to identify your dog's breed with precision.</p>
                            </div>
                            <Link href="/scanhistory" className="history-btn">
                                <History size={15} />
                                Scan History
                                <ChevronRight size={13} style={{ opacity: 0.5 }} />
                            </Link>
                        </div>

                        {/* Stat row */}
                        <div className="stat-row fu fu-2">
                            {[
                                { icon: <Cpu size={15} />, label: 'Engine', value: 'v3.2.1', sub: 'Latest model', bar: 100 },
                                { icon: <Activity size={15} />, label: 'Accuracy', value: '98.4%', sub: '120+ breeds', bar: 98 },
                                { icon: <Eye size={15} />, label: 'Latency', value: '~1.2s', sub: 'Avg scan time', bar: 75 },
                            ].map((s, i) => (
                                <div className="stat-card" key={i}>
                                    <div className="stat-icon">{s.icon}</div>
                                    <div className="stat-label mono">{s.label}</div>
                                    <div className="stat-value">{s.value}</div>
                                    <div className="stat-sub mono">{s.sub}</div>
                                    <div className="stat-bar-bg">
                                        <div className="stat-bar-fill" style={{ width: `${s.bar}%`, animationDelay: `${i * 0.2}s` }} />
                                    </div>
                                </div>
                            ))}
                        </div>

                        {/* Error Alerts */}
                        {localError && showLocalError && (
                            <div className="alert-error" style={{ opacity: showLocalError ? 1 : 0 }}>
                                <div style={{ display: 'flex', alignItems: 'flex-start', gap: 12 }}>
                                    <XCircle size={17} color="var(--red)" style={{ flexShrink: 0, marginTop: 2 }} />
                                    <div style={{ flex: 1 }}>
                                        <p style={{ color: '#FF8091', fontWeight: 700, margin: 0, fontSize: 14 }}>
                                            {localError.not_a_dog ? 'Not a Dog Detected' : 'Analysis Error'}
                                        </p>
                                        <p style={{ color: '#FF8091', fontSize: 13, margin: '4px 0 0', opacity: 0.8 }}>{localError.message}</p>
                                        {localError.not_a_dog && (
                                            <button className="btn-neon" onClick={handleReset}
                                                style={{ marginTop: 14, background: 'var(--red)', fontSize: 13, padding: '9px 20px' }}>
                                                Try Another Image
                                            </button>
                                        )}
                                    </div>
                                </div>
                            </div>
                        )}
                        {cameraError && (
                            <div className="alert-warn">
                                <div style={{ display: 'flex', alignItems: 'flex-start', gap: 12 }}>
                                    <CircleAlert size={17} color="var(--amber)" style={{ flexShrink: 0, marginTop: 2 }} />
                                    <div>
                                        <p style={{ color: '#FFD580', fontWeight: 700, margin: 0, fontSize: 14 }}>Camera Error</p>
                                        <p style={{ color: '#FFD580', fontSize: 13, margin: '4px 0 0', opacity: 0.8 }}>{cameraError}</p>
                                        <p className="mono" style={{ color: '#FFD580', fontSize: 10, marginTop: 6, opacity: 0.5 }}>DISMISSING IN 5S</p>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Main Scan Card */}
                        <div className={`scan-card fu fu-3 ${showCamera ? 'camera-open' : ''}`}>
                            <div className="sc-corner sc-tl" /><div className="sc-corner sc-tr" />
                            <div className="sc-corner sc-bl" /><div className="sc-corner sc-br" />

                            {/* Top bar */}
                            <div className="card-topbar">
                                <div className="topbar-dots">
                                    <div className="td td-r" /><div className="td td-y" /><div className="td td-g" />
                                </div>
                                <span className="topbar-label mono">doglens://scan</span>
                                <div className="topbar-status mono">
                                    <span className="topbar-status-dot" />
                                    {processing ? 'PROCESSING' : preview ? 'IMAGE LOADED' : showCamera ? 'CAMERA ACTIVE' : 'AWAITING INPUT'}
                                </div>
                            </div>

                            <form onSubmit={handleSubmit} style={{ padding: '24px 24px 28px' }}>
                                {!preview && !showCamera ? (
                                    <>
                                        {/* Drop Zone */}
                                        <div
                                            className={`drop-zone ${isDragging ? 'dz-drag' : ''}`}
                                            onClick={triggerFileInput}
                                            onDragOver={handleDragOver}
                                            onDragLeave={handleDragLeave}
                                            onDrop={handleDrop}
                                        >
                                            <div className="dz-scan-line" />
                                            <div className="dz-left-line" />
                                            <div className="dz-right-line" />
                                            <input ref={fileInputRef} type="file" accept="image/*" style={{ display: 'none' }} onChange={handleFileChange} />

                                            <div className="dz-icon-wrap">
                                                <div className="dz-icon-ring-1" />
                                                <div className="dz-icon-ring-2" />
                                                <div className="dz-icon-bg" />
                                                <div className="dz-icon-bg-inner"><Upload size={28} /></div>
                                            </div>

                                            <div style={{ textAlign: 'center' }}>
                                                <p style={{ color: 'var(--t1)', fontWeight: 700, fontSize: 16, margin: 0 }}>
                                                    Drop your dog image here
                                                </p>
                                                <p style={{ color: 'var(--t2)', fontSize: 13, marginTop: 5 }}>
                                                    or <span style={{ color: 'var(--neon)', fontWeight: 700 }}>click to browse files</span>
                                                </p>
                                            </div>

                                            <p className="mono" style={{ color: 'var(--t3)', fontSize: 10, margin: 0, letterSpacing: '0.1em' }}>
                                                ALL IMAGE FORMATS · MAX 10MB
                                            </p>
                                        </div>

                                        {/* Divider */}
                                        <div className="dz-divider">
                                            <div className="dz-divider-line" />
                                            <span className="dz-divider-text">or use camera</span>
                                            <div className="dz-divider-line" />
                                        </div>

                                        {/* Camera button */}
                                        <button type="button" onClick={startCamera} className="btn-camera">
                                            <Camera size={17} />
                                            Activate Camera
                                        </button>

                                        <p className="mono" style={{ textAlign: 'center', color: 'var(--t3)', fontSize: 10, marginTop: 8, letterSpacing: '0.08em' }}>
                                            CHROME · EDGE · SAFARI · FIREFOX
                                        </p>

                                        {errors.image && (
                                            <p style={{ color: 'var(--red)', fontSize: 13, marginTop: 12, textAlign: 'center' }}>{errors.image}</p>
                                        )}

                                        {/* Tips */}
                                        <div className="tips-panel">
                                            <div className="tips-header">
                                                <Zap size={14} color="var(--neon)" />
                                                <span className="tips-title mono">Capture Tips</span>
                                                <Shield size={12} color="var(--t3)" style={{ marginLeft: 'auto' }} />
                                            </div>
                                            {[
                                                { t: 'Ensure your dog is clearly visible in the frame', bold: false },
                                                { t: 'Use good lighting without harsh shadows', bold: false },
                                                { t: 'Center your dog, avoid cluttered backgrounds', bold: false },
                                                { t: 'Front or side angles work best for accuracy', bold: false },
                                                { t: 'Only dog images are accepted by the system', bold: true },
                                            ].map((tip, i) => (
                                                <div className="tip-row" key={i}>
                                                    <span className="tip-num mono">0{i + 1}</span>
                                                    <span className={`tip-text ${tip.bold ? 'bold' : ''}`}>{tip.t}</span>
                                                </div>
                                            ))}
                                        </div>
                                    </>
                                ) : showCamera ? (
                                    <div>
                                        <div className="camera-frame">
                                            <video ref={videoRef} autoPlay playsInline muted
                                                style={{ display: 'block', width: '100%', maxHeight: '65vh', objectFit: 'cover', background: '#000' }} />
                                            <canvas ref={canvasRef} style={{ display: 'none' }} />
                                            {/* Camera HUD overlay */}
                                            <div className="camera-hud">
                                                <div className="cam-sweep" />
                                                <div className="cam-hud-corner chc-tl" />
                                                <div className="cam-hud-corner chc-tr" />
                                                <div className="cam-hud-corner chc-bl" />
                                                <div className="cam-hud-corner chc-br" />
                                                <div className="cam-crosshair">
                                                    <div className="cam-ch-h" />
                                                    <div className="cam-ch-v" />
                                                    <div className="cam-ch-center" />
                                                </div>
                                                <div className="cam-label">● REC · {facingMode === 'environment' ? 'REAR' : 'FRONT'} CAM</div>
                                            </div>
                                            <button type="button" onClick={switchCamera} className="cam-switch-btn" title="Switch Camera">
                                                <SwitchCamera size={18} />
                                            </button>
                                        </div>
                                        <div style={{ display: 'flex', gap: 12, marginTop: 18 }}>
                                            <button type="button" onClick={capturePhoto} className="btn-capture">
                                                <ScanIcon size={18} />
                                                Capture & Scan
                                            </button>
                                            <button type="button" onClick={stopCamera} className="btn-outline">
                                                <X size={16} />
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                ) : (
                                    <div>
                                        {/* Preview with HUD overlay */}
                                        <div className="preview-frame" style={{ position: 'relative' }}>
                                            {particleActive && (
                                                <div className="particle-burst">
                                                    {[...Array(12)].map((_, i) => {
                                                        const angle = (i / 12) * 360;
                                                        const dist = 80 + Math.random() * 60;
                                                        const dx = Math.cos(angle * Math.PI / 180) * dist + 'px';
                                                        const dy = Math.sin(angle * Math.PI / 180) * dist + 'px';
                                                        return <div className="pb-dot" key={i} style={{ '--dx': dx, '--dy': dy } as any} />;
                                                    })}
                                                </div>
                                            )}
                                            <img src={preview || ''} alt="Preview"
                                                style={{ display: 'block', maxHeight: 400, width: '100%', objectFit: 'contain', background: 'var(--s1)' }} />
                                            <div className="preview-overlay">
                                                <div className="preview-scanlines" />
                                                <div className="preview-sweep" />
                                                <div className="prev-hud-corner phc-tl" />
                                                <div className="prev-hud-corner phc-tr" />
                                                <div className="prev-hud-corner phc-bl" />
                                                <div className="prev-hud-corner phc-br" />
                                                <div className="preview-meta">
                                                    <span className="preview-meta-badge mono">IMAGE LOADED</span>
                                                    {fileInfo && <span className="preview-meta-badge mono" style={{ fontSize: 8 }}>{fileInfo.split('(')[1]?.replace(')', '') || ''}</span>}
                                                </div>
                                            </div>
                                        </div>

                                        {fileInfo && (
                                            <p className="mono" style={{ textAlign: 'center', color: 'var(--t3)', fontSize: 11, marginTop: 8, letterSpacing: '0.06em' }}>
                                                {fileInfo}
                                            </p>
                                        )}

                                        <div className="analyze-row">
                                            <button type="submit" className="btn-neon" disabled={processing}
                                                style={{ flex: 1, padding: '14px 20px' }}>
                                                <ScanIcon size={17} />
                                                {processing ? 'Analyzing...' : 'Analyze Image'}
                                            </button>
                                            <button type="button" onClick={handleReset} className="btn-outline" disabled={processing}>
                                                <X size={16} />
                                                Reset
                                            </button>
                                        </div>
                                    </div>
                                )}
                            </form>
                        </div>

                    </div>
                </div>
            </div>
        </>
    );
};

export default Scan;