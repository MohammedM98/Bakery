import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

/** Previous / next with "page X of Y"; hidden when everything fits on one page. */
export function PaginationLinks({ page }: { page: Paginated<unknown> }) {
    if (page.last_page <= 1) return null;

    return (
        <nav aria-label="الصفحات" className="flex items-center justify-between gap-3">
            <Button variant="outline" className="h-11" disabled={!page.prev_page_url} asChild={!!page.prev_page_url}>
                {page.prev_page_url ? (
                    <Link href={page.prev_page_url}>
                        <ChevronRight />
                        السابق
                    </Link>
                ) : (
                    <span>
                        <ChevronRight />
                        السابق
                    </span>
                )}
            </Button>
            <span className="text-sm text-muted-foreground tabular-nums">
                صفحة {page.current_page} من {page.last_page}
            </span>
            <Button variant="outline" className="h-11" disabled={!page.next_page_url} asChild={!!page.next_page_url}>
                {page.next_page_url ? (
                    <Link href={page.next_page_url}>
                        التالي
                        <ChevronLeft />
                    </Link>
                ) : (
                    <span>
                        التالي
                        <ChevronLeft />
                    </span>
                )}
            </Button>
        </nav>
    );
}
