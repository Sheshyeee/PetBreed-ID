import Header from '@/components/header';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import { Card, CardContent } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';

const ViewSimulation = () => {
    return (
        <div>
            <Header />
            <main className="flex flex-col px-60">
                <div className="flex items-center space-x-6">
                    <Link href="/scan-results">
                        <ArrowLeft color="black" size="19" />
                    </Link>
                    <div>
                        <h1 className="mt-6 text-lg font-bold dark:text-white">
                            Future Appearance Simulation
                        </h1>
                        <h1 className="text-sm text-gray-600 dark:text-white/70">
                            See how your Golden Retriever will look as they age
                        </h1>
                    </div>
                </div>
                <Card className="mt-4 bg-orange-50 pl-8 outline outline-orange-200">
                    <h1 className="text-sm">
                        <span className="font-bold text-orange-900">
                            Note:{' '}
                        </span>{' '}
                        <span className="text-orange-800">
                            This prediction is based on typical breed patterns.
                            Actual aging may vary depending on your pet’s
                            genetics, health, and environment.
                        </span>
                    </h1>
                </Card>
                <div className="mt-4 flex w-full flex-col gap-6">
                    <Tabs defaultValue="first">
                        <TabsList className="w-full">
                            <TabsTrigger value="first">2 Years</TabsTrigger>
                            <TabsTrigger value="second">5 Years</TabsTrigger>
                            <TabsTrigger value="third">10 Years</TabsTrigger>
                        </TabsList>
                        <TabsContent value="first">
                            <Card>
                                <CardContent>
                                    <div className="flex gap-4 space-y-2 p-4">
                                        <div className="flex-1">
                                            <div>Current Appearance</div>
                                            <div className="mx-auto h-[90%] w-[90%] p-4 pt-6">
                                                <img
                                                    src="/dogpic.jpg"
                                                    alt=""
                                                    className="rounded-2xl"
                                                />
                                            </div>
                                            <p className="text-gray-700">
                                                Present day appearance
                                            </p>
                                        </div>
                                        <div className="flex-1">
                                            <div>Future Appearance</div>
                                            <div className="mx-auto h-[90%] w-[90%] p-4 pt-6">
                                                <img
                                                    src="/dogpic.jpg"
                                                    alt=""
                                                    className="rounded-2xl"
                                                />
                                            </div>
                                            <p className="text-gray-700">
                                                Predicted at 2 years
                                            </p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </TabsContent>
                        <TabsContent value="second">
                            <Card>
                                <CardContent>
                                    <div className="flex gap-4 p-4">
                                        <div className="flex-1">
                                            <div>Current Appearance</div>
                                            <div className="mx-auto h-[90%] w-[90%] p-4 pt-6">
                                                <img
                                                    src="/dogpic.jpg"
                                                    alt=""
                                                    className="rounded-2xl"
                                                />
                                            </div>
                                            <p className="text-gray-700">
                                                Present day appearance
                                            </p>
                                        </div>
                                        <div className="flex-1">
                                            <div>Future Appearance</div>
                                            <div className="mx-auto h-[90%] w-[90%] p-4 pt-6">
                                                <img
                                                    src="/dogpic.jpg"
                                                    alt=""
                                                    className="rounded-2xl"
                                                />
                                            </div>
                                            <p className="text-gray-700">
                                                Pridected at 5 years
                                            </p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </TabsContent>
                        <TabsContent value="third">
                            <Card>
                                <CardContent>
                                    <div className="flex gap-4 p-4">
                                        <div className="flex-1">
                                            <div>Current Appearance</div>
                                            <div className="mx-auto h-[90%] w-[90%] p-4 pt-6">
                                                <img
                                                    src="/dogpic.jpg"
                                                    alt=""
                                                    className="rounded-2xl"
                                                />
                                            </div>
                                            <p className="text-gray-700">
                                                Present day appearance
                                            </p>
                                        </div>
                                        <div className="flex-1">
                                            <div>Future Appearance</div>
                                            <div className="mx-auto h-[90%] w-[90%] p-4 pt-6">
                                                <img
                                                    src="/dogpic.jpg"
                                                    alt=""
                                                    className="rounded-2xl"
                                                />
                                            </div>
                                            <p className="text-gray-700">
                                                Pridected at 10 years
                                            </p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </TabsContent>
                    </Tabs>
                </div>
            </main>
        </div>
    );
};

export default ViewSimulation;
