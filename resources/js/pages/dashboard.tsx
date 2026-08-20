import { Head, Link } from '@inertiajs/react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

type Props = {
    summary: {
        draft_submitted: number;
        in_analysis: number;
        awaiting_signature: number;
        ready_for_pickup: number;
        total: number;
    };
    chartData: Array<{ status: string; count: number }>;
    recentOrders: Array<{
        id: number;
        reference_no: string;
        customer_name: string;
        status: string;
        status_label: string;
        total_cost: string | number;
        created_at: string | null;
    }>;
    roles: string[];
};

export default function Dashboard({
    summary,
    chartData,
    recentOrders,
    roles,
}: Props) {
    const roleDestinations: Record<
        string,
        { label: string; href: string; description: string }
    > = {
        admin: {
            label: 'Document Control',
            href: '/admin/controlled-forms',
            description: 'Manage forms, revisions, and audit trails.',
        },
        receiving: {
            label: 'Receiving queue',
            href: '/receiving',
            description: 'Price and receive newly submitted requests.',
        },
        analyst: {
            label: 'Analyst workspace',
            href: '/analyst',
            description: 'Enter and complete assigned results.',
        },
        head_analysis: {
            label: 'Signing queue',
            href: '/head',
            description: 'Review and sign finished files.',
        },
    };
    const cards = [
        ['Awaiting receive', summary.draft_submitted, '/receiving?status=draft_submitted'],
        ['In analysis', summary.in_analysis, '/analyst'],
        ['Awaiting signature', summary.awaiting_signature, '/head?tab=unsigned'],
        ['Ready for pickup', summary.ready_for_pickup, '/history?status=unsigned'],
    ] as const;

    const quickLinks = roles
        .map((role) => roleDestinations[role])
        .filter(
            (
                item,
            ): item is {
                label: string;
                href: string;
                description: string;
            } => Boolean(item),
        )
        .filter(
            (item, index, list) =>
                list.findIndex((entry) => entry.href === item.href) === index,
        );

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-col gap-4 p-4">
                <div>
                    <h1 className="font-heading text-2xl font-semibold text-[#1A3694]">
                        Laboratory dashboard
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {summary.total} total job orders across the laboratory
                        workflow.
                    </p>
                </div>

                {quickLinks.length > 0 && (
                    <div className="rounded-xl border bg-white p-4">
                        <p className="text-sm font-medium text-[#1A3694]">
                            You can work in
                        </p>
                        <div className="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                            {quickLinks.map((item) => (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    className="rounded-lg border bg-[#f8fafc] px-3 py-3 transition hover:border-[#1A3694]/30 hover:bg-[#eef3fb]"
                                >
                                    <p className="font-medium text-slate-900">
                                        {item.label}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {item.description}
                                    </p>
                                </Link>
                            ))}
                        </div>
                    </div>
                )}

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {cards.map(([label, value, href]) => (
                        <Link
                            key={label}
                            href={href}
                            className="rounded-xl border bg-gradient-to-br from-white to-[#e8eef8]/60 p-4"
                        >
                            <p className="text-sm text-muted-foreground">
                                {label}
                            </p>
                            <p className="mt-2 font-heading text-3xl font-semibold text-[#1A3694]">
                                {value}
                            </p>
                            <p className="mt-2 text-xs font-medium text-[#365BB0]">
                                Open queue
                            </p>
                        </Link>
                    ))}
                </div>

                <div className="grid gap-4 xl:grid-cols-2">
                    <div className="rounded-xl border p-4">
                        <h2 className="mb-4 font-medium">Orders by status</h2>
                        <div className="h-64">
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={chartData}>
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis dataKey="status" tickLine={false} axisLine={false} />
                                    <YAxis allowDecimals={false} />
                                    <Tooltip />
                                    <Bar dataKey="count" fill="#1A3694" />
                                </BarChart>
                            </ResponsiveContainer>
                        </div>
                    </div>

                    <div className="rounded-xl border p-4">
                        <h2 className="mb-4 font-medium">Recent job orders</h2>
                        <div className="space-y-2">
                            {recentOrders.map((order) => (
                                <div
                                    key={order.id}
                                    className="flex items-center justify-between rounded-lg border px-3 py-2 text-sm"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {order.reference_no}
                                        </p>
                                        <p className="text-muted-foreground">
                                            {order.customer_name} ·{' '}
                                            {order.status_label}
                                        </p>
                                    </div>
                                    <div className="text-right">
                                        <p className="text-xs text-muted-foreground">
                                            {order.created_at}
                                        </p>
                                        <Link
                                            href={
                                                order.status === 'draft_submitted'
                                                    ? `/receiving/${order.id}`
                                                    : order.status === 'in_analysis'
                                                      ? '/analyst'
                                                      : order.status === 'ready_for_pickup'
                                                        ? `/history/${order.id}`
                                                        : '/dashboard'
                                            }
                                            className="mt-1 inline-block text-xs font-medium text-[#1A3694]"
                                        >
                                            {order.status === 'draft_submitted'
                                                ? 'Open in Receiving'
                                                : order.status === 'in_analysis'
                                                  ? 'Open Analyst workspace'
                                                  : order.status === 'ready_for_pickup'
                                                    ? 'Open in History'
                                                    : 'View workflow'}
                                        </Link>
                                    </div>
                                </div>
                            ))}
                            {recentOrders.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    No job orders yet. Start at{' '}
                                    <Link
                                        href="/intake"
                                        className="text-[#1A3694] underline"
                                    >
                                        /intake
                                    </Link>
                                    .
                                </p>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: '/dashboard' }],
};
