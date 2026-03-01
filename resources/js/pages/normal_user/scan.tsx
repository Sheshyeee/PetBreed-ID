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

    const isCameraSupported = () => {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) return false;
        const userAgent = navigator.userAgent.toLowerCase();
        const isChrome = /chrome|chromium|crios/.test(userAgent) && !/edg/.test(userAgent);
        const isEdge = /edg/.test(userAgent);
        const isSafari = /safari/.test(userAgent) && !/chrome/.test(userAgent);
        const isFirefox = /firefox|fxios/.test(userAgent);
        return isChrome || isEdge || isSafari || isFirefox;
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

    useEffect(() => {
        if (success) setShowLoading(false);
    }, [success]);

    useEffect(() => {
        if (cameraError) {
            const timer = setTimeout(() => setCameraError(null), 5000);
            return () => clearTimeout(timer);
        }
    }, [cameraError]);

    useEffect(() => {
        return () => {
            if (stream) stream.getTracks().forEach((track) => track.stop());
        };
    }, [stream]);

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
            setFileInfo(`${file.name} (${(file.size / 1024).toFixed(1)}KB, ${img.width}x${img.height})`);
            URL.revokeObjectURL(objectUrl);
        };
        img.onerror = () => {
            setFileInfo(`${file.name} (${(file.size / 1024).toFixed(1)}KB)`);
            URL.revokeObjectURL(objectUrl);
        };
        img.src = objectUrl;
        setData('image', file);
        stopCamera();
    };

    const triggerFileInput = () => fileInputRef.current?.click();

    const startCamera = async () => {
        if (!isCameraSupported()) {
            alert('Camera feature is only available on Chrome, Edge, Safari, and Firefox browsers.');
            return;
        }
        try {
            setCameraError(null);
            if (stream) { stream.getTracks().forEach((track) => track.stop()); setStream(null); }
            const constraints: MediaStreamConstraints = {
                video: { facingMode, width: { ideal: 1920 }, height: { ideal: 1080 } },
                audio: false,
            };
            const mediaStream = await navigator.mediaDevices.getUserMedia(constraints);
            setStream(mediaStream);
            setTimeout(() => {
                if (videoRef.current && mediaStream.active) {
                    videoRef.current.srcObject = mediaStream;
                    videoRef.current.play().catch(console.error);
                }
            }, 100);
            setShowCamera(true);
        } catch (err: any) {
            let errorMessage = 'Unable to access camera. ';
            if (err.name === 'NotAllowedError') errorMessage += 'Please allow camera permissions and try again.';
            else if (err.name === 'NotFoundError') errorMessage += 'No camera found on this device.';
            else if (err.name === 'NotReadableError') errorMessage += 'Camera is already in use by another application.';
            else errorMessage += 'Please check permissions or use file upload instead.';
            setCameraError(errorMessage);
            setShowCamera(false);
        }
    };

    const stopCamera = () => {
        if (stream) { stream.getTracks().forEach((track) => track.stop()); setStream(null); }
        if (videoRef.current) videoRef.current.srcObject = null;
        setShowCamera(false);
        setCameraError(null);
    };

    const switchCamera = async () => {
        const newFacingMode = facingMode === 'user' ? 'environment' : 'user';
        setFacingMode(newFacingMode);
        if (stream) { stream.getTracks().forEach((track) => track.stop()); setStream(null); }
        setTimeout(async () => {
            try {
                setCameraError(null);
                const constraints: MediaStreamConstraints = {
                    video: { facingMode: newFacingMode, width: { ideal: 1920 }, height: { ideal: 1080 } },
                    audio: false,
                };
                const mediaStream = await navigator.mediaDevices.getUserMedia(constraints);
                setStream(mediaStream);
                if (videoRef.current && mediaStream.active) {
                    videoRef.current.srcObject = mediaStream;
                    videoRef.current.play().catch(console.error);
                }
            } catch (err: any) {
                setCameraError('Failed to switch camera. This device may only have one camera.');
                setFacingMode(facingMode === 'user' ? 'environment' : 'user');
            }
        }, 200);
    };

    const capturePhoto = () => {
        if (videoRef.current && canvasRef.current) {
            const video = videoRef.current;
            const canvas = canvasRef.current;
            if (video.readyState !== video.HAVE_ENOUGH_DATA) {
                alert('Camera is still loading. Please wait a moment and try again.');
                return;
            }
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const ctx = canvas.getContext('2d');
            if (ctx) {
                ctx.drawImage(video, 0, 0);
                canvas.toBlob((blob) => {
                    if (blob) {
                        const file = new File([blob], `camera-capture-${Date.now()}.jpg`, { type: 'image/jpeg' });
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

    return (
        <>
            <style>{`
                @import url('https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Mono:wght@300;400;500&display=swap');

                :root {
                    --neon: #00FFB2;
                    --neon-dim: rgba(0,255,178,0.12);
                    --neon-glow: 0 0 20px rgba(0,255,178,0.4), 0 0 60px rgba(0,255,178,0.15);
                    --surface: #0A0C0F;
                    --surface-2: #111317;
                    --surface-3: #181B21;
                    --border: rgba(255,255,255,0.06);
                    --border-bright: rgba(0,255,178,0.25);
                    --text-1: #F0F2F5;
                    --text-2: #7A8394;
                    --red: #FF4757;
                    --amber: #FFB830;
                }

                .scan-page * { font-family: 'Syne', sans-serif; }
                .mono { font-family: 'DM Mono', monospace !important; }

                .scan-page {
                    background: var(--surface);
                    min-height: 100vh;
                    position: relative;
                    overflow-x: hidden;
                }

                /* Animated grid background */
                .grid-bg {
                    position: fixed;
                    inset: 0;
                    background-image:
                        linear-gradient(rgba(0,255,178,0.03) 1px, transparent 1px),
                        linear-gradient(90deg, rgba(0,255,178,0.03) 1px, transparent 1px);
                    background-size: 60px 60px;
                    mask-image: radial-gradient(ellipse 80% 60% at 50% 0%, black 40%, transparent 100%);
                    pointer-events: none;
                    z-index: 0;
                }

                /* Radial glow at top */
                .top-glow {
                    position: fixed;
                    top: -200px;
                    left: 50%;
                    transform: translateX(-50%);
                    width: 800px;
                    height: 400px;
                    background: radial-gradient(ellipse, rgba(0,255,178,0.08) 0%, transparent 70%);
                    pointer-events: none;
                    z-index: 0;
                }

                .content-wrap {
                    position: relative;
                    z-index: 1;
                }

                /* Scan card */
                .scan-card {
                    background: var(--surface-2);
                    border: 1px solid var(--border);
                    border-radius: 20px;
                    overflow: hidden;
                    position: relative;
                    transition: border-color 0.3s;
                }

                .scan-card::before {
                    content: '';
                    position: absolute;
                    inset: 0;
                    background: linear-gradient(135deg, rgba(0,255,178,0.03) 0%, transparent 50%);
                    pointer-events: none;
                }

                /* Corner accents */
                .corner {
                    position: absolute;
                    width: 20px;
                    height: 20px;
                    border-color: var(--neon);
                    border-style: solid;
                    opacity: 0.6;
                    z-index: 2;
                }
                .corner-tl { top: 12px; left: 12px; border-width: 2px 0 0 2px; }
                .corner-tr { top: 12px; right: 12px; border-width: 2px 2px 0 0; }
                .corner-bl { bottom: 12px; left: 12px; border-width: 0 0 2px 2px; }
                .corner-br { bottom: 12px; right: 12px; border-width: 0 2px 2px 0; }

                /* Drop zone */
                .drop-zone {
                    border: 1.5px dashed rgba(0,255,178,0.2);
                    border-radius: 14px;
                    background: rgba(0,255,178,0.02);
                    transition: all 0.3s ease;
                    cursor: pointer;
                    position: relative;
                    overflow: hidden;
                }

                .drop-zone:hover, .drop-zone.dragging {
                    border-color: rgba(0,255,178,0.5);
                    background: rgba(0,255,178,0.05);
                    box-shadow: inset 0 0 40px rgba(0,255,178,0.05), var(--neon-glow);
                }

                .drop-zone::after {
                    content: '';
                    position: absolute;
                    top: -100%;
                    left: 0;
                    width: 100%;
                    height: 2px;
                    background: linear-gradient(90deg, transparent, var(--neon), transparent);
                    animation: scan-line 3s ease-in-out infinite;
                    opacity: 0;
                    transition: opacity 0.3s;
                }
                .drop-zone:hover::after, .drop-zone.dragging::after { opacity: 1; }

                @keyframes scan-line {
                    0% { top: -2px; }
                    100% { top: 102%; }
                }

                /* Upload icon pulse */
                .icon-ring {
                    width: 80px;
                    height: 80px;
                    border-radius: 50%;
                    background: var(--neon-dim);
                    border: 1.5px solid rgba(0,255,178,0.3);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    position: relative;
                    transition: all 0.3s;
                }

                .icon-ring::before {
                    content: '';
                    position: absolute;
                    inset: -8px;
                    border-radius: 50%;
                    border: 1px solid rgba(0,255,178,0.1);
                    animation: pulse-ring 2.5s ease-out infinite;
                }

                @keyframes pulse-ring {
                    0% { transform: scale(0.95); opacity: 0.6; }
                    70% { transform: scale(1.1); opacity: 0; }
                    100% { transform: scale(1.1); opacity: 0; }
                }

                .drop-zone:hover .icon-ring {
                    background: rgba(0,255,178,0.18);
                    border-color: rgba(0,255,178,0.6);
                    box-shadow: var(--neon-glow);
                }

                /* Neon button */
                .btn-neon {
                    background: var(--neon);
                    color: #000;
                    font-weight: 700;
                    font-size: 14px;
                    letter-spacing: 0.05em;
                    border: none;
                    border-radius: 10px;
                    padding: 12px 28px;
                    cursor: pointer;
                    transition: all 0.25s;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    position: relative;
                    overflow: hidden;
                }
                .btn-neon:hover {
                    background: #00e6a0;
                    box-shadow: var(--neon-glow);
                    transform: translateY(-1px);
                }
                .btn-neon:disabled {
                    opacity: 0.5;
                    cursor: not-allowed;
                    transform: none;
                    box-shadow: none;
                }

                /* Ghost button */
                .btn-ghost {
                    background: transparent;
                    color: var(--text-2);
                    font-weight: 600;
                    font-size: 14px;
                    border: 1px solid var(--border);
                    border-radius: 10px;
                    padding: 12px 28px;
                    cursor: pointer;
                    transition: all 0.25s;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                .btn-ghost:hover {
                    border-color: rgba(255,255,255,0.15);
                    color: var(--text-1);
                    background: rgba(255,255,255,0.04);
                }

                /* Camera outline button */
                .btn-outline-neon {
                    background: transparent;
                    color: var(--neon);
                    font-weight: 600;
                    font-size: 14px;
                    border: 1.5px solid rgba(0,255,178,0.35);
                    border-radius: 10px;
                    padding: 12px 28px;
                    cursor: pointer;
                    transition: all 0.25s;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 8px;
                    width: 100%;
                }
                .btn-outline-neon:hover {
                    border-color: var(--neon);
                    background: var(--neon-dim);
                    box-shadow: var(--neon-glow);
                }

                /* Tips card */
                .tips-card {
                    background: rgba(0,255,178,0.03);
                    border: 1px solid rgba(0,255,178,0.1);
                    border-radius: 14px;
                    padding: 16px 20px;
                }

                .tip-dot {
                    width: 5px;
                    height: 5px;
                    border-radius: 50%;
                    background: var(--neon);
                    flex-shrink: 0;
                    margin-top: 7px;
                    box-shadow: 0 0 6px var(--neon);
                }

                /* Alert/error */
                .alert-error {
                    background: rgba(255,71,87,0.08);
                    border: 1px solid rgba(255,71,87,0.25);
                    border-radius: 14px;
                    padding: 16px 20px;
                    transition: opacity 0.5s;
                }
                .alert-camera {
                    background: rgba(255,184,48,0.07);
                    border: 1px solid rgba(255,184,48,0.2);
                    border-radius: 14px;
                    padding: 16px 20px;
                }

                /* Status badge */
                .status-badge {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    background: var(--neon-dim);
                    border: 1px solid rgba(0,255,178,0.2);
                    border-radius: 100px;
                    padding: 4px 12px;
                    font-size: 11px;
                    font-weight: 600;
                    color: var(--neon);
                    letter-spacing: 0.08em;
                    text-transform: uppercase;
                }
                .status-dot {
                    width: 6px;
                    height: 6px;
                    border-radius: 50%;
                    background: var(--neon);
                    box-shadow: 0 0 8px var(--neon);
                    animation: blink 2s ease-in-out infinite;
                }
                @keyframes blink {
                    0%, 100% { opacity: 1; }
                    50% { opacity: 0.3; }
                }

                /* Preview image */
                .preview-wrap {
                    border-radius: 14px;
                    overflow: hidden;
                    border: 1px solid var(--border-bright);
                    position: relative;
                    box-shadow: 0 0 40px rgba(0,255,178,0.1);
                }

                /* Video */
                .camera-wrap {
                    border-radius: 14px;
                    overflow: hidden;
                    border: 1px solid var(--border-bright);
                    position: relative;
                }

                /* QR Modal */
                .modal-overlay {
                    position: fixed;
                    inset: 0;
                    background: rgba(0,0,0,0.8);
                    backdrop-filter: blur(12px);
                    z-index: 50;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 16px;
                }
                .modal-inner {
                    background: var(--surface-2);
                    border: 1px solid var(--border);
                    border-radius: 24px;
                    padding: 36px;
                    max-width: 440px;
                    width: 100%;
                    position: relative;
                }

                /* Floating QR */
                .fab-qr {
                    position: fixed;
                    bottom: 28px;
                    right: 28px;
                    z-index: 40;
                    width: 52px;
                    height: 52px;
                    border-radius: 14px;
                    background: var(--neon);
                    color: #000;
                    border: none;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    box-shadow: 0 4px 24px rgba(0,255,178,0.35);
                    transition: all 0.25s;
                }
                .fab-qr:hover {
                    transform: scale(1.08) translateY(-2px);
                    box-shadow: 0 8px 32px rgba(0,255,178,0.5);
                }

                /* History link */
                .history-link {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    color: var(--text-2);
                    font-size: 13px;
                    font-weight: 600;
                    text-decoration: none;
                    border: 1px solid var(--border);
                    border-radius: 8px;
                    padding: 8px 14px;
                    transition: all 0.2s;
                    letter-spacing: 0.02em;
                }
                .history-link:hover {
                    color: var(--text-1);
                    border-color: rgba(255,255,255,0.15);
                    background: rgba(255,255,255,0.04);
                }

                /* Divider */
                .divider {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    margin: 16px 0;
                }
                .divider-line {
                    flex: 1;
                    height: 1px;
                    background: var(--border);
                }
                .divider-text {
                    font-size: 11px;
                    font-weight: 600;
                    color: var(--text-2);
                    letter-spacing: 0.1em;
                    text-transform: uppercase;
                }

                /* Shimmer on submit button */
                .btn-neon::before {
                    content: '';
                    position: absolute;
                    top: 0; left: -100%;
                    width: 60%;
                    height: 100%;
                    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.25), transparent);
                    transform: skewX(-20deg);
                    transition: left 0.5s;
                }
                .btn-neon:hover::before { left: 160%; }

                /* Fade in animation */
                @keyframes fadeUp {
                    from { opacity: 0; transform: translateY(16px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                .fade-up { animation: fadeUp 0.5s ease forwards; }
                .fade-up-1 { animation-delay: 0.05s; opacity: 0; }
                .fade-up-2 { animation-delay: 0.12s; opacity: 0; }
                .fade-up-3 { animation-delay: 0.2s; opacity: 0; }

                /* Camera switch button */
                .switch-cam-btn {
                    position: absolute;
                    top: 14px;
                    right: 14px;
                    width: 42px;
                    height: 42px;
                    border-radius: 12px;
                    background: rgba(0,0,0,0.6);
                    backdrop-filter: blur(8px);
                    border: 1px solid rgba(255,255,255,0.1);
                    color: white;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    transition: all 0.2s;
                    z-index: 10;
                }
                .switch-cam-btn:hover {
                    background: rgba(0,255,178,0.2);
                    border-color: rgba(0,255,178,0.5);
                }

                /* Preview scanning effect */
                .preview-scan-line {
                    position: absolute;
                    left: 0; top: 0;
                    width: 100%;
                    height: 2px;
                    background: linear-gradient(90deg, transparent, var(--neon), transparent);
                    animation: preview-scan 2.5s ease-in-out infinite;
                    opacity: 0.6;
                }
                @keyframes preview-scan {
                    0% { top: 0; opacity: 0.8; }
                    50% { opacity: 0.4; }
                    100% { top: 100%; opacity: 0; }
                }

                /* Modal feature row */
                .modal-feature {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    padding: 10px 0;
                    border-bottom: 1px solid var(--border);
                    color: var(--text-2);
                    font-size: 13px;
                }
                .modal-feature:last-child { border-bottom: none; }
                .modal-feature-icon {
                    width: 30px;
                    height: 30px;
                    border-radius: 8px;
                    background: var(--neon-dim);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    flex-shrink: 0;
                    color: var(--neon);
                }

                /* Capture button */
                .btn-capture {
                    background: linear-gradient(135deg, #00FFB2, #00D4FF);
                    color: #000;
                    font-weight: 700;
                    font-size: 14px;
                    border: none;
                    border-radius: 10px;
                    padding: 13px 28px;
                    cursor: pointer;
                    transition: all 0.25s;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    flex: 1;
                    justify-content: center;
                    box-shadow: 0 4px 20px rgba(0,255,178,0.3);
                }
                .btn-capture:hover {
                    transform: translateY(-1px);
                    box-shadow: 0 8px 30px rgba(0,255,178,0.4);
                }
            `}</style>

            <div className="scan-page">
                <div className="grid-bg" />
                <div className="top-glow" />

                <Header />

                <div className="mx-6">
                    <AnalysisLoadingDialog isOpen={showLoading} />
                </div>

                {/* QR Modal */}
                {showQRModal && (
                    <div className="modal-overlay" onClick={() => setShowQRModal(false)}>
                        <div className="modal-inner" onClick={(e) => e.stopPropagation()}>
                            <div className="corner corner-tl" />
                            <div className="corner corner-tr" />
                            <div className="corner corner-bl" />
                            <div className="corner corner-br" />

                            <button
                                onClick={() => setShowQRModal(false)}
                                style={{
                                    position: 'absolute', top: 16, right: 16,
                                    background: 'rgba(255,255,255,0.05)',
                                    border: '1px solid rgba(255,255,255,0.08)',
                                    borderRadius: 8, padding: 6, cursor: 'pointer',
                                    color: '#7A8394', transition: 'all 0.2s',
                                    display: 'flex', alignItems: 'center',
                                }}
                            >
                                <X size={16} />
                            </button>

                            <div style={{ textAlign: 'center', marginBottom: 28 }}>
                                <div style={{
                                    width: 56, height: 56,
                                    background: 'var(--neon-dim)',
                                    border: '1.5px solid rgba(0,255,178,0.3)',
                                    borderRadius: 16, display: 'flex',
                                    alignItems: 'center', justifyContent: 'center',
                                    margin: '0 auto 14px',
                                }}>
                                    <Smartphone size={26} color="var(--neon)" />
                                </div>
                                <h2 style={{ color: 'var(--text-1)', fontSize: 22, fontWeight: 700, margin: 0 }}>
                                    Install Mobile App
                                </h2>
                                <p style={{ color: 'var(--text-2)', fontSize: 13, marginTop: 6 }}>
                                    Scan with your device to download the Android app
                                </p>
                            </div>

                            <div style={{ display: 'flex', justifyContent: 'center', marginBottom: 24 }}>
                                <div style={{
                                    background: '#fff',
                                    borderRadius: 16,
                                    padding: 12,
                                    boxShadow: '0 0 40px rgba(0,255,178,0.2)',
                                }}>
                                    <img
                                        src="/doglens_apk_qr.jpeg"
                                        alt="QR Code"
                                        style={{ width: 160, height: 160, display: 'block' }}
                                    />
                                </div>
                            </div>

                            <div style={{
                                background: 'rgba(255,255,255,0.02)',
                                border: '1px solid var(--border)',
                                borderRadius: 12,
                                overflow: 'hidden',
                                marginBottom: 20,
                            }}>
                                <div className="modal-feature">
                                    <div className="modal-feature-icon"><Download size={14} /></div>
                                    Fast & Easy Installation
                                </div>
                                <div className="modal-feature">
                                    <div className="modal-feature-icon"><Smartphone size={14} /></div>
                                    Available on Android
                                </div>
                                <div className="modal-feature">
                                    <div className="modal-feature-icon"><Camera size={14} /></div>
                                    All Features On-The-Go
                                </div>
                            </div>

                            <button className="btn-neon" style={{ width: '100%', justifyContent: 'center' }} onClick={() => setShowQRModal(false)}>
                                Close
                            </button>
                        </div>
                    </div>
                )}

                {/* FAB */}
                <button className="fab-qr" onClick={() => setShowQRModal(true)} title="Install Mobile App">
                    <QrCode size={20} />
                </button>

                <div className="content-wrap" style={{ padding: '0 16px', paddingBottom: 60 }}>
                    <div style={{ maxWidth: 900, margin: '0 auto', paddingTop: 32 }}>

                        {/* Page Header */}
                        <div className="fade-up fade-up-1" style={{
                            display: 'flex',
                            alignItems: 'flex-start',
                            justifyContent: 'space-between',
                            marginBottom: 28,
                            flexWrap: 'wrap',
                            gap: 12,
                        }}>
                            <div>
                                <div className="status-badge" style={{ marginBottom: 12 }}>
                                    <span className="status-dot" />
                                    AI Breed Detection
                                </div>
                                <h1 style={{
                                    color: 'var(--text-1)',
                                    fontSize: 'clamp(22px, 4vw, 30px)',
                                    fontWeight: 800,
                                    margin: 0,
                                    lineHeight: 1.15,
                                    letterSpacing: '-0.02em',
                                }}>
                                    Scan Your Dog
                                </h1>
                                <p style={{
                                    color: 'var(--text-2)',
                                    fontSize: 14,
                                    marginTop: 6,
                                    fontWeight: 400,
                                    lineHeight: 1.5,
                                }}>
                                    Upload a photo or use your camera to identify breed with precision.
                                </p>
                            </div>
                            <a href="/scanhistory" className="history-link">
                                <History size={15} />
                                Scan History
                            </a>
                        </div>

                        {/* Error Messages */}
                        {localError && showLocalError && (
                            <div className="alert-error fade-up" style={{ marginBottom: 20, opacity: showLocalError ? 1 : 0 }}>
                                <div style={{ display: 'flex', alignItems: 'flex-start', gap: 12 }}>
                                    <XCircle size={18} color="var(--red)" style={{ flexShrink: 0, marginTop: 2 }} />
                                    <div style={{ flex: 1 }}>
                                        <p style={{ color: '#FF8091', fontWeight: 700, margin: 0, fontSize: 14 }}>
                                            {localError.not_a_dog ? 'Not a Dog Detected' : 'Analysis Error'}
                                        </p>
                                        <p style={{ color: '#FF8091', fontSize: 13, margin: '4px 0 0', opacity: 0.8 }}>
                                            {localError.message}
                                        </p>
                                        {localError.not_a_dog && (
                                            <button
                                                onClick={handleReset}
                                                className="btn-neon"
                                                style={{ marginTop: 14, background: 'var(--red)', color: '#fff' }}
                                            >
                                                Try Another Image
                                            </button>
                                        )}
                                    </div>
                                </div>
                            </div>
                        )}

                        {cameraError && (
                            <div className="alert-camera fade-up" style={{ marginBottom: 20 }}>
                                <div style={{ display: 'flex', alignItems: 'flex-start', gap: 12 }}>
                                    <CircleAlert size={18} color="var(--amber)" style={{ flexShrink: 0, marginTop: 2 }} />
                                    <div>
                                        <p style={{ color: '#FFD080', fontWeight: 700, margin: 0, fontSize: 14 }}>Camera Error</p>
                                        <p style={{ color: '#FFD080', fontSize: 13, margin: '4px 0 0', opacity: 0.8 }}>{cameraError}</p>
                                        <p className="mono" style={{ color: '#FFD080', fontSize: 11, marginTop: 6, opacity: 0.5 }}>
                                            Auto-dismissing in 5s
                                        </p>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Main Card */}
                        <div
                            className={`scan-card fade-up fade-up-2 ${showCamera ? '' : ''}`}
                            style={{ transition: 'all 0.3s' }}
                        >
                            <div className="corner corner-tl" />
                            <div className="corner corner-tr" />
                            <div className="corner corner-bl" />
                            <div className="corner corner-br" />

                            <form onSubmit={handleSubmit} style={{ padding: '28px 28px' }}>
                                {!preview && !showCamera ? (
                                    <>
                                        {/* Drop Zone */}
                                        <div
                                            className={`drop-zone ${isDragging ? 'dragging' : ''}`}
                                            onClick={triggerFileInput}
                                            onDragOver={handleDragOver}
                                            onDragLeave={handleDragLeave}
                                            onDrop={handleDrop}
                                            style={{
                                                minHeight: 240,
                                                display: 'flex',
                                                flexDirection: 'column',
                                                alignItems: 'center',
                                                justifyContent: 'center',
                                                gap: 16,
                                                padding: '40px 20px',
                                            }}
                                        >
                                            <input
                                                ref={fileInputRef}
                                                type="file"
                                                accept="image/*"
                                                className="hidden"
                                                onChange={handleFileChange}
                                            />

                                            <div className="icon-ring">
                                                <Upload size={28} color="var(--neon)" />
                                            </div>

                                            <div style={{ textAlign: 'center' }}>
                                                <p style={{ color: 'var(--text-1)', fontWeight: 700, fontSize: 16, margin: 0 }}>
                                                    Drop your dog image here
                                                </p>
                                                <p style={{ color: 'var(--text-2)', fontSize: 13, marginTop: 5 }}>
                                                    or <span style={{ color: 'var(--neon)', fontWeight: 600 }}>click to browse</span>
                                                </p>
                                            </div>

                                            <p className="mono" style={{ color: 'var(--text-2)', fontSize: 11, opacity: 0.6, margin: 0 }}>
                                                ALL IMAGE FORMATS · MAX 10MB
                                            </p>
                                        </div>

                                        {/* Divider */}
                                        <div className="divider">
                                            <div className="divider-line" />
                                            <span className="divider-text">or</span>
                                            <div className="divider-line" />
                                        </div>

                                        {/* Camera Button */}
                                        <button type="button" onClick={startCamera} className="btn-outline-neon">
                                            <Camera size={17} />
                                            Use Camera
                                        </button>

                                        <p className="mono" style={{
                                            textAlign: 'center',
                                            color: 'var(--text-2)',
                                            fontSize: 11,
                                            marginTop: 10,
                                            opacity: 0.5,
                                        }}>
                                            SUPPORTS CHROME · EDGE · SAFARI · FIREFOX
                                        </p>

                                        {errors.image && (
                                            <p style={{ color: 'var(--red)', fontSize: 13, marginTop: 12, textAlign: 'center' }}>
                                                {errors.image}
                                            </p>
                                        )}

                                        {/* Tips */}
                                        <div className="tips-card" style={{ marginTop: 24 }}>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 12 }}>
                                                <Zap size={15} color="var(--neon)" />
                                                <span style={{ color: 'var(--neon)', fontWeight: 700, fontSize: 12, letterSpacing: '0.08em', textTransform: 'uppercase' }}>
                                                    Tips for Best Results
                                                </span>
                                            </div>
                                            <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                                                {[
                                                    'Ensure your dog is clearly visible in the frame',
                                                    'Use good lighting without harsh shadows',
                                                    'Center your dog and avoid cluttered backgrounds',
                                                    'Front or side angles work best for accuracy',
                                                    'Only dog images are accepted',
                                                ].map((tip, i) => (
                                                    <div key={i} style={{ display: 'flex', gap: 10, alignItems: 'flex-start' }}>
                                                        <span className="tip-dot" />
                                                        <span style={{ color: 'rgba(0,255,178,0.75)', fontSize: 13, lineHeight: 1.5, fontWeight: i === 4 ? 700 : 400 }}>
                                                            {tip}
                                                        </span>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    </>
                                ) : showCamera ? (
                                    <div>
                                        <div className="camera-wrap">
                                            <video
                                                ref={videoRef}
                                                autoPlay
                                                playsInline
                                                muted
                                                style={{
                                                    display: 'block',
                                                    width: '100%',
                                                    maxHeight: '65vh',
                                                    objectFit: 'cover',
                                                    background: '#000',
                                                }}
                                            />
                                            <canvas ref={canvasRef} style={{ display: 'none' }} />
                                            <button
                                                type="button"
                                                onClick={switchCamera}
                                                className="switch-cam-btn"
                                                title="Switch Camera"
                                            >
                                                <SwitchCamera size={18} />
                                            </button>
                                        </div>
                                        <div style={{ display: 'flex', gap: 12, marginTop: 20 }}>
                                            <button type="button" onClick={capturePhoto} className="btn-capture">
                                                <ScanIcon size={18} />
                                                Capture & Scan
                                            </button>
                                            <button type="button" onClick={stopCamera} className="btn-ghost">
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                ) : (
                                    <div>
                                        <div className="preview-wrap">
                                            <div className="preview-scan-line" />
                                            <img
                                                src={preview || ''}
                                                style={{
                                                    display: 'block',
                                                    maxHeight: 400,
                                                    width: '100%',
                                                    objectFit: 'contain',
                                                    background: '#000',
                                                }}
                                                alt="Preview"
                                            />
                                        </div>

                                        {fileInfo && (
                                            <p className="mono" style={{
                                                textAlign: 'center',
                                                color: 'var(--text-2)',
                                                fontSize: 11,
                                                marginTop: 10,
                                                opacity: 0.6,
                                            }}>
                                                {fileInfo}
                                            </p>
                                        )}

                                        <div style={{ display: 'flex', gap: 12, marginTop: 20 }}>
                                            <button
                                                type="submit"
                                                className="btn-neon"
                                                disabled={processing}
                                                style={{ flex: 1, justifyContent: 'center' }}
                                            >
                                                <ScanIcon size={17} />
                                                {processing ? 'Analyzing...' : 'Analyze Image'}
                                            </button>
                                            <button
                                                type="button"
                                                onClick={handleReset}
                                                className="btn-ghost"
                                                disabled={processing}
                                            >
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