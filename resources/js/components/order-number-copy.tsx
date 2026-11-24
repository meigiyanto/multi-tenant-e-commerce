import { useState } from 'react';
import { Check, Copy } from 'lucide-react';
import { Button } from '@/components/ui/button';

type Props = {
    value: string;
    className?: string;
    size?: 'default' | 'sm' | 'lg' | 'icon';
};

export default function OrderNumberCopy({ value, className, size = 'sm' }: Props) {
    const [copied, setCopied] = useState(false);

    async function copyOrderNumber() {
        try {
            await navigator.clipboard.writeText(value);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1800);
        } catch {
            setCopied(false);
        }
    }

    return (
        <span className="inline-flex items-center gap-2">
            <Button type="button" size={size} variant="outline" className={className} onClick={copyOrderNumber} aria-label="Copy order number">
                {copied ? <Check className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
                <span>{copied ? 'Copied' : 'Copy'}</span>
            </Button>
        </span>
    );
}
