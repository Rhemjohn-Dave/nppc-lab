import { Head, Link } from '@inertiajs/react';
import { CheckCircle2, Clock3, Printer } from 'lucide-react';
import IntakePortalChrome from '@/components/intake/intake-portal-chrome';
import { Button } from '@/components/ui/button';
import { limsPageBackground } from '@/lib/lims-page-shell';
import { cn } from '@/lib/utils';

type Props = {
    jobOrder: {
        id: number;
        reference_no: string;
        customer_name: string;
    };
};

export default function IntakeSuccess({ jobOrder }: Props) {
    return (
        <div
            className={cn(
                'relative min-h-screen text-slate-900',
                limsPageBackground,
            )}
        >
            <Head title="Request submitted" />
            <IntakePortalChrome
                actions={
                    <Button
                        asChild
                        variant="outline"
                        className="h-8 border-slate-200 bg-white px-3 text-xs"
                    >
                        <Link href="/intake">Exit</Link>
                    </Button>
                }
            />

            <div className="mx-auto flex w-full max-w-lg flex-col px-4 py-10 sm:px-5">
                <div className="intake-enter rounded-xl border border-slate-200 bg-white p-6 text-center shadow-xs sm:p-8">
                    <CheckCircle2 className="mx-auto size-12 text-nppc-900" />
                    <p className="mt-4 text-[10px] font-semibold tracking-[0.16em] text-nppc-700 uppercase">
                        Submission complete
                    </p>
                    <h1 className="mt-1 font-heading text-2xl font-semibold text-nppc-900 sm:text-3xl">
                        Request submitted successfully
                    </h1>
                    <p className="mt-3 text-sm text-slate-600">
                        Thank you, {jobOrder.customer_name}. Your request has
                        been received by the laboratory.
                    </p>
                    <div className="mt-6 rounded-xl border border-nppc-100 bg-nppc-50 px-4 py-5">
                        <p className="text-[10px] font-semibold tracking-[0.18em] text-nppc-700 uppercase">
                            Request reference
                        </p>
                        <p className="mt-2 font-heading text-3xl font-semibold tracking-wide text-nppc-900 sm:text-4xl">
                            {jobOrder.reference_no}
                        </p>
                    </div>

                    <div className="mt-6 grid gap-3 text-left text-sm text-slate-600">
                        <div className="flex gap-3 rounded-xl border border-slate-200 p-3">
                            <Printer className="mt-0.5 size-4 shrink-0 text-nppc-900" />
                            <p>
                                Receiving will review pricing and print your
                                Request for Analysis form. Keep this reference
                                number.
                            </p>
                        </div>
                        <div className="flex gap-3 rounded-xl border border-slate-200 p-3">
                            <Clock3 className="mt-0.5 size-4 shrink-0 text-nppc-900" />
                            <p>
                                Typical turnaround is 5–7 working days. You will
                                receive updates using your registered contact
                                information when results are ready for pickup.
                            </p>
                        </div>
                    </div>

                    <div className="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
                        <Button
                            asChild
                            className="h-11 gap-2 bg-nppc-900 hover:bg-nppc-700"
                        >
                            <Link href="/intake">Done</Link>
                        </Button>
                        <Button asChild variant="outline" className="h-11">
                            <Link href="/intake/create">Start new request</Link>
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    );
}

IntakeSuccess.layout = null;
