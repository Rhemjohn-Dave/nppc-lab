import { router, usePage } from '@inertiajs/react';
import {
    createContext,
    useContext,
    useEffect,
    useRef,
    useState,
    type ReactNode,
} from 'react';

export type NavigationMode = 'none' | 'page' | 'partial';

export type InertiaNavigationState = {
    isNavigating: boolean;
    mode: NavigationMode;
    isInitialLoading: boolean;
    isRefreshing: boolean;
};

type VisitLike = {
    only?: string[];
    preserveState?: boolean;
    prefetch?: boolean;
    showProgress?: boolean;
};

type TrackedVisit = {
    mode: NavigationMode;
};

const defaultState: InertiaNavigationState = {
    isNavigating: false,
    mode: 'none',
    isInitialLoading: false,
    isRefreshing: false,
};

const InertiaNavigationContext =
    createContext<InertiaNavigationState>(defaultState);

function shouldTrackVisit(visit: VisitLike): boolean {
    if (visit.prefetch) {
        return false;
    }

    // Background/async visits (e.g. link prefetch on hover) should not skeleton the current page.
    if (visit.showProgress === false) {
        return false;
    }

    return true;
}

function resolveMode(visit: VisitLike): NavigationMode {
    if (visit.only && visit.only.length > 0) {
        return 'partial';
    }

    if (visit.preserveState) {
        return 'partial';
    }

    return 'page';
}

function deriveState(
    visits: TrackedVisit[],
): InertiaNavigationState {
    if (visits.length === 0) {
        return defaultState;
    }

    const hasPageVisit = visits.some((visit) => visit.mode === 'page');

    return {
        isNavigating: true,
        mode: hasPageVisit ? 'page' : 'partial',
        isInitialLoading: hasPageVisit,
        isRefreshing: !hasPageVisit,
    };
}

export function InertiaNavigationProvider({
    children,
}: {
    children: ReactNode;
}) {
    const page = usePage();
    const activeVisitsRef = useRef<TrackedVisit[]>([]);
    const lastPropsRef = useRef(page.props);
    const [state, setState] = useState<InertiaNavigationState>(defaultState);

    const syncState = () => {
        setState(deriveState(activeVisitsRef.current));
    };

    const completeVisit = () => {
        if (activeVisitsRef.current.length === 0) {
            return;
        }

        activeVisitsRef.current.shift();
        syncState();
    };

    useEffect(() => {
        const removeStart = router.on('start', (event) => {
            const visit = event.detail.visit as VisitLike;

            if (!shouldTrackVisit(visit)) {
                return;
            }

            activeVisitsRef.current.push({
                mode: resolveMode(visit),
            });
            syncState();
        });

        const removeFinish = router.on('finish', completeVisit);
        const removeError = router.on('error', completeVisit);
        const removeCancel = router.on('cancel', completeVisit);

        return () => {
            removeStart();
            removeFinish();
            removeError();
            removeCancel();
        };
    }, []);

    useEffect(() => {
        if (lastPropsRef.current === page.props) {
            return;
        }

        lastPropsRef.current = page.props;

        const partialIndex = activeVisitsRef.current.findIndex(
            (visit) => visit.mode === 'partial',
        );

        if (partialIndex === -1) {
            return;
        }

        activeVisitsRef.current.splice(partialIndex, 1);
        syncState();
    }, [page.props]);

    return (
        <InertiaNavigationContext.Provider value={state}>
            {children}
        </InertiaNavigationContext.Provider>
    );
}

export function useInertiaNavigation(): InertiaNavigationState {
    return useContext(InertiaNavigationContext);
}
