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

    useEffect(() => {
        if (success) {
            setShowResults(true);
        }
    }, [success, error]);

    const validateImageFile = (file: File): string | null => {
        // Check file size (10MB limit)
        if (file.size > 10 * 1024 * 1024) {
            return 'File is too large. Maximum size is 10MB.';
        }

        // UPDATED: Accept more image types including AVIF
        const validTypes = [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/webp',
            'image/gif',
            'image/avif', // ADDED: AVIF support
            'image/bmp',
            'image/x-ms-bmp',
            'image/svg+xml',
        ];

        if (!validTypes.includes(file.type)) {
            console.warn('File type not in valid list:', file.type);
            // Don't block upload - let server validate
            // Some browsers report wrong MIME types
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

            // Validate file
            const validationError = validateImageFile(file);
            if (validationError) {
                alert(validationError);
                e.target.value = '';
                return;
            }

            // Create preview
            const reader = new FileReader();
            reader.onload = (e) => {
                const result = e.target?.result as string;
                setPreview(result);

                // Verify it's actually an image by loading it
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
                    // Still allow upload - server will validate properly
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

        post('/analyze', {
            forceFormData: true,
            preserveScroll: true,
            preserveState: (page) =>
                Object.keys(page.props.errors || {}).length > 0,
            onStart: () => {
                setShowResults(false);
            },
        });
    };

    const handleReset = () => {
        reset();
        setPreview(null);
        setShowResults(false);
        setFileInfo('');
    };

    return (
        <>
            <Header />

            <div className="flex flex-col items-center bg-[#FDFDFC] text-[#1b1b18] dark:bg-[#0a0a0a]">
                <div className="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-10">
                    <div>
                        <h1 className="mt-6 text-lg font-bold dark:text-white">
                            Scan Your Pet
                        </h1>
                        <p className="text-sm text-gray-600 dark:text-white/70">
                            Upload a photo or use your camera to identify your
                            pet's breed.
                        </p>
                    </div>

                    {/* Error Message */}
                    {error && (
                        <Card className="mx-auto mt-6 w-full max-w-4xl border-red-400 bg-red-50 dark:bg-red-950">
                            <CardContent className="p-4">
                                <div className="flex items-center gap-2">
                                    <XCircle
                                        size={20}
                                        className="text-red-600 dark:text-red-400"
                                    />
                                    <p className="font-bold text-red-700 dark:text-red-400">
                                        Error
                                    </p>
                                </div>
                                <p className="mt-2 text-sm text-red-600 dark:text-red-300">
                                    {error.message}
                                </p>
                            </CardContent>
                        </Card>
                    )}

                    {/* Success Message with Results */}
                    {success && showResults && (
                        <Card className="mx-auto mt-6 w-full max-w-4xl border-green-400 bg-green-50 dark:bg-green-950">
                            <CardContent className="p-6">
                                <div className="flex items-center gap-2">
                                    <CheckCircle2
                                        size={24}
                                        className="text-green-600 dark:text-green-400"
                                    />
                                    <h2 className="text-xl font-bold text-green-700 dark:text-green-400">
                                        Analysis Complete!
                                    </h2>
                                </div>

                                <div className="mt-4">
                                    <p className="text-lg font-semibold text-gray-800 dark:text-white">
                                        Detected Breed:{' '}
                                        <span className="text-green-600 dark:text-green-400">
                                            {success.breed}
                                        </span>
                                    </p>
                                    <p className="text-sm text-gray-600 dark:text-gray-300">
                                        Confidence:{' '}
                                        {(success.confidence * 100).toFixed(2)}%
                                    </p>
                                </div>

                                {success.top_predictions &&
                                    success.top_predictions.length > 0 && (
                                        <div className="mt-4">
                                            <h3 className="font-semibold text-gray-700 dark:text-gray-300">
                                                Top Predictions:
                                            </h3>
                                            <ul className="mt-2 space-y-2">
                                                {success.top_predictions.map(
                                                    (pred, idx) => (
                                                        <li
                                                            key={idx}
                                                            className="flex justify-between text-sm"
                                                        >
                                                            <span className="text-gray-700 dark:text-gray-300">
                                                                {idx + 1}.{' '}
                                                                {pred.breed}
                                                            </span>
                                                            <span className="text-gray-600 dark:text-gray-400">
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

                    <Card className="mx-auto mt-8 w-full max-w-4xl">
                        <form onSubmit={handleSubmit}>
                            <CardContent className="p-6">
                                {!preview ? (
                                    <>
                                        <div
                                            onClick={triggerFileInput}
                                            className="flex h-[200px] cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-gray-300 transition-colors hover:border-gray-400 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-gray-600"
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
                                                    size={36}
                                                    className="p-2 text-black dark:text-white"
                                                />
                                            </div>

                                            <p className="mt-2 text-sm text-gray-600 dark:text-white/70">
                                                Drop your image here
                                            </p>
                                            <p className="text-sm text-gray-600 dark:text-white/70">
                                                or click to browse
                                            </p>
                                            <p className="text-sm text-gray-600 dark:text-white/70">
                                                All image formats supported (Max
                                                10 MB)
                                            </p>
                                        </div>

                                        {errors.image && (
                                            <p className="mt-2 text-sm text-red-600">
                                                {errors.image}
                                            </p>
                                        )}

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
                                                        Ensure your pet is
                                                        clearly visible
                                                    </li>
                                                    <li className="text-sm text-gray-600 dark:text-white/70">
                                                        Use good lighting
                                                    </li>
                                                    <li className="text-sm text-gray-600 dark:text-white/70">
                                                        Center your pet in the
                                                        frame
                                                    </li>
                                                    <li className="text-sm text-gray-600 dark:text-white/70">
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
                                                className="mx-auto max-h-96 w-full object-contain"
                                                alt="Preview"
                                            />
                                        </div>
                                        {fileInfo && (
                                            <p className="mt-2 text-center text-xs text-gray-500">
                                                {fileInfo}
                                            </p>
                                        )}
                                        <div className="mt-4 flex gap-2">
                                            <Button
                                                type="submit"
                                                className="flex-1"
                                                disabled={processing}
                                            >
                                                {processing
                                                    ? 'Analyzing...'
                                                    : 'Analyze Image'}
                                            </Button>
                                            <Button
                                                type="button"
                                                className="w-32"
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
