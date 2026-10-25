import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Kind = 'headphones' | 'laptop' | 'monitor' | 'keyboard' | 'accessories';

const paths: Record<Kind, ReactNode> = {
    headphones: (
        <>
            <path d="M42 104V78a78 78 0 0 1 156 0v26" />
            <rect x="26" y="92" width="36" height="50" rx="16" />
            <rect x="178" y="92" width="36" height="50" rx="16" />
            <path d="M44 104v26M196 104v26" opacity="0.45" />
            <circle
                cx="44"
                cy="117"
                r="4"
                className="fill-signal stroke-none"
            />
            <circle
                cx="196"
                cy="117"
                r="4"
                className="fill-signal stroke-none"
                opacity="0.35"
            />
        </>
    ),
    laptop: (
        <>
            <rect x="52" y="28" width="136" height="88" rx="2" />
            <rect x="60" y="36" width="120" height="72" opacity="0.35" />
            <path d="M24 124h192l12 12H12z" />
            <path d="M96 118h48" opacity="0.35" />
            <path d="M74 132h92" opacity="0.2" />
        </>
    ),
    monitor: (
        <>
            <rect x="34" y="24" width="172" height="100" rx="2" />
            <rect x="42" y="32" width="156" height="84" opacity="0.35" />
            <path d="M120 124v16M92 146h56" />
            <path d="M120 146v-6" opacity="0.3" />
        </>
    ),
    keyboard: (
        <>
            <rect x="24" y="52" width="192" height="66" rx="2" />
            {Array.from({ length: 4 }).map((_, row) =>
                Array.from({ length: 12 }).map((_, column) => (
                    <rect
                        key={`${row}-${column}`}
                        x={32 + column * 15}
                        y={60 + row * 14}
                        width="11"
                        height="9"
                        opacity={
                            row === 3 && column > 4 && column < 9 ? 0.15 : 0.4
                        }
                    />
                )),
            )}
            <path d="M84 114h72" opacity="0.35" />
        </>
    ),
    accessories: (
        <>
            <path d="M52 76h136v58H52z" />
            <path d="M52 76l18-22h100l18 22" />
            <path d="M120 54v80M52 104h136" opacity="0.3" />
        </>
    ),
};

export default function ProductIllustration({
    kind,
    className,
    label,
}: {
    kind: string;
    className?: string;
    label?: string;
}) {
    const normalized = (kind in paths ? kind : 'accessories') as Kind;

    return (
        <svg
            viewBox="0 0 240 160"
            role="img"
            aria-label={label ?? 'Product line drawing'}
            className={cn('h-full w-full', className)}
            fill="none"
            stroke="currentColor"
            strokeWidth="1.4"
            strokeLinecap="square"
            strokeLinejoin="miter"
        >
            {paths[normalized]}
        </svg>
    );
}
