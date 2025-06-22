# Astro.js Base Styles Configuration

This document contains the base styles for the Hondatabase Astro.js website, adapted from the original base-styles.css with ui-sans-serif font system.

## CSS Variables and Theme System

The website uses a comprehensive CSS custom properties system for theming with light/dark mode support:

```css
/* Base Website Styles */
:root {
    --primary         : #e30613;
    --secondary       : #bb0a21;
    --background-light: #ffffff;
    --text-light      : #333333;
    --card-bg-light   : #ffffff;
    --border-light    : #dee2e6;
    --text-muted-light: #666666;
    
    --background-dark: #1a1a1a;
    --text-dark      : #f0f0f0;
    --card-bg-dark   : #2d2d2d;
    --border-dark    : #404040;
    --text-muted-dark: #a0a0a0;

    /* Default light theme */
    --background: var(--background-light);
    --text      : var(--text-light);
    --card-bg   : var(--card-bg-light);
    --border    : var(--border-light);
    --text-muted: var(--text-muted-light);
}

[data-theme="dark"] {
    --background: var(--background-dark);
    --text      : var(--text-dark);
    --card-bg   : var(--card-bg-dark);
    --border    : var(--border-dark);
    --text-muted: var(--text-muted-dark);
}
```

## Complete Base Styles (Astro.js Ready)

```css
/* Base Website Styles - No external icon imports needed, using Lucide locally */
:root {
    --primary         : #e30613;
    --secondary       : #bb0a21;
    --background-light: #ffffff;
    --text-light      : #333333;
    --card-bg-light   : #ffffff;
    --border-light    : #dee2e6;
    --text-muted-light: #666666;
    
    --background-dark: #1a1a1a;
    --text-dark      : #f0f0f0;
    --card-bg-dark   : #2d2d2d;
    --border-dark    : #404040;
    --text-muted-dark: #a0a0a0;

    /* Default light theme */
    --background: var(--background-light);
    --text      : var(--text-light);
    --card-bg   : var(--card-bg-light);
    --border    : var(--border-light);
    --text-muted: var(--text-muted-light);
}

[data-theme="dark"] {
    --background: var(--background-dark);
    --text      : var(--text-dark);
    --card-bg   : var(--card-bg-dark);
    --border    : var(--border-dark);
    --text-muted: var(--text-muted-dark);
}

body {
    font-family: ui-sans-serif, -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif;
    max-width  : 1000px;
    margin     : 0 auto;
    padding    : 2rem;
    background : var(--background);
    color      : var(--text);
    line-height: 1.5;
    transition : background 0.3s, color 0.3s;
}

h1, h2, h3, h4, h5, h6 {
    font-family: ui-sans-serif, -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif;
    font-weight: 600;
}

header h1 {
    color: var(--primary);
    font-size: 2.5rem;
    margin-bottom: 2rem;
    font-weight: 700;
}

header h2 {
    color: var(--text-light);
    font-size: 2rem;
    margin: 1.5rem 0;
    font-weight: 600;
}

summary { cursor: pointer; }

article {
    background: var(--card-bg);
    padding: 1.5rem;
    border-radius: 0.5rem;
    border: 1px solid var(--border);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    margin: 1rem 0;
    transition: background 0.3s, border-color 0.3s;
}

article h2 {
    color: var(--primary);
    font-size: 1.5rem;
    margin: 1.5rem 0 1rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

article h2 .lucide { 
    width: 1.5rem; 
    height: 1.5rem;
    stroke-width: 2;
}

a {
    color: var(--primary);
    text-decoration: none;
    transition: color 0.2s;
}

a:hover {
    color: #b31329;
}

ul {
    list-style-type: none;
    padding-left: 0;
}

li {
    margin: 0.75rem 0;
    padding-left: 1.75rem;
    position: relative;
}

li .lucide {
    position: absolute;
    left: 0;
    top: 0.2rem;
    width: 1rem;
    height: 1rem;
    color: var(--primary);
    stroke-width: 2;
}

ol {
    padding-left: 1.2rem;
}

ol li {
    padding-left: 0.5rem;
}

footer {
    margin-top: 3rem;
    padding-top: 2rem;
    border-top: 1px solid var(--border);
    text-align: center;
    color: var(--text-muted);
    transition: border-color 0.3s;
}

/* Light/Dark Mode Toggle */
.theme-toggle {
    position: fixed;
    top: 1rem;
    right: 1rem;
    z-index: 1000;
}

.theme-toggle__input {
    display: none;
}

.theme-toggle__label {
    display: flex;
    align-items: center;
    cursor: pointer;
    background: var(--card-bg);
    padding: 0.5rem;
    border-radius: 2rem;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--border);
    transition: background 0.3s, border-color 0.3s;
}

.theme-toggle__label .lucide {
    width: 1.5rem;
    height: 1.5rem;
    transition: opacity 0.3s;
    color: var(--text);
    stroke-width: 2;
}

.theme-toggle__input:checked + .theme-toggle__label .lucide:first-child { opacity: 0; }

.theme-toggle__input:not(:checked) + .theme-toggle__label .lucide:last-child { opacity: 0; }

/* Responsive Design */
@media (max-width: 768px) {
    body {
        padding: 1rem;
    }

    header h1 {
        font-size: 2rem;
    }

    header h2 {
        font-size: 1.5rem;
    }
}
```

## Key Features

### Typography

- **Font Stack**: `ui-sans-serif, -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif`
- **Headings**: Same font family with font-weight 600-700
- **Body**: Standard weight 400 with line-height 1.5

### Color Scheme

- **Primary**: `#e30613` (Honda Red)
- **Secondary**: `#bb0a21` (Darker Honda Red)
- **Hover States**: `#b31329`

### Theme System

- Automatic light/dark mode support via `[data-theme="dark"]`
- CSS custom properties for easy theme switching
- Smooth transitions between themes (0.3s)

### Layout

- **Max Width**: 1000px centered
- **Responsive**: Mobile-first approach with breakpoint at 768px
- **Cards**: Article styling with rounded borders and subtle shadows

### Interactive Elements

- **Theme Toggle**: Fixed position toggle with Lucide Icons (Sun/Moon)
- **Hover Effects**: Smooth color transitions
- **Focus States**: Accessible interaction patterns

## Implementation Notes for Astro.js

1. **Global Styles**: Add this CSS to your main layout or global stylesheet
2. **Lucide Icons**: Install locally via npm: `npm install lucide-astro`
3. **Theme Toggle**: Implement JavaScript to handle `data-theme` attribute switching
4. **Font Loading**: ui-sans-serif provides excellent performance as it uses system fonts

## Lucide Icons Installation

Install Lucide Icons for Astro:

```bash
npm install lucide-astro
```

The Lucide Astro package provides optimised SVG icons as Astro components.

## Usage in Astro Components

```astro
---
// Example component structure
import { Wrench, Sun, Moon } from 'lucide-astro';
---

<html data-theme="light">
<head>
    <link rel="stylesheet" href="/styles/base.css">
</head>
<body>
    <header>
        <h1>Hondatabase</h1>
    </header>
    
    <main>
        <article>
            <h2><Wrench class="lucide" />Technical Guide</h2>
            <p>Content goes here...</p>
        </article>
    </main>
    
    <div class="theme-toggle">
        <input type="checkbox" id="theme-toggle" class="theme-toggle__input">
        <label for="theme-toggle" class="theme-toggle__label">
            <Sun class="lucide" />
            <Moon class="lucide" />
        </label>
    </div>
</body>
</html>
```

## Common Lucide Icons for Honda Database

Import these commonly used icons in your Astro components:

```astro
---
import { 
    Wrench,        // Technical/repair guides
    Car,           // Vehicle-related content
    Settings,      // Configuration guides
    Book,          // Documentation
    Search,        // Search functionality
    Home,          // Navigation
    User,          // User profiles
    Sun,           // Light mode
    Moon,          // Dark mode
    ChevronRight,  // Navigation arrows
    ExternalLink,  // External links
    Download,      // Downloads
    Upload,        // Uploads
    AlertTriangle, // Warnings
    CheckCircle,   // Success states
    Info,          // Information
    Menu,          // Mobile menu
    X              // Close buttons
} from 'lucide-astro';
---
```
