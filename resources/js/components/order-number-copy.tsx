import { useState } from 'react';
import { Check, Copy } from 'lucide-react';
import { Button } from '@/components/ui/button';

type Props = {
    value: string;
    className?: string;
    size?: 'default' | 'sm' | 'lg' | 'icon';
};

async function writeClipboard(value: string): Promise<void> {
    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(value);
            return;
        }
    } catch {
        // Fall back to the legacy clipboard API when permission is denied.
    }

    const textarea = document.createElement('textarea');
    textarea.value = value;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    textarea.style.pointerEvents = 'none';
    document.body.appendChild(textarea);
    textarea.select();

    try {
        if (!document.execCommand('copy')) {
            throw new Error('Clipboard access is unavailable.');
        }
    } finally {
        textarea.remove();
    }
}

export default function OrderNumberCopy({ value, className, size = 'sm' }: Props) {
    const [copied, setCopied] = useState(false);
    const [error, setError] = useState(false);

    async function copyOrderNumber() {
        try {
            await writeClipboard(value);
            setCopied(true);
            setError(false);
            window.setTimeout(() => setCopied(false), 1800);
        } catch {
            setCopied(false);
            setError(true);
            window.setTimeout(() => setError(false), 2500);
        }
    }

    return (
        <span className="inline-flex items-center gap-2">
            <Button type="button" size={size} variant="outline" className={className} onClick={copyOrderNumber} aria-label="Copy order number">
                {copied ? <Check className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
                <span>{copied ? 'Copied' : error ? 'Copy failed' : 'Copy'}</span>
            </Button>
            <span className="sr-only" role="status" aria-live="polite">{copied ? 'Order number copied to clipboard.' : error ? 'Unable to copy order number.' : ''}</span>
        </span>
    );
}
