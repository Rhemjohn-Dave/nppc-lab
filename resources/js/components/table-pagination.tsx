import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

export type LaravelPaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type LaravelProps = {
    mode?: 'laravel';
    links: LaravelPaginationLink[];
    from?: number | null;
    to?: number | null;
    total?: number | null;
    label?: string;
};

type ClientProps = {
    mode: 'client';
    page: number;
    totalPages: number;
    totalItems: number;
    pageSize: number;
    onPageChange: (page: number) => void;
    label?: string;
    filteredTotal?: number;
};

type Props = LaravelProps | ClientProps;

function visiblePages(current: number, total: number): number[] {
    if (total <= 7) {
        return Array.from({ length: total }, (_, index) => index + 1);
    }

    const pages = new Set<number>([1, total, current, current - 1, current + 1]);

    if (current <= 3) {
        pages.add(2);
        pages.add(3);
        pages.add(4);
    }

    if (current >= total - 2) {
        pages.add(total - 1);
        pages.add(total - 2);
        pages.add(total - 3);
    }

    return [...pages].filter((page) => page >= 1 && page <= total).sort((a, b) => a - b);
}

export default function TablePagination(props: Props) {
    if (props.mode === 'client') {
        const {
            page,
            totalPages,
            totalItems,
            pageSize,
            onPageChange,
            label = 'items',
            filteredTotal,
        } = props;

        if (totalItems === 0) {
            return null;
        }

        const rangeStart = (page - 1) * pageSize + 1;
        const rangeEnd = Math.min(page * pageSize, totalItems);
        const showControls = totalItems > pageSize;
        const pages = visiblePages(page, totalPages);

        return (
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-xs text-muted-foreground">
                    Showing {rangeStart}–{rangeEnd} of {totalItems} {label}
                    {filteredTotal != null && filteredTotal !== totalItems
                        ? ` (filtered from ${filteredTotal})`
                        : ''}
                </p>
                {showControls && (
                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={page <= 1}
                            onClick={() => onPageChange(page - 1)}
                        >
                            Previous
                        </Button>
                        <div className="flex flex-wrap gap-1">
                            {pages.map((pageNumber, index) => {
                                const prev = pages[index - 1];
                                const showEllipsis =
                                    prev != null && pageNumber - prev > 1;

                                return (
                                    <span
                                        key={pageNumber}
                                        className="flex items-center gap-1"
                                    >
                                        {showEllipsis && (
                                            <span className="px-1 text-xs text-muted-foreground">
                                                …
                                            </span>
                                        )}
                                        <Button
                                            size="sm"
                                            variant={
                                                pageNumber === page
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            className={
                                                pageNumber === page
                                                    ? 'bg-[#1A3694] hover:bg-[#365BB0]'
                                                    : ''
                                            }
                                            onClick={() =>
                                                onPageChange(pageNumber)
                                            }
                                        >
                                            {pageNumber}
                                        </Button>
                                    </span>
                                );
                            })}
                        </div>
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={page >= totalPages}
                            onClick={() => onPageChange(page + 1)}
                        >
                            Next
                        </Button>
                    </div>
                )}
            </div>
        );
    }

    const {
        links,
        from = null,
        to = null,
        total = null,
        label = 'items',
    } = props;

    if (!links || links.length <= 3) {
        if (total == null) {
            return null;
        }

        return (
            <p className="text-xs text-muted-foreground">
                Showing {from ?? 0}–{to ?? 0} of {total} {label}
            </p>
        );
    }

    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            {total != null && (
                <p className="text-xs text-muted-foreground">
                    Showing {from ?? 0}–{to ?? 0} of {total} {label}
                </p>
            )}
            <div className="flex flex-wrap gap-2">
                {links.map((link, index) => (
                    <Button
                        key={`${link.label}-${index}`}
                        asChild={!!link.url}
                        size="sm"
                        variant={link.active ? 'default' : 'outline'}
                        disabled={!link.url}
                        className={
                            link.active
                                ? 'bg-[#1A3694] hover:bg-[#365BB0]'
                                : ''
                        }
                    >
                        {link.url ? (
                            <Link
                                href={link.url}
                                dangerouslySetInnerHTML={{
                                    __html: link.label,
                                }}
                            />
                        ) : (
                            <span
                                dangerouslySetInnerHTML={{
                                    __html: link.label,
                                }}
                            />
                        )}
                    </Button>
                ))}
            </div>
        </div>
    );
}
