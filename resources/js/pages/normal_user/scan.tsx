import Header from '@/components/header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Link } from '@inertiajs/react';
import { Camera, CircleAlert, RefreshCw, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface CameraInterfaceProps {
    onCapture?: (file: File, imageUrl: string) => void;
    onClose?: () => void;
}

const CameraInterface: React.FC<CameraInterfaceProps> = ({
    onCapture,
    onClose,
}) => {
    const videoRef = useRef<HTMLVideoElement>(null);
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const [stream, setStream] = useState<MediaStream | null>(null);
    const [isLoading, setIsLoading] = useState<boolean>(true);
    const [error, setError] = useState<string | null>(null);
    const [facingMode, setFacingMode] = useState<'user' | 'environment'>(
        'environment',
    );
    const [hasMultipleCameras, setHasMultipleCameras] =
        useState<boolean>(false);
    const [capturedImage, setCapturedImage] = useState<string | null>(null);

    useEffect(() => {
        checkMultipleCameras();
        startCamera();

        return () => {
            stopCamera();
        };
    }, [facingMode]);

    const checkMultipleCameras = async (): Promise<void> => {
        try {
            const devices = await navigator.mediaDevices.enumerateDevices();
            const videoDevices = devices.filter(
                (device) => device.kind === 'videoinput',
            );
            setHasMultipleCameras(videoDevices.length > 1);
        } catch (err) {
            console.error('Error checking cameras:', err);
        }
    };

    const startCamera = async (): Promise<void> => {
        setIsLoading(true);
        setError(null);

        try {
            if (stream) {
                stream.getTracks().forEach((track) => track.stop());
            }

            const constraints: MediaStreamConstraints = {
                video: {
                    facingMode: facingMode,
                    width: { ideal: 1920 },
                    height: { ideal: 1080 },
                },
                audio: false,
            };

            const mediaStream =
                await navigator.mediaDevices.getUserMedia(constraints);

            if (videoRef.current) {
                videoRef.current.srcObject = mediaStream;
                setStream(mediaStream);
            }

            setIsLoading(false);
        } catch (err) {
            console.error('Error accessing camera:', err);
            const error = err as DOMException;
            setError(
                error.name === 'NotAllowedError'
                    ? 'Camera access denied. Please allow camera permissions.'
                    : error.name === 'NotFoundError'
                      ? 'No camera found on this device.'
                      : 'Failed to access camera. Please try again.',
            );
            setIsLoading(false);
        }
    };

    const stopCamera = (): void => {
        if (stream) {
            stream.getTracks().forEach((track) => track.stop());
            setStream(null);
        }
    };

    const switchCamera = (): void => {
        setFacingMode((prev) => (prev === 'user' ? 'environment' : 'user'));
    };

    const capturePhoto = (): void => {
        if (!videoRef.current || !canvasRef.current) return;

        const video = videoRef.current;
        const canvas = canvasRef.current;

        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;

        const context = canvas.getContext('2d');
        if (!context) return;

        context.drawImage(video, 0, 0, canvas.width, canvas.height);

        canvas.toBlob(
            (blob) => {
                if (blob) {
                    const imageUrl = URL.createObjectURL(blob);
                    setCapturedImage(imageUrl);
                }
            },
            'image/jpeg',
            0.95,
        );
    };

    const retakePhoto = (): void => {
        setCapturedImage(null);
        stopCamera();
        if (onClose) onClose();
    };

    const confirmPhoto = (): void => {
        if (capturedImage && onCapture) {
            fetch(capturedImage)
                .then((res) => res.blob())
                .then((blob) => {
                    const file = new File([blob], 'pet-photo.jpg', {
                        type: 'image/jpeg',
                    });
                    onCapture(file, capturedImage);
                    stopCamera();
                });
        }
    };

    const handleClose = (): void => {
        stopCamera();
        if (onClose) onClose();
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/90">
            <div className="relative h-full w-full max-w-4xl">
                <button
                    onClick={handleClose}
                    className="absolute top-4 right-4 z-10 rounded-full bg-black/50 p-2 text-white hover:bg-black/70"
                >
                    <X size={24} />
                </button>

                <div className="flex h-full flex-col items-center justify-center p-4">
                    {error ? (
                        <Card className="w-full max-w-md">
                            <CardContent className="p-6 text-center">
                                <CircleAlert
                                    className="mx-auto mb-4 text-red-500"
                                    size={48}
                                />
                                <p className="mb-4 text-red-600">{error}</p>
                                <Button
                                    onClick={startCamera}
                                    className="w-full"
                                >
                                    Try Again
                                </Button>
                            </CardContent>
                        </Card>
                    ) : capturedImage ? (
                        <div className="flex h-full flex-col items-center justify-center">
                            <img
                                src={capturedImage}
                                alt="Captured"
                                className="max-h-[70vh] rounded-lg object-contain"
                            />
                            <div className="mt-6 flex gap-4">
                                <Link href="/scan-results">
                                    <Button className="w-[500px] flex-1 bg-black px-8 dark:bg-white">
                                        Analyze Image
                                    </Button>
                                </Link>
                                <Button
                                    onClick={retakePhoto}
                                    variant="outline"
                                    className="dark:bg-black dark:text-white"
                                >
                                    Cancel
                                </Button>
                            </div>
                        </div>
                    ) : (
                        <div className="relative w-full max-w-3xl">
                            {isLoading && (
                                <div className="absolute inset-0 flex items-center justify-center rounded-lg bg-gray-900">
                                    <div className="text-center text-white">
                                        <div className="mx-auto mb-2 h-8 w-8 animate-spin rounded-full border-4 border-gray-600 border-t-white"></div>
                                        <p>Starting camera...</p>
                                    </div>
                                </div>
                            )}

                            <video
                                ref={videoRef}
                                autoPlay
                                playsInline
                                muted
                                className="w-full rounded-lg"
                            />

                            <canvas ref={canvasRef} className="hidden" />

                            {!isLoading && (
                                <div className="absolute right-0 bottom-6 left-0 flex items-center justify-center gap-4">
                                    {hasMultipleCameras && (
                                        <button
                                            onClick={switchCamera}
                                            className="rounded-full bg-black/50 p-3 text-white hover:bg-black/70"
                                        >
                                            <RefreshCw size={24} />
                                        </button>
                                    )}

                                    <button
                                        onClick={capturePhoto}
                                        className="rounded-full bg-white p-4 hover:bg-gray-200"
                                    >
                                        <Camera
                                            size={32}
                                            className="text-black"
                                        />
                                    </button>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};

const Scan: React.FC = () => {
    const [showCamera, setShowCamera] = useState<boolean>(false);
    const [uploadedImage, setUploadedImage] = useState<string | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const handleFileUpload = (
        event: React.ChangeEvent<HTMLInputElement>,
    ): void => {
        const file = event.target.files?.[0];
        if (file && file.size <= 10 * 1024 * 1024) {
            const imageUrl = URL.createObjectURL(file);
            setUploadedImage(imageUrl);
            console.log('File uploaded:', file);
        } else {
            alert('File size must be less than 10MB');
        }
    };

    const handleCameraCapture = (file: File, imageUrl: string): void => {
        setUploadedImage(imageUrl);
        setShowCamera(false);
        console.log('Photo captured:', file);
    };

    const triggerFileInput = (): void => {
        fileInputRef.current?.click();
    };

    return (
        <>
            <div>
                <Header />
            </div>
            <div className="flex flex-col items-center bg-[#FDFDFC] text-[#1b1b18] dark:bg-[#0a0a0a]">
                <div className="mx-auto w-full max-w-7xl pl-10">
                    <div>
                        <h1 className="mt-6 text-lg font-bold dark:text-white">
                            Scan Your Pet
                        </h1>
                        <h1 className="text-sm text-gray-600 dark:text-white/70">
                            Upload a photo or use your camera to identify your
                            pet's breed.
                        </h1>
                    </div>

                    <Card className="mx-auto mt-8 w-full max-w-4xl">
                        <CardContent className="p-6">
                            {uploadedImage ? (
                                <div className="text-center">
                                    <img
                                        src={uploadedImage}
                                        alt="Uploaded pet"
                                        className="mx-auto max-h-96 rounded-lg object-contain"
                                    />
                                    <div className="flex gap-4 pr-10 pl-10">
                                        <Button asChild className="mt-4 flex-1">
                                            <Link href="/scan-results">
                                                Analyze Image
                                            </Link>
                                        </Button>

                                        <Button
                                            onClick={() =>
                                                setUploadedImage(null)
                                            }
                                            variant="outline"
                                            className="mt-4"
                                        >
                                            Cancel
                                        </Button>
                                    </div>
                                </div>
                            ) : (
                                <>
                                    <div
                                        onClick={triggerFileInput}
                                        className="flex h-[200px] cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-gray-300 transition hover:border-gray-400 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-gray-600"
                                    >
                                        <input
                                            ref={fileInputRef}
                                            type="file"
                                            accept="image/jpeg,image/png,image/webp"
                                            onChange={handleFileUpload}
                                            className="hidden"
                                        />
                                        <div className="rounded-full bg-gray-200 dark:bg-gray-800">
                                            <Camera
                                                size={36}
                                                className="p-2 text-black dark:text-white"
                                            />
                                        </div>
                                        <p className="mt-2 text-sm text-gray-600 dark:text-white/70">
                                            Drop your image here
                                        </p>
                                        <p className="mt-1 text-sm text-gray-600 dark:text-white/70">
                                            or click to browse
                                        </p>
                                        <p className="mt-1 text-sm text-gray-600 dark:text-white/70">
                                            Supports: JPG, PNG, WebP (Max 10 MB)
                                        </p>
                                    </div>

                                    <div className="my-6 flex items-center">
                                        <div className="flex-grow border-t border-gray-300 dark:border-gray-700"></div>
                                        <span className="mx-4 text-sm text-gray-500 dark:text-gray-400">
                                            or use camera
                                        </span>
                                        <div className="flex-grow border-t border-gray-300 dark:border-gray-700"></div>
                                    </div>

                                    <Button
                                        onClick={() => setShowCamera(true)}
                                        className="w-full dark:bg-gray-900 dark:text-white"
                                    >
                                        <Camera className="mr-2" size={20} />
                                        Use Camera
                                    </Button>

                                    <Card className="mt-6 border-blue-400 bg-blue-50 dark:bg-gray-950">
                                        <CardContent className="p-4">
                                            <div className="flex items-center gap-2">
                                                <CircleAlert
                                                    size={20}
                                                    className="text-blue-600 dark:text-blue-400"
                                                />
                                                <p className="font-bold text-gray-700 dark:text-white/70">
                                                    Tips for Best Results
                                                </p>
                                            </div>
                                            <ul className="mt-2 list-disc space-y-1 pl-8">
                                                <li className="text-sm text-gray-600 dark:text-white/70">
                                                    Ensure your pet is clearly
                                                    visible in the photo
                                                </li>
                                                <li className="text-sm text-gray-600 dark:text-white/70">
                                                    Use a well-lit environment
                                                    for better image quality
                                                </li>
                                                <li className="text-sm text-gray-600 dark:text-white/70">
                                                    Position your pet in the
                                                    center of the frame
                                                </li>
                                                <li className="text-sm text-gray-600 dark:text-white/70">
                                                    Better angles can improve
                                                    accuracy
                                                </li>
                                            </ul>
                                        </CardContent>
                                    </Card>
                                </>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {showCamera && (
                    <CameraInterface
                        onCapture={handleCameraCapture}
                        onClose={() => setShowCamera(false)}
                    />
                )}
            </div>
        </>
    );
};

export default Scan;
