import Header from '@/components/header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Link } from '@inertiajs/react';
import { Activity, Clock, Globe, Sparkles } from 'lucide-react';

const ScanResults = () => {
    return (
        <div>
            <Header />
            <main className="flex flex-col px-60">
                <div className="flex items-center justify-between">
                    <div className="">
                        <h1 className="mt-6 text-lg font-bold dark:text-white">
                            Scan Results
                        </h1>
                        <h1 className="text-sm text-gray-600 dark:text-white/70">
                            Here's what we found about your pet
                        </h1>
                    </div>
                    <div>
                        <Link href="/scan">
                            <Button>New Scan</Button>
                        </Link>
                    </div>
                </div>
                <Card className="mt-6 flex flex-row items-center justify-center bg-cyan-50 p-10 outline outline-cyan-200">
                    <div className="w-35">
                        <img
                            src="/dogpic.jpg"
                            alt="Pet"
                            className="h-30 w-30 rounded-2xl shadow-2xs"
                        />
                    </div>
                    <div className="flex-1 space-y-2">
                        <Badge
                            variant="secondary"
                            className="bg-cyan-700 text-white dark:bg-cyan-600"
                        >
                            Primary Match
                        </Badge>
                        <h1 className="text-lg">Golden Retriever</h1>
                        <div className="flex w-[300px] justify-between">
                            <p className="text-sm text-gray-600">
                                Confidence Score
                            </p>
                            <p>85%</p>
                        </div>
                        <Progress
                            value={85}
                            className="h-3 w-[300px] [&>div]:bg-blue-500"
                        />
                        <p className="text-gray-600">
                            The Golden Retriever is a Scottish breed of
                            retriever dog of medium size. It is characterised by
                            a gentle and affectionate nature and a striking
                            golden coat. They are frequently kept as pets and
                            are among the most registered breeds.
                        </p>
                    </div>
                </Card>
                <Card className="mt-5 px-10 py-6">
                    <h1>Top Possible Breeds</h1>
                    <Card className="bg-violet-50 px-6 py-3 outline outline-violet-100">
                        <div className="flex items-center gap-5">
                            <div className="flex w-15 items-center justify-center rounded-2xl border bg-white p-4">
                                1
                            </div>
                            <div className="flex-1">
                                <h1>Golden Retriever</h1>
                                <div className="flex items-center gap-4">
                                    <Progress
                                        value={85}
                                        className="h-2 w-[300px] [&>div]:bg-blue-500"
                                    />
                                    <p>85%</p>
                                </div>
                            </div>
                        </div>
                    </Card>
                    <Card className="bg-violet-50 px-6 py-3 outline outline-violet-100">
                        <div className="flex items-center gap-5">
                            <div className="flex w-15 items-center justify-center rounded-2xl border bg-white p-4">
                                2
                            </div>
                            <div className="flex-1">
                                <h1>Golden Retriever</h1>
                                <div className="flex items-center gap-4">
                                    <Progress
                                        value={75}
                                        className="h-2 w-[300px] [&>div]:bg-blue-500"
                                    />
                                    <p>75%</p>
                                </div>
                            </div>
                        </div>
                    </Card>
                    <Card className="bg-violet-50 px-6 py-3 outline outline-violet-100">
                        <div className="flex items-center gap-5">
                            <div className="flex w-15 items-center justify-center rounded-2xl border bg-white p-4">
                                3
                            </div>
                            <div className="flex-1">
                                <h1>Golden Retriever</h1>
                                <div className="flex items-center gap-4">
                                    <Progress
                                        value={65}
                                        className="h-2 w-[300px] [&>div]:bg-blue-500"
                                    />
                                    <p>65%</p>
                                </div>
                            </div>
                        </div>
                    </Card>
                </Card>

                <div className="py-6">Explore More Insights</div>

                <div className="mb-10 flex gap-10">
                    <Card className="hover:border-md flex w-1/3 flex-col gap-10 px-8 hover:border-violet-400">
                        <div className="flex h-15 w-15 items-center justify-center rounded-2xl bg-violet-200 p-4">
                            <Clock color="#3f005c" size="40" />
                        </div>
                        <h1 className="text-lg">Future Appearance</h1>
                        <p className="text-gray-500">
                            See how your pet will look as they age over the
                            years
                        </p>
                        <Button variant="outline" asChild>
                            <Link href="/simulation">
                                View Simulation
                                <Sparkles color="#000000" />
                            </Link>
                        </Button>
                    </Card>

                    <Card className="flex w-1/3 flex-col gap-10 px-8 hover:border-red-400 hover:shadow-md">
                        <div className="flex h-15 w-15 items-center justify-center rounded-2xl bg-pink-200 p-4">
                            <Activity color="#750056" size="40" />
                        </div>
                        <h1 className="text-lg">Health Risk</h1>
                        <p className="text-gray-500">
                            Learn about breed specific-health considerations
                        </p>
                        <Button variant="outline" asChild>
                            <Link href="/health-risk">
                                View Risk
                                <Sparkles color="#000000" />
                            </Link>
                        </Button>
                    </Card>
                    <Card className="flex w-1/3 flex-col gap-10 px-8 hover:border-blue-400 hover:shadow-md">
                        <div className="flex h-15 w-15 items-center justify-center rounded-2xl bg-blue-200 p-4">
                            <Globe color="#042381" size="40" />
                        </div>
                        <h1 className="text-lg">Origin History</h1>
                        <p className="text-gray-500">
                            Discover the history and origin of your pet's breed
                        </p>

                        <Button variant="outline" asChild>
                            <Link href="/origin">
                                Eplore History
                                <Sparkles color="#000000" />
                            </Link>
                        </Button>
                    </Card>
                </div>
            </main>
        </div>
    );
};

export default ScanResults;
