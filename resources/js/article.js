import 'katex/dist/katex.min.css';
import renderMathInElement from 'katex/dist/contrib/auto-render';

document.addEventListener('DOMContentLoaded', () => {
    const prose = document.querySelector('.prose-article');
    if (!prose) return;
    renderMathInElement(prose, {
        delimiters: [
            { left: '$$', right: '$$', display: true },
            { left: '$', right: '$', display: false },
        ],
        throwOnError: false,
    });
});
