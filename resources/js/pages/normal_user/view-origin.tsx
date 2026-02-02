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
        <div className="min-h-screen w-full bg-background pb-10">
            <Header />
            <main className="container mx-auto mt-[-10px] flex max-w-5xl flex-col gap-6 px-4 py-6 md:px-8">
                {/* --- Header Section --- */}
                <div className="flex items-start space-x-4 md:items-center md:space-x-6">
                    <Link href={`/scan-results`} className="mt-1 md:mt-0">
                        <ArrowLeft className="h-5 w-5 text-black dark:text-white" />
                    </Link>
                    <div>
                        <h1 className="text-lg font-bold dark:text-white">
                            {results?.breed || 'Breed'} Origin & History
                        </h1>
                        <p className="text-sm text-gray-600 dark:text-white/70">
                            Breed heritage and evolution
                        </p>
                    </div>
                </div>

                {/* --- Geographic Origin Card --- */}
                <Card className="mt-2 flex flex-col bg-cyan-50 p-6 outline outline-cyan-200 md:p-10">
                    <h2 className="mb-6 text-lg font-semibold md:mb-8">
                        Geographic Origin
                    </h2>

                    <div className="flex flex-col gap-8 md:flex-row">
                        {/* Text Info */}
                        <div className="w-full md:w-1/2">
                            <div className="flex gap-4 md:gap-3">
                                <div className="mt-1 shrink-0">
                                    <MapPin className="h-6 w-6 text-[#002680]" />
                                </div>
                                <div className="space-y-6 md:space-y-12">
                                    <div>
                                        <h3 className="font-bold text-gray-900">
                                            {country}
                                        </h3>
                                        <p className="text-sm text-gray-700">
                                            {region}
                                        </p>
                                    </div>
                                    <p className="text-sm leading-relaxed text-gray-700 md:text-base">
                                        {description}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Right Side: Flag Display */}
                        <div className="flex w-full flex-col items-center justify-center gap-4 rounded-2xl bg-white/50 p-6 shadow-sm md:w-1/2 md:p-8">
                            <img
                                src={flagUrl}
                                alt={`${country} Flag`}
                                className="h-auto w-24 rounded-md object-cover shadow-md md:w-32"
                            />
                            <div className="flex flex-col items-center justify-center text-center">
                                <p className="font-bold text-gray-900">
                                    {country}
                                </p>
                                <p className="text-sm text-gray-600">
                                    {region}
                                </p>
                            </div>
                        </div>
                    </div>
                </Card>

                {/* --- History Timeline Card --- */}
                <Card className="mt-2 p-6 md:p-10">
                    <h2 className="mb-6 text-lg font-semibold">
                        History Timeline
                    </h2>

                    {timeline.length > 0 ? (
                        <div className="relative ml-3 space-y-8 border-l-2 border-gray-200">
                            {timeline.map((item, index) => (
                                <div key={index} className="relative pl-6">
                                    {/* Dot on timeline */}
                                    <div className="absolute top-1 -left-[9px] h-4 w-4 rounded-full border-2 border-white bg-cyan-500"></div>

                                    <div className="flex flex-col sm:flex-row sm:items-baseline sm:gap-4">
                                        <span className="min-w-[80px] font-bold text-cyan-700">
                                            {item.year}
                                        </span>
                                        <p className="mt-1 text-sm text-gray-600 sm:mt-0">
                                            {item.event}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="text-sm text-gray-500">
                            Timeline data unavailable.
                        </div>
                    )}
                </Card>

                {/* --- Detailed History Accordion --- */}
                <Card className="mt-2 p-6 md:p-10">
                    <h2 className="mb-6 text-lg font-semibold">
                        Detailed History
                    </h2>

                    {details.length > 0 ? (
                        <Accordion type="single" collapsible className="w-full">
                            {details.map((detail, index) => (
                                <AccordionItem
                                    key={index}
                                    value={`item-${index}`}
                                >
                                    <AccordionTrigger className="md:text-md text-left text-base font-bold">
                                        {detail.title}
                                    </AccordionTrigger>
                                    <AccordionContent className="flex flex-col gap-2 text-sm leading-relaxed text-gray-600 md:text-base">
                                        {detail.content}
                                    </AccordionContent>
                                </AccordionItem>
                            ))}
                        </Accordion>
                    ) : (
                        <div className="text-sm text-gray-500">
                            Detailed history unavailable.
                        </div>
                    )}
                </Card>
            </main>
        </div>
    );
};

export default ViewOrigin;
