import type {
    FormAnalysis,
    FormPackage,
    FormSample,
} from '@/components/request-for-analysis-form';

export type PricingLine = {
    id: number;
    quantity: number;
    unit_price: number;
};

export type SamplePackageGroup = {
    package: FormPackage;
    members: FormAnalysis[];
    memberLineIds: number[];
};

export type SamplePricingGroup = {
    sample: FormSample;
    sampleIndex: number;
    controlNumber: string | null;
    packages: SamplePackageGroup[];
    individuals: FormAnalysis[];
};
