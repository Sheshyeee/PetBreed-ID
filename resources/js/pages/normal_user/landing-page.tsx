import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/react';
import { Camera, Upload } from 'lucide-react';

function LandingPage() {
    return (
        <div className="flex w-full gap-4">
            <div className="flex-1 text-black dark:text-white">
                <h1 className="text-[60px] font-bold">Identify Any Dog</h1>
                <h1 className="mt-[-30px] text-[60px] font-bold">
                    Breed Instantly
                </h1>
                <p className="text-lg text-gray-600 dark:text-white/70">
                    Upload a photo or take a picture to discover your dog's
                    breed using advanced AI recognition technology.
                </p>
                <Link href="/scan">
                    <Button className="mt-8">Start Scanning</Button>
                </Link>
                <div className="mt-[60px] flex gap-4">
                    <div className="flex w-[45%] gap-2 rounded-2xl bg-white p-4 shadow dark:bg-gray-900">
                        <div className="w-12">
                            <div className="flex w-full items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-800">
                                <Camera
                                    size={36}
                                    color="#5f97f2"
                                    className="p-1"
                                />
                            </div>
                        </div>
                        <div className="flex-1">
                            <p className="font-bold">Take a Photo</p>
                            <p className="text-xs text-gray-600 dark:text-white/70">
                                Use your camera to capture a photo instantly
                            </p>
                        </div>
                    </div>
                    <div className="flex w-[45%] gap-2 rounded-2xl bg-white p-4 shadow dark:bg-gray-900">
                        <div className="w-12">
                            <div className="flex w-full items-center justify-center rounded-lg bg-violet-200 dark:bg-violet-950">
                                <Upload
                                    size={36}
                                    color="#6623e1"
                                    className="p-1"
                                />
                            </div>
                        </div>
                        <div className="flex-1">
                            <p className="font-bold">Upload Image</p>
                            <p className="text-xs text-gray-600 dark:text-white/70">
                                Drag and drop or select from your device
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div className="flex-1">
                <div className="w-full">
                    <img
                        src="/dogpic.jpg"
                        alt="My Photo"
                        className="h-[420px] w-full rounded-lg object-cover"
                    />
                </div>
            </div>
        </div>
    );
}

export default LandingPage;
