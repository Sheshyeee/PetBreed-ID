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
            return 'border-red-200 bg-red-100 text-red-800 hover:bg-red-200 dark:border-red-800 dark:bg-red-950/50 dark:text-red-200';
        if (r.includes('moderate'))
            return 'border-amber-200 bg-amber-100 text-amber-800 hover:bg-amber-200 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-200';
        return 'border-blue-200 bg-blue-100 text-blue-800 hover:bg-blue-200 dark:border-blue-800 dark:bg-blue-950/50 dark:text-blue-200';
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
        <div className="min-h-screen w-full bg-gray-50 dark:bg-gray-950">
            <Header />
            <main className="mx-auto mt-[-35px] w-full max-w-5xl px-8 pt-4 pb-8 sm:px-10 md:px-8">
                {/* --- Top Bar --- */}
                <div className="mb-6 flex items-start gap-4 sm:items-center sm:gap-6">
                    <Link href={`/scan-results`} className="mt-1 sm:mt-0">
                        <ArrowLeft className="h-5 w-5 text-gray-900 dark:text-white" />
                    </Link>
                    <div>
                        <h1 className="text-lg font-bold text-gray-900 sm:text-lg dark:text-white">
                            Health Risk Visualization
                        </h1>
                        <p className="mt-[-3] text-sm text-gray-600 dark:text-gray-400">
                            Breed-specific health considerations for{' '}
                            {results?.breed || 'your dog'}
                        </p>
                    </div>
                </div>

                {/* --- Disclaimer --- */}
                <Card className="mt-6 border-red-200 bg-red-50 p-6 sm:p-8 dark:border-red-800 dark:bg-red-950/30">
                    <div className="flex gap-4">
                        <TriangleAlert className="h-6 w-6 shrink-0 text-red-600 dark:text-red-400" />
                        <div className="flex flex-col gap-2">
                            <span className="text-base font-bold text-red-800 dark:text-red-200">
                                Medical Disclaimer
                            </span>
                            <span className="text-sm leading-relaxed text-red-700 dark:text-red-300">
                                This information is for educational purposes
                                only and is not a medical diagnosis. Always
                                consult with a licensed veterinarian for proper
                                medical advice specific to your pet.
                            </span>
                        </div>
                    </div>
                </Card>

                {/* --- Breed Risk Profile Chart --- */}
                <Card className="mt-6 border-gray-200 bg-white p-8 sm:p-10 dark:border-gray-800 dark:bg-gray-900">
                    <h2 className="mb-6 text-lg font-bold text-gray-900 sm:text-lg dark:text-white">
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

                    <p className="mt-6 text-center text-sm text-gray-600 dark:text-gray-400">
                        Risk levels are relative to breed averages • Higher
                        values indicate more common health concerns
                    </p>
                </Card>

                <h2 className="mt-6 text-lg font-bold text-gray-900 sm:text-lg dark:text-white">
                    Common Health Concerns
                </h2>

                {/* --- Dynamic Health Concerns --- */}
                {concerns.length > 0 ? (
                    <div className="mt-6 flex flex-col gap-6">
                        {concerns.map((concern, index) => (
                            <Card
                                key={index}
                                className="flex flex-col gap-6 border-gray-200 bg-white p-6 sm:p-8 dark:border-gray-800 dark:bg-gray-900"
                            >
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <h3 className="text-lg font-bold text-gray-900 dark:text-white">
                                        {concern.name}
                                    </h3>
                                    <Badge
                                        className={`w-fit ${getRiskColor(concern.risk_level)}`}
                                    >
                                        {concern.risk_level}
                                    </Badge>
                                </div>

                                <div>
                                    <h4 className="mb-2 text-base font-semibold text-gray-900 dark:text-white">
                                        Description
                                    </h4>
                                    <p className="text-sm leading-relaxed text-gray-700 dark:text-gray-300">
                                        {concern.description}
                                    </p>
                                </div>
                                <div>
                                    <h4 className="mb-2 text-base font-semibold text-gray-900 dark:text-white">
                                        Prevention & Management
                                    </h4>
                                    <p className="text-sm leading-relaxed text-gray-700 dark:text-gray-300">
                                        {concern.prevention}
                                    </p>
                                </div>
                            </Card>
                        ))}
                    </div>
                ) : (
                    <div className="text-sm text-gray-600 italic dark:text-gray-400">
                        No specific health concerns were generated for this
                        scan.
                    </div>
                )}

                {/* --- Recommended Screenings --- */}
                <Card className="mt-6 flex flex-col gap-6 border-cyan-200 bg-cyan-50 p-8 sm:p-10 dark:border-cyan-800 dark:bg-cyan-950/40">
                    <h3 className="text-lg font-bold text-cyan-900 dark:text-cyan-100">
                        Recommended Health Screenings
                    </h3>

                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        {screenings.map((screening, index) => (
                            <Card
                                key={index}
                                className="flex flex-col gap-2 border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                            >
                                <h4 className="text-base font-semibold text-gray-900 dark:text-white">
                                    {screening.name}
                                </h4>
                                <p className="text-sm text-gray-700 dark:text-gray-300">
                                    {screening.description}
                                </p>
                            </Card>
                        ))}
                    </div>
                </Card>

                {/* --- Lifespan & Care Tips --- */}
                <Card className="mt-6 mb-8 border-gray-200 bg-white p-8 sm:p-10 dark:border-gray-800 dark:bg-gray-900">
                    <h3 className="mb-8 text-lg font-bold text-gray-900 sm:text-lg dark:text-white">
                        Typical Lifespan & Care Tips
                    </h3>

                    <div className="flex flex-col items-center gap-8 md:flex-row md:items-start md:justify-evenly">
                        {/* Lifespan Stat */}
                        <div className="flex flex-col items-center justify-center text-center">
                            <p className="text-5xl font-bold text-cyan-600 dark:text-cyan-400">
                                {lifespan}
                            </p>
                            <p className="mt-2 text-lg font-semibold text-gray-900 dark:text-white">
                                Years
                            </p>
                            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                Average lifespan
                            </p>
                        </div>

                        {/* Tips Column 1 */}
                        <div className="flex flex-col items-center justify-center space-y-2 text-center md:items-start md:text-left">
                            <ul className="list-inside list-disc space-y-2 text-sm text-gray-700 dark:text-gray-300">
                                {tipsCol1.map((tip, idx) => (
                                    <li key={idx}>{tip}</li>
                                ))}
                            </ul>
                        </div>

                        {/* Tips Column 2 */}
                        <div className="flex flex-col items-center justify-center space-y-2 text-center md:items-start md:text-left">
                            <ul className="list-inside list-disc space-y-2 text-sm text-gray-700 dark:text-gray-300">
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
