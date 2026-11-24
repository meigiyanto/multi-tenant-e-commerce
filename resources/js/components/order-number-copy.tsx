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
            if (navigator.clipboard?.writeText) {
                await navigator.clipboard.writeText(value);
            } else {
                const input = document.createElement('textarea');
                input.value = value;
                input.style.position = 'fixed';
                input.style.opacity = '0';
                document.body.appendChild(input);
                input.select();
                const copied = document.execCommand('copy');
                input.remove();
                if (!copied) throw new Error('Clipboard access is unavailable.');
            }
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
