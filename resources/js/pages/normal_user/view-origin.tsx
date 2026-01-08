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

const ViewOrigin = () => {
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
                            Bulldog Origin & History
                        </h1>
                        <h1 className="text-sm text-gray-600 dark:text-white/70">
                            Breed heritage and evolution
                        </h1>
                    </div>
                </div>
                <Card className="mt-5 flex bg-cyan-50 p-10 outline outline-cyan-200">
                    <div className="">
                        <h1 className="mb-8">Geographic Origin</h1>
                        <div className="flex gap-8">
                            <div className="w-1/2">
                                <div className="flex gap-3 space-y-12">
                                    <MapPin color="#002680" />
                                    <div>
                                        <h1 className="font-bold">
                                            Scotland, United Kingdom
                                        </h1>
                                        <h2 className="text-sm text-gray-700">
                                            Scottish Highlands region
                                        </h2>
                                    </div>
                                </div>
                                <p className="text-gray-700">
                                    The Golden Retriever originated in the
                                    Scottish Highlands during the mid-19th
                                    century. Lord Tweedmouth bred the first
                                    Golden Retrievers at his estate in
                                    Inverness-shire.
                                </p>
                            </div>

                            <div className="flex w-1/2 flex-col items-center justify-center gap-4 rounded-2xl bg-violet-200 p-8">
                                <img
                                    src="/flag.jpg"
                                    alt=""
                                    className="rounded-md"
                                />
                                <div className="flex flex-col items-center justify-center">
                                    <p className="font-bold">Scotland</p>
                                    <p className="text-gray-700">
                                        Scottish Highlands
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </Card>

                <Card className="mt-4 p-10">
                    <h1>History Timeline</h1>
                </Card>

                <Card className="mt-4 p-10">
                    <h1>Detailed History</h1>

                    <Accordion type="single" collapsible className="w-full">
                        <AccordionItem value="item-1">
                            <AccordionTrigger className="text-md font-bold">
                                Product Information
                            </AccordionTrigger>
                            <AccordionContent className="flex flex-col gap-2 text-balance">
                                <p>
                                    Our flagship product combines cutting-edge
                                    technology with sleek design. Built with
                                    premium materials, it offers unparalleled
                                    performance and reliability.
                                </p>
                                <p>
                                    Key features include advanced processing
                                    capabilities, and an intuitive user
                                    interface designed for both beginners and
                                    experts.
                                </p>
                            </AccordionContent>
                        </AccordionItem>
                        <AccordionItem value="item-2">
                            <AccordionTrigger className="text-md font-bold">
                                Shipping Details
                            </AccordionTrigger>
                            <AccordionContent className="flex flex-col gap-2 text-balance">
                                <p>
                                    We offer worldwide shipping through trusted
                                    courier partners. Standard delivery takes
                                    3-5 business days, while express shipping
                                    ensures delivery within 1-2 business days.
                                </p>
                                <p>
                                    All orders are carefully packaged and fully
                                    insured. Track your shipment in real-time
                                    through our dedicated tracking portal.
                                </p>
                            </AccordionContent>
                        </AccordionItem>
                        <AccordionItem value="item-3">
                            <AccordionTrigger className="text-md font-bold">
                                Return Policy
                            </AccordionTrigger>
                            <AccordionContent className="flex flex-col gap-2 text-balance">
                                <p>
                                    We stand behind our products with a
                                    comprehensive 30-day return policy. If
                                    you&apos;re not completely satisfied, simply
                                    return the item in its original condition.
                                </p>
                                <p>
                                    Our hassle-free return process includes free
                                    return shipping and full refunds processed
                                    within 48 hours of receiving the returned
                                    item.
                                </p>
                            </AccordionContent>
                        </AccordionItem>
                    </Accordion>
                </Card>
            </main>
        </div>
    );
};

export default ViewOrigin;
