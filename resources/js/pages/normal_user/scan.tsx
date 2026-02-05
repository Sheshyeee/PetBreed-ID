import AnalysisLoadingDialog from '@/components/AnalysisLoadingDialog';
import Header from '@/components/header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useForm, usePage } from '@inertiajs/react';
import { Camera, CheckCircle2, CircleAlert, XCircle } from 'lucide-react';
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
    const pageProps = usePage<PageProps>().props;

    const success = pageProps.flash?.success || pageProps.success;
    const error = pageProps.flash?.error || pageProps.error;

    const { data, setData, post, processing, errors, reset } = useForm({
        image: null as File | null,
    });

    const [preview, setPreview] = useState<string | null>(null);
    const [showResults, setShowResults] = useState(false);
    const [fileInfo, setFileInfo] = useState<string>('');
    const [showLoading, setShowLoading] = useState(false);

    useEffect(() => {
        if (success) {
            setShowResults(true);
            setShowLoading(false);
        }
        if (error) {
            setShowLoading(false);
        }
    }, [success, error]);

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
            console.log('=== FILE SELECTED ===');
            console.log('Name:', file.name);
            console.log('Size:', file.size, 'bytes');
            console.log('Type:', file.type);

            const validationError = validateImageFile(file);
            if (validationError) {
                alert(validationError);
                e.target.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                const result = e.target?.result as string;
                setPreview(result);

                const img = new Image();
                img.onload = () => {
                    console.log('Image loaded successfully');
                    console.log('Dimensions:', img.width, 'x', img.height);
                    setFileInfo(
                        `${file.name} (${(file.size / 1024).toFixed(1)}KB, ${img.width}x${img.height})`,
                    );
                };
                img.onerror = () => {
                    console.error('Failed to load image preview');
                    setFileInfo(
                        `${file.name} (${(file.size / 1024).toFixed(1)}KB)`,
                    );
                };
                img.src = result;
            };
            reader.onerror = () => {
                console.error('Failed to read file');
                alert('Failed to read the file.');
            };
            reader.readAsDataURL(file);

            setData('image', file);
            setShowResults(false);
        }
    };

    const triggerFileInput = (): void => {
        fileInputRef.current?.click();
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
            preserveScroll: true,
            preserveState: (page) =>
                Object.keys(page.props.errors || {}).length > 0,
            onStart: () => {
                setShowResults(false);
            },
            onError: () => {
                setShowLoading(false);
            },
        });
    };

    const handleReset = () => {
        reset();
        setPreview(null);
        setShowResults(false);
        setFileInfo('');
        setShowLoading(false);
    };

    return (
        <>
            <Header />

            {/* Compact Loading Dialog */}
            <AnalysisLoadingDialog isOpen={showLoading} />

            <div className="mt-[-20px] min-h-screen text-[#1b1b18] dark:bg-[#0a0a0a]">
                <div className="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-10">
                    {/* Page Header */}
                    <div className="mb-6 ml-2">
                        <h1 className="text-md font-bold sm:text-xl lg:text-lg dark:text-white">
                            Scan Your Pet
                        </h1>
                        <p className="mt-[-5px] text-xs text-gray-600 sm:text-sm dark:text-white/70">
                            Upload a photo or use your camera to identify your
                            pet's breed.
                        </p>
                    </div>

                    {/* Error Message */}
                    {error && (
                        <Card className="mb-6 border-red-400 bg-red-50 dark:border-red-800 dark:bg-red-950">
                            <CardContent className="p-4 sm:p-6">
                                <div className="flex items-start gap-2 sm:items-center">
                                    <XCircle
                                        size={20}
                                        className="mt-0.5 shrink-0 text-red-600 sm:mt-0 dark:text-red-400"
                                    />
                                    <div className="flex-1">
                                        <p className="font-bold text-red-700 dark:text-red-400">
                                            Error
                                        </p>
                                        <p className="mt-1 text-xs text-red-600 sm:text-sm dark:text-red-300">
                                            {error.message}
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Success Message with Results */}
                    {success && showResults && (
                        <Card className="mb-6 border-green-400 bg-green-50 dark:border-green-800 dark:bg-green-950">
                            <CardContent className="p-4 sm:p-6">
                                <div className="flex items-start gap-2 sm:items-center">
                                    <CheckCircle2
                                        size={24}
                                        className="mt-0.5 shrink-0 text-green-600 sm:mt-0 dark:text-green-400"
                                    />
                                    <h2 className="text-lg font-bold text-green-700 sm:text-xl dark:text-green-400">
                                        Analysis Complete!
                                    </h2>
                                </div>

                                <div className="mt-4">
                                    <p className="text-base font-semibold text-gray-800 sm:text-lg dark:text-white">
                                        Detected Breed:{' '}
                                        <span className="text-green-600 dark:text-green-400">
                                            {success.breed}
                                        </span>
                                    </p>
                                    <p className="mt-1 text-xs text-gray-600 sm:text-sm dark:text-gray-300">
                                        Confidence:{' '}
                                        {(success.confidence * 100).toFixed(2)}%
                                    </p>
                                </div>

                                {success.top_predictions &&
                                    success.top_predictions.length > 0 && (
                                        <div className="mt-4">
                                            <h3 className="text-sm font-semibold text-gray-700 sm:text-base dark:text-gray-300">
                                                Top Predictions:
                                            </h3>
                                            <ul className="mt-2 space-y-2">
                                                {success.top_predictions.map(
                                                    (pred, idx) => (
                                                        <li
                                                            key={idx}
                                                            className="flex justify-between gap-4 text-xs sm:text-sm"
                                                        >
                                                            <span className="text-gray-700 dark:text-gray-300">
                                                                {idx + 1}.{' '}
                                                                {pred.breed}
                                                            </span>
                                                            <span className="shrink-0 text-gray-600 dark:text-gray-400">
                                                                {(
                                                                    pred.confidence *
                                                                    100
                                                                ).toFixed(2)}
                                                                %
                                                            </span>
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        </div>
                                    )}

                                <Button
                                    onClick={handleReset}
                                    className="mt-4 w-full"
                                    variant="outline"
                                >
                                    Scan Another Image
                                </Button>
                            </CardContent>
                        </Card>
                    )}

                    {/* Upload Card */}
                    <Card className="mx-auto w-full max-w-4xl">
                        <form onSubmit={handleSubmit}>
                            <CardContent className="p-4 sm:p-6">
                                {!preview ? (
                                    <>
                                        <div
                                            onClick={triggerFileInput}
                                            className="flex h-[180px] cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-gray-300 transition-colors hover:border-gray-400 sm:h-[200px] lg:h-[240px] dark:border-gray-700 dark:bg-gray-900 dark:hover:border-gray-600"
                                        >
                                            <input
                                                ref={fileInputRef}
                                                type="file"
                                                accept="image/*"
                                                className="hidden"
                                                onChange={handleFileChange}
                                            />

                                            <div className="rounded-full bg-gray-200 dark:bg-gray-800">
                                                <Camera
                                                    size={32}
                                                    className="p-2 text-black sm:size-9 dark:text-white"
                                                />
                                            </div>

                                            <p className="mt-2 text-xs text-gray-600 sm:text-sm dark:text-white/70">
                                                Drop your image here
                                            </p>
                                            <p className="text-xs text-gray-600 sm:text-sm dark:text-white/70">
                                                or click to browse
                                            </p>
                                            <p className="mt-1 text-xs text-gray-600 sm:text-sm dark:text-white/70">
                                                All image formats supported (Max
                                                10 MB)
                                            </p>
                                        </div>

                                        {errors.image && (
                                            <p className="mt-2 text-xs text-red-600 sm:text-sm">
                                                {errors.image}
                                            </p>
                                        )}

                                        <Card className="mt-4 border-blue-400 bg-blue-50 sm:mt-6 dark:border-blue-800 dark:bg-gray-950">
                                            <CardContent className="p-3 sm:p-4">
                                                <div className="flex items-start gap-2 sm:items-center">
                                                    <CircleAlert
                                                        size={18}
                                                        className="mt-0.5 shrink-0 text-blue-600 sm:mt-0 sm:size-5 dark:text-blue-400"
                                                    />
                                                    <p className="text-xs font-bold text-gray-700 sm:text-sm dark:text-white/70">
                                                        Tips for Best Results
                                                    </p>
                                                </div>

                                                <ul className="mt-2 list-disc space-y-1 pl-6 sm:pl-8">
                                                    <li className="text-xs text-gray-600 sm:text-sm dark:text-white/70">
                                                        Ensure your pet is
                                                        clearly visible
                                                    </li>
                                                    <li className="text-xs text-gray-600 sm:text-sm dark:text-white/70">
                                                        Use good lighting
                                                    </li>
                                                    <li className="text-xs text-gray-600 sm:text-sm dark:text-white/70">
                                                        Center your pet in the
                                                        frame
                                                    </li>
                                                    <li className="text-xs text-gray-600 sm:text-sm dark:text-white/70">
                                                        Better angles improve
                                                        accuracy
                                                    </li>
                                                </ul>
                                            </CardContent>
                                        </Card>
                                    </>
                                ) : (
                                    <div>
                                        <div className="overflow-hidden rounded-lg">
                                            <img
                                                src={preview}
                                                className="mx-auto max-h-64 w-full object-contain sm:max-h-80 lg:max-h-96"
                                                alt="Preview"
                                            />
                                        </div>
                                        {fileInfo && (
                                            <p className="mt-2 text-center text-xs text-gray-500 dark:text-gray-400">
                                                {fileInfo}
                                            </p>
                                        )}
                                        <div className="mt-4 flex flex-col gap-2 sm:flex-row">
                                            <Button
                                                type="submit"
                                                className="w-full sm:flex-1"
                                                disabled={processing}
                                            >
                                                {processing
                                                    ? 'Analyzing...'
                                                    : 'Analyze Image'}
                                            </Button>
                                            <Button
                                                type="button"
                                                className="w-full sm:w-32"
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
