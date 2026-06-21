import 'katex/dist/katex.min.css';
import renderMathInElement from 'katex/dist/contrib/auto-render';

const renderArticleMath = () => {
    const prose = document.querySelector('.prose-article');
    if (!prose || prose.dataset.katexRendered === 'true') return;

    renderMathInElement(prose, {
        delimiters: [
            { left: '$$', right: '$$', display: true },
            { left: '$', right: '$', display: false },
        ],
        throwOnError: false,
    });

    prose.dataset.katexRendered = 'true';
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', renderArticleMath, { once: true });
} else {
    renderArticleMath();
}

document.addEventListener('livewire:navigated', renderArticleMath);
