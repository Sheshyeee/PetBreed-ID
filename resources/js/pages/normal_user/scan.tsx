import AnalysisLoadingDialog from '@/components/AnalysisLoadingDialog';
import Header from '@/components/header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Link, useForm, usePage } from '@inertiajs/react';
import { Camera, CircleAlert, SwitchCamera, XCircle } from 'lucide-react';
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
    const [facingMode, setFacingMode] = useState<'user' | 'environment'>(
        'environment',
    );
    const [cameraError, setCameraError] = useState<string | null>(null);
    const [localError, setLocalError] = useState<ErrorFlash | null>(null);
    const [showLocalError, setShowLocalError] = useState(false);

    // Check if camera is supported on current platform
    const isCameraSupported = () => {
        // Check if mediaDevices API is available
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            return false;
        }

        // Check for supported browsers
        const userAgent = navigator.userAgent.toLowerCase();
        const isChrome =
            /chrome|chromium|crios/.test(userAgent) && !/edg/.test(userAgent);
        const isEdge = /edg/.test(userAgent);
        const isSafari = /safari/.test(userAgent) && !/chrome/.test(userAgent);
        const isFirefox = /firefox|fxios/.test(userAgent);

        return isChrome || isEdge || isSafari || isFirefox;
    };

    // Handle flash error messages with timeout
    useEffect(() => {
        if (error?.message) {
            setShowLoading(false);
            setLocalError(error);
            setShowLocalError(true);

            // Auto-dismiss error after 5 seconds
            const timer = setTimeout(() => {
                setShowLocalError(false);
                // Clear the error after fade out animation
                setTimeout(() => {
                    setLocalError(null);
                }, 500); // Match transition duration
            }, 7000);

            return () => clearTimeout(timer);
        }
    }, [error]);

    useEffect(() => {
        if (success) {
            setShowLoading(false);
        }
    }, [success]);

    // Auto-dismiss camera error
    useEffect(() => {
        if (cameraError) {
            const timer = setTimeout(() => {
                setCameraError(null);
            }, 5000);

            return () => clearTimeout(timer);
        }
    }, [cameraError]);

    // Cleanup camera stream on unmount
    useEffect(() => {
        return () => {
            if (stream) {
                stream.getTracks().forEach((track) => track.stop());
            }
        };
    }, [stream]);

    const validateImageFile = (file: File): string | null => {
        if (file.size > 10 * 1024 * 1024) {
            return 'File is too large. Maximum size is 10MB.';
        }

        const validTypes = [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/webp',
            'image/gif',
            'image/avif',
            'image/bmp',
            'image/x-ms-bmp',
            'image/svg+xml',
        ];

        if (!validTypes.includes(file.type)) {
            console.warn('File type not in valid list:', file.type);
        }

        return null;
    };

    const handleFileChange = (e: ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            processImageFile(file);
        }
    };

    const processImageFile = (file: File) => {
        console.log('=== FILE SELECTED ===');
        console.log('Name:', file.name);
        console.log('Size:', file.size, 'bytes');
        console.log('Type:', file.type);

        const validationError = validateImageFile(file);
        if (validationError) {
            alert(validationError);
            return;
        }

        // Create preview using URL.createObjectURL instead of FileReader
        const objectUrl = URL.createObjectURL(file);
        setPreview(objectUrl);

        // Load image to get dimensions
        const img = new Image();
        img.onload = () => {
            console.log('Image loaded successfully');
            console.log('Dimensions:', img.width, 'x', img.height);
            setFileInfo(
                `${file.name} (${(file.size / 1024).toFixed(1)}KB, ${img.width}x${img.height})`,
            );
            // Clean up the object URL after image is loaded
            URL.revokeObjectURL(objectUrl);
        };
        img.onerror = () => {
            console.error('Failed to load image preview');
            setFileInfo(`${file.name} (${(file.size / 1024).toFixed(1)}KB)`);
            URL.revokeObjectURL(objectUrl);
        };
        img.src = objectUrl;

        setData('image', file);
        stopCamera(); // Stop camera when file is selected
    };

    const triggerFileInput = (): void => {
        fileInputRef.current?.click();
    };

    const startCamera = async () => {
        // Check platform support first
        if (!isCameraSupported()) {
            alert(
                'Camera feature is only available on Chrome, Edge, Safari, and Firefox browsers. Please use one of these browsers or upload an image file instead.',
            );
            return;
        }

        try {
            setCameraError(null);

            // Stop any existing stream first
            if (stream) {
                stream.getTracks().forEach((track) => track.stop());
                setStream(null);
            }

            const constraints: MediaStreamConstraints = {
                video: {
                    facingMode: facingMode,
                    width: { ideal: 1920 },
                    height: { ideal: 1080 },
                },
                audio: false,
            };

            console.log('Requesting camera with facingMode:', facingMode);
            const mediaStream =
                await navigator.mediaDevices.getUserMedia(constraints);

            setStream(mediaStream);

            // Wait a bit for state to update before setting video source
            setTimeout(() => {
                if (videoRef.current && mediaStream.active) {
                    videoRef.current.srcObject = mediaStream;
                    videoRef.current.play().catch((err) => {
                        console.error('Error playing video:', err);
                    });
                }
            }, 100);

            setShowCamera(true);
        } catch (err: any) {
            console.error('Error accessing camera:', err);
            let errorMessage = 'Unable to access camera. ';

            if (err.name === 'NotAllowedError') {
                errorMessage +=
                    'Please allow camera permissions and try again.';
            } else if (err.name === 'NotFoundError') {
                errorMessage += 'No camera found on this device.';
            } else if (err.name === 'NotReadableError') {
                errorMessage +=
                    'Camera is already in use by another application.';
            } else {
                errorMessage +=
                    'Please check permissions or use file upload instead.';
            }

            setCameraError(errorMessage);
            setShowCamera(false);
        }
    };

    const stopCamera = () => {
        if (stream) {
            stream.getTracks().forEach((track) => {
                track.stop();
            });
            setStream(null);
        }
        if (videoRef.current) {
            videoRef.current.srcObject = null;
        }
        setShowCamera(false);
        setCameraError(null);
    };

    const switchCamera = async () => {
        const newFacingMode = facingMode === 'user' ? 'environment' : 'user';
        setFacingMode(newFacingMode);

        // Stop current stream
        if (stream) {
            stream.getTracks().forEach((track) => track.stop());
            setStream(null);
        }

        // Small delay before starting new stream
        setTimeout(async () => {
            try {
                setCameraError(null);

                const constraints: MediaStreamConstraints = {
                    video: {
                        facingMode: newFacingMode,
                        width: { ideal: 1920 },
                        height: { ideal: 1080 },
                    },
                    audio: false,
                };

                console.log('Switching to facingMode:', newFacingMode);
                const mediaStream =
                    await navigator.mediaDevices.getUserMedia(constraints);

                setStream(mediaStream);

                if (videoRef.current && mediaStream.active) {
                    videoRef.current.srcObject = mediaStream;
                    videoRef.current.play().catch((err) => {
                        console.error('Error playing video after switch:', err);
                    });
                }
            } catch (err: any) {
                console.error('Error switching camera:', err);
                setCameraError(
                    'Failed to switch camera. This device may only have one camera.',
                );
                // Try to restart with original facing mode
                setFacingMode(facingMode === 'user' ? 'environment' : 'user');
            }
        }, 200);
    };

    const capturePhoto = () => {
        if (videoRef.current && canvasRef.current) {
            const video = videoRef.current;
            const canvas = canvasRef.current;

            // Check if video is actually playing
            if (video.readyState !== video.HAVE_ENOUGH_DATA) {
                alert(
                    'Camera is still loading. Please wait a moment and try again.',
                );
                return;
            }

            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;

            const ctx = canvas.getContext('2d');
            if (ctx) {
                ctx.drawImage(video, 0, 0);

                canvas.toBlob(
                    (blob) => {
                        if (blob) {
                            const file = new File(
                                [blob],
                                `camera-capture-${Date.now()}.jpg`,
                                { type: 'image/jpeg' },
                            );
                            processImageFile(file);
                        }
                    },
                    'image/jpeg',
                    0.95,
                );
            }
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!data.image) {
            alert('Please select an image first');
            return;
        }

        console.log(
            'Submitting:',
            data.image.name,
            data.image.type,
            data.image.size,
        );

        setShowLoading(true);

        post('/analyze', {
            forceFormData: true,
            preserveScroll: false,
            onError: () => {
                setShowLoading(false);
            },
        });
    };

    const handleReset = () => {
        // Revoke object URL if preview exists
        if (preview) {
            URL.revokeObjectURL(preview);
        }

        reset();
        setPreview(null);
        setFileInfo('');
        setShowLoading(false);
        setCameraError(null);
        setLocalError(null);
        setShowLocalError(false);
        stopCamera();
    };

    // Cleanup preview URL on unmount
    useEffect(() => {
        return () => {
            if (preview) {
                URL.revokeObjectURL(preview);
            }
        };
    }, [preview]);

    return (
        <>
            <Header />

            {/* Compact Loading Dialog */}
            <div className="mx-6">
                <AnalysisLoadingDialog isOpen={showLoading} />
            </div>

            <div className="mt-[-45px] p-4 text-gray-900 sm:mt-[-20px] sm:p-0 dark:text-white">
                <div className="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-10">
                    {/* Page Header */}
                    <div className="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div className="flex-1">
                            <h1 className="text-lg font-bold tracking-tight text-gray-900 sm:text-lg dark:text-white">
                                Scan Your Dog
                            </h1>
                            <p className="mt-[-5px] text-sm text-gray-600 sm:text-sm dark:text-gray-400">
                                Upload a photo or use your camera to identify
                                your dog's breed with AI-powered analysis.
                            </p>
                        </div>
                        <Button
                            asChild
                            variant="outline"
                            className="shrink-0 border-gray-300 bg-white hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:bg-gray-800"
                        >
                            <Link href="/scanhistory">Scan History</Link>
                        </Button>
                    </div>

                    {/* Error Message with Auto-dismiss */}
                    {localError && showLocalError && (
                        <Card
                            className={`mb-6 border-red-300 bg-red-50 shadow-sm transition-opacity duration-500 dark:border-red-900 dark:bg-red-950/50 ${
                                showLocalError ? 'opacity-100' : 'opacity-0'
                            }`}
                        >
                            <CardContent className="p-4 sm:p-6">
                                <div className="flex items-start gap-3">
                                    <XCircle
                                        size={22}
                                        className="mt-0.5 shrink-0 text-red-600 dark:text-red-400"
                                    />
                                    <div className="flex-1">
                                        <p className="font-semibold text-red-900 dark:text-red-400">
                                            {localError.not_a_dog
                                                ? 'Not a Dog Detected'
                                                : 'Error'}
                                        </p>
                                        <p className="mt-1 text-sm text-red-700 dark:text-red-300">
                                            {localError.message}
                                        </p>

                                        {localError.not_a_dog && (
                                            <Button
                                                onClick={handleReset}
                                                className="mt-4 bg-red-600 hover:bg-red-700 dark:bg-white dark:hover:bg-red-600"
                                            >
                                                Upload Another Image
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Camera Error with Auto-dismiss */}
                    {cameraError && (
                        <Card className="mb-6 border-orange-300 bg-orange-50 shadow-sm transition-opacity duration-500 dark:border-orange-900 dark:bg-orange-950/50">
                            <CardContent className="p-4 sm:p-6">
                                <div className="flex items-start gap-3">
                                    <CircleAlert
                                        size={22}
                                        className="mt-0.5 shrink-0 text-orange-600 dark:text-orange-400"
                                    />
                                    <div className="flex-1">
                                        <p className="font-semibold text-orange-900 dark:text-orange-400">
                                            Camera Error
                                        </p>
                                        <p className="mt-1 text-sm text-orange-700 dark:text-orange-300">
                                            {cameraError}
                                        </p>
                                        <p className="mt-2 text-xs text-orange-600 dark:text-orange-400">
                                            This message will disappear in 5
                                            seconds
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Upload Card - Wider for camera */}
                    <Card
                        className={`mx-auto w-full border-gray-200 bg-white p-1 shadow-md dark:border-gray-800 dark:bg-gray-900 ${
                            showCamera ? 'max-w-6xl' : 'max-w-4xl'
                        } transition-all duration-300`}
                    >
                        <form onSubmit={handleSubmit}>
                            <CardContent className="p-6 sm:p-8">
                                {!preview && !showCamera ? (
                                    <>
                                        <div
                                            onClick={triggerFileInput}
                                            className="group relative flex h-64 cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 transition-all hover:border-gray-400 hover:bg-gray-100 sm:h-72 lg:h-60 dark:border-gray-700 dark:bg-gray-800/50 dark:hover:border-gray-600 dark:hover:bg-gray-800"
                                        >
                                            <input
                                                ref={fileInputRef}
                                                type="file"
                                                accept="image/*"
                                                className="hidden"
                                                onChange={handleFileChange}
                                            />

                                            <div className="flex flex-col items-center">
                                                <div className="rounded-full bg-gray-200 p-4 transition-all group-hover:scale-110 dark:bg-gray-700">
                                                    <Camera
                                                        size={40}
                                                        className="text-gray-600 dark:text-gray-300"
                                                    />
                                                </div>

                                                <p className="mt-4 text-base font-medium text-gray-700 dark:text-gray-300">
                                                    Drop your dog image here
                                                </p>
                                                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                                    or click to browse
                                                </p>
                                                <p className="mt-4 text-xs text-gray-500 dark:text-gray-500">
                                                    All image formats supported
                                                    • Max 10 MB
                                                </p>
                                            </div>
                                        </div>

                                        {/* Camera Button */}
                                        <div className="mt-4 w-full">
                                            <Button
                                                type="button"
                                                onClick={startCamera}
                                                variant="outline"
                                                className="w-full gap-2 border-gray-300 bg-white hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700"
                                            >
                                                <Camera size={20} />
                                                Use Camera
                                            </Button>
                                            <p className="mt-2 text-center text-[9px] text-gray-500 dark:text-gray-400">
                                                Use Chrome, Edge, Safari, or
                                                Firefox for camera
                                            </p>
                                        </div>

                                        {errors.image && (
                                            <p className="mt-3 text-sm font-medium text-red-600 dark:text-red-400">
                                                {errors.image}
                                            </p>
                                        )}

                                        <Card className="mt-4 border-blue-200 bg-blue-50 dark:border-blue-900 dark:bg-blue-950/30">
                                            <CardContent className="p-0 px-4">
                                                <div className="flex items-center gap-2">
                                                    <CircleAlert
                                                        size={20}
                                                        className="shrink-0 text-blue-600 dark:text-blue-400"
                                                    />
                                                    <p className="font-semibold text-blue-900 dark:text-blue-300">
                                                        Tips for Best Results
                                                    </p>
                                                </div>

                                                <ul className="mt-3 space-y-1 pl-1">
                                                    <li className="flex items-start gap-2 text-sm text-blue-800 dark:text-blue-200">
                                                        <span className="inline-block h-1.5 w-1.5 shrink-0 rounded-full bg-blue-600 dark:bg-blue-400"></span>
                                                        <span>
                                                            Ensure your dog is
                                                            clearly visible in
                                                            the frame
                                                        </span>
                                                    </li>
                                                    <li className="flex items-start gap-2 text-sm text-blue-800 dark:text-blue-200">
                                                        <span className="inline-block h-1.5 w-1.5 shrink-0 rounded-full bg-blue-600 dark:bg-blue-400"></span>
                                                        <span>
                                                            Use good lighting
                                                            without harsh
                                                            shadows
                                                        </span>
                                                    </li>
                                                    <li className="flex items-start gap-2 text-sm text-blue-800 dark:text-blue-200">
                                                        <span className="inline-block h-1.5 w-1.5 shrink-0 rounded-full bg-blue-600 dark:bg-blue-400"></span>
                                                        <span>
                                                            Center your dog and
                                                            avoid cluttered
                                                            backgrounds
                                                        </span>
                                                    </li>
                                                    <li className="flex items-start gap-2 text-sm text-blue-800 dark:text-blue-200">
                                                        <span className="inline-block h-1.5 w-1.5 shrink-0 rounded-full bg-blue-600 dark:bg-blue-400"></span>
                                                        <span>
                                                            Front or side angles
                                                            work best for
                                                            accuracy
                                                        </span>
                                                    </li>
                                                    <li className="flex items-start gap-2 text-sm text-blue-800 dark:text-blue-200">
                                                        <span className="inline-block h-1.5 w-1.5 shrink-0 rounded-full bg-blue-600 dark:bg-blue-400"></span>
                                                        <span className="font-semibold">
                                                            Only dog images are
                                                            accepted
                                                        </span>
                                                    </li>
                                                </ul>
                                            </CardContent>
                                        </Card>
                                    </>
                                ) : showCamera ? (
                                    <div>
                                        <div className="relative overflow-hidden rounded-xl border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                                            <video
                                                ref={videoRef}
                                                autoPlay
                                                playsInline
                                                muted
                                                className="mx-auto h-[70vh] w-full object-cover"
                                            />
                                            <canvas
                                                ref={canvasRef}
                                                className="hidden"
                                            />

                                            {/* Switch Camera Button */}
                                            <button
                                                type="button"
                                                onClick={switchCamera}
                                                className="absolute top-4 right-4 rounded-full bg-black/50 p-3 text-white transition-all hover:bg-black/70"
                                                title="Switch Camera"
                                            >
                                                <SwitchCamera size={24} />
                                            </button>
                                        </div>

                                        <div className="mt-6 flex flex-col gap-3 sm:flex-row">
                                            <Button
                                                type="button"
                                                onClick={capturePhoto}
                                                className="w-full bg-blue-600 hover:bg-blue-700 sm:flex-1 dark:bg-blue-600 dark:hover:bg-blue-700"
                                            >
                                                <Camera
                                                    size={20}
                                                    className="mr-2"
                                                />
                                                Capture Photo
                                            </Button>
                                            <Button
                                                type="button"
                                                className="w-full border-gray-300 bg-white hover:bg-gray-50 sm:w-auto sm:px-8 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700"
                                                variant="outline"
                                                onClick={stopCamera}
                                            >
                                                Cancel
                                            </Button>
                                        </div>
                                    </div>
                                ) : (
                                    <div>
                                        <div className="overflow-hidden rounded-xl border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                                            <img
                                                src={preview || ''}
                                                className="mx-auto max-h-80 w-full object-contain sm:max-h-96"
                                                alt="Preview"
                                            />
                                        </div>
                                        {fileInfo && (
                                            <p className="mt-3 text-center text-sm text-gray-600 dark:text-gray-400">
                                                {fileInfo}
                                            </p>
                                        )}
                                        <div className="mt-6 flex flex-col gap-3 sm:flex-row">
                                            <Button
                                                type="submit"
                                                className="w-full bg-blue-600 hover:bg-blue-700 sm:flex-1 dark:bg-blue-600 dark:hover:bg-blue-700"
                                                disabled={processing}
                                            >
                                                {processing
                                                    ? 'Analyzing...'
                                                    : 'Analyze Image'}
                                            </Button>
                                            <Button
                                                type="button"
                                                className="w-full border-gray-300 bg-white hover:bg-gray-50 sm:w-auto sm:px-8 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700"
                                                variant="outline"
                                                onClick={handleReset}
                                                disabled={processing}
                                            >
                                                Cancel
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </form>
                    </Card>
                </div>
            </div>
        </>
    );
};

export default Scan;
