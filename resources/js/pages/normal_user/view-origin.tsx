import Header from '@/components/header';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/accordion';
import { Card } from '@/components/ui/card';
import { Link } from '@inertiajs/react';
import { ArrowLeft, MapPin } from 'lucide-react';
import { FC } from 'react';

// --- Interfaces ---

interface TimelineItem {
    year: string;
    event: string;
}

interface HistoryDetail {
    title: string;
    content: string;
}

interface OriginData {
    country: string;
    country_code: string; // e.g., "gb"
    region: string;
    description: string;
    timeline: TimelineItem[];
    details: HistoryDetail[];
}

interface ScanResult {
    scan_id: string;
    breed: string;
    // This comes from DB as a JSON string or Object
    origin_history: string | OriginData;
}

interface ViewOriginProps {
    results: ScanResult;
}

// --- Component ---

const ViewOrigin: FC<ViewOriginProps> = ({ results }) => {
    // 1. Safe Parse of Origin Data
    let originData: OriginData = {
        country: 'Unknown',
        country_code: '',
        region: 'Unknown Region',
        description: 'Origin details unavailable.',
        timeline: [],
        details: [],
    };

    try {
        if (typeof results?.origin_history === 'string') {
            originData = JSON.parse(results.origin_history);
        } else if (
            typeof results?.origin_history === 'object' &&
            results?.origin_history !== null
        ) {
            originData = results.origin_history as OriginData;
        }
    } catch (e) {
        console.error('Failed to parse origin history', e);
    }

    const { country, country_code, region, description, timeline, details } =
        originData;

    // Dynamic Flag URL (using flagcdn.com)
    // Fallback to a generic placeholder if no code
    const flagUrl = country_code
        ? `https://flagcdn.com/w160/${country_code.toLowerCase()}.png`
        : 'https://via.placeholder.com/150?text=No+Flag';

    return (
        <div className="min-h-screen w-full bg-gray-50 pb-10 dark:bg-gray-950">
            <Header />
            <main className="mx-auto mt-[-25px] w-full max-w-7xl px-8 pt-4 pb-8 sm:px-8 md:px-8">
                {/* --- Header Section --- */}
                <div className="mb-6 flex items-start gap-4 sm:items-center sm:gap-6">
                    <Link href={`/scan-results`} className="mt-1 sm:mt-0">
                        <ArrowLeft className="h-5 w-5 text-gray-900 dark:text-white" />
                    </Link>
                    <div>
                        <h1 className="text-xl font-bold text-gray-900 sm:text-lg dark:text-white">
                            {results?.breed || 'Breed'} Origin & History
                        </h1>
                        <p className="mt-[-2] text-sm text-gray-600 dark:text-gray-400">
                            Breed heritage and evolution
                        </p>
                    </div>
                </div>

                {/* --- Geographic Origin Card --- */}
                <Card className="mt-6 flex flex-col border-cyan-200 bg-cyan-50 p-8 sm:p-10 md:p-12 dark:border-cyan-800 dark:bg-cyan-950/40">
                    <h2 className="mb-8 text-lg font-bold text-gray-900 sm:text-lg dark:text-white">
                        Geographic Origin
                    </h2>

                    <div className="flex flex-col gap-8 md:flex-row md:gap-12">
                        {/* Text Info */}
                        <div className="w-full md:w-1/2">
                            <div className="flex gap-4">
                                <div className="mt-1 shrink-0">
                                    <MapPin className="h-7 w-7 text-cyan-700 dark:text-cyan-400" />
                                </div>
                                <div className="space-y-6">
                                    <div>
                                        <h3 className="text-xl font-bold text-gray-900 dark:text-white">
                                            {country}
                                        </h3>
                                        <p className="mt-1 text-sm text-gray-700 dark:text-gray-300">
                                            {region}
                                        </p>
                                    </div>
                                    <p className="text-sm leading-relaxed text-gray-700 dark:text-gray-300">
                                        {description}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Right Side: Flag Display */}
                        <div className="flex w-full flex-col items-center justify-center gap-4 rounded-2xl border border-cyan-200 bg-white/80 p-8 shadow-sm md:w-1/2 dark:border-cyan-800 dark:bg-gray-800/50">
                            <img
                                src={flagUrl}
                                alt={`${country} Flag`}
                                className="h-auto w-32 rounded-lg object-cover shadow-md ring-1 ring-black/5 md:w-40 dark:ring-white/10"
                            />
                            <div className="flex flex-col items-center justify-center text-center">
                                <p className="text-lg font-bold text-gray-900 dark:text-white">
                                    {country}
                                </p>
                                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    {region}
                                </p>
                            </div>
                        </div>
                    </div>
                </Card>

                {/* --- History Timeline Card --- */}
                <Card className="mt-6 border-gray-200 bg-white p-8 sm:p-10 md:p-12 dark:border-gray-800 dark:bg-gray-900">
                    <h2 className="mb-8 text-lg font-bold text-gray-900 sm:text-lg dark:text-white">
                        History Timeline
                    </h2>

                    {timeline.length > 0 ? (
                        <div className="relative ml-3 space-y-8 border-l-2 border-gray-300 dark:border-gray-700">
                            {timeline.map((item, index) => (
                                <div key={index} className="relative pl-8">
                                    {/* Dot on timeline */}
                                    <div className="absolute top-1 -left-[9px] h-4 w-4 rounded-full border-2 border-white bg-cyan-600 shadow-sm dark:border-gray-900 dark:bg-cyan-500"></div>

                                    <div className="flex flex-col sm:flex-row sm:items-baseline sm:gap-6">
                                        <span className="min-w-[100px] text-base font-bold text-cyan-700 dark:text-cyan-400">
                                            {item.year}
                                        </span>
                                        <p className="mt-2 text-sm leading-relaxed text-gray-700 sm:mt-0 dark:text-gray-300">
                                            {item.event}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="text-sm text-gray-600 dark:text-gray-400">
                            Timeline data unavailable.
                        </div>
                    )}
                </Card>

                {/* --- Detailed History Accordion --- */}
                <Card className="mt-6 border-gray-200 bg-white p-8 sm:p-10 md:p-12 dark:border-gray-800 dark:bg-gray-900">
                    <h2 className="mb-8 text-lg font-bold text-gray-900 sm:text-lg dark:text-white">
                        Detailed History
                    </h2>

                    {details.length > 0 ? (
                        <Accordion type="single" collapsible className="w-full">
                            {details.map((detail, index) => (
                                <AccordionItem
                                    key={index}
                                    value={`item-${index}`}
                                    className="border-gray-200 dark:border-gray-800"
                                >
                                    <AccordionTrigger className="sm:text-md text-left text-base font-bold text-gray-900 hover:text-gray-700 dark:text-white dark:hover:text-gray-300">
                                        {detail.title}
                                    </AccordionTrigger>
                                    <AccordionContent className="flex flex-col gap-2 pt-2 text-sm leading-relaxed text-gray-700 dark:text-gray-300">
                                        {detail.content}
                                    </AccordionContent>
                                </AccordionItem>
                            ))}
                        </Accordion>
                    ) : (
                        <div className="text-sm text-gray-600 dark:text-gray-400">
                            Detailed history unavailable.
                        </div>
                    )}
                </Card>
            </main>
        </div>
    );
};

export default ViewOrigin;
