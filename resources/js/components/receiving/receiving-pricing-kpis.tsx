import QueueKpi from '@/components/lims/queue-kpi';

type Props = {
    samplesCount: number;
    analysesCount: number;
    packagesCount: number;
    individualsCount: number;
};

export default function ReceivingPricingKpis({
    samplesCount,
    analysesCount,
    packagesCount,
    individualsCount,
}: Props) {
    return (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <QueueKpi
                label="Samples"
                value={samplesCount}
                hint={samplesCount === 1 ? 'sample' : 'samples'}
            />
            <QueueKpi
                label="Analyses"
                value={analysesCount}
                hint={analysesCount === 1 ? 'line' : 'lines'}
            />
            <QueueKpi
                label="Packages"
                value={packagesCount}
                hint={packagesCount === 1 ? 'package' : 'packages'}
            />
            <QueueKpi
                label="Individual tests"
                value={individualsCount}
                hint={individualsCount === 1 ? 'test' : 'tests'}
            />
        </div>
    );
}
