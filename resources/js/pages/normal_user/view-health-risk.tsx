import Header from '@/components/header';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { Link } from '@inertiajs/react';
import { ArrowLeft, TriangleAlert } from 'lucide-react';
import { FC, useMemo } from 'react';
import {
    PolarAngleAxis,
    PolarGrid,
    PolarRadiusAxis,
    Radar,
    RadarChart,
    ResponsiveContainer,
} from 'recharts';

// --- Types & Interfaces ---

interface HealthConcern {
    name: string;
    risk_level: string;
    description: string;
    prevention: string;
}

interface Screening {
    name: string;
    description: string;
}

interface HealthRisksData {
    concerns: HealthConcern[];
    screenings: Screening[];
    lifespan: string;
    care_tips: string[];
}

interface ScanResult {
    scan_id: string;
    breed: string;
    // Data might come as a JSON string from DB or an Object if cast in Model
    health_risks: string | HealthRisksData;
    created_at: string;
}

interface ViewHealthRiskProps {
    results: ScanResult;
}

// --- Component ---

const ViewHealthRisk: FC<ViewHealthRiskProps> = ({ results }) => {
    // 1. Safe Parsing: Ensure we have a valid object even if DB stored a string
    let healthData: HealthRisksData = {
        concerns: [],
        screenings: [],
        lifespan: 'Unknown',
        care_tips: [],
    };

    try {
        if (typeof results?.health_risks === 'string') {
            healthData = JSON.parse(results.health_risks);
        } else if (
            typeof results?.health_risks === 'object' &&
            results?.health_risks !== null
        ) {
            healthData = results.health_risks as HealthRisksData;
        }
    } catch (error) {
        console.error('Failed to parse health risks JSON:', error);
    }

    const {
        concerns = [],
        screenings = [],
        lifespan = 'Unknown',
        care_tips = [],
    } = healthData;

    // 2. Logic: Split care tips into two columns for the layout
    const midPoint = Math.ceil(care_tips.length / 2);
    const tipsCol1 = care_tips.slice(0, midPoint);
    const tipsCol2 = care_tips.slice(midPoint);

    // 3. Helper: Risk Color Logic
    const getRiskColor = (risk: string) => {
        const r = risk.toLowerCase();
        if (r.includes('high'))
            return 'bg-red-100 text-red-700 hover:bg-red-200 border-red-200';
        if (r.includes('moderate'))
            return 'bg-amber-100 text-amber-700 hover:bg-amber-200 border-amber-200';
        return 'bg-blue-100 text-blue-700 hover:bg-blue-200 border-blue-200';
    };

    // 4. Radar Chart Data Processing - FULLY DYNAMIC
    const radarData = useMemo(() => {
        // If no concerns, show empty state data
        if (!concerns || concerns.length === 0) {
            return [{ category: 'No Data', value: 0 }];
        }

        // Take up to 8 concerns for optimal radar chart visualization
        const topConcerns = concerns.slice(0, 8);

        // Map each concern directly to radar data
        return topConcerns.map((concern) => {
            const riskLevel = concern.risk_level.toLowerCase();

            // Calculate risk score (0-100) based on risk level
            let score = 25; // Base value for low/unknown
            if (riskLevel.includes('high')) score = 80;
            else if (riskLevel.includes('moderate')) score = 55;
            else if (riskLevel.includes('low')) score = 30;

            return {
                category: concern.name, // Use actual concern name
                value: score,
            };
        });
    }, [concerns]);

    return (
        <div className="min-h-screen w-full bg-background">
            <Header />
            <main className="container mx-auto mt-[-15px] flex max-w-5xl flex-col gap-6 px-4 py-6 md:px-8">
                {/* --- Top Bar --- */}
                <div className="flex items-start space-x-4 md:items-center md:space-x-6">
                    {/* Link back to the main results page, preserving the ID if needed */}
                    <Link href={`/scan-results`} className="mt-1 md:mt-0">
                        <ArrowLeft className="h-5 w-5 text-black dark:text-white" />
                    </Link>
                    <div>
                        <h1 className="text-lg font-bold dark:text-white">
                            Health Risk Visualization
                        </h1>
                        <p className="text-sm text-gray-600 dark:text-white/70">
                            Breed-specific health considerations for{' '}
                            {results?.breed || 'your dog'}
                        </p>
                    </div>
                </div>

                {/* --- Disclaimer --- */}
                <Card className="bg-red-50 p-6 outline outline-red-300">
                    <div className="flex gap-4">
                        <TriangleAlert className="h-6 w-6 shrink-0 text-[#cc0000]" />
                        <div className="flex flex-col gap-2">
                            <span className="text-sm font-bold text-red-700">
                                Medical Disclaimer
                            </span>
                            <span className="text-sm text-red-700">
                                This information is for educational purposes
                                only and is not a medical diagnosis. Always
                                consult with a licensed veterinarian for proper
                                medical advice specific to your pet.
                            </span>
                        </div>
                    </div>
                </Card>

                {/* --- Breed Risk Profile Chart --- */}
                <Card className="p-6">
                    <h2 className="mb-6 text-lg font-semibold">
                        Breed Risk Profile
                    </h2>

                    <div className="w-full">
                        <ResponsiveContainer width="100%" height={400}>
                            <RadarChart data={radarData}>
                                <defs>
                                    <linearGradient
                                        id="radarGradient"
                                        x1="0"
                                        y1="0"
                                        x2="0"
                                        y2="1"
                                    >
                                        <stop
                                            offset="0%"
                                            stopColor="#06b6d4"
                                            stopOpacity={0.8}
                                        />
                                        <stop
                                            offset="100%"
                                            stopColor="#0891b2"
                                            stopOpacity={0.3}
                                        />
                                    </linearGradient>
                                </defs>
                                <PolarGrid
                                    stroke="#cbd5e1"
                                    strokeWidth={1}
                                    strokeDasharray="3 3"
                                />
                                <PolarAngleAxis
                                    dataKey="category"
                                    tick={{
                                        fill: '#475569',
                                        fontSize: 13,
                                        fontWeight: 500,
                                    }}
                                />
                                <PolarRadiusAxis
                                    angle={90}
                                    domain={[0, 100]}
                                    tick={{ fill: '#94a3b8', fontSize: 11 }}
                                    axisLine={false}
                                />
                                <Radar
                                    name="Risk Level"
                                    dataKey="value"
                                    stroke="#0891b2"
                                    strokeWidth={2.5}
                                    fill="url(#radarGradient)"
                                    fillOpacity={0.6}
                                />
                            </RadarChart>
                        </ResponsiveContainer>
                    </div>

                    <p className="mt-4 text-center text-xs text-gray-500 dark:text-gray-400">
                        Risk levels are relative to breed averages • Higher
                        values indicate more common health concerns
                    </p>
                </Card>

                <h2 className="text-lg font-medium">Common Health Concerns</h2>

                {/* --- Dynamic Health Concerns --- */}
                {concerns.length > 0 ? (
                    concerns.map((concern, index) => (
                        <Card key={index} className="flex flex-col gap-4 p-6">
                            <div className="flex items-center justify-between">
                                <h3 className="font-medium">{concern.name}</h3>
                                <Badge
                                    className={getRiskColor(concern.risk_level)}
                                >
                                    {concern.risk_level}
                                </Badge>
                            </div>

                            <div>
                                <h4 className="mb-1 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    Description
                                </h4>
                                <p className="text-sm text-gray-600 dark:text-gray-400">
                                    {concern.description}
                                </p>
                            </div>
                            <div>
                                <h4 className="mb-1 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    Prevention & Management
                                </h4>
                                <p className="text-sm text-gray-600 dark:text-gray-400">
                                    {concern.prevention}
                                </p>
                            </div>
                        </Card>
                    ))
                ) : (
                    <div className="text-sm text-gray-500 italic">
                        No specific health concerns were generated for this
                        scan.
                    </div>
                )}

                {/* --- Recommended Screenings --- */}
                <Card className="flex flex-col gap-4 bg-cyan-50 p-6 outline outline-cyan-200">
                    <h3 className="font-medium text-cyan-900">
                        Recommended Health Screenings
                    </h3>

                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        {screenings.map((screening, index) => (
                            <Card
                                key={index}
                                className="flex flex-col gap-1 p-4 shadow-sm"
                            >
                                <h4 className="text-sm font-semibold">
                                    {screening.name}
                                </h4>
                                <p className="text-xs text-gray-600">
                                    {screening.description}
                                </p>
                            </Card>
                        ))}
                    </div>
                </Card>

                {/* --- Lifespan & Care Tips --- */}
                <Card className="mb-6 p-6">
                    <h3 className="mb-6 font-medium">
                        Typical Lifespan & Care Tips
                    </h3>

                    <div className="flex flex-col items-center gap-8 md:flex-row md:items-start md:justify-evenly">
                        {/* Lifespan Stat */}
                        <div className="flex flex-col items-center justify-center text-center">
                            <p className="text-4xl font-bold text-cyan-700">
                                {lifespan}
                            </p>
                            <p className="font-medium">Years</p>
                            <p className="text-sm text-gray-500">
                                Average lifespan
                            </p>
                        </div>

                        {/* Tips Column 1 */}
                        <div className="flex flex-col items-center justify-center space-y-2 text-center md:items-start md:text-left">
                            <ul className="list-inside list-disc space-y-1 text-sm text-gray-600">
                                {tipsCol1.map((tip, idx) => (
                                    <li key={idx}>{tip}</li>
                                ))}
                            </ul>
                        </div>

                        {/* Tips Column 2 */}
                        <div className="flex flex-col items-center justify-center space-y-2 text-center md:items-start md:text-left">
                            <ul className="list-inside list-disc space-y-1 text-sm text-gray-600">
                                {tipsCol2.map((tip, idx) => (
                                    <li key={idx}>{tip}</li>
                                ))}
                            </ul>
                        </div>
                    </div>
                </Card>
            </main>
        </div>
    );
};

export default ViewHealthRisk;
