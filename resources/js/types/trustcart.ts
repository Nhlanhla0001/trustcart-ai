export type Verdict = 'qualified' | 'unverified' | 'rejected';
export type CheckResult = 'pass' | 'fail' | 'unverified';

export type Check = {
    key: string;
    label: string;
    requirement: string;
    evidence: string;
    result: CheckResult;
    detail: string;
};

export type ProductSnapshot = {
    id: number;
    sku: string;
    name: string;
    brand: string;
    category: string;
    tagline: string;
    price_cents: number;
    price: string;
    currency: string;
    stock: number;
    merchant: string;
    merchant_verified: boolean;
    illustration: string;
    specs: Record<string, unknown> & { features?: string[] };
    data_gaps: string[];
    last_synced_at: string | null;
};

export type Candidate = {
    product: ProductSnapshot;
    verdict: Verdict;
    score: number;
    checks: Check[];
    failed: string[];
    unverified: string[];
    reason: string;
};

export type RequirementsPayload = {
    category: string;
    budget_cents: number | null;
    currency: string;
    required_features: string[];
    min_specs: Record<string, number>;
    preferences: string[];
    summary: string;
};

export type EngineInfo = {
    rules_version?: string;
    prompt_version?: string;
    provider?: string | null;
    model?: string | null;
    extraction_source?: string;
    extraction_error?: string | null;
    narrative_source?: string;
    narrative_provider?: string | null;
    narrative_error?: string | null;
    duration_ms?: number;
    catalog_size?: number;
    evaluated_at?: string;
};

export type Flash = {
    success: string | null;
    error: string | null;
    info: string | null;
};

export type SharedProps = {
    name: string;
    flash: Flash;
    demo: {
        paypal_configured: boolean;
        ai_configured: boolean;
        ai_provider: string;
        environment: string;
        approval_ttl_minutes: number;
    };
    [key: string]: unknown;
};

export type DemoEvent = {
    id: number;
    type: string;
    status: string | null;
    provider_event_id?: string | null;
    created_at: string | null;
};

export type AuditEvent = {
    id: number;
    type: string;
    data: Record<string, unknown> | null;
    created_at: string | null;
};
