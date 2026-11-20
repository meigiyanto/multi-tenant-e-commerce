import animate from 'tailwindcss-animate';
import plugin from 'tailwindcss/plugin';

const color = (name) =>
    `hsl(var(--${name}) / <alpha-value>)`;

export default {
    darkMode: ['class'],
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.{js,ts,jsx,tsx}',
        './app/**/*.php',
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: [
                    'Instrument Sans',
                    'ui-sans-serif',
                    'system-ui',
                    'sans-serif',
                    'Apple Color Emoji',
                    'Segoe UI Emoji',
                    'Segoe UI Symbol',
                    'Noto Color Emoji',
                ],
            },
            borderRadius: {
                lg: 'var(--radius)',
                md: 'calc(var(--radius) - 2px)',
                sm: 'calc(var(--radius) - 4px)',
            },
            colors: {
                background: color('background'),
                foreground: color('foreground'),
                card: {
                    DEFAULT: color('card'),
                    foreground: color('card-foreground'),
                },
                popover: {
                    DEFAULT: color('popover'),
                    foreground: color('popover-foreground'),
                },
                primary: {
                    DEFAULT: color('primary'),
                    foreground: color('primary-foreground'),
                },
                secondary: {
                    DEFAULT: color('secondary'),
                    foreground: color('secondary-foreground'),
                },
                muted: {
                    DEFAULT: color('muted'),
                    foreground: color('muted-foreground'),
                },
                accent: {
                    DEFAULT: color('accent'),
                    foreground: color('accent-foreground'),
                },
                destructive: {
                    DEFAULT: color('destructive'),
                    foreground: color('destructive-foreground'),
                },
                border: color('border'),
                input: color('input'),
                ring: color('ring'),
                chart: {
                    1: color('chart-1'),
                    2: color('chart-2'),
                    3: color('chart-3'),
                    4: color('chart-4'),
                    5: color('chart-5'),
                },
                sidebar: {
                    DEFAULT: color('sidebar'),
                    foreground: color('sidebar-foreground'),
                    primary: color('sidebar-primary'),
                    'primary-foreground': color('sidebar-primary-foreground'),
                    accent: color('sidebar-accent'),
                    'accent-foreground': color('sidebar-accent-foreground'),
                    border: color('sidebar-border'),
                    ring: color('sidebar-ring'),
                },
            },
        },
    },
    plugins: [
        animate,
        plugin(({ addUtilities }) => {
            addUtilities({
                '.shadow-xs': {
                    boxShadow: '0 1px 2px 0 rgb(0 0 0 / 0.05)',
                },
                '.outline-hidden': {
                    outline: '2px solid transparent',
                    outlineOffset: '2px',
                },
            });
        }),
    ],
};
