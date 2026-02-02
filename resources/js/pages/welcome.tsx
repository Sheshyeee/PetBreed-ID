import Header from '@/components/header';
import LandingPage from './normal_user/landing-page';

export default function Welcome({
    canRegister = true,
}: {
    canRegister?: boolean;
}) {
    return (
        <>
            <div>
                <Header />
                <div className="mt-[10px] flex w-full items-center justify-center opacity-100 transition-opacity duration-75">
                    <main className="flex w-full max-w-[335px] flex-col-reverse items-center justify-center lg:max-w-[1100px] lg:flex-row">
                        <LandingPage />
                    </main>
                </div>
                <div className="hidden h-14.5 lg:block"></div>
            </div>
        </>
    );
}
