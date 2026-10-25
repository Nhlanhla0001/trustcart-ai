import { cn } from '@/lib/utils';

const labels: Record<string, { text: string; className: string }> = {
    pass: { text: 'Pass', className: 'border-pass/40 bg-pass/5 text-pass' },
    fail: { text: 'Fail', className: 'border-fail/40 bg-fail/5 text-fail' },
    unverified: {
        text: 'Unverified',
        className: 'border-warn/40 bg-warn/5 text-warn',
    },
    qualified: {
        text: 'Qualified',
        className: 'border-pass/40 bg-pass/5 text-pass',
    },
    recommended: {
        text: 'Qualified',
        className: 'border-pass/40 bg-pass/5 text-pass',
    },
    rejected: {
        text: 'Rejected',
        className: 'border-fail/40 bg-fail/5 text-fail',
    },
    refused: {
        text: 'Refused',
        className: 'border-fail/40 bg-fail/5 text-fail',
    },
    pending: {
        text: 'Pending',
        className: 'border-warn/40 bg-warn/5 text-warn',
    },
    authorized: {
        text: 'Authorized',
        className: 'border-signal/50 bg-signal/10 text-ink',
    },
    completed: {
        text: 'Paid',
        className: 'border-pass/40 bg-pass/5 text-pass',
    },
    consumed: {
        text: 'Consumed',
        className: 'border-pass/40 bg-pass/5 text-pass',
    },
    invalidated: {
        text: 'Void',
        className: 'border-fail/40 bg-fail/5 text-fail',
    },
    expired: {
        text: 'Expired',
        className: 'border-rule-strong bg-paper-deep text-muted',
    },
    cancelled: {
        text: 'Cancelled',
        className: 'border-rule-strong bg-paper-deep text-muted',
    },
    failed: {
        text: 'Failed',
        className: 'border-fail/40 bg-fail/5 text-fail',
    },
    capturing: {
        text: 'Verifying',
        className: 'border-warn/40 bg-warn/5 text-warn',
    },
    created: {
        text: 'Created',
        className: 'border-rule-strong bg-paper-deep text-ink-soft',
    },
};

export default function StatusStamp({
    kind,
    className,
}: {
    kind: string;
    className?: string;
}) {
    const entry = labels[kind] ?? {
        text: kind,
        className: 'border-rule-strong bg-paper-deep text-ink-soft',
    };

    return (
        <span className={cn('stamp', entry.className, className)}>
            {entry.text}
        </span>
    );
}
