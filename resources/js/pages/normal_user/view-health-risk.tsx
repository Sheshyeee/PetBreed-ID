import Header from '@/components/header';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { Link } from '@inertiajs/react';
import { ArrowLeft, TriangleAlert } from 'lucide-react';

const ViewHealthRisk = () => {
    return (
        <div>
            <Header />
            <main className="flex flex-col gap-4 px-60">
                <div className="flex items-center space-x-6">
                    <Link href="/scan-results">
                        <ArrowLeft color="black" size="19" />
                    </Link>
                    <div>
                        <h1 className="mt-6 text-lg font-bold dark:text-white">
                            Health Risk Visualization
                        </h1>
                        <h1 className="text-sm text-gray-600 dark:text-white/70">
                            Breed-specific health considerations for Golden
                            Retriever
                        </h1>
                    </div>
                </div>
                <Card className="bg-red-50 px-8 pl-8 outline outline-red-300">
                    <h1 className="flex gap-4">
                        <TriangleAlert color="#cc0000" />
                        <div className="flex flex-col gap-2">
                            <span className="text-sm font-bold text-red-700">
                                Medical Disclaimer
                            </span>
                            <span className="text-sm text-red-700">
                                This information is for educational purposes
                                only and is not a medical diagnosis. Always
                                consult with a licensed veterinarian for proper
                                medical advice and health screenings specific to
                                your pet.
                            </span>
                        </div>
                    </h1>
                </Card>

                <Card className="">
                    <div className="px-8">
                        <p className="font-medium">Breed Risk Profile</p>
                    </div>
                </Card>

                <h2 className="font-medium">Common Health Concerns</h2>

                <Card className="px-8">
                    <div>
                        <h1 className="font-medium">Cancer</h1>
                        <Badge className="bg-red-100 text-red-500">
                            High Risk
                        </Badge>
                    </div>

                    <div>
                        <h1 className="">Description</h1>
                        <p className="text-gray-600">
                            Golden Retrievers have higher rates of certain
                            cancers, particularly hemangiosarcoma and lymphoma.
                        </p>
                    </div>
                    <div>
                        <h1 className="">Prevention & Management</h1>
                        <p className="text-gray-600">
                            Regular vet check-ups, early detection screenings,
                            healthy lifestyle
                        </p>
                    </div>
                </Card>

                <Card className="bg-cyan-50 px-8 outline outline-cyan-200">
                    <h1 className="font-medium">
                        Recommended Health Screenings
                    </h1>
                    <div className="flex gap-2">
                        <div className="flex w-1/2 flex-col gap-2">
                            <Card className="gap-1 px-6">
                                <h1>Annual Physical Exam</h1>
                                <p className="text-gray-600">
                                    Comprehensive check-up with your
                                    veterinarian
                                </p>
                            </Card>
                            <Card className="gap-1 px-6">
                                <h1>Annual Physical Exam</h1>
                                <p className="text-gray-600">
                                    Comprehensive check-up with your
                                    veterinarian
                                </p>
                            </Card>
                        </div>
                        <div className="flex w-1/2 flex-col gap-2">
                            <Card className="gap-1 px-6">
                                <h1>Annual Physical Exam</h1>
                                <p className="text-gray-600">
                                    Comprehensive check-up with your
                                    veterinarian
                                </p>
                            </Card>
                            <Card className="gap-1 px-6">
                                <h1>Annual Physical Exam</h1>
                                <p className="text-gray-600">
                                    Comprehensive check-up with your
                                    veterinarian
                                </p>
                            </Card>
                        </div>
                    </div>
                </Card>

                <Card className="mb-6 px-8">
                    <h1 className="font-medium">
                        Typical Lifespan & Care Tips
                    </h1>
                    <div className="flex justify-evenly">
                        <div className="flex flex-col items-center justify-center">
                            <p className="text-4xl text-cyan-700">10-12</p>
                            <p className="font-medium">Years</p>
                            <p>Average lifespan</p>
                        </div>
                        <div className="flex flex-col items-center justify-center space-y-2">
                            <ul>
                                <li>Regular exercise (1-2 hours daily)</li>
                                <li> Balanced, high-quality diet</li>
                                <li>Weight management</li>
                            </ul>
                        </div>
                        <div className="flex flex-col items-center justify-center">
                            <ul>
                                <li>Regular exercise (1-2 hours daily)</li>
                                <li> Balanced, high-quality diet</li>
                                <li>Weight management</li>
                            </ul>
                        </div>
                    </div>
                </Card>
            </main>
        </div>
    );
};

export default ViewHealthRisk;
