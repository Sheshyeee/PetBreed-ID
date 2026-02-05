import Header from '@/components/header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Link } from '@inertiajs/react';
import { Calendar } from 'lucide-react';

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
    return (
        <div className="min-h-screen w-full bg-background">
            <Header />

            <div className="container mx-auto max-w-7xl px-4 py-8 lg:px-10">
                {/* Header Section */}
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-bold sm:text-2xl lg:text-3xl dark:text-white">
                            My Scan History
                        </h1>
                        <p className="mt-1 text-sm text-gray-600 sm:text-base dark:text-white/70">
                            View your pet breed identification scans
                        </p>
                    </div>
                    <Button asChild>
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
                            <h3 className="mb-2 text-lg font-semibold text-blue-900 dark:text-blue-100">
                                Veterinarian Verification
                            </h3>
                            <p className="text-sm text-blue-800 dark:text-blue-200">
                                All AI breed identifications can be reviewed by
                                licensed veterinarians for accuracy. Verified
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
                        <Button asChild>
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
                                <div className="relative h-48 w-full overflow-hidden">
                                    <img
                                        src={scan.image}
                                        alt={scan.breed}
                                        className="h-full w-full object-cover"
                                    />
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
