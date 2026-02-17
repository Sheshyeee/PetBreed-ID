import Header from '@/components/header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Link, router } from '@inertiajs/react';
import {
    Calendar,
    Camera,
    Download,
    QrCode,
    Smartphone,
    Trash2,
    X,
} from 'lucide-react';
import { useState } from 'react';

// Define the Scan interface
interface Scan {
    id: number;
    scan_id: string;
    image: string;
    breed: string;
    confidence: number;
    date: string;
    status: 'pending' | 'verified';
}

// Define the User interface
interface User {
    name: string;
    email: string;
    avatar?: string;
}

// Define the component props
interface ScanHistoryProps {
    mockScans: Scan[];
    user: User;
}

const ScanHistory: React.FC<ScanHistoryProps> = ({ mockScans, user }) => {
    const handleDelete = (scanId: number) => {
        router.delete(`/scanhistory/${scanId}`, {
            preserveScroll: true,
            onSuccess: () => {
                // Optional: Show success message
            },
            onError: () => {
                alert('Failed to delete scan. Please try again.');
            },
        });
    };

    const [open, setOpen] = useState(false);
    const [showQRModal, setShowQRModal] = useState(false);

    return (
        <div className="min-h-screen w-full bg-background">
            <Header />

            {showQRModal && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
                    onClick={() => setShowQRModal(false)}
                >
                    <div
                        className="relative w-full max-w-md rounded-2xl bg-white p-8 shadow-2xl dark:bg-gray-900"
                        onClick={(e) => e.stopPropagation()}
                    >
                        {/* Close Button */}
                        <button
                            onClick={() => setShowQRModal(false)}
                            className="absolute top-4 right-4 rounded-full p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                        >
                            <X size={20} />
                        </button>

                        {/* Header */}
                        <div className="mb-6 text-center">
                            <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br from-[#5C469C] to-[#0C134F]">
                                <Smartphone size={32} className="text-white" />
                            </div>
                            <h2 className="text-2xl font-bold text-gray-900 dark:text-white">
                                Install Mobile App
                            </h2>
                            <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                Scan the QR code with your mobile device to
                                download and install the app
                            </p>
                        </div>

                        {/* QR Code */}
                        <div className="mb-6 flex justify-center">
                            <div className="rounded-2xl bg-white p-4 shadow-lg ring-1 ring-gray-200 dark:ring-gray-700">
                                <img
                                    src="/doglens_apk_qr.jpeg"
                                    alt="QR Code for App Installation"
                                    className="h-48 w-48"
                                />
                            </div>
                        </div>

                        {/* Instructions */}
                        <div className="space-y-3 rounded-xl bg-gradient-to-br from-[#0C134F]/5 to-[#5C469C]/5 p-4 dark:from-[#0C134F]/20 dark:to-[#5C469C]/20">
                            <div className="flex items-start gap-3">
                                <div className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#5C469C] text-sm font-semibold text-white">
                                    1
                                </div>
                                <p className="text-sm text-gray-700 dark:text-gray-300">
                                    Scan the QR code above
                                </p>
                            </div>
                            <div className="flex items-start gap-3">
                                <div className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#5C469C] text-sm font-semibold text-white">
                                    2
                                </div>
                                <p className="text-sm text-gray-700 dark:text-gray-300">
                                    Follow the link to download and install the
                                    app
                                </p>
                            </div>
                        </div>

                        {/* Features */}
                        <div className="mt-6 space-y-2">
                            <div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                <Download
                                    size={16}
                                    className="text-[#5C469C]"
                                />
                                <span>Fast & Easy Installation</span>
                            </div>
                            <div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                <Smartphone
                                    size={16}
                                    className="text-[#5C469C]"
                                />
                                <span>Available on Android</span>
                            </div>
                            <div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                <Camera size={16} className="text-[#5C469C]" />
                                <span>Access All Features On-The-Go</span>
                            </div>
                        </div>

                        {/* Action Button */}
                        <Button
                            onClick={() => setShowQRModal(false)}
                            className="mt-6 w-full bg-gradient-to-r from-[#5C469C] to-[#0C134F] hover:from-[#4a3880] hover:to-[#0a0f3d]"
                        >
                            Close
                        </Button>
                    </div>
                </div>
            )}

            {/* Floating QR Button */}
            <button
                onClick={() => setShowQRModal(true)}
                className="fixed right-6 bottom-6 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-[#FF7070] to-[#EB4C4C] text-white shadow-lg transition-all hover:scale-110 hover:shadow-xl"
                title="Install Mobile App"
            >
                <QrCode size={24} />
            </button>

            <div className="container mx-auto mt-[-30px] max-w-7xl px-8 py-8 sm:mt-[-20px] lg:px-10">
                {/* Header Section */}
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-lg font-bold sm:text-2xl lg:text-lg dark:text-white">
                            My Scan History
                        </h1>
                        <p className="mt-[-5px] text-sm text-gray-600 sm:text-sm dark:text-white/70">
                            View your pet breed identification scans
                        </p>
                    </div>
                    <Button
                        asChild
                        variant="outline"
                        className="shrink-0 border-gray-300 bg-white hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:bg-gray-800"
                    >
                        <Link href="/scan">New Scan</Link>
                    </Button>
                </div>

                {/* Veterinarian Verification Info */}
                <Card className="mb-8 border-blue-200 bg-blue-50 p-6 dark:border-blue-900 dark:bg-blue-950">
                    <div className="flex gap-4">
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-500 text-white">
                            <svg
                                className="h-6 w-6"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                        </div>
                        <div>
                            <h3 className="text-md mb-2 font-semibold text-blue-900 dark:text-blue-100">
                                Veterinarian Verification
                            </h3>
                            <p className="text-sm text-blue-800 dark:text-blue-200">
                                All system breed identifications can be reviewed
                                by licensed veterinarians for accuracy. Verified
                                scans have been confirmed by professional vets,
                                while pending scans are awaiting review. This
                                ensures you get the most reliable breed
                                information for your pet.
                            </p>
                        </div>
                    </div>
                </Card>

                {/* Empty State */}
                {mockScans.length === 0 && (
                    <Card className="p-12 text-center">
                        <div className="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                            <Calendar className="h-10 w-10 text-gray-400" />
                        </div>
                        <h3 className="mb-2 text-xl font-semibold">
                            No scans yet
                        </h3>
                        <p className="mb-6 text-gray-600 dark:text-gray-400">
                            Start by scanning your first pet!
                        </p>
                        <Button
                            asChild
                            variant="outline"
                            className="shrink-0 border-gray-300 bg-white hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:hover:bg-gray-800"
                        >
                            <Link href="/scan">Scan Your Pet</Link>
                        </Button>
                    </Card>
                )}

                {/* Scan Results Grid */}
                {mockScans.length > 0 && (
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                        {mockScans.map((scan) => (
                            <Card
                                key={scan.id}
                                className="overflow-hidden rounded-lg p-0"
                            >
                                {/* Image */}
                                <div className="relative h-78 w-full overflow-hidden">
                                    <img
                                        src={scan.image}
                                        alt={scan.breed}
                                        className="h-full w-full object-cover"
                                    />
                                    {/* Delete Button Overlay */}
                                    <Button
                                        onClick={() => handleDelete(scan.id)}
                                        variant="destructive"
                                        size="icon"
                                        className="absolute top-2 right-2 h-8 w-8 opacity-0 transition-opacity group-hover:opacity-100 hover:opacity-100"
                                        title="Delete scan"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                </div>

                                {/* Content */}
                                <div className="p-4">
                                    <div className="mb-3 flex items-start justify-between">
                                        <h3 className="text-xl font-semibold">
                                            {scan.breed}
                                        </h3>
                                        <Badge
                                            variant="secondary"
                                            className="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100"
                                        >
                                            {scan.confidence}%
                                        </Badge>
                                    </div>

                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                            <Calendar className="h-4 w-4" />
                                            <span>{scan.date}</span>
                                        </div>

                                        {scan.status === 'verified' ? (
                                            <Badge className="bg-blue-500 text-white hover:bg-blue-600">
                                                <svg
                                                    className="mr-1 h-3 w-3"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path
                                                        strokeLinecap="round"
                                                        strokeLinejoin="round"
                                                        strokeWidth={2}
                                                        d="M5 13l4 4L19 7"
                                                    />
                                                </svg>
                                                Verified
                                            </Badge>
                                        ) : (
                                            <Badge
                                                variant="outline"
                                                className="border-orange-500 text-orange-600 dark:text-orange-400"
                                            >
                                                <svg
                                                    className="mr-1 h-3 w-3"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path
                                                        strokeLinecap="round"
                                                        strokeLinejoin="round"
                                                        strokeWidth={2}
                                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                                                    />
                                                </svg>
                                                Pending
                                            </Badge>
                                        )}
                                    </div>

                                    {/* Delete Button in Card Footer */}
                                    <div className="mt-4 border-t pt-3">
                                        <Button
                                            onClick={() =>
                                                handleDelete(scan.id)
                                            }
                                            variant="ghost"
                                            size="sm"
                                            className="w-full text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-950 dark:hover:text-red-300"
                                        >
                                            <Trash2 className="mr-2 h-4 w-4" />
                                            Delete Scan
                                        </Button>
                                    </div>
                                </div>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
};

export default ScanHistory;
