import {
    Activity,
    Brain,
    CheckCircle2,
    Globe,
    Loader2,
    Sparkles,
    Upload,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    AlertDialog,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogHeader,
    AlertDialogTitle,
} from './ui/alert-dialog';

interface AnalysisStage {
    id: string;
    label: string;
    icon: React.ReactNode;
    duration: number;
}

interface AnalysisLoadingDialogProps {
    isOpen: boolean;
}

const AnalysisLoadingDialog: React.FC<AnalysisLoadingDialogProps> = ({
    isOpen,
}) => {
    const [currentStageIndex, setCurrentStageIndex] = useState(0);
    const [progress, setProgress] = useState(0);

    const stages: AnalysisStage[] = [
        {
            id: 'upload',
            label: 'Uploading image',
            icon: <Upload className="h-4 w-4" />,
            duration: 800,
        },
        {
            id: 'identify',
            label: 'Identifying breed',
            icon: <Brain className="h-4 w-4" />,
            duration: 3900,
        },
        {
            id: 'features',
            label: 'Extracting features',
            icon: <Activity className="h-4 w-4" />,
            duration: 2100,
        },
        {
            id: 'origin',
            label: 'Generating origin data',
            icon: <Globe className="h-4 w-4" />,
            duration: 2000,
        },
        {
            id: 'health',
            label: 'Creating health analysis',
            icon: <Sparkles className="h-4 w-4" />,
            duration: 2000,
        },
        {
            id: 'finalize',
            label: 'Finalizing analysis',
            icon: <Sparkles className="h-4 w-4" />,
            duration: 1500,
        },
    ];

    const totalDuration = stages.reduce(
        (sum, stage) => sum + stage.duration,
        0,
    );

    useEffect(() => {
        if (!isOpen) {
            setCurrentStageIndex(0);
            setProgress(0);
            return;
        }

        let cumulativeTime = 0;
        let currentIndex = 0;

        const interval = setInterval(() => {
            cumulativeTime += 50;

            const newProgress = Math.min(
                (cumulativeTime / totalDuration) * 100,
                100,
            );
            setProgress(newProgress);

            let timeSum = 0;
            for (let i = 0; i < stages.length; i++) {
                timeSum += stages[i].duration;
                if (cumulativeTime < timeSum) {
                    currentIndex = i;
                    break;
                }
            }

            setCurrentStageIndex(currentIndex);

            if (cumulativeTime >= totalDuration) {
                clearInterval(interval);
            }
        }, 50);

        return () => clearInterval(interval);
    }, [isOpen, totalDuration]);

    const currentStage = stages[currentStageIndex];

    return (
        <div className="mx-6">
            <AlertDialog open={isOpen}>
                <AlertDialogContent className="max-w-md border-0 bg-white p-0 shadow-xl sm:mx-0 dark:bg-gray-900">
                    {/* Header Section with Gradient */}
                    <div className="relative overflow-hidden rounded-t-lg bg-gradient-to-br from-cyan-500 via-blue-600 to-indigo-600 px-6 pt-6 pb-8">
                        {/* Animated Background Pattern */}
                        <div className="absolute inset-0 opacity-10">
                            <div className="absolute -top-4 -left-4 h-24 w-24 animate-pulse rounded-full bg-white blur-2xl"></div>
                            <div
                                className="absolute top-1/2 -right-4 h-32 w-32 animate-pulse rounded-full bg-white blur-3xl"
                                style={{ animationDelay: '0.7s' }}
                            ></div>
                        </div>

                        <AlertDialogHeader className="relative space-y-0">
                            <AlertDialogTitle className="flex items-center gap-3 text-white">
                                <div className="flex h-12 w-12 items-center justify-center rounded-full bg-white/20 ring-2 ring-white/30 backdrop-blur-sm">
                                    <Brain className="h-6 w-6 text-white" />
                                </div>
                                <div className="flex-1">
                                    <h2 className="text-xl font-bold">
                                        Analyzing Your Pet
                                    </h2>
                                    <p className="text-sm font-normal text-cyan-100">
                                        Breed identification
                                    </p>
                                </div>
                            </AlertDialogTitle>
                        </AlertDialogHeader>
                    </div>

                    {/* Content Section */}
                    <div className="px-6 pt-4 pb-6">
                        <AlertDialogDescription className="space-y-5">
                            {/* Current Stage Display */}
                            <div className="relative overflow-hidden rounded-xl border border-cyan-200 bg-gradient-to-br from-cyan-50 to-blue-50 p-4 shadow-sm dark:border-cyan-800 dark:from-cyan-950/50 dark:to-blue-950/50">
                                <div className="flex items-center gap-4">
                                    <div className="relative flex h-12 w-12 shrink-0 items-center justify-center">
                                        {/* Animated Ring */}
                                        <div className="absolute inset-0 animate-ping rounded-full bg-cyan-400 opacity-20"></div>
                                        <div className="relative flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-cyan-500 to-blue-600 shadow-lg">
                                            <Loader2 className="h-5 w-5 animate-spin text-white" />
                                        </div>
                                    </div>
                                    <div className="flex-1">
                                        <p className="text-base font-semibold text-gray-900 dark:text-white">
                                            {currentStage?.label}...
                                        </p>
                                        <div className="mt-1 flex items-center gap-2">
                                            <span className="text-xs text-gray-600 dark:text-gray-400">
                                                Step {currentStageIndex + 1} of{' '}
                                                {stages.length}
                                            </span>
                                            <span className="text-xs text-cyan-600 dark:text-cyan-400">
                                                •
                                            </span>
                                            <span className="text-xs font-medium text-cyan-600 dark:text-cyan-400">
                                                {Math.round(progress)}% Complete
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Progress Bar */}
                            <div className="space-y-2">
                                <div className="relative h-2.5 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                    <div
                                        className="h-full rounded-full bg-gradient-to-r from-cyan-500 via-blue-600 to-indigo-600 transition-all duration-300 ease-out"
                                        style={{ width: `${progress}%` }}
                                    ></div>
                                </div>
                            </div>

                            {/* Completed Steps List */}
                            <div className="max-h-[280px] overflow-y-auto rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                                <p className="mb-3 text-xs font-semibold tracking-wide text-gray-600 uppercase dark:text-gray-400">
                                    Progress Steps
                                </p>
                                <div className="space-y-2.5">
                                    {stages.map((stage, index) => {
                                        const isCompleted =
                                            index < currentStageIndex;
                                        const isCurrent =
                                            index === currentStageIndex;

                                        return (
                                            <div
                                                key={stage.id}
                                                className={`flex items-center gap-3 transition-all duration-300 ${
                                                    isCompleted || isCurrent
                                                        ? 'opacity-100'
                                                        : 'opacity-40'
                                                }`}
                                            >
                                                {isCompleted ? (
                                                    <div className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-green-500 shadow-sm">
                                                        <CheckCircle2 className="h-3.5 w-3.5 text-white" />
                                                    </div>
                                                ) : isCurrent ? (
                                                    <div className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-cyan-500 shadow-sm">
                                                        <Loader2 className="h-3 w-3 animate-spin text-white" />
                                                    </div>
                                                ) : (
                                                    <div className="h-5 w-5 shrink-0 rounded-full border-2 border-gray-300 dark:border-gray-600"></div>
                                                )}
                                                <span
                                                    className={`text-sm ${
                                                        isCompleted
                                                            ? 'font-medium text-green-700 dark:text-green-400'
                                                            : isCurrent
                                                              ? 'font-semibold text-cyan-700 dark:text-cyan-300'
                                                              : 'text-gray-500 dark:text-gray-500'
                                                    }`}
                                                >
                                                    {stage.label}
                                                </span>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        </AlertDialogDescription>
                    </div>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
};

export default AnalysisLoadingDialog;
